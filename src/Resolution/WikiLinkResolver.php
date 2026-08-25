<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Resolution;

use Title;
use TitleFactory;

final class WikiLinkResolver {
	public function __construct( private readonly TitleFactory $titleFactory ) {
	}

	public function resolve( string $text ): ?Title {
		$title = $this->titleFactory->newFromText( $text );
		if ( $title === null || $title->isExternal() ) {
			return null;
		}
		return $title;
	}
}
