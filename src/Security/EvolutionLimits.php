<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Security;

use InvalidArgumentException;

final class EvolutionLimits {
	public function __construct(
		public readonly int $maxInputBytes = 131072,
		public readonly int $maxNodes = 250,
		public readonly int $maxEdges = 500,
		public readonly int $maxConditionsPerEdge = 20,
		public readonly int $maxAttributes = 32,
		public readonly int $maxValueLength = 4096,
		public readonly int $maxNodeIdLength = 128,
		public readonly int $maxGraphsPerPage = 20
	) {
		$values = [
			$maxInputBytes,
			$maxNodes,
			$maxEdges,
			$maxAttributes,
			$maxValueLength,
			$maxNodeIdLength,
			$maxGraphsPerPage,
		];
		foreach ( $values as $value ) {
			if ( $value < 1 ) {
				throw new InvalidArgumentException( 'Evolution limits must be positive.' );
			}
		}
		if ( $maxConditionsPerEdge < 0 ) {
			throw new InvalidArgumentException( 'The condition limit cannot be negative.' );
		}
	}
}
