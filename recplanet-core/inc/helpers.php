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
function legacy_slug( ?string $text ): string {
	$text = remove_accents( (string) $text );
	$text = strtolower( $text );
	$text = preg_replace( '/[^a-z0-9]+/', '-', $text );
	return trim( $text, '-' );
}

/** Country code to name, for the few countries the database reaches beyond the US. */
function country_name( string $code ): string {
	$names = [ 'us' => 'United States', 'ca' => 'Canada', 'mx' => 'Mexico', 'es' => 'Spain', 'uk' => 'United Kingdom', 'gb' => 'United Kingdom', 'in' => 'India', 'br' => 'Brazil', 'cn' => 'China', 'za' => 'South Africa', 'au' => 'Australia', 'cr' => 'Costa Rica', 'aq' => 'Antarctica', 'gl' => 'Greenland', 'nz' => 'New Zealand', 'fr' => 'France', 'de' => 'Germany', 'it' => 'Italy', 'jp' => 'Japan', 'ie' => 'Ireland', 'nl' => 'Netherlands', 'ch' => 'Switzerland', 'at' => 'Austria', 'no' => 'Norway', 'se' => 'Sweden', 'fi' => 'Finland', 'dk' => 'Denmark', 'pt' => 'Portugal', 'ar' => 'Argentina', 'cl' => 'Chile', 'pe' => 'Peru', 'co' => 'Colombia', 'ec' => 'Ecuador', 'pa' => 'Panama', 'bz' => 'Belize', 'gt' => 'Guatemala', 'ke' => 'Kenya', 'tz' => 'Tanzania', 'bw' => 'Botswana', 'na' => 'Namibia', 'zm' => 'Zambia', 'zw' => 'Zimbabwe', 'ug' => 'Uganda', 'eg' => 'Egypt', 'ma' => 'Morocco', 'th' => 'Thailand', 'np' => 'Nepal', 'id' => 'Indonesia', 'my' => 'Malaysia', 'sg' => 'Singapore', 'ph' => 'Philippines', 'kr' => 'South Korea', 'ru' => 'Russia', 'pl' => 'Poland', 'cz' => 'Czechia', 'gr' => 'Greece', 'hr' => 'Croatia', 'is' => 'Iceland', 'tr' => 'Turkey', 'il' => 'Israel', 'jo' => 'Jordan', 'ae' => 'United Arab Emirates', 'bs' => 'Bahamas', 'jm' => 'Jamaica', 'cu' => 'Cuba', 'do' => 'Dominican Republic', 'pr' => 'Puerto Rico', 'vi' => 'US Virgin Islands', 'bm' => 'Bermuda', 'fj' => 'Fiji', 've' => 'Venezuela', 'bo' => 'Bolivia', 'uy' => 'Uruguay', 'py' => 'Paraguay' ];
	return $names[ strtolower( $code ) ] ?? strtoupper( $code );
}

/** Province, state or region name for a code outside the US; the code itself when unknown. */
function region_name( string $country, string $code ): string {
	$country = strtolower( $country );
	$code    = strtoupper( $code );
	if ( 'us' === $country ) {
		return us_states()[ $code ] ?? $code;
	}
	$maps = [
		'ca' => [ 'AB' => 'Alberta', 'BC' => 'British Columbia', 'MB' => 'Manitoba', 'NB' => 'New Brunswick', 'NL' => 'Newfoundland and Labrador', 'NS' => 'Nova Scotia', 'NT' => 'Northwest Territories', 'NU' => 'Nunavut', 'ON' => 'Ontario', 'PE' => 'Prince Edward Island', 'QC' => 'Quebec', 'SK' => 'Saskatchewan', 'YT' => 'Yukon' ],
		'au' => [ 'NSW' => 'New South Wales', 'VIC' => 'Victoria', 'QLD' => 'Queensland', 'SA' => 'South Australia', 'WA' => 'Western Australia', 'TAS' => 'Tasmania', 'NT' => 'Northern Territory', 'ACT' => 'Australian Capital Territory' ],
	];
	return $maps[ $country ][ $code ] ?? $code;
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
