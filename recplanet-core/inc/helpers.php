<?php
/**
 * Small shared helpers. Everything here is safe to call from the theme.
 */

namespace RP;

defined( 'ABSPATH' ) || exit;

const POST_PARK    = 'rp_park';
const POST_PHOTO   = 'rp_photo';
const POST_CONTEST = 'rp_contest';

const TAX_ACTIVITY = 'rp_activity';
const TAX_FACILITY = 'rp_facility';
const TAX_STEWARD  = 'rp_steward';
const TAX_PLACE    = 'rp_place';

/** Steward levels, in the order they are shown. */
const STEWARD_LEVELS = [
	'city'    => 'City or town',
	'county'  => 'County',
	'state'   => 'State',
	'federal' => 'Federal',
	'tribal'  => 'Tribal',
	'other'   => 'Other',
];

/** The 45 activity values as they exist in the Drupal select list. */
const ACTIVITIES = [
	'Playground', 'Picnicking', 'Basketball', 'Restrooms', 'Baseball', 'Hiking', 'Softball', 'Soccer', 'Tennis',
	'Fishing', 'Walking', 'Biking', 'Swimming', 'Kayaking', 'Canoeing', 'Volleyball', 'Camping', 'Boating',
	'Sprayground', 'Hunting', 'Horse Back Riding', 'Dog-Park', 'Horseshoes', 'Cross-Country Skiing', 'Skate Park',
	'Disc-Golf', 'Golf', 'Water-Skiing', 'Handball', 'Ice-Skating', 'Roller-hockey', 'Snowmobiling', 'Sailboarding',
	'Sailing', 'Backpacking', 'Bocce', 'Climbing', 'Archery Range', 'Surfing', 'Scuba', 'Skiing', 'Rafting',
	'Caving', 'Hang-Gliding',
];

/** Table name with the site prefix. */
function table( string $name ): string {
	global $wpdb;
	return $wpdb->prefix . 'rp_' . $name;
}

/** Format acres the way the site always does: thousands separators, two decimals. */
function format_acres( float|int|null $acres, int $decimals = 2 ): string {
	if ( null === $acres ) {
		return '';
	}
	return number_format( (float) $acres, $decimals, '.', ',' );
}

/** Lower-case, dash-separated slug that matches the old Drupal aliases (they were pathauto defaults). */
function legacy_slug( string $text ): string {
	$text = remove_accents( $text );
	$text = strtolower( $text );
	$text = preg_replace( '/[^a-z0-9]+/', '-', $text );
	return trim( $text, '-' );
}

/** US state code to name; also used to parse the old Park Tags. */
function us_states(): array {
	return [
		'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
		'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'FL' => 'Florida', 'GA' => 'Georgia',
		'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
		'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts',
		'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana',
		'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico',
		'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
		'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
		'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont',
		'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
		'DC' => 'District of Columbia', 'PR' => 'Puerto Rico',
	];
}
