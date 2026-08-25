<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Rendering;

use File;
use Html;
use HtmlArmor;
use MediaWiki\Extension\MonsterEvolution\Model\EvolutionEdge;
use MediaWiki\Extension\MonsterEvolution\Model\EvolutionGraph;
use MediaWiki\Extension\MonsterEvolution\Model\EvolutionNode;
use MediaWiki\Extension\MonsterEvolution\Resolution\WikiFileResolver;
use MediaWiki\Extension\MonsterEvolution\Resolution\WikiLinkResolver;
use MediaWiki\Linker\LinkRenderer;
use ParserOutput;
use Title;

final class EvolutionRenderer {
	public function __construct(
		private readonly WikiLinkResolver $linkResolver,
		private readonly WikiFileResolver $fileResolver,
		private readonly LinkRenderer $linkRenderer,
		private readonly bool $zoomEnabled,
		private readonly string $missingImage
	) {
	}

	public function render( EvolutionGraph $graph, ParserOutput $output ): string {
		$output->setExtensionData( 'MonsterEvolution', true );
		$output->addModuleStyles( [ 'ext.monsterEvolution.styles' ] );
		$output->addModules( [ 'ext.monsterEvolution' ] );
		$nodes = array_values( $graph->getNodes() );
		$indexes = [];
		foreach ( $nodes as $index => $node ) {
			$indexes[$node->id] = $index;
		}

		$nodeHtml = '';
		foreach ( $nodes as $index => $node ) {
			$nodeHtml .= $this->renderNode( $node, $index, $graph, $output );
		}
		$canvas = Html::rawElement(
			'div',
			[ 'class' => 'mw-monster-evolution-canvas' ],
			Html::element( 'svg', [
				'class' => 'mw-monster-evolution-svg',
				'aria-hidden' => 'true',
				'focusable' => 'false',
			] ) . $nodeHtml
		);
		$stage = Html::rawElement( 'div', [ 'class' => 'mw-monster-evolution-stage' ], $canvas );
		$viewport = Html::rawElement( 'div', [
			'class' => 'mw-monster-evolution-viewport',
			'tabindex' => '0',
		], $stage );
		$controls = $this->renderControls( $graph );
		$relationships = $this->renderRelationships( $graph->getEdges(), $graph, $indexes );

		return Html::rawElement( 'div', [
			'class' => 'mw-monster-evolution mw-monster-evolution--' . $graph->theme .
				' mw-monster-evolution--' . $graph->direction,
			'data-direction' => $graph->direction,
			'data-zoom' => $graph->zoom && $this->zoomEnabled ? 'true' : 'false',
			'aria-label' => wfMessage( 'monsterevolution-graph-label' )->inContentLanguage()->text(),
		], $controls . $viewport . $relationships );
	}

	private function renderNode(
		EvolutionNode $node,
		int $index,
		EvolutionGraph $graph,
		ParserOutput $output
	): string {
		$linkTitle = $node->link !== null ? $this->linkResolver->resolve( $node->link ) : null;
		if ( $linkTitle !== null ) {
			$output->addLink( $linkTitle );
		}
		$classes = [ 'mw-monster-evolution-node' ];
		foreach ( $node->classes as $class ) {
			$classes[] = 'mw-monster-evolution-node--custom-' . $class;
		}
		$content = $this->renderImage( $node, $graph, $linkTitle, $output );
		$name = Html::element( 'span', [ 'class' => 'mw-monster-evolution-node-name' ], $node->name );
		$content .= $linkTitle !== null
			? $this->linkRenderer->makeLink( $linkTitle, new HtmlArmor( $name ) )
			: $name;
		if ( $node->form !== null ) {
			$content .= Html::element( 'span', [ 'class' => 'mw-monster-evolution-node-form' ], $node->form );
		}
		if ( $node->subtitle !== null ) {
			$content .= Html::element(
				'span',
				[ 'class' => 'mw-monster-evolution-node-subtitle' ],
				$node->subtitle
			);
		}
		if ( $node->tooltip !== null ) {
			$content .= Html::rawElement(
				'details',
				[ 'class' => 'mw-monster-evolution-node-details' ],
				Html::element(
					'summary',
					[],
					wfMessage( 'monsterevolution-more-information' )->inContentLanguage()->text()
				) . Html::element( 'div', [], $node->tooltip )
			);
		}
		$content .= Html::element( 'button', [
			'type' => 'button',
			'class' => 'mw-monster-evolution-highlight',
			'aria-pressed' => 'false',
			'aria-label' => wfMessage( 'monsterevolution-highlight-paths', $node->name )
				->inContentLanguage()->text(),
		], '◎' );

		return Html::rawElement( 'article', [
			'class' => implode( ' ', $classes ),
			'data-node-index' => (string)$index,
		], $content );
	}

	private function renderImage(
		EvolutionNode $node,
		EvolutionGraph $graph,
		?Title $linkTitle,
		ParserOutput $output
	): string {
		if ( $node->image === null ) {
			return '';
		}
		$file = $this->fileResolver->resolve( $node->image );
		if ( $file === null && $this->missingImage !== '' ) {
			$file = $this->fileResolver->resolve( $this->missingImage );
		}
		if ( $file === null ) {
			$output->addImage( $node->image, false, false );
			return Html::element( 'span', [
				'class' => 'mw-monster-evolution-node-image mw-monster-evolution-node-image--missing',
				'role' => 'img',
				'aria-label' => wfMessage( 'monsterevolution-missing-image', $node->name )
					->inContentLanguage()->text(),
			], wfMessage( 'monsterevolution-missing-image-short' )->inContentLanguage()->text() );
		}

		$this->registerFileDependency( $output, $file );
		$width = $node->imageWidth ?? $graph->defaultImageWidth;
		$height = $node->imageHeight ?? $graph->defaultImageHeight;
		$thumbnail = $file->transform( [ 'width' => $width, 'height' => $height ] );
		if ( !$thumbnail || $thumbnail->isError() ) {
			return Html::element( 'span', [
				'class' => 'mw-monster-evolution-node-image mw-monster-evolution-node-image--missing',
			], wfMessage( 'monsterevolution-missing-image-short' )->inContentLanguage()->text() );
		}
		$imageHtml = Html::rawElement( 'span', [ 'class' => 'mw-monster-evolution-node-image' ],
			$thumbnail->toHtml( [ 'alt' => $node->name, 'loading' => 'lazy' ] )
		);
		return $linkTitle !== null
			? $this->linkRenderer->makeLink( $linkTitle, new HtmlArmor( $imageHtml ) )
			: $imageHtml;
	}

	private function registerFileDependency( ParserOutput $output, File $file ): void {
		$output->addImage( $file->getName(), $file->getTimestamp(), $file->getSha1() );
	}

	/**
	 * @param EvolutionEdge[] $edges
	 * @param array<string,int> $indexes
	 */
	private function renderRelationships( array $edges, EvolutionGraph $graph, array $indexes ): string {
		$items = '';
		$nodes = $graph->getNodes();
		foreach ( $edges as $edgeIndex => $edge ) {
			$source = $nodes[$edge->source];
			$target = $nodes[$edge->target];
			$summary = wfMessage( 'monsterevolution-relationship', $source->name, $target->name )
				->inContentLanguage()->text();
			if ( $edge->label !== null ) {
				$summary .= ' — ' . $edge->label;
			}
			$content = Html::element( 'span', [ 'class' => 'mw-monster-evolution-edge-summary' ], $summary );
			if ( $edge->conditions !== [] ) {
				$conditionItems = '';
				foreach ( $edge->conditions as $condition ) {
					$conditionItems .= Html::element( 'li', [], $condition->text );
				}
				$content .= Html::rawElement( 'details', [ 'class' => 'mw-monster-evolution-condition' ],
					Html::element( 'summary', [], wfMessage( 'monsterevolution-requirements' )
						->inContentLanguage()->text() ) . Html::rawElement( 'ul', [], $conditionItems )
				);
			}
			$items .= Html::rawElement( 'li', [
				'class' => 'mw-monster-evolution-edge mw-monster-evolution-edge--' . $edge->type,
				'data-edge-index' => (string)$edgeIndex,
				'data-source' => (string)$indexes[$edge->source],
				'data-target' => (string)$indexes[$edge->target],
				'data-edge-type' => $edge->type,
				'data-edge-label' => $edge->label ?? '',
			], $content );
		}
		return Html::rawElement( 'section', [
			'class' => 'mw-monster-evolution-relationships',
			'aria-label' => wfMessage( 'monsterevolution-relationships-label' )->inContentLanguage()->text(),
		], Html::element( 'h3', [ 'class' => 'mw-monster-evolution-visually-hidden' ],
			wfMessage( 'monsterevolution-relationships-label' )->inContentLanguage()->text()
		) . Html::rawElement( 'ul', [], $items ) );
	}

	private function renderControls( EvolutionGraph $graph ): string {
		if ( !$this->zoomEnabled || !$graph->controls ) {
			return '';
		}
		$buttons = '';
		foreach ( [
			'in' => 'monsterevolution-zoom-in',
			'out' => 'monsterevolution-zoom-out',
			'reset' => 'monsterevolution-zoom-reset',
			'fit' => 'monsterevolution-zoom-fit',
		] as $action => $message ) {
			$buttons .= Html::element( 'button', [
				'type' => 'button',
				'class' => 'mw-monster-evolution-control',
				'data-zoom-action' => $action,
			], wfMessage( $message )->inContentLanguage()->text() );
		}
		return Html::rawElement( 'div', [
			'class' => 'mw-monster-evolution-controls',
			'role' => 'group',
			'aria-label' => wfMessage( 'monsterevolution-zoom-controls' )->inContentLanguage()->text(),
		], $buttons );
	}
}
