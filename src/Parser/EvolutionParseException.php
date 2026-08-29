<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Parser;

use RuntimeException;

/**
 * Controlled user-facing parse failure.
 *
 * `message` helps developers and tests, while `messageKey`/`messageParams` are the
 * localized public contract rendered by ParserHooks. Raw wikitext is never treated
 * as HTML when an error reaches the page.
 */
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
