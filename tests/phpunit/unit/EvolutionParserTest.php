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
