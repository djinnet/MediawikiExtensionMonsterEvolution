<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Parser;

use MediaWiki\Extension\MonsterEvolution\Model\EvolutionCondition;
use MediaWiki\Extension\MonsterEvolution\Model\EvolutionEdge;
use MediaWiki\Extension\MonsterEvolution\Model\EvolutionGraph;
use MediaWiki\Extension\MonsterEvolution\Model\EvolutionNode;
use MediaWiki\Extension\MonsterEvolution\Security\EvolutionLimits;
use MediaWiki\Extension\MonsterEvolution\Security\LocalFileNamePolicy;

/**
 * Turns the bounded evolution language into an immutable graph model.
 *
 * EvolutionTokenizer owns lexical concerns and EvolutionValueValidator owns scalar
 * security policy. This class is the grammar coordinator: it recognizes node and
 * edge statements, resolves endpoints, enforces graph-size limits, and constructs
 * model objects. Keeping those responsibilities explicit makes the data flow easier
 * to audit and prevents rendering details from leaking into parsing.
 */
final class EvolutionParser {
	private const NODE_ATTRIBUTES = [
		'id', 'name', 'image', 'link', 'subtitle', 'form', 'tooltip', 'class',
		'imagewidth', 'imageheight',
	];

	private const EDGE_ATTRIBUTES = [ 'type', 'label', 'conditions', 'icon', 'iconposition', 'link' ];
	private const GRAPH_ATTRIBUTES = [
		'direction', 'theme', 'imagewidth', 'imageheight', 'zoom', 'controls',
		'layout', 'center', 'radialshape', 'radialstart',
	];
	private const DIRECTIONS = [ 'left-to-right', 'right-to-left', 'top-to-bottom', 'bottom-to-top' ];
	private const THEMES = [ 'default', 'compact', 'cards', 'minimal' ];
	private const ICON_POSITIONS = [ 'above', 'next-to' ];
	private const LAYOUTS = [ 'layered', 'radial' ];
	private const RADIAL_SHAPES = [ 'circle', 'polygon' ];
	private const RADIAL_STARTS = [ 'top', 'right', 'bottom', 'left' ];

	private EvolutionTokenizer $tokenizer;
	private EvolutionValueValidator $validator;

	public function __construct(
		private readonly EvolutionLimits $limits,
		private readonly string $defaultDirection = 'left-to-right',
		private readonly string $defaultTheme = 'default',
		private readonly int $defaultImageWidth = 96,
		private readonly int $defaultImageHeight = 96,
		?EvolutionValueValidator $validator = null
	) {
		$this->tokenizer = new EvolutionTokenizer( $limits );
		$this->validator = $validator ?? new EvolutionValueValidator( $limits, new LocalFileNamePolicy() );
	}

	/**
	 * @param string $source
	 * @param array<string,string> $tagAttributes
	 */
	public function parse( string $source, array $tagAttributes = [] ): EvolutionGraph {
		if ( strlen( $source ) > $this->limits->maxInputBytes ) {
			throw $this->error( 'The graph input is too large.', 0, 'monsterevolution-error-input-limit' );
		}
		if ( !mb_check_encoding( $source, 'UTF-8' ) ) {
			throw $this->error( 'The graph input is not valid UTF-8.', 0 );
		}

		$options = $this->validator->normalizeKeys( $tagAttributes );
		$this->validator->assertAllowedAttributes( $options, self::GRAPH_ATTRIBUTES, 0 );
		$direction = strtolower( trim( $options['direction'] ?? $this->defaultDirection ) );
		$direction = match ( $direction ) {
			'horizontal' => 'left-to-right',
			'vertical' => 'top-to-bottom',
			default => $direction,
		};
		if ( !in_array( $direction, self::DIRECTIONS, true ) ) {
			throw $this->error( "Invalid direction \"$direction\".", 0, 'monsterevolution-error-option' );
		}
		$theme = strtolower( trim( $options['theme'] ?? $this->defaultTheme ) );
		if ( !in_array( $theme, self::THEMES, true ) ) {
			throw $this->error( "Invalid theme \"$theme\".", 0, 'monsterevolution-error-option' );
		}
		$layout = strtolower( trim( $options['layout'] ?? 'layered' ) );
		if ( !in_array( $layout, self::LAYOUTS, true ) ) {
			throw $this->error( "Invalid layout \"$layout\".", 0, 'monsterevolution-error-option' );
		}
		$center = $this->validator->nullableTrim( $options['center'] ?? null );
		$radialShape = strtolower( trim( $options['radialshape'] ?? 'circle' ) );
		$radialStart = strtolower( trim( $options['radialstart'] ?? 'top' ) );
		if ( !in_array( $radialShape, self::RADIAL_SHAPES, true ) ) {
			throw $this->error(
				"Invalid radial shape \"$radialShape\".",
				0,
				'monsterevolution-error-option'
			);
		}
		if ( !in_array( $radialStart, self::RADIAL_STARTS, true ) ) {
			throw $this->error(
				"Invalid radial start \"$radialStart\".",
				0,
				'monsterevolution-error-option'
			);
		}
		if ( $layout === 'radial' ) {
			if ( $center === null ) {
				throw $this->error(
					'Radial layout requires a center node ID.',
					0,
					'monsterevolution-error-option'
				);
			}
			$this->validator->validateId( $center, 0 );
		} elseif (
			$center !== null || isset( $options['radialshape'] ) || isset( $options['radialstart'] )
		) {
			throw $this->error(
				'Center and radial options require layout="radial".',
				0,
				'monsterevolution-error-option'
			);
		}
		$imageWidth = $this->validator->parseDimension(
			$options['imagewidth'] ?? null,
			$this->defaultImageWidth,
			0
		);
		$imageHeight = $this->validator->parseDimension(
			$options['imageheight'] ?? null,
			$this->defaultImageHeight,
			0
		);
		$zoom = $this->validator->parseBoolean( $options['zoom'] ?? 'false', 0 );
		$controls = $this->validator->parseBoolean( $options['controls'] ?? 'false', 0 ) && $zoom;
		$graph = new EvolutionGraph(
			direction: $direction,
			theme: $theme,
			defaultImageWidth: $imageWidth,
			defaultImageHeight: $imageHeight,
			zoom: $zoom,
			controls: $controls,
			layout: $layout,
			center: $center,
			radialShape: $radialShape,
			radialStart: $radialStart
		);

		$statements = $this->tokenizer->tokenize( str_replace( "\r\n", "\n", $source ) );
		$edgeStatements = [];
		$hasDeclarations = false;
		foreach ( $statements as $statement ) {
			if ( preg_match( '/^\[\s*node(?:\s|\])/i', $statement->text ) ) {
				$hasDeclarations = true;
				$node = $this->parseNode( $statement );
				if ( isset( $graph->getNodes()[$node->id] ) ) {
					throw $this->error(
						"Duplicate node ID \"{$node->id}\".",
						$statement->line,
						'monsterevolution-error-duplicate-node',
						[ $node->id ]
					);
				}
				if ( count( $graph->getNodes() ) >= $this->limits->maxNodes ) {
					throw $this->error(
						'The node limit was exceeded.',
						$statement->line,
						'monsterevolution-error-node-limit'
					);
				}
				$graph->addNode( $node );
			} else {
				$edgeStatements[] = $statement;
			}
		}

		$autoIds = [];
		foreach ( $edgeStatements as $statement ) {
			$this->parseEdgeStatement( $graph, $statement, $hasDeclarations, $autoIds );
		}
		if ( $graph->getNodes() === [] ) {
			throw $this->error( 'The graph contains no nodes.', 0, 'monsterevolution-error-empty' );
		}
		if ( $center !== null && !isset( $graph->getNodes()[$center] ) ) {
			throw $this->error(
				"Unknown center node \"$center\".",
				0,
				'monsterevolution-error-unknown-node',
				[ $center ]
			);
		}
		return $graph;
	}

	private function parseNode( EvolutionStatement $statement ): EvolutionNode {
		$text = $statement->text;
		if ( $text[strlen( $text ) - 1] !== ']' ) {
			throw $this->error( 'A node declaration must end with ].', $statement->line );
		}
		if ( !preg_match( '/^\[\s*node\b/i', $text, $match ) ) {
			throw $this->error( 'Invalid node declaration.', $statement->line );
		}
		$attributeText = substr( $text, strlen( $match[0] ), -1 );
		$attributes = $this->tokenizer->parseAttributes( $attributeText, $statement->line );
		$this->validator->assertAllowedAttributes( $attributes, self::NODE_ATTRIBUTES, $statement->line );
		$id = trim( $attributes['id'] ?? '' );
		$name = trim( $attributes['name'] ?? '' );
		$this->validator->validateId( $id, $statement->line );
		$this->validator->validateText( $name, 'name', $statement->line, false, true );

		$image = $this->validator->nullableTrim( $attributes['image'] ?? null );
		if ( $image !== null ) {
			$this->validator->validateLocalFileName( $image, 'image', $statement->line );
		}
		$link = $this->validator->nullableTrim( $attributes['link'] ?? null );
		if ( $link !== null ) {
			$this->validator->validateLinkTitle( $link, $statement->line );
		}
		$subtitle = $this->validator->nullableTrim( $attributes['subtitle'] ?? null );
		$form = $this->validator->nullableTrim( $attributes['form'] ?? null );
		$tooltip = $this->validator->nullableTrim( $attributes['tooltip'] ?? null );
		foreach ( [ 'subtitle' => $subtitle, 'form' => $form, 'tooltip' => $tooltip ] as $key => $value ) {
			if ( $value !== null ) {
				$this->validator->validateText( $value, $key, $statement->line, $key === 'tooltip' );
			}
		}

		$classes = [];
		$classValue = $this->validator->nullableTrim( $attributes['class'] ?? null );
		if ( $classValue !== null ) {
			$classes = preg_split( '/\s+/', strtolower( $classValue ) ) ?: [];
			if ( count( $classes ) > 8 ) {
				throw $this->error( 'A node may have at most eight custom classes.', $statement->line );
			}
			foreach ( $classes as $class ) {
				$this->validator->validateSemanticToken( $class, 'class', $statement->line );
			}
		}

		return new EvolutionNode(
			$id,
			$name,
			$image,
			$link,
			$subtitle,
			$form,
			$tooltip,
			$classes,
			isset( $attributes['imagewidth'] )
				? $this->validator->parseDimension(
					$attributes['imagewidth'],
					$this->defaultImageWidth,
					$statement->line
				)
				: null,
			isset( $attributes['imageheight'] )
				? $this->validator->parseDimension(
					$attributes['imageheight'],
					$this->defaultImageHeight,
					$statement->line
				)
				: null
		);
	}

	/**
	 * @param EvolutionGraph $graph
	 * @param EvolutionStatement $statement
	 * @param bool $explicit
	 * @param array<string,string> &$autoIds
	 */
	private function parseEdgeStatement(
		EvolutionGraph $graph,
		EvolutionStatement $statement,
		bool $explicit,
		array &$autoIds
	): void {
		$edgeText = $statement->text;
		$attributes = [];
		$blockStart = strpos( $edgeText, '[' );
		if ( $blockStart !== false ) {
			if ( $edgeText[strlen( $edgeText ) - 1] !== ']' ) {
				throw $this->error( 'An edge attribute block must end with ].', $statement->line );
			}
			$attributes = $this->tokenizer->parseAttributes(
				substr( $edgeText, $blockStart + 1, -1 ),
				$statement->line
			);
			$edgeText = trim( substr( $edgeText, 0, $blockStart ) );
		}
		$this->validator->assertAllowedAttributes( $attributes, self::EDGE_ATTRIBUTES, $statement->line );
		$parts = preg_split(
			'/\s*(<->|->)\s*/',
			$edgeText,
			-1,
			PREG_SPLIT_DELIM_CAPTURE
		);
		if ( $parts === false || count( $parts ) < 3 || count( $parts ) % 2 === 0 ) {
			throw $this->error( 'Expected an evolution such as A -> B.', $statement->line );
		}

		$type = strtolower( trim( $attributes['type'] ?? 'custom' ) );
		$this->validator->validateSemanticToken( $type, 'type', $statement->line );
		$label = $this->validator->nullableTrim( $attributes['label'] ?? null );
		if ( $label !== null ) {
			$this->validator->validateText( $label, 'label', $statement->line, true );
		}
		$conditions = $this->parseConditions( $attributes['conditions'] ?? null, $statement->line );
		$icon = $this->validator->nullableTrim( $attributes['icon'] ?? null );
		if ( $icon !== null ) {
			$this->validator->validateLocalFileName( $icon, 'icon', $statement->line );
		}
		$iconPosition = strtolower( trim( $attributes['iconposition'] ?? 'next-to' ) );
		if ( !in_array( $iconPosition, self::ICON_POSITIONS, true ) ) {
			throw $this->error(
				"Invalid icon position \"$iconPosition\".",
				$statement->line,
				'monsterevolution-error-option'
			);
		}
		$link = $this->validator->nullableTrim( $attributes['link'] ?? null );
		if ( $link !== null ) {
			$this->validator->validateLinkTitle( $link, $statement->line );
		}

		for ( $index = 0; $index < count( $parts ) - 2; $index += 2 ) {
			$sourceText = trim( $parts[$index] );
			$operator = $parts[$index + 1];
			$targetText = trim( $parts[$index + 2] );
			if ( $sourceText === '' || $targetText === '' ) {
				throw $this->error( 'Evolution endpoints cannot be empty.', $statement->line );
			}
			$source = $this->resolveEndpoint( $graph, $sourceText, $explicit, $autoIds, $statement->line );
			$target = $this->resolveEndpoint( $graph, $targetText, $explicit, $autoIds, $statement->line );
			$this->addEdge( $graph, new EvolutionEdge(
				source: $source,
				target: $target,
				type: $type,
				label: $label,
				conditions: $conditions,
				line: $statement->line,
				icon: $icon,
				iconPosition: $iconPosition,
				link: $link
			) );
			if ( $operator === '<->' ) {
				$this->addEdge( $graph, new EvolutionEdge(
					source: $target,
					target: $source,
					type: $type === 'custom' ? 'reversible' : $type,
					label: $label,
					conditions: $conditions,
					line: $statement->line,
					icon: $icon,
					iconPosition: $iconPosition,
					link: $link
				) );
			}
		}
	}

	/**
	 * @param EvolutionGraph $graph
	 * @param string $endpoint
	 * @param bool $explicit
	 * @param array<string,string> &$autoIds
	 * @param int $line
	 */
	private function resolveEndpoint(
		EvolutionGraph $graph,
		string $endpoint,
		bool $explicit,
		array &$autoIds,
		int $line
	): string {
		if ( $explicit ) {
			$this->validator->validateId( $endpoint, $line );
			if ( !isset( $graph->getNodes()[$endpoint] ) ) {
				throw $this->error(
					"Unknown node \"$endpoint\".",
					$line,
					'monsterevolution-error-unknown-node',
					[ $endpoint ]
				);
			}
			return $endpoint;
		}

		$this->validator->validateText( $endpoint, 'name', $line, false, true );
		if ( isset( $autoIds[$endpoint] ) ) {
			return $autoIds[$endpoint];
		}
		if ( count( $graph->getNodes() ) >= $this->limits->maxNodes ) {
			throw $this->error( 'The node limit was exceeded.', $line, 'monsterevolution-error-node-limit' );
		}
		$id = 'auto-' . ( count( $autoIds ) + 1 );
		$autoIds[$endpoint] = $id;
		$graph->addNode( new EvolutionNode( $id, $endpoint, null, $endpoint ) );
		return $id;
	}

	private function addEdge( EvolutionGraph $graph, EvolutionEdge $edge ): void {
		if ( count( $graph->getEdges() ) >= $this->limits->maxEdges ) {
			throw $this->error( 'The edge limit was exceeded.', $edge->line, 'monsterevolution-error-edge-limit' );
		}
		$graph->addEdge( $edge );
	}

	/** @return EvolutionCondition[] */
	private function parseConditions( ?string $value, int $line ): array {
		$value = $this->validator->nullableTrim( $value );
		if ( $value === null ) {
			return [];
		}
		$this->validator->validateText( $value, 'conditions', $line, true );
		$parts = preg_split( '/[;\n]/u', $value ) ?: [];
		$conditions = [];
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( $part === '' ) {
				continue;
			}
			if ( count( $conditions ) >= $this->limits->maxConditionsPerEdge ) {
				throw $this->error(
					'The condition limit was exceeded.',
					$line,
					'monsterevolution-error-condition-limit'
				);
			}
			$conditions[] = new EvolutionCondition( $part );
		}
		return $conditions;
	}

	private function error(
		string $message,
		int $line,
		string $messageKey = 'monsterevolution-error-syntax',
		array $params = []
	): EvolutionParseException {
		return new EvolutionParseException( $message, $line, $messageKey, $params ?: [ $message ] );
	}
}
