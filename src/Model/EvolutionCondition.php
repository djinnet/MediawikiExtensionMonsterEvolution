<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Model;

/** One plain-text requirement attached to a directed evolution edge. */
final class EvolutionCondition {
	public function __construct( public readonly string $text ) {
	}
}
