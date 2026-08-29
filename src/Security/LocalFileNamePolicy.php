<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Security;

/**
 * Central policy for user-supplied local MediaWiki file names.
 *
 * This class intentionally does not resolve files. It answers only whether text is
 * structurally safe to pass to TitleFactory in the File namespace. Keeping the
 * policy separate lets parser input, configuration, node images, and edge icons use
 * one allow-list without coupling validation to MediaWiki services.
 */
final class LocalFileNamePolicy {
	public function isAllowed( string $value, bool $allowEmpty = false ): bool {
		if ( $value === '' ) {
			return $allowEmpty;
		}

		return mb_check_encoding( $value, 'UTF-8' ) &&
			!str_contains( $value, '..' ) &&
			!str_contains( $value, '/' ) &&
			!str_contains( $value, '\\' ) &&
			!str_contains( $value, ':' ) &&
			!preg_match( '/%(?:2e|2f|5c)/i', $value );
	}
}
