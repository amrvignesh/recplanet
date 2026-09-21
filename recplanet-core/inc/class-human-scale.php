<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * "1.75 acres is about one and a third football fields." "Yellowstone is 2,700 Central Parks."
 * Generated from acreage alone. Reference sizes are stated so the copy can be defended.
 */
class Human_Scale {

	const FOOTBALL_FIELD = 1.32;        // NFL field with end zones, acres
	const CENTRAL_PARK   = 843.0;       // acres
	const MANHATTAN      = 14690.0;     // acres
	const RHODE_ISLAND   = 776960.0;    // land acres
	const YELLOWSTONE    = 2219791.0;   // acres

	public static function describe( ?float $acres ): string {
		if ( null === $acres || $acres <= 0 ) {
			return '';
		}
		if ( $acres < 13.2 ) {                       // up to ten fields: use fractions
			return 'about ' . self::fraction_words( $acres / self::FOOTBALL_FIELD ) . ' football field' . ( $acres / self::FOOTBALL_FIELD >= 1.75 ? 's' : '' );
		}
		if ( $acres < self::CENTRAL_PARK ) {
			return 'about ' . self::round_words( $acres / self::FOOTBALL_FIELD ) . ' football fields';
		}
		if ( $acres < self::MANHATTAN * 2 ) {
			return 'about ' . self::fraction_words( $acres / self::CENTRAL_PARK ) . ' Central Park' . ( $acres / self::CENTRAL_PARK >= 1.75 ? 's' : '' );
		}
		if ( $acres < self::RHODE_ISLAND * 2 ) {
			return 'about ' . self::round_words( $acres / self::CENTRAL_PARK ) . ' Central Parks';
		}
		if ( $acres < self::YELLOWSTONE * 2 ) {
			return 'about ' . self::fraction_words( $acres / self::RHODE_ISLAND ) . ' Rhode Island' . ( $acres / self::RHODE_ISLAND >= 1.75 ? 's' : '' );
		}
		return 'about ' . self::round_words( $acres / self::YELLOWSTONE ) . ' Yellowstones';
	}

	/** 1.33 -> "one and a third"; 2.5 -> "two and a half"; 0.5 -> "half a". */
	public static function fraction_words( float $x ): string {
		$whole = (int) floor( $x );
		$frac  = $x - $whole;
		$names = [ 0 => 'no', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten' ];
		$fracs = [ [ 0.25, 'a quarter' ], [ 0.333, 'a third' ], [ 0.5, 'a half' ], [ 0.667, 'two thirds' ], [ 0.75, 'three quarters' ] ];
		$best  = null;
		$bd    = 1;
		foreach ( $fracs as [ $v, $w ] ) {
			if ( abs( $frac - $v ) < $bd ) {
				$bd = abs( $frac - $v );
				$best = $w;
			}
		}
		if ( $frac < 0.125 ) {
			$best = null;
		} elseif ( $frac > 0.875 ) {
			$whole++;
			$best = null;
		}
		if ( 0 === $whole ) {
			return $best ? str_replace( 'a ', '', $best ) . ' of a' : 'a fraction of a';
		}
		$w = $names[ $whole ] ?? number_format( $whole );
		return $best ? "$w and $best" : $w;
	}

	/** Round to a figure people repeat: 2 significant digits, then thousands separators. */
	public static function round_words( float $x ): string {
		if ( $x < 20 ) {
			return (string) round( $x );
		}
		$mag = pow( 10, floor( log10( $x ) ) - 1 );
		return number_format( round( $x / $mag ) * $mag );
	}
}
