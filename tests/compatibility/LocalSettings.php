<?php

// Minimal configuration for loading MonsterEvolution against a local MediaWiki checkout.
$wgSitename = 'MonsterEvolution compatibility test';
$wgMetaNamespace = 'MonsterEvolution_compatibility_test';
$wgScriptPath = '';
$wgServer = 'http://localhost';
$wgLanguageCode = 'en';
$wgDBtype = 'sqlite';
$wgDBname = 'monster_evolution_compatibility';
$wgSQLiteDataDir = sys_get_temp_dir();
$wgCacheDirectory = sys_get_temp_dir();
$wgLocalisationCacheConf['store'] = 'files';
$wgMainCacheType = CACHE_NONE;
$wgMessageCacheType = CACHE_NONE;
$wgUseDatabaseMessages = false;
$wgSecretKey = str_repeat( 'a', 64 );
$wgUpgradeKey = 'monster-evolution-compatibility-test';

wfLoadExtension( 'MonsterEvolution', dirname( __DIR__, 2 ) . '/extension.json' );
