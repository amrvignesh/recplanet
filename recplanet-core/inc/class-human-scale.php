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
		// Football fields up to about ten of them, as words; then counted; then Central Parks; then Yellowstones.
		$fields = $acres / self::FOOTBALL_FIELD;
		if ( $fields < 10 ) {
			return 'about ' . self::fraction_words( $fields ) . ' football ' . self::plural( 'field', $fields );
		}
		if ( $acres < self::CENTRAL_PARK ) {
			return 'about ' . self::round_words( $fields ) . ' football fields';
		}
		$parks = $acres / self::CENTRAL_PARK;
		if ( $parks < 10 ) {
			return 'about ' . self::fraction_words( $parks ) . ' Central ' . self::plural( 'Park', $parks );
		}
		if ( $acres < self::YELLOWSTONE * 1.5 ) {
			return 'about ' . self::round_words( $parks ) . ' Central Parks';   // Yellowstone itself: about 2,600
		}
		$ys = $acres / self::YELLOWSTONE;
		return 'about ' . ( $ys < 10 ? self::fraction_words( $ys ) : self::round_words( $ys ) ) . ' ' . self::plural( 'Yellowstone', $ys );
	}

	/** Singular only for exactly one; "one and a third fields" is plural. */
	private static function plural( string $word, float $n ): string {
		return ( abs( $n - 1 ) < 0.125 ) ? $word : $word . 's';
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
