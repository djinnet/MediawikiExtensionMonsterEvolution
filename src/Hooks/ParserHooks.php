<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Hooks;

use Config;
use MediaWiki\Extension\MonsterEvolution\Parser\EvolutionParseException;
use MediaWiki\Extension\MonsterEvolution\Parser\EvolutionParser;
use MediaWiki\Extension\MonsterEvolution\Rendering\EvolutionRenderer;
use MediaWiki\Extension\MonsterEvolution\Security\EvolutionLimits;
use MediaWiki\Hook\OutputPageParserOutputHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Html\Html;
use OutputPage;
use Parser;
use ParserOutput;
use PPFrame;
use WeakMap;

final class ParserHooks implements ParserFirstCallInitHook, OutputPageParserOutputHook {
	/** @var WeakMap<ParserOutput,int> */
	private WeakMap $graphCounts;

	public function __construct(
		private readonly EvolutionParser $evolutionParser,
		private readonly EvolutionRenderer $renderer,
		private readonly EvolutionLimits $limits,
		private readonly Config $config
	) {
		$this->graphCounts = new WeakMap();
	}

	/** @param Parser $parser */
	public function onParserFirstCallInit( $parser ): void {
		$parser->setHook( 'evolution', [ $this, 'renderTag' ] );
		$parser->setFunctionHook( 'evolution', [ $this, 'renderParserFunction' ], Parser::SFH_OBJECT_ARGS );
	}

	/**
	 * MediaWiki 1.35 can omit parser-added script modules from edit-preview output.
	 * Add the modules directly to OutputPage when this parser output contains a graph.
	 *
	 * @param OutputPage $out
	 * @param ParserOutput $parserOutput
	 */
	public function onOutputPageParserOutput( $out, $parserOutput ): void {
		if ( $parserOutput->getExtensionData( 'MonsterEvolution' ) !== true ) {
			return;
		}
		$out->addModules( [ 'ext.monsterEvolution' ] );
		$out->addModuleStyles( [ 'ext.monsterEvolution.styles' ] );
	}

	/**
	 * @param string $input
	 * @param array<string,string> $attributes
	 * @param Parser $parser
	 * @param PPFrame $frame
	 */
	public function renderTag( string $input, array $attributes, Parser $parser, PPFrame $frame ): string {
		if ( strlen( $input ) > $this->limits->maxInputBytes ) {
			return $this->renderError( $parser, new EvolutionParseException(
				'The graph input is too large.',
				0,
				'monsterevolution-error-input-limit'
			) );
		}
		$expanded = $parser->recursivePreprocess( $input, $frame );
		return $this->renderDefinition( $expanded, $attributes, $parser );
	}

	/**
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param array<int,mixed> $arguments
	 * @return array<int|string,mixed>
	 */
	public function renderParserFunction( Parser $parser, PPFrame $frame, array $arguments ): array {
		if ( $arguments === [] ) {
			return [ $this->renderDefinition( '', [], $parser ), 'noparse' => true, 'isHTML' => true ];
		}
		$source = trim( $frame->expand( $arguments[0] ) );
		$attributes = [];
		foreach ( array_slice( $arguments, 1 ) as $argument ) {
			$option = trim( $frame->expand( $argument ) );
			$equals = strpos( $option, '=' );
			if ( $equals === false ) {
				return [ $this->renderError( $parser, new EvolutionParseException(
					'Parser-function options must use key=value.',
					0
				) ), 'noparse' => true, 'isHTML' => true ];
			}
			$key = trim( substr( $option, 0, $equals ) );
			if ( array_key_exists( $key, $attributes ) ) {
				return [ $this->renderError( $parser, new EvolutionParseException(
					"Duplicate parser-function option \"$key\".",
					0
				) ), 'noparse' => true, 'isHTML' => true ];
			}
			$attributes[$key] = trim( substr( $option, $equals + 1 ) );
		}
		return [ $this->renderDefinition( $source, $attributes, $parser ), 'noparse' => true, 'isHTML' => true ];
	}

	/**
	 * @param string $source
	 * @param array<string,string> $attributes
	 * @param Parser $parser
	 */
	private function renderDefinition( string $source, array $attributes, Parser $parser ): string {
		$output = $parser->getOutput();
		$count = $this->graphCounts[$output] ?? 0;
		if ( $count >= $this->limits->maxGraphsPerPage ) {
			return $this->renderError( $parser, new EvolutionParseException(
				'The per-page graph limit was exceeded.',
				0,
				'monsterevolution-error-graph-limit'
			) );
		}
		$this->graphCounts[$output] = $count + 1;
		try {
			$graph = $this->evolutionParser->parse( $source, $attributes );
			return $this->renderer->render( $graph, $output );
		} catch ( EvolutionParseException $exception ) {
			return $this->renderError( $parser, $exception );
		}
	}

	private function renderError( Parser $parser, EvolutionParseException $exception ): string {
		if ( $this->config->get( 'MonsterEvolutionEnableTrackingCategory' ) ) {
			$parser->addTrackingCategory( 'monsterevolution-error-category' );
		}
		$detail = wfMessage( $exception->messageKey, ...$exception->messageParams )
			->inContentLanguage()->text();
		$message = $exception->sourceLine > 0
			? wfMessage( 'monsterevolution-error-line', $exception->sourceLine, $detail )
				->inContentLanguage()->text()
			: wfMessage( 'monsterevolution-error', $detail )->inContentLanguage()->text();
		return Html::element( 'div', [
			'class' => 'mw-monster-evolution-error error',
			'role' => 'alert',
		], $message );
	}
}
