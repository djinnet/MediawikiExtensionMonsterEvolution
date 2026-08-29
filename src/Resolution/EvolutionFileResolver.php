<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Resolution;

use File;

/**
 * Resolves an already-validated local file title to a MediaWiki file.
 *
 * Keeping this contract smaller than RepoGroup makes the renderer depend only on
 * the operation it needs. The production adapter can use MediaWiki's repository
 * stack while tests or future storage adapters can provide the same behavior.
 */
interface EvolutionFileResolver {
	public function resolve( string $name ): ?File;
}
