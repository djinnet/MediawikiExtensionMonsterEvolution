<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Resolution;

use MediaWiki\Linker\LinkTarget;

/**
 * Converts validated wiki-title text into the link abstraction used by MediaWiki.
 */
interface EvolutionLinkResolver {
	public function resolve( string $text ): ?LinkTarget;

	/**
	 * Whether title text resolves to a page or another known MediaWiki target.
	 *
	 * Keeping this query behind the resolver lets the renderer detect red links
	 * without depending on MediaWiki's concrete Title class. Callers should invoke
	 * it only when missing-page tracking is enabled because it can require a lookup.
	 */
	public function isKnown( string $text ): bool;
}
