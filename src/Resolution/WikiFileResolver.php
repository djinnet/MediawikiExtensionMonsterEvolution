<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Resolution;

use File;
use RepoGroup;
use TitleFactory;

final class WikiFileResolver {
	public function __construct(
		private readonly TitleFactory $titleFactory,
		private readonly RepoGroup $repoGroup
	) {
	}

	public function resolve( string $name ): ?File {
		$title = $this->titleFactory->newFromText( $name, NS_FILE );
		if ( $title === null || $title->getNamespace() !== NS_FILE || $title->isExternal() ) {
			return null;
		}
		$file = $this->repoGroup->findFile( $title );
		return $file && $file->exists() ? $file : null;
	}
}
