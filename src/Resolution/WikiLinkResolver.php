<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Resolution;

use MediaWiki\Linker\LinkTarget;
use Title;
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

	public function isKnown( LinkTarget $target ): bool {
		// resolve() only returns Title instances. Retaining the guard makes this
		// adapter fail closed if a different LinkTarget implementation is supplied.
		return $target instanceof Title && $target->isKnown();
	}
}
