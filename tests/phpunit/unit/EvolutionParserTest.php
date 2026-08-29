<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Tests\Unit;

use MediaWiki\Extension\MonsterEvolution\Parser\EvolutionParseException;
use MediaWiki\Extension\MonsterEvolution\Parser\EvolutionParser;
use MediaWiki\Extension\MonsterEvolution\Security\EvolutionLimits;
use PHPUnit\Framework\TestCase;

/** @covers \MediaWiki\Extension\MonsterEvolution\Parser\EvolutionParser */
final class EvolutionParserTest extends TestCase {
	private EvolutionParser $parser;

	protected function setUp(): void {
		$this->parser = new EvolutionParser( new EvolutionLimits() );
	}

	public function testLinearShorthand(): void {
		$graph = $this->parser->parse( 'Slime -> Big Slime -> King Slime' );
		$this->assertCount( 3, $graph->getNodes() );
		$this->assertCount( 2, $graph->getEdges() );
		$this->assertSame( 'Slime', $graph->getNodes()['auto-1']->name );
		$this->assertSame( 'Slime', $graph->getNodes()['auto-1']->link );
	}

	public function testBranchingAndMultipleParents(): void {
		$source = <<<'WIKI'
[node id="a" name="A"]
[node id="b" name="B"]
[node id="c" name="C"]
[node id="d" name="D"]
a -> b
a -> c
b -> d
c -> d
WIKI;
		$graph = $this->parser->parse( $source );
		$this->assertCount( 4, $graph->getNodes() );
		$this->assertCount( 4, $graph->getEdges() );
		$this->assertSame( 'd', $graph->getEdges()[2]->target );
		$this->assertSame( 'd', $graph->getEdges()[3]->target );
	}

	public function testFusionConditionsAndUnicode(): void {
		$source = <<<'WIKI'
[node id="fire" name="火竜 🐉"]
[node id="dark" name="Dark Dragon"]
[node id="chaos" name="Chaos Dragon"]
fire -> chaos [type="fusion" label="Fusion" conditions="Level >= 50; Time = Night"]
dark -> chaos [type="fusion"]
WIKI;
		$graph = $this->parser->parse( $source );
		$this->assertCount( 2, $graph->getEdges() );
		$this->assertCount( 2, $graph->getEdges()[0]->conditions );
		$this->assertSame( '火竜 🐉', $graph->getNodes()['fire']->name );
	}

	public function testEdgeLabelIconsAndPositions(): void {
		$graph = $this->parser->parse( <<<'WIKI'
A -> B [label="Fire Stone" icon="Fire Stone.png" link="Item:Fire Stone"]
B -> C [label="Moon Stone" icon="Moon Stone.svg" iconPosition="above"]
C -> D [icon="Trade.webp"]
WIKI );
		$this->assertSame( 'Fire Stone.png', $graph->getEdges()[0]->icon );
		$this->assertSame( 'Item:Fire Stone', $graph->getEdges()[0]->link );
		$this->assertSame( 'next-to', $graph->getEdges()[0]->iconPosition );
		$this->assertSame( 'above', $graph->getEdges()[1]->iconPosition );
		$this->assertNull( $graph->getEdges()[2]->label );
	}

	/** @dataProvider invalidEdgeIconProvider */
	public function testUnsafeEdgeIconsAreRejected( string $icon ): void {
		$this->expectException( EvolutionParseException::class );
		$this->parser->parse( 'A -> B [icon="' . $icon . '"]' );
	}

	public static function invalidEdgeIconProvider(): array {
		return [
			'remote' => [ 'https://attacker.example/stone.png' ],
			'traversal' => [ '../LocalSettings.php' ],
			'namespace prefix' => [ 'File:Fire Stone.png' ],
			'windows path' => [ 'C:\\Windows\\win.ini' ],
		];
	}

	public function testInvalidEdgeIconPositionIsRejected(): void {
		$this->expectException( EvolutionParseException::class );
		$this->parser->parse( 'A -> B [iconPosition="below"]' );
	}

	/** @dataProvider invalidEdgeLinkProvider */
	public function testExternalOrExecutableEdgeLinksAreRejected( string $link ): void {
		$this->expectException( EvolutionParseException::class );
		$this->parser->parse( 'A -> B [link="' . $link . '"]' );
	}

	public static function invalidEdgeLinkProvider(): array {
		return [
			'remote' => [ 'https://attacker.example/Fire_Stone' ],
			'script' => [ 'javascript:alert(1)' ],
			'data' => [ 'data:text/html,payload' ],
			'local file URL' => [ 'file:///etc/passwd' ],
		];
	}

	public function testReversibleCycleAndSelfLoopRemainFinite(): void {
		$source = <<<'WIKI'
A <-> B
B -> C
C -> A
C -> C
WIKI;
		$graph = $this->parser->parse( $source );
		$this->assertCount( 5, $graph->getEdges() );
		$this->assertSame( 'reversible', $graph->getEdges()[1]->type );
		$this->assertSame( $graph->getEdges()[4]->source, $graph->getEdges()[4]->target );
	}

	public function testDirectionsAndThemes(): void {
		$graph = $this->parser->parse( 'A -> B', [
			'direction' => 'vertical',
			'theme' => 'compact',
			'zoom' => 'true',
			'controls' => 'true',
		] );
		$this->assertSame( 'top-to-bottom', $graph->direction );
		$this->assertSame( 'compact', $graph->theme );
		$this->assertTrue( $graph->zoom );
		$this->assertTrue( $graph->controls );
	}

	public function testRadialLayoutOptions(): void {
		$graph = $this->parser->parse( <<<'WIKI'
[node id="eevee" name="Eevee"]
[node id="flareon" name="Flareon"]
eevee -> flareon
WIKI, [
			'layout' => 'radial',
			'center' => 'eevee',
			'radialShape' => 'polygon',
			'radialStart' => 'right',
		] );

		$this->assertSame( 'radial', $graph->layout );
		$this->assertSame( 'eevee', $graph->center );
		$this->assertSame( 'polygon', $graph->radialShape );
		$this->assertSame( 'right', $graph->radialStart );
	}

	/** @dataProvider invalidRadialOptionsProvider */
	public function testInvalidRadialOptionsAreRejected( array $options ): void {
		$this->expectException( EvolutionParseException::class );
		$this->parser->parse( <<<'WIKI'
[node id="eevee" name="Eevee"]
[node id="flareon" name="Flareon"]
eevee -> flareon
WIKI, $options );
	}

	public static function invalidRadialOptionsProvider(): array {
		return [
			'missing center' => [ [ 'layout' => 'radial' ] ],
			'unknown center' => [ [ 'layout' => 'radial', 'center' => 'missing' ] ],
			'center on layered' => [ [ 'center' => 'eevee' ] ],
			'unknown layout' => [ [ 'layout' => 'orbit' ] ],
			'unknown shape' => [
				[ 'layout' => 'radial', 'center' => 'eevee', 'radialShape' => 'hexagon' ],
			],
			'unknown start' => [
				[ 'layout' => 'radial', 'center' => 'eevee', 'radialStart' => 'diagonal' ],
			],
		];
	}

	public function testUnknownNodeIsControlledError(): void {
		$this->expectException( EvolutionParseException::class );
		$this->expectExceptionMessage( 'Unknown node' );
		$this->parser->parse( <<<'WIKI'
[node id="a" name="A"]
a -> typo
WIKI );
	}

	public function testDuplicateIdIsControlledError(): void {
		$this->expectException( EvolutionParseException::class );
		$this->parser->parse( <<<'WIKI'
[node id="same" name="A"]
[node id="same" name="B"]
WIKI );
	}

	/** @dataProvider unsafeAttributeProvider */
	public function testUnsafeOrUnknownAttributesAreRejected( string $attribute ): void {
		$this->expectException( EvolutionParseException::class );
		$this->parser->parse( "[node id=\"safe\" name=\"Safe\" $attribute]" );
	}

	public static function unsafeAttributeProvider(): array {
		return [
			'onclick' => [ 'onclick="alert(1)"' ],
			'onerror' => [ 'onerror="alert(1)"' ],
			'style' => [ 'style="background:url(javascript:alert(1))"' ],
			'srcdoc' => [ 'srcdoc="<script>alert(1)</script>"' ],
		];
	}

	/** @dataProvider invalidImageProvider */
	public function testRemoteAndPathImagesAreRejected( string $image ): void {
		$this->expectException( EvolutionParseException::class );
		$this->parser->parse( '[node id="safe" name="Safe" image="' . $image . '"]' );
	}

	public static function invalidImageProvider(): array {
		return [
			'remote' => [ 'https://attacker.example/x.png' ],
			'unix traversal' => [ '../../LocalSettings.php' ],
			'windows traversal' => [ '..\\..\\LocalSettings.php' ],
			'unix absolute' => [ '/etc/passwd' ],
			'windows absolute' => [ 'C:\\Windows\\win.ini' ],
			'encoded traversal' => [ '%2e%2e%2fsecret' ],
		];
	}

	public function testLimitsAreEnforcedBeforeGraphGrowth(): void {
		$parser = new EvolutionParser( new EvolutionLimits( maxNodes: 2 ) );
		$this->expectException( EvolutionParseException::class );
		$parser->parse( 'A -> B -> C' );
	}
}
