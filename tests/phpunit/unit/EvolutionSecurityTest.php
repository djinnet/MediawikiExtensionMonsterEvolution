<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Tests\Unit;

use MediaWiki\Extension\MonsterEvolution\Parser\EvolutionParseException;
use MediaWiki\Extension\MonsterEvolution\Parser\EvolutionParser;
use MediaWiki\Extension\MonsterEvolution\Security\EvolutionLimits;
use PHPUnit\Framework\TestCase;

/** @covers \MediaWiki\Extension\MonsterEvolution\Parser\EvolutionParser */
final class EvolutionSecurityTest extends TestCase {
	/** @dataProvider textPayloadProvider */
	public function testTextPayloadsRemainPlainModelData( string $payload ): void {
		$parser = new EvolutionParser( new EvolutionLimits() );
		$escaped = str_replace( [ '\\', '"' ], [ '\\\\', '\\"' ], $payload );
		$graph = $parser->parse( '[node id="safe" name="' . $escaped . '"]' );
		$this->assertSame( $payload, $graph->getNodes()['safe']->name );
	}

	public static function textPayloadProvider(): array {
		return [
			'script' => [ '<script>alert(1)</script>' ],
			'image event' => [ '<img src=x onerror=alert(1)>' ],
			'svg event' => [ '<svg onload=alert(1)>' ],
			'attribute break' => [ '\"><script>alert(1)</script>' ],
			'closing tag' => [ '</div><script>alert(1)</script>' ],
			'css text' => [ '</style><script>alert(1)</script>' ],
		];
	}

	/** @dataProvider invalidLinkProvider */
	public function testExternalAndExecutableLinksAreRejected( string $link ): void {
		$parser = new EvolutionParser( new EvolutionLimits() );
		$this->expectException( EvolutionParseException::class );
		$parser->parse( '[node id="safe" name="Safe" link="' . $link . '"]' );
	}

	public static function invalidLinkProvider(): array {
		return [
			'javascript' => [ 'javascript:alert(1)' ],
			'data' => [ 'data:text/html,x' ],
			'file' => [ 'file:///etc/passwd' ],
			'vbscript' => [ 'vbscript:msgbox(1)' ],
			'localhost' => [ 'https://127.0.0.1/' ],
			'url' => [ 'http://localhost/' ],
		];
	}

	public function testValueAndInputLimits(): void {
		$parser = new EvolutionParser( new EvolutionLimits( maxInputBytes: 64, maxValueLength: 16 ) );
		try {
			$parser->parse( str_repeat( 'A', 65 ) );
			$this->fail( 'Oversized input should fail.' );
		} catch ( EvolutionParseException $exception ) {
			$this->assertSame( 'monsterevolution-error-input-limit', $exception->messageKey );
		}

		$this->expectException( EvolutionParseException::class );
		$parser->parse( '[node id="safe" name="' . str_repeat( 'x', 17 ) . '"]' );
	}

	public function testMalformedFuzzCorpusAlwaysReturnsOrThrowsControlledError(): void {
		$parser = new EvolutionParser( new EvolutionLimits( maxInputBytes: 4096 ) );
		$corpus = [
			'[', ']', '[node', '[node id="x]', '[node id="x" id="y"]',
			'A ----> B', 'A ->', '-> B', "[node id=\"x\" name=\"\0\"]",
			str_repeat( '[', 1000 ), str_repeat( '"', 1000 ),
			"[node id=\"x\" name=\"e\u{0301}🐉\"]\nx -> x",
		];
		foreach ( $corpus as $input ) {
			try {
				$graph = $parser->parse( $input );
				$this->assertNotEmpty( $graph->getNodes() );
			} catch ( EvolutionParseException $exception ) {
				$this->assertNotSame( '', $exception->getMessage() );
			}
		}
	}
}
