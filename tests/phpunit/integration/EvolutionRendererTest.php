<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Tests\Integration;

use MediaWiki\Extension\MonsterEvolution\Model\EvolutionEdge;
use MediaWiki\Extension\MonsterEvolution\Model\EvolutionGraph;
use MediaWiki\Extension\MonsterEvolution\Model\EvolutionNode;
use MediaWiki\Extension\MonsterEvolution\Rendering\EvolutionRenderer;
use MediaWiki\Extension\MonsterEvolution\Resolution\WikiFileResolver;
use MediaWiki\Extension\MonsterEvolution\Resolution\WikiLinkResolver;
use MediaWikiIntegrationTestCase;
use ParserOutput;

/** @covers \MediaWiki\Extension\MonsterEvolution\Rendering\EvolutionRenderer */
final class EvolutionRendererTest extends MediaWikiIntegrationTestCase {
	private function getRenderer(): EvolutionRenderer {
		return $this->getServiceContainer()->getService( 'MonsterEvolution.Renderer' );
	}

	private function newRenderer( bool $missingPageTrackingEnabled ): EvolutionRenderer {
		$services = $this->getServiceContainer();
		return new EvolutionRenderer(
			new WikiLinkResolver( $services->getTitleFactory() ),
			new WikiFileResolver( $services->getTitleFactory(), $services->getRepoGroup() ),
			$services->getLinkRendererFactory()->create(),
			true,
			'',
			$missingPageTrackingEnabled
		);
	}

	public function testHostileTextIsEscapedInEveryRenderedContext(): void {
		$payload = '\"><script>alert(1)</script><img src=x onerror=alert(1)>';
		$graph = new EvolutionGraph( 'left-to-right', 'default', 96, 96, false, false );
		$graph->addNode( new EvolutionNode( 'a', $payload, null, null, $payload, null, $payload ) );
		$graph->addNode( new EvolutionNode( 'b', 'B' ) );
		$graph->addEdge( new EvolutionEdge( 'a', 'b', 'custom', $payload ) );

		$html = $this->getRenderer()->render( $graph, new ParserOutput() );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<img src=x', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function testRendererMarksOutputAndRegistersBothResourceModules(): void {
		$graph = new EvolutionGraph( 'bottom-to-top', 'minimal', 96, 96, false, false );
		$graph->addNode( new EvolutionNode( 'a', 'A' ) );
		$output = new ParserOutput();
		$this->getRenderer()->render( $graph, $output );

		$this->assertTrue( $output->getExtensionData( 'MonsterEvolution' ) );
		$this->assertContains( 'ext.monsterEvolution', $output->getModules() );
		$this->assertContains( 'ext.monsterEvolution.styles', $output->getModuleStyles() );
	}

	public function testRendererExposesValidatedRadialMetadata(): void {
		$graph = new EvolutionGraph(
			direction: 'left-to-right',
			theme: 'default',
			defaultImageWidth: 96,
			defaultImageHeight: 96,
			zoom: false,
			controls: false,
			layout: 'radial',
			center: 'eevee',
			radialShape: 'polygon',
			radialStart: 'right'
		);
		$graph->addNode( new EvolutionNode( 'flareon', 'Flareon' ) );
		$graph->addNode( new EvolutionNode( 'eevee', 'Eevee' ) );
		$graph->addEdge( new EvolutionEdge( 'eevee', 'flareon' ) );
		$html = $this->getRenderer()->render( $graph, new ParserOutput() );

		$this->assertStringContainsString( 'data-layout="radial"', $html );
		$this->assertStringContainsString( 'data-center="1"', $html );
		$this->assertStringContainsString( 'data-radial-shape="polygon"', $html );
		$this->assertStringContainsString( 'data-radial-start="right"', $html );
	}

	public function testInternalLinkUsesMediaWikiLinkRenderer(): void {
		$graph = new EvolutionGraph( 'left-to-right', 'default', 96, 96, false, false );
		$graph->addNode( new EvolutionNode( 'safe', 'Safe title', null, 'Help:Contents' ) );
		$output = new ParserOutput();
		$html = $this->getRenderer()->render( $graph, $output );

		$this->assertStringContainsString( '<a ', $html );
		$this->assertStringContainsString( 'Safe title', $html );
		$this->assertNotEmpty( $output->getLinks() );
	}

	public function testMissingNodeLinkMarksOutputForTrackingCategory(): void {
		$renderer = $this->newRenderer( true );
		$graph = new EvolutionGraph( 'left-to-right', 'default', 96, 96, false, false );
		$graph->addNode( new EvolutionNode(
			'missing',
			'Missing creature',
			null,
			'MonsterEvolution test missing target 7f052db4'
		) );
		$output = new ParserOutput();

		$renderer->render( $graph, $output );

		$this->assertTrue( $output->getExtensionData( 'MonsterEvolutionMissingPage' ) );
		$this->assertNotEmpty( $output->getLinks() );
	}

	public function testInvalidEdgeLinkMarksOutputForTrackingCategory(): void {
		$renderer = $this->newRenderer( true );
		$graph = new EvolutionGraph( 'left-to-right', 'default', 96, 96, false, false );
		$graph->addNode( new EvolutionNode( 'a', 'A' ) );
		$graph->addNode( new EvolutionNode( 'b', 'B' ) );
		$graph->addEdge( new EvolutionEdge( 'a', 'b', link: '[' ) );
		$output = new ParserOutput();

		$renderer->render( $graph, $output );

		$this->assertTrue( $output->getExtensionData( 'MonsterEvolutionMissingPage' ) );
	}

	public function testKnownLinkDoesNotMarkOutputForTrackingCategory(): void {
		$renderer = $this->newRenderer( true );
		$graph = new EvolutionGraph( 'left-to-right', 'default', 96, 96, false, false );
		$graph->addNode( new EvolutionNode( 'known', 'Known target', null, 'Special:Version' ) );
		$output = new ParserOutput();

		$renderer->render( $graph, $output );

		$this->assertNull( $output->getExtensionData( 'MonsterEvolutionMissingPage' ) );
	}

	public function testDisabledMissingPageTrackingDoesNotMarkOutput(): void {
		$renderer = $this->newRenderer( false );
		$graph = new EvolutionGraph( 'left-to-right', 'default', 96, 96, false, false );
		$graph->addNode( new EvolutionNode(
			'missing',
			'Missing creature',
			null,
			'MonsterEvolution test missing target 7f052db4'
		) );
		$output = new ParserOutput();

		$renderer->render( $graph, $output );

		$this->assertNull( $output->getExtensionData( 'MonsterEvolutionMissingPage' ) );
	}

	public function testMissingImageHasNoDirectImageUrl(): void {
		$graph = new EvolutionGraph( 'left-to-right', 'default', 96, 96, false, false );
		$graph->addNode( new EvolutionNode( 'safe', 'Safe', 'Certainly Missing 12345.png' ) );
		$html = $this->getRenderer()->render( $graph, new ParserOutput() );

		$this->assertStringContainsString( 'mw-monster-evolution-node-image--missing', $html );
		$this->assertStringNotContainsString( '/images/', $html );
		$this->assertStringNotContainsString( 'http://', $html );
		$this->assertStringNotContainsString( 'https://', $html );
	}

	public function testMissingEdgeIconPreservesTextAndSafePositionMetadata(): void {
		$graph = new EvolutionGraph( 'left-to-right', 'default', 96, 96, false, false );
		$graph->addNode( new EvolutionNode( 'a', 'A' ) );
		$graph->addNode( new EvolutionNode( 'b', 'B' ) );
		$graph->addEdge( new EvolutionEdge(
			source: 'a',
			target: 'b',
			label: 'Rare stone',
			icon: 'Certainly Missing Edge Icon 12345.png',
			iconPosition: 'above'
		) );
		$output = new ParserOutput();
		$html = $this->getRenderer()->render( $graph, $output );

		$this->assertStringContainsString( 'Rare stone', $html );
		$this->assertStringContainsString( 'data-edge-icon-position="above"', $html );
		$this->assertStringNotContainsString( 'mw-monster-evolution-edge-icon-source', $html );
		$this->assertNotEmpty( $output->getImages() );
	}

	public function testEdgeLabelLinkUsesMediaWikiLinkRenderer(): void {
		$graph = new EvolutionGraph( 'left-to-right', 'default', 96, 96, false, false );
		$graph->addNode( new EvolutionNode( 'a', 'Eevee' ) );
		$graph->addNode( new EvolutionNode( 'b', 'Flareon' ) );
		$graph->addEdge( new EvolutionEdge(
			source: 'a',
			target: 'b',
			label: 'Fire Stone',
			link: 'Fire Stone'
		) );
		$output = new ParserOutput();
		$html = $this->getRenderer()->render( $graph, $output );

		$this->assertStringContainsString( 'mw-monster-evolution-edge-label-link', $html );
		$this->assertStringContainsString( 'Fire Stone', $html );
		$this->assertNotEmpty( $output->getLinks() );
	}
}
