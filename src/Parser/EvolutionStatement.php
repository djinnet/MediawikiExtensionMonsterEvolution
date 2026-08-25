<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Parser;

final class EvolutionStatement {
	public function __construct(
		public readonly string $text,
		public readonly int $line
	) {
	}
}
