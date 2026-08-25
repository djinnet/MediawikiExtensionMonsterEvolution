<?php

$config = require dirname( __DIR__ ) . '/vendor/mediawiki/mediawiki-phan-config/src/config.php';

$config['directory_list'] = [
	'src',
	'tests/phpunit',
];
$config['exclude_analysis_directory_list'] = [
	'vendor',
];
$config['quick_mode'] = false;

return $config;
