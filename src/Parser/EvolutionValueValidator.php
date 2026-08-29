<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Parser;

use MediaWiki\Extension\MonsterEvolution\Security\EvolutionLimits;
use MediaWiki\Extension\MonsterEvolution\Security\LocalFileNamePolicy;

/**
 * Validates scalar values after EvolutionTokenizer has identified their syntax.
 *
 * Grammar and graph construction belong to EvolutionParser; URL, file-name,
 * dimension, token, and text policies belong here. This separation keeps security
 * decisions consistent and makes additions such as edge icons reuse the same
 * validation path as node images.
 */
final class EvolutionValueValidator {
	public function __construct(
		private readonly EvolutionLimits $limits,
		private readonly LocalFileNamePolicy $fileNamePolicy
	) {
	}

	/**
	 * Attribute names are case-insensitive in wikitext. Normalize once so every
	 * downstream allow-list and duplicate check sees the same representation.
	 *
	 * @param array<string,string> $attributes
	 * @return array<string,string>
	 */
	public function normalizeKeys( array $attributes ): array {
		$normalized = [];
		foreach ( $attributes as $key => $value ) {
			$key = strtolower( (string)$key );
			if ( isset( $normalized[$key] ) ) {
				throw $this->error( "Duplicate attribute \"$key\".", 0 );
			}
			$normalized[$key] = (string)$value;
		}
		return $normalized;
	}

	/**
	 * @param array<string,string> $attributes
	 * @param string[] $allowed
	 */
	public function assertAllowedAttributes( array $attributes, array $allowed, int $line ): void {
		foreach ( array_keys( $attributes ) as $attribute ) {
			if ( !in_array( $attribute, $allowed, true ) ) {
				throw $this->error(
					"Unknown attribute \"$attribute\".",
					$line,
					'monsterevolution-error-unknown-attribute',
					[ $attribute ]
				);
			}
		}
	}

	public function validateId( string $id, int $line ): void {
		if ( $id === '' || strlen( $id ) > $this->limits->maxNodeIdLength ||
			!preg_match( '/\A[A-Za-z0-9_-]+\z/D', $id )
		) {
			throw $this->error(
				"Invalid node ID \"$id\".",
				$line,
				'monsterevolution-error-invalid-id',
				[ $id ]
			);
		}
	}

	public function validateText(
		string $value,
		string $field,
		int $line,
		bool $allowNewlines = false,
		bool $required = false
	): void {
		if ( $required && trim( $value ) === '' ) {
			throw $this->error( "The $field value is required.", $line );
		}
		if ( mb_strlen( $value ) > $this->limits->maxValueLength ) {
			throw $this->error( "The $field value is too long.", $line, 'monsterevolution-error-limit' );
		}
		$controlPattern = $allowNewlines ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/';
		if ( preg_match( $controlPattern, $value ) ) {
			throw $this->error( "The $field value contains control characters.", $line );
		}
	}

	public function validateSemanticToken( string $value, string $field, int $line ): void {
		if ( !preg_match( '/\A[a-z][a-z0-9_-]{0,31}\z/D', $value ) ) {
			throw $this->error( "Invalid $field value \"$value\".", $line );
		}
	}

	public function validateLinkTitle( string $value, int $line ): void {
		$this->validateText( $value, 'link', $line );
		if (
			preg_match( '/\A\s*(?:https?|javascript|data|vbscript|file):/i', $value ) ||
			str_contains( $value, '://' )
		) {
			throw $this->error(
				'External or executable links are not allowed.',
				$line,
				'monsterevolution-error-invalid-link'
			);
		}
	}

	public function validateLocalFileName( string $value, string $field, int $line ): void {
		$this->validateText( $value, $field, $line );
		if ( !$this->fileNamePolicy->isAllowed( $value ) ) {
			throw $this->error(
				'The image must be a local MediaWiki file name.',
				$line,
				'monsterevolution-error-invalid-image'
			);
		}
	}

	public function parseDimension( ?string $value, int $default, int $line ): int {
		if ( $value === null || $value === '' ) {
			$value = (string)$default;
		}
		if ( !preg_match( '/\A[0-9]{1,3}\z/D', $value ) ) {
			throw $this->error( 'Image dimensions must be whole pixel values.', $line );
		}
		$dimension = (int)$value;
		if ( $dimension < 16 || $dimension > 512 ) {
			throw $this->error( 'Image dimensions must be between 16 and 512 pixels.', $line );
		}
		return $dimension;
	}

	public function parseBoolean( string $value, int $line ): bool {
		return match ( strtolower( trim( $value ) ) ) {
			'1', 'true', 'yes' => true,
			'0', 'false', 'no' => false,
			default => throw $this->error( "Invalid Boolean value \"$value\".", $line ),
		};
	}

	public function nullableTrim( ?string $value ): ?string {
		if ( $value === null ) {
			return null;
		}
		$value = trim( $value );
		return $value === '' ? null : $value;
	}

	private function error(
		string $message,
		int $line,
		string $messageKey = 'monsterevolution-error-syntax',
		array $params = []
	): EvolutionParseException {
		return new EvolutionParseException( $message, $line, $messageKey, $params ?: [ $message ] );
	}
}
