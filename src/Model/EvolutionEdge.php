<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Model;

/**
 * Immutable directed connection between two nodes.
 *
 * The model contains validated wiki-level values, never resolved URLs or HTML.
 * Rendering concerns therefore stay out of parsing and can be changed without
 * changing graph semantics.
 */
final class EvolutionEdge {
	/**
	 * @param string $source
	 * @param string $target
	 * @param string $type
	 * @param string|null $label
	 * @param EvolutionCondition[] $conditions
	 * @param int $line
	 * @param string|null $icon
	 * @param string $iconPosition
	 * @param string|null $link
	 */
	public function __construct(
		public readonly string $source,
		public readonly string $target,
		public readonly string $type = 'custom',
		public readonly ?string $label = null,
		public readonly array $conditions = [],
		public readonly int $line = 1,
		public readonly ?string $icon = null,
		public readonly string $iconPosition = 'next-to',
		public readonly ?string $link = null
	) {
	}
}
