<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Model;

final class EvolutionNode {
	/**
	 * @param string $id
	 * @param string $name
	 * @param string|null $image
	 * @param string|null $link
	 * @param string|null $subtitle
	 * @param string|null $form
	 * @param string|null $tooltip
	 * @param string[] $classes
	 * @param int|null $imageWidth
	 * @param int|null $imageHeight
	 */
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
