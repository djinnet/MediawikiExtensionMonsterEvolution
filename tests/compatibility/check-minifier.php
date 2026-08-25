<?php

declare( strict_types=1 );

$mediaWikiPath = $argv[1] ?? '';
if ( $mediaWikiPath === '' || !is_file( $mediaWikiPath . '/includes/libs/JavaScriptMinifier.php' ) ) {
	fwrite( STDERR, "Usage: php check-minifier.php /path/to/mediawiki\n" );
	exit( 2 );
}

require_once $mediaWikiPath . '/includes/libs/JavaScriptMinifier.php';
$source = file_get_contents( dirname( __DIR__, 2 ) . '/resources/ext.monsterEvolution/evolution.js' );
$minified = JavaScriptMinifier::minify( $source );
$requiredFragments = [
	"path:'M '+x+' '+y+' C '",
	"viewBox','0 0 '+layout.width+' '+layout.height",
];
foreach ( $requiredFragments as $fragment ) {
	if ( strpos( $minified, $fragment ) === false ) {
		fwrite( STDERR, "MediaWiki's JavaScript minifier damaged SVG geometry.\n" );
		exit( 1 );
	}
}

fwrite( STDOUT, "MediaWiki JavaScript minifier compatibility passed.\n" );
