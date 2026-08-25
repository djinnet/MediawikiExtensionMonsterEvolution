<?php

declare( strict_types=1 );

use MediaWiki\Extension\MonsterEvolution\Parser\EvolutionParseException;
use MediaWiki\Extension\MonsterEvolution\Parser\EvolutionParser;
use MediaWiki\Extension\MonsterEvolution\Security\EvolutionLimits;

spl_autoload_register( static function ( string $class ): void {
	$prefix = 'MediaWiki\\Extension\\MonsterEvolution\\';
	if ( !str_starts_with( $class, $prefix ) ) {
		return;
	}
	$path = dirname( __DIR__ ) . '/src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
	if ( is_file( $path ) ) {
		require_once $path;
	}
} );

$checks = 0;
$failures = [];

$assert = static function ( bool $condition, string $message ) use ( &$checks, &$failures ): void {
	$checks++;
	if ( !$condition ) {
		$failures[] = $message;
	}
};

$expectError = static function ( callable $callback, string $message ) use ( $assert ): void {
	try {
		$callback();
		$assert( false, $message );
	} catch ( EvolutionParseException ) {
		$assert( true, $message );
	}
};

$parser = new EvolutionParser( new EvolutionLimits() );
$linear = $parser->parse( 'Slime -> Big Slime -> King Slime' );
$assert( count( $linear->getNodes() ) === 3, 'Linear shorthand creates three nodes.' );
$assert( count( $linear->getEdges() ) === 2, 'Linear shorthand creates two edges.' );

$complex = $parser->parse( <<<'WIKI'
[node id="a" name="A"]
[node id="b" name="B"]
[node id="c" name="C"]
[node id="d" name="D"]
a -> b [type="level" label="Level 10"]
a -> c
b -> d [conditions="Night; Item = Stone"]
c -> d [type="fusion"]
d -> a
d -> d
WIKI );
$assert( count( $complex->getNodes() ) === 4, 'Explicit graph creates four nodes.' );
$assert( count( $complex->getEdges() ) === 6, 'Branch, merge, cycle, and self-loop are retained.' );
$assert( count( $complex->getEdges()[2]->conditions ) === 2, 'Structured conditions are split.' );

$reversible = $parser->parse( 'A <-> B' );
$assert( count( $reversible->getEdges() ) === 2, 'Reversible syntax creates two directed edges.' );
$assert( $reversible->getEdges()[1]->type === 'reversible', 'Reverse edge has a semantic type.' );

$unicode = $parser->parse( '[node id="dragon" name="火竜 🐉" tooltip="é form"]' );
$assert( $unicode->getNodes()['dragon']->name === '火竜 🐉', 'Unicode display text survives validation.' );

$expectError(
	static fn () => $parser->parse( '[node id="a" name="A"]' . "\n" . 'a -> typo' ),
	'Unknown explicit endpoints are rejected.'
);
$expectError(
	static fn () => $parser->parse( '[node id="x" name="A"]' . "\n" . '[node id="x" name="B"]' ),
	'Duplicate IDs are rejected.'
);
$expectError(
	static fn () => $parser->parse( '[node id="x" name="A" onclick="alert(1)"]' ),
	'Event attributes are rejected.'
);
$expectError(
	static fn () => $parser->parse( '[node id="x" name="A" style="url(javascript:x)"]' ),
	'Style attributes are rejected.'
);

foreach ( [
	'https://attacker.example/x.png',
	'../../LocalSettings.php',
	'..\\..\\LocalSettings.php',
	'/etc/passwd',
	'C:\\Windows\\win.ini',
	'%2e%2e%2fsecret',
] as $image ) {
	$expectError(
		static fn () => $parser->parse( '[node id="x" name="A" image="' . $image . '"]' ),
		"Unsafe image is rejected: $image"
	);
}

foreach ( [
	'javascript:alert(1)',
	'data:text/html,x',
	'file:///etc/passwd',
	'vbscript:msgbox(1)',
	'https://127.0.0.1/',
	'http://localhost/',
] as $link ) {
	$expectError(
		static fn () => $parser->parse( '[node id="x" name="A" link="' . $link . '"]' ),
		"Unsafe link is rejected: $link"
	);
}

$limited = new EvolutionParser( new EvolutionLimits( maxInputBytes: 64, maxNodes: 2 ) );
$expectError( static fn () => $limited->parse( str_repeat( 'A', 65 ) ), 'Oversized input is rejected.' );
$expectError( static fn () => $limited->parse( 'A -> B -> C' ), 'Oversized graph is rejected.' );

$payload = '<script>alert(1)</script><img src=x onerror=alert(1)>';
$payloadGraph = $parser->parse( '[node id="x" name="' . $payload . '"]' );
$assert( $payloadGraph->getNodes()['x']->name === $payload, 'HTML payload remains inert model text.' );

$nodeLines = [];
for ( $index = 0; $index < 250; $index++ ) {
	$nodeLines[] = "[node id=\"n$index\" name=\"Node $index\"]";
}
$edgeLines = [];
for ( $index = 0; $index < 249; $index++ ) {
	$edgeLines[] = "n$index -> n" . ( $index + 1 );
}
$edgeLines[] = 'n249 -> n0';
for ( $index = 0; $index < 250; $index++ ) {
	$edgeLines[] = 'n0 -> n' . $index;
}
$maximumGraphSource = implode( "\n", array_merge( $nodeLines, $edgeLines ) );
$startedAt = microtime( true );
$maximumGraph = $parser->parse( $maximumGraphSource );
$assert( count( $maximumGraph->getNodes() ) === 250, 'Maximum-size cyclic graph retains 250 nodes.' );
$assert( count( $maximumGraph->getEdges() ) === 500, 'Maximum-size cyclic graph retains 500 edges.' );
$assert( microtime( true ) - $startedAt < 5.0, 'Maximum-size parse remains bounded.' );
$expectError(
	static fn () => $parser->parse( $maximumGraphSource . "\n" . 'n1 -> n1' ),
	'Maximum plus one edge is rejected.'
);

$tooManyConditions = implode( ';', array_map(
	static fn ( int $index ): string => "Condition $index",
	range( 1, 21 )
) );
$expectError(
	static fn () => $parser->parse( 'A -> B [conditions="' . $tooManyConditions . '"]' ),
	'Maximum plus one condition is rejected.'
);

if ( $failures !== [] ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

fwrite( STDOUT, "MonsterEvolution standalone unit checks passed ($checks assertions)." . PHP_EOL );
