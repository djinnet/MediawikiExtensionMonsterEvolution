<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Parser;

use MediaWiki\Extension\MonsterEvolution\Security\EvolutionLimits;

final class EvolutionTokenizer {
	public function __construct( private readonly EvolutionLimits $limits ) {
	}

	/** @return EvolutionStatement[] */
	public function tokenize( string $source ): array {
		$statements = [];
		$buffer = '';
		$line = 1;
		$statementLine = 1;
		$depth = 0;
		$quote = null;
		$escaped = false;
		$length = strlen( $source );

		for ( $offset = 0; $offset < $length; $offset++ ) {
			$char = $source[$offset];
			if ( $quote !== null ) {
				$buffer .= $char;
				if ( $escaped ) {
					$escaped = false;
				} elseif ( $char === '\\' ) {
					$escaped = true;
				} elseif ( $char === $quote ) {
					$quote = null;
				}
			} elseif ( $char === '"' || $char === "'" ) {
				$quote = $char;
				$buffer .= $char;
			} elseif ( $char === '[' ) {
				$depth++;
				if ( $depth > 1 ) {
					throw $this->error( 'Nested brackets are not supported.', $line );
				}
				$buffer .= $char;
			} elseif ( $char === ']' ) {
				$depth--;
				if ( $depth < 0 ) {
					throw $this->error( 'Unexpected closing bracket.', $line );
				}
				$buffer .= $char;
			} elseif ( $char === "\n" && $depth === 0 ) {
				$this->appendStatement( $statements, $buffer, $statementLine );
				$buffer = '';
				$statementLine = $line + 1;
			} else {
				$buffer .= $char;
			}

			if ( $char === "\n" ) {
				$line++;
			}
		}

		if ( $quote !== null ) {
			throw $this->error( 'Unterminated quoted value.', $line );
		}
		if ( $depth !== 0 ) {
			throw $this->error( 'Unterminated attribute block.', $line );
		}
		$this->appendStatement( $statements, $buffer, $statementLine );
		return $statements;
	}

	/** @return array<string,string> */
	public function parseAttributes( string $source, int $line ): array {
		$attributes = [];
		$offset = 0;
		$length = strlen( $source );
		while ( $offset < $length ) {
			while ( $offset < $length && ctype_space( $source[$offset] ) ) {
				$offset++;
			}
			if ( $offset >= $length ) {
				break;
			}
			if ( !preg_match( '/\G([A-Za-z][A-Za-z0-9]*)\s*=\s*/', $source, $match, 0, $offset ) ) {
				throw $this->error( 'Expected an attribute in key="value" form.', $line );
			}
			$key = strtolower( $match[1] );
			$offset += strlen( $match[0] );
			if ( isset( $attributes[$key] ) ) {
				throw $this->error( "Duplicate attribute \"$key\".", $line );
			}
			if ( count( $attributes ) >= $this->limits->maxAttributes ) {
				throw $this->error( 'Too many attributes.', $line, 'monsterevolution-error-limit' );
			}
			if ( $offset >= $length || ( $source[$offset] !== '"' && $source[$offset] !== "'" ) ) {
				throw $this->error( "Attribute \"$key\" must use quotes.", $line );
			}
			$quote = $source[$offset++];
			$value = '';
			$closed = false;
			while ( $offset < $length ) {
				$char = $source[$offset++];
				if ( $char === $quote ) {
					$closed = true;
					break;
				}
				if ( $char === '\\' && $offset < $length ) {
					$next = $source[$offset++];
					$value .= match ( $next ) {
						'n' => "\n",
						't' => "\t",
						'\\' => '\\',
						'"' => '"',
						"'" => "'",
						default => $next,
					};
				} else {
					$value .= $char;
				}
				if ( mb_strlen( $value ) > $this->limits->maxValueLength ) {
					throw $this->error( "Attribute \"$key\" is too long.", $line, 'monsterevolution-error-limit' );
				}
			}
			if ( !$closed ) {
				throw $this->error( "Unterminated value for \"$key\".", $line );
			}
			$attributes[$key] = $value;
		}
		return $attributes;
	}

	/** @param EvolutionStatement[] &$statements */
	private function appendStatement( array &$statements, string $buffer, int $line ): void {
		$text = trim( $buffer );
		if ( $text !== '' ) {
			$statements[] = new EvolutionStatement( $text, $line );
		}
	}

	private function error(
		string $message,
		int $line,
		string $messageKey = 'monsterevolution-error-syntax'
	): EvolutionParseException {
		return new EvolutionParseException( $message, $line, $messageKey, [ $message ] );
	}
}
