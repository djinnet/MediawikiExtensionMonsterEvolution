<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Resolution;

use File;
use RepoGroup;
use TitleFactory;

/** MediaWiki repository-backed adapter for EvolutionFileResolver. */
final class WikiFileResolver implements EvolutionFileResolver {
	public function __construct(
		private readonly TitleFactory $titleFactory,
		private readonly RepoGroup $repoGroup
	) {
	}

	public function resolve( string $name ): ?File {
		// NS_FILE forces file-title semantics; isExternal blocks interwiki titles.
		$title = $this->titleFactory->newFromText( $name, NS_FILE );
		if ( $title === null || $title->getNamespace() !== NS_FILE || $title->isExternal() ) {
			return null;
		}
		$file = $this->repoGroup->findFile( $title );
		return $file && $file->exists() ? $file : null;
	}
}
