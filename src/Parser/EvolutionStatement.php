<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Parser;

/** Tokenizer output retaining the original line for editor diagnostics. */
final class EvolutionStatement {
	public function __construct(
		public readonly string $text,
		public readonly int $line
	) {
	}
}
