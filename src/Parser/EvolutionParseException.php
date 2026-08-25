<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Parser;

use RuntimeException;

final class EvolutionParseException extends RuntimeException {
	public function __construct(
		string $message,
		public readonly int $sourceLine = 0,
		public readonly string $messageKey = 'monsterevolution-error-generic',
		public readonly array $messageParams = []
	) {
		parent::__construct( $message );
	}
}
