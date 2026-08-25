<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Model;

final class EvolutionEdge {
	/** @param EvolutionCondition[] $conditions */
	public function __construct(
		public readonly string $source,
		public readonly string $target,
		public readonly string $type = 'custom',
		public readonly ?string $label = null,
		public readonly array $conditions = [],
		public readonly int $line = 1
	) {
	}
}
