<?php

$config = require dirname( __DIR__ ) . '/vendor/mediawiki/mediawiki-phan-config/src/config.php';

$mediaWikiInstallPath = getenv( 'MW_INSTALL_PATH' );
$mediaWikiInstallPath = $mediaWikiInstallPath === false ? '../..' : $mediaWikiInstallPath;
$mediaWikiPhpUnitPath = str_replace( '\\', '/', $mediaWikiInstallPath ) . '/tests/phpunit';

$config['directory_list'] = array_merge(
	$config['directory_list'],
	[
		'tests/phpunit',
	]
);
$config['file_list'] = array_merge(
	$config['file_list'] ?? [],
	[
		$mediaWikiPhpUnitPath . '/MediaWikiIntegrationTestCase.php',
	]
);
$config['exclude_analysis_directory_list'] = array_merge(
	$config['exclude_analysis_directory_list'],
	[
		'vendor',
		$mediaWikiPhpUnitPath,
	]
);
$config['quick_mode'] = false;

return $config;
