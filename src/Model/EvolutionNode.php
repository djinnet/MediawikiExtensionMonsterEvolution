<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Model;

final class EvolutionNode {
	/** @param string[] $classes */
	public function __construct(
		public readonly string $id,
		public readonly string $name,
		public readonly ?string $image = null,
		public readonly ?string $link = null,
		public readonly ?string $subtitle = null,
		public readonly ?string $form = null,
		public readonly ?string $tooltip = null,
		public readonly array $classes = [],
		public readonly ?int $imageWidth = null,
		public readonly ?int $imageHeight = null
	) {
	}
}
