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

$expectErrorDetails = static function (
	callable $callback,
	string $message,
	?string $messageKey = null,
	?int $line = null
) use ( $assert ): void {
	try {
		$callback();
		$assert( false, $message );
	} catch ( EvolutionParseException $exception ) {
		$assert( true, $message );
		if ( $messageKey !== null ) {
			$assert( $exception->messageKey === $messageKey, "$message uses the expected message key." );
		}
		if ( $line !== null ) {
			$assert( $exception->sourceLine === $line, "$message reports the expected source line." );
		}
	}
};

$parser = new EvolutionParser( new EvolutionLimits() );

// Keep shipped documentation honest: every <evolution> block in every demo must
// remain parseable as syntax evolves. This catches stale examples before release.
$demoFiles = glob( dirname( __DIR__ ) . '/demo/*.wiki' ) ?: [];
$assert( count( $demoFiles ) >= 7, 'The documented demo catalog contains at least seven examples.' );
foreach ( $demoFiles as $demoFile ) {
	$demoSource = file_get_contents( $demoFile );
	$demoMatches = [];
	preg_match_all( '/<evolution\b([^>]*)>(.*?)<\/evolution>/si', $demoSource, $demoMatches, PREG_SET_ORDER );
	$assert( $demoMatches !== [], basename( $demoFile ) . ' contains an evolution block.' );
	foreach ( $demoMatches as $demoMatch ) {
		$demoAttributes = [];
		$attributeMatches = [];
		preg_match_all(
			'/([A-Za-z][A-Za-z0-9]*)\s*=\s*"([^"]*)"/',
			$demoMatch[1],
			$attributeMatches,
			PREG_SET_ORDER
		);
		foreach ( $attributeMatches as $attributeMatch ) {
			$demoAttributes[$attributeMatch[1]] = $attributeMatch[2];
		}
		$demoGraph = $parser->parse( $demoMatch[2], $demoAttributes );
		$assert( $demoGraph->getNodes() !== [], basename( $demoFile ) . ' parses into a nonempty graph.' );
	}
}

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

$duplicates = $parser->parse( "A -> B\nA -> C\nB -> A" );
$assert( count( $duplicates->getNodes() ) === 3, 'Repeated shorthand names reuse one node.' );
$assert( $duplicates->getEdges()[2]->target === 'auto-1', 'Repeated shorthand endpoints keep stable IDs.' );

$caseSensitive = $parser->parse( 'Slime -> slime' );
$assert( count( $caseSensitive->getNodes() ) === 2, 'Shorthand names remain Unicode case-sensitive.' );

$chainMetadata = $parser->parse( 'A -> B <-> C [type="quest" label="Clear dungeon" conditions="Key; Boss"]' );
$assert( count( $chainMetadata->getEdges() ) === 3, 'Mixed-operator chains create every directed edge.' );
foreach ( $chainMetadata->getEdges() as $edge ) {
	$assert( $edge->type === 'quest', 'Chain metadata is applied consistently to each generated edge.' );
	$assert( $edge->label === 'Clear dungeon', 'Chain labels are applied consistently.' );
	$assert( count( $edge->conditions ) === 2, 'Chain conditions are applied consistently.' );
}

$iconEdges = $parser->parse( <<<'WIKI'
A -> B [label="Fire Stone" icon="Fire Stone.png" link="Item:Fire Stone"]
B -> C [label="Moon Stone" icon="Moon Stone.svg" iconPosition="above"]
C -> D [icon="Trade symbol.webp" iconPosition="next-to"]
WIKI );
$assert( $iconEdges->getEdges()[0]->icon === 'Fire Stone.png', 'An edge accepts a local icon file.' );
$assert( $iconEdges->getEdges()[0]->link === 'Item:Fire Stone', 'An edge label accepts an internal link.' );
$assert( $iconEdges->getEdges()[0]->iconPosition === 'next-to', 'Edge icons default beside the label.' );
$assert( $iconEdges->getEdges()[1]->iconPosition === 'above', 'An edge icon may appear above its label.' );
$assert( $iconEdges->getEdges()[2]->label === null, 'An icon-only edge does not invent label text.' );

foreach ( [
	'https://attacker.example/stone.png',
	'../LocalSettings.php',
	'File:Fire Stone.png',
	'C:\\Windows\\win.ini',
	'%2e%2e%2fsecret',
] as $icon ) {
	$expectError(
		static fn () => $parser->parse( 'A -> B [icon="' . $icon . '"]' ),
		"Unsafe edge icon is rejected: $icon"
	);
}
foreach ( [ 'below', 'left', 'next', 'above label' ] as $position ) {
	$expectError(
		static fn () => $parser->parse( 'A -> B [iconPosition="' . $position . '"]' ),
		"Invalid edge icon position is rejected: $position"
	);
}
foreach ( [
	'javascript:alert(1)',
	'data:text/html,x',
	'file:///etc/passwd',
	'https://attacker.example/Fire_Stone',
	'http://localhost/Fire_Stone',
] as $link ) {
	$expectError(
		static fn () => $parser->parse( 'A -> B [link="' . $link . '"]' ),
		"Unsafe edge-label link is rejected: $link"
	);
}

$nodeMetadata = $parser->parse(
	'[node id="meta" name="Meta" image="Creature portrait.png" link="Help:Creature" ' .
	'subtitle="Rare" form="Night" tooltip="Line one\\nLine two" class="Boss FIRE_2" ' .
	'imageWidth="16" imageHeight="512"]'
);
$metadataNode = $nodeMetadata->getNodes()['meta'];
$assert( $metadataNode->image === 'Creature portrait.png', 'Safe local file names are retained.' );
$assert( $metadataNode->link === 'Help:Creature', 'Internal namespace links are retained.' );
$assert( $metadataNode->classes === [ 'boss', 'fire_2' ], 'Custom classes are normalized to lowercase.' );
$assert( $metadataNode->imageWidth === 16, 'Minimum per-node image width is accepted.' );
$assert( $metadataNode->imageHeight === 512, 'Maximum per-node image height is accepted.' );
$assert( $metadataNode->tooltip === "Line one\nLine two", 'Escaped tooltip newlines are decoded.' );

foreach ( [
	'left-to-right', 'right-to-left', 'top-to-bottom', 'bottom-to-top',
] as $direction ) {
	$graph = $parser->parse( 'A -> B', [ 'direction' => strtoupper( $direction ) ] );
	$assert( $graph->direction === $direction, "Direction $direction is accepted case-insensitively." );
}
$assert(
	$parser->parse( 'A -> B', [ 'direction' => 'horizontal' ] )->direction === 'left-to-right',
	'Horizontal direction alias is normalized.'
);
$assert(
	$parser->parse( 'A -> B', [ 'direction' => 'vertical' ] )->direction === 'top-to-bottom',
	'Vertical direction alias is normalized.'
);
foreach ( [ 'default', 'compact', 'cards', 'minimal' ] as $theme ) {
	$graph = $parser->parse( 'A -> B', [ 'theme' => strtoupper( $theme ) ] );
	$assert( $graph->theme === $theme, "Theme $theme is accepted case-insensitively." );
}

$radialSource = <<<'WIKI'
[node id="eevee" name="Eevee"]
[node id="vaporeon" name="Vaporeon"]
[node id="jolteon" name="Jolteon"]
eevee -> vaporeon
eevee -> jolteon
WIKI;
$radial = $parser->parse( $radialSource, [
	'layout' => 'RADIAL',
	'center' => 'eevee',
	'radialShape' => 'POLYGON',
	'radialStart' => 'RIGHT',
] );
$assert( $radial->layout === 'radial', 'Radial layout is accepted case-insensitively.' );
$assert( $radial->center === 'eevee', 'Radial layout retains its explicit center ID.' );
$assert( $radial->radialShape === 'polygon', 'Radial polygon shape is normalized.' );
$assert( $radial->radialStart === 'right', 'Radial starting position is normalized.' );
foreach ( [ 'top', 'right', 'bottom', 'left' ] as $start ) {
	$graph = $parser->parse( $radialSource, [
		'layout' => 'radial',
		'center' => 'eevee',
		'radialStart' => $start,
	] );
	$assert( $graph->radialStart === $start, "Radial starting position $start is accepted." );
}
$expectError(
	static fn () => $parser->parse( $radialSource, [ 'layout' => 'radial' ] ),
	'Radial layout without a center is rejected.'
);
$expectError(
	static fn () => $parser->parse( $radialSource, [ 'layout' => 'radial', 'center' => 'missing' ] ),
	'Radial layout with an unknown center is rejected.'
);
$expectError(
	static fn () => $parser->parse( $radialSource, [ 'center' => 'eevee' ] ),
	'Radial-only options are rejected for layered layout.'
);
foreach ( [
	[ 'layout' => 'orbit' ],
	[ 'layout' => 'radial', 'center' => 'eevee', 'radialShape' => 'hexagon' ],
	[ 'layout' => 'radial', 'center' => 'eevee', 'radialStart' => 'diagonal' ],
] as $invalidRadialOptions ) {
	$expectError(
		static fn () => $parser->parse( $radialSource, $invalidRadialOptions ),
		'Invalid radial graph options are rejected.'
	);
}

foreach ( [ '1', 'true', 'TRUE', 'yes', ' YES ' ] as $truthy ) {
	$assert( $parser->parse( 'A -> B', [ 'zoom' => $truthy ] )->zoom, "Boolean $truthy is true." );
}
foreach ( [ '0', 'false', 'FALSE', 'no', ' NO ' ] as $falsey ) {
	$assert( !$parser->parse( 'A -> B', [ 'zoom' => $falsey ] )->zoom, "Boolean $falsey is false." );
}
$controlsWithoutZoom = $parser->parse( 'A -> B', [ 'controls' => 'true', 'zoom' => 'false' ] );
$assert( !$controlsWithoutZoom->controls, 'Controls remain disabled when graph zoom is disabled.' );

$tagDimensions = $parser->parse( 'A -> B', [ 'imageWidth' => '16', 'IMAGEHEIGHT' => '512' ] );
$assert( $tagDimensions->defaultImageWidth === 16, 'Minimum graph image width is accepted.' );
$assert( $tagDimensions->defaultImageHeight === 512, 'Maximum graph image height is accepted.' );

$multiline = $parser->parse( <<<'WIKI'
[node
 id="a"
 name="Quoted \"Alpha\""
 subtitle='single quoted value'
]
[node id="b" name="B"]

a -> b [
 label="Line one\nLine two"
 conditions="First;
Second;;"
]
WIKI );
$assert( $multiline->getNodes()['a']->name === 'Quoted "Alpha"', 'Escaped quotes survive multiline declarations.' );
$assert( $multiline->getNodes()['a']->subtitle === 'single quoted value', 'Single-quoted attributes are accepted.' );
$assert( $multiline->getEdges()[0]->label === "Line one\nLine two", 'Escaped edge-label newlines are decoded.' );
$assert( count( $multiline->getEdges()[0]->conditions ) === 2, 'Blank structured conditions are ignored.' );

$crlf = $parser->parse( "A -> B\r\nB -> C\r\n" );
$assert( count( $crlf->getEdges() ) === 2, 'CRLF source is normalized correctly.' );

$isolated = $parser->parse( '[node id="alone" name="Alone"]' );
$assert( count( $isolated->getNodes() ) === 1, 'An explicitly declared isolated node is valid.' );
$assert( count( $isolated->getEdges() ) === 0, 'An isolated node does not invent relationships.' );

$expectError(
	static fn () => $parser->parse( '[node id="a" name="A"]' . "\n" . 'a -> typo' ),
	'Unknown explicit endpoints are rejected.'
);

foreach ( [
	'' => '',
	'space' => 'bad id',
	'dot' => 'bad.id',
	'unicode' => 'drágon',
	'slash' => 'bad/id',
	'too long' => str_repeat( 'a', 129 ),
] as $label => $id ) {
	$expectError(
		static fn () => $parser->parse( '[node id="' . $id . '" name="A"]' ),
		"Invalid node ID is rejected: $label"
	);
}
$boundaryId = str_repeat( 'a', 128 );
$assert(
	isset( $parser->parse( '[node id="' . $boundaryId . '" name="A"]' )->getNodes()[$boundaryId] ),
	'Maximum-length node ID is accepted.'
);

foreach ( [ '1bad', '-bad', 'bad token', 'bad.dot', str_repeat( 'a', 33 ) ] as $type ) {
	$expectError(
		static fn () => $parser->parse( 'A -> B [type="' . $type . '"]' ),
		"Invalid semantic edge type is rejected: $type"
	);
}
$assert(
	$parser->parse( 'A -> B [type="a_b-2"]' )->getEdges()[0]->type === 'a_b-2',
	'Valid semantic type punctuation is accepted.'
);

$nineClasses = implode( ' ', array_map( static fn ( int $index ): string => "c$index", range( 1, 9 ) ) );
$expectError(
	static fn () => $parser->parse( '[node id="x" name="A" class="' . $nineClasses . '"]' ),
	'More than eight custom classes are rejected.'
);

foreach ( [ '15', '513', '-1', '16.5', '512px', '1000' ] as $dimension ) {
	$expectError(
		static fn () => $parser->parse( 'A -> B', [ 'imageWidth' => $dimension ] ),
		"Invalid image dimension is rejected: $dimension"
	);
}
$assert(
	$parser->parse( 'A -> B', [ 'imageWidth' => '016' ] )->defaultImageWidth === 16,
	'Zero-padded dimensions are normalized to integers.'
);
foreach ( [ 'on', 'off', '2', '', 'null' ] as $boolean ) {
	$expectError(
		static fn () => $parser->parse( 'A -> B', [ 'zoom' => $boolean ] ),
		"Invalid Boolean is rejected: $boolean"
	);
}

foreach ( [
	[ [ 'direction' => 'diagonal' ], 'invalid direction', 'monsterevolution-error-option' ],
	[ [ 'theme' => 'neon' ], 'invalid theme', 'monsterevolution-error-option' ],
	[ [ 'style' => 'display:none' ], 'unknown graph attribute', 'monsterevolution-error-unknown-attribute' ],
] as [ $options, $label, $messageKey ] ) {
	$expectErrorDetails(
		static fn () => $parser->parse( 'A -> B', $options ),
		"Graph option error is controlled: $label",
		$messageKey
	);
}
$expectError(
	static fn () => $parser->parse( 'A -> B', [ 'theme' => 'default', 'THEME' => 'cards' ] ),
	'Case-insensitive duplicate graph attributes are rejected.'
);

foreach ( [
	'empty graph' => '',
	'only whitespace' => " \n\t ",
	'missing target' => 'A ->',
	'missing source' => '-> B',
	'wrong arrow' => 'A => B',
	'unexpected closing bracket' => 'A -> B]',
	'nested bracket' => 'A -> B [label="x" [type="level"]]',
	'unterminated bracket' => 'A -> B [label="x"',
	'unterminated quote' => 'A -> B [label="x]',
	'unquoted attribute' => 'A -> B [label=bad]',
	'trailing text' => 'A -> B [label="x"] trailing',
] as $label => $source ) {
	$expectError( static fn () => $parser->parse( $source ), "Malformed syntax is rejected: $label" );
}

$expectErrorDetails(
	static fn () => $parser->parse( "A -> B\n[node id=\"bad id\" name=\"Bad\"]" ),
	'Errors retain the declaration source line.',
	'monsterevolution-error-invalid-id',
	2
);

$expectError(
	static fn () => $parser->parse( "[node id=\"x\" name=\"A\"]\nx -> x [unknown=\"value\"]" ),
	'Unknown edge attributes are rejected.'
);

$invalidUtf8 = "[node id=\"x\" name=\"\xC3\x28\"]";
$expectError( static fn () => $parser->parse( $invalidUtf8 ), 'Invalid UTF-8 input is rejected.' );

$zeroConditionParser = new EvolutionParser( new EvolutionLimits( maxConditionsPerEdge: 0 ) );
$assert(
	count( $zeroConditionParser->parse( 'A -> B [conditions=";;"]' )->getEdges()[0]->conditions ) === 0,
	'A zero condition limit allows an empty condition list.'
);
$expectError(
	static fn () => $zeroConditionParser->parse( 'A -> B [conditions="Required"]' ),
	'A zero condition limit rejects the first real condition.'
);

$attributeLimitedParser = new EvolutionParser( new EvolutionLimits( maxAttributes: 2 ) );
$assert(
	count( $attributeLimitedParser->parse( '[node id="x" name="A"]' )->getNodes() ) === 1,
	'The exact attribute-count limit is accepted.'
);
$expectError(
	static fn () => $attributeLimitedParser->parse( '[node id="x" name="A" subtitle="Too many"]' ),
	'Maximum plus one attribute is rejected.'
);

$valueLimitedParser = new EvolutionParser( new EvolutionLimits( maxValueLength: 4 ) );
$assert(
	$valueLimitedParser->parse( '[node id="x" name="🐉🐉🐉🐉"]' )->getNodes()['x']->name === '🐉🐉🐉🐉',
	'Value limits count Unicode characters rather than bytes.'
);
$expectError(
	static fn () => $valueLimitedParser->parse( '[node id="x" name="🐉🐉🐉🐉🐉"]' ),
	'Maximum plus one Unicode character is rejected.'
);

foreach ( [
	static fn () => new EvolutionLimits( maxInputBytes: 0 ),
	static fn () => new EvolutionLimits( maxNodes: 0 ),
	static fn () => new EvolutionLimits( maxEdges: 0 ),
	static fn () => new EvolutionLimits( maxConditionsPerEdge: -1 ),
] as $index => $callback ) {
	try {
		$callback();
		$assert( false, "Invalid limit set $index is rejected." );
	} catch ( InvalidArgumentException ) {
		$assert( true, "Invalid limit set $index is rejected." );
	}
}
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
