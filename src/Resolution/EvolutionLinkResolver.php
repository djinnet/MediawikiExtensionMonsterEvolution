<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Resolution;

use MediaWiki\Linker\LinkTarget;

/**
 * Converts validated wiki-title text into the link abstraction used by MediaWiki.
 */
interface EvolutionLinkResolver {
	public function resolve( string $text ): ?LinkTarget;
}
