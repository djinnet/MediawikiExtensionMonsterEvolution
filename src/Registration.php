<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution;

use UnexpectedValueException;

final class Registration {
	private const DIRECTIONS = [
		'left-to-right',
		'right-to-left',
		'top-to-bottom',
		'bottom-to-top',
	];

	private const THEMES = [ 'default', 'compact', 'cards', 'minimal' ];

	public static function validate( array $credits ): void {
		self::assertEnum(
			self::getConfig( 'MonsterEvolutionDefaultDirection' ),
			'MonsterEvolutionDefaultDirection',
			self::DIRECTIONS
		);
		self::assertEnum( self::getConfig( 'MonsterEvolutionDefaultTheme' ), 'MonsterEvolutionDefaultTheme', self::THEMES );
		self::assertInt( self::getConfig( 'MonsterEvolutionDefaultImageWidth' ), 'MonsterEvolutionDefaultImageWidth', 16, 512 );
		self::assertInt(
			self::getConfig( 'MonsterEvolutionDefaultImageHeight' ),
			'MonsterEvolutionDefaultImageHeight',
			16,
			512
		);
		self::assertInt( self::getConfig( 'MonsterEvolutionMaxInputBytes' ), 'MonsterEvolutionMaxInputBytes', 1024, 1048576 );
		self::assertInt( self::getConfig( 'MonsterEvolutionMaxNodes' ), 'MonsterEvolutionMaxNodes', 1, 1000 );
		self::assertInt( self::getConfig( 'MonsterEvolutionMaxEdges' ), 'MonsterEvolutionMaxEdges', 1, 4000 );
		self::assertInt(
			self::getConfig( 'MonsterEvolutionMaxConditionsPerEdge' ),
			'MonsterEvolutionMaxConditionsPerEdge',
			0,
			100
		);
		self::assertInt( self::getConfig( 'MonsterEvolutionMaxAttributes' ), 'MonsterEvolutionMaxAttributes', 1, 64 );
		self::assertInt( self::getConfig( 'MonsterEvolutionMaxValueLength' ), 'MonsterEvolutionMaxValueLength', 1, 16384 );
		self::assertInt( self::getConfig( 'MonsterEvolutionMaxNodeIdLength' ), 'MonsterEvolutionMaxNodeIdLength', 1, 128 );
		self::assertInt( self::getConfig( 'MonsterEvolutionMaxGraphsPerPage' ), 'MonsterEvolutionMaxGraphsPerPage', 1, 100 );
		self::assertBoolean( self::getConfig( 'MonsterEvolutionEnableZoom' ), 'MonsterEvolutionEnableZoom' );
		self::assertBoolean(
			self::getConfig( 'MonsterEvolutionEnableTrackingCategory' ),
			'MonsterEvolutionEnableTrackingCategory'
		);
		self::assertFileName(
			self::getConfig( 'MonsterEvolutionMissingImage' ),
			'MonsterEvolutionMissingImage'
		);
	}

	private static function getConfig( string $name ): mixed {
		$globalName = 'wg' . $name;
		if ( !array_key_exists( $globalName, $GLOBALS ) ) {
			throw new UnexpectedValueException( "$name was not registered." );
		}
		return $GLOBALS[$globalName];
	}

	private static function assertEnum( mixed $value, string $name, array $allowed ): void {
		if ( !is_string( $value ) || !in_array( $value, $allowed, true ) ) {
			throw new UnexpectedValueException( "$name has an invalid value." );
		}
	}

	private static function assertInt(
		mixed $value,
		string $name,
		int $minimum,
		int $maximum
	): void {
		if ( !is_int( $value ) || $value < $minimum || $value > $maximum ) {
			throw new UnexpectedValueException( "$name must be between $minimum and $maximum." );
		}
	}

	private static function assertBoolean( mixed $value, string $name ): void {
		if ( !is_bool( $value ) ) {
			throw new UnexpectedValueException( "$name must be a Boolean." );
		}
	}

	private static function assertFileName( mixed $value, string $name ): void {
		if ( !is_string( $value ) || strlen( $value ) > 255 ||
			str_contains( $value, '..' ) || str_contains( $value, '/' ) ||
			str_contains( $value, '\\' ) || str_contains( $value, ':' ) ||
			preg_match( '/%(?:2e|2f|5c)/i', $value )
		) {
			throw new UnexpectedValueException( "$name must be an empty value or a local file name." );
		}
	}
}
