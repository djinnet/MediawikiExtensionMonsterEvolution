<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Model;

final class EvolutionCondition {
	public function __construct( public readonly string $text ) {
	}
}
