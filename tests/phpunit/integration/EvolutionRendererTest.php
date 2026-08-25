<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Tests\Integration;

use MediaWiki\Extension\MonsterEvolution\Model\EvolutionEdge;
use MediaWiki\Extension\MonsterEvolution\Model\EvolutionGraph;
use MediaWiki\Extension\MonsterEvolution\Model\EvolutionNode;
use MediaWiki\Extension\MonsterEvolution\Rendering\EvolutionRenderer;
use ParserOutput;
use MediaWikiIntegrationTestCase;

final class EvolutionRendererTest extends MediaWikiIntegrationTestCase {
	private function getRenderer(): EvolutionRenderer {
		return $this->getServiceContainer()->getService( 'MonsterEvolution.Renderer' );
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

	public function testInternalLinkUsesMediaWikiLinkRenderer(): void {
		$graph = new EvolutionGraph( 'left-to-right', 'default', 96, 96, false, false );
		$graph->addNode( new EvolutionNode( 'safe', 'Safe title', null, 'Help:Contents' ) );
		$output = new ParserOutput();
		$html = $this->getRenderer()->render( $graph, $output );

		$this->assertStringContainsString( '<a ', $html );
		$this->assertStringContainsString( 'Safe title', $html );
		$this->assertNotEmpty( $output->getLinks() );
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
}
