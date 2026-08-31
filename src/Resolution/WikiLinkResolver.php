<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Resolution;

use MediaWiki\Linker\LinkTarget;
use TitleFactory;

/** MediaWiki title-backed adapter for EvolutionLinkResolver. */
final class WikiLinkResolver implements EvolutionLinkResolver {
	public function __construct( private readonly TitleFactory $titleFactory ) {
	}

	public function resolve( string $text ): ?LinkTarget {
		// LinkRenderer, not the extension, remains responsible for the final URL.
		$title = $this->titleFactory->newFromText( $text );
		if ( $title === null || $title->isExternal() ) {
			return null;
		}
		return $title;
	}

	public function isKnown( string $text ): bool {
		// Title moved from the global namespace in newer MediaWiki releases. Let
		// TitleFactory's branch-specific return type provide the concrete class so
		// this adapter remains statically analyzable on every supported branch.
		$title = $this->titleFactory->newFromText( $text );
		return $title !== null && !$title->isExternal() && $title->isKnown();
	}
}
