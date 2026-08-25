'use strict';

const fs = require( 'fs' );
const path = require( 'path' );

const root = path.resolve( __dirname, '../..' );
const js = fs.readFileSync( path.join( root, 'resources/ext.monsterEvolution/evolution.js' ), 'utf8' );
const phpFiles = [];

function collectPhp( directory ) {
	fs.readdirSync( directory, { withFileTypes: true } ).forEach( ( entry ) => {
		const target = path.join( directory, entry.name );
		if ( entry.isDirectory() ) {
			collectPhp( target );
		} else if ( entry.name.endsWith( '.php' ) ) {
			phpFiles.push( target );
		}
	} );
}

collectPhp( path.join( root, 'src' ) );
const php = phpFiles.map( ( file ) => fs.readFileSync( file, 'utf8' ) ).join( '\n' );

const forbiddenJs = [
	/\.innerHTML\s*=/,
	/\beval\s*\(/,
	/\bnew\s+Function\b/,
	/document\.write\s*\(/,
	/setTimeout\s*\(\s*['"]|setInterval\s*\(\s*['"]/,
	/\.setAttribute\(\s*['"]on/i,
	/\bfetch\s*\(|XMLHttpRequest|WebSocket\s*\(/
];
const forbiddenPhp = [
	/\bunserialize\s*\(/i,
	/\bshell_exec\s*\(|\bexec\s*\(|\bsystem\s*\(|\bpassthru\s*\(/i,
	/\bcurl_exec\s*\(|\bfile_get_contents\s*\(\s*\$|\bfopen\s*\(\s*\$/i,
	/<script\b/i,
	/\bon(?:click|error|load)\s*=/i
];

const failures = [];
forbiddenJs.forEach( ( pattern ) => {
	if ( pattern.test( js ) ) {
		failures.push( `Forbidden JavaScript pattern: ${ pattern }` );
	}
} );
forbiddenPhp.forEach( ( pattern ) => {
	if ( pattern.test( php ) ) {
		failures.push( `Forbidden PHP pattern: ${ pattern }` );
	}
} );
if ( !js.includes( '.textContent =' ) || !js.includes( 'new Map(' ) || !js.includes( 'new Set(' ) ) {
	failures.push( 'Expected safe DOM and graph collection primitives are missing.' );
}
if ( js.includes( '`' ) ) {
	failures.push( 'Template literals are unsafe with the MediaWiki 1.35 JavaScript minifier.' );
}
if ( failures.length > 0 ) {
	throw new Error( failures.join( '\n' ) );
}

process.stdout.write( `Security invariants OK (${ phpFiles.length } PHP files checked).\n` );
