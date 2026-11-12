<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static lookup of axe-core "wcagXXX" tags to WCAG success-criterion numbers.
 *
 * This is a hardcoded table rather than a computed split of the tag's digits
 * because several WCAG 2.1 criteria (e.g. 2.4.11, 1.4.10) have two-digit
 * segments that don't parse as "one digit per SC level".
 */
class BB_A11y_Wcag_Map {

	const MAP = array(
		'wcag111'  => '1.1.1',
		'wcag121'  => '1.2.1',
		'wcag122'  => '1.2.2',
		'wcag123'  => '1.2.3',
		'wcag124'  => '1.2.4',
		'wcag125'  => '1.2.5',
		'wcag131'  => '1.3.1',
		'wcag132'  => '1.3.2',
		'wcag133'  => '1.3.3',
		'wcag134'  => '1.3.4',
		'wcag135'  => '1.3.5',
		'wcag141'  => '1.4.1',
		'wcag142'  => '1.4.2',
		'wcag143'  => '1.4.3',
		'wcag144'  => '1.4.4',
		'wcag145'  => '1.4.5',
		'wcag1410' => '1.4.10',
		'wcag1411' => '1.4.11',
		'wcag1412' => '1.4.12',
		'wcag1413' => '1.4.13',
		'wcag211'  => '2.1.1',
		'wcag212'  => '2.1.2',
		'wcag214'  => '2.1.4',
		'wcag221'  => '2.2.1',
		'wcag222'  => '2.2.2',
		'wcag231'  => '2.3.1',
		'wcag241'  => '2.4.1',
		'wcag242'  => '2.4.2',
		'wcag243'  => '2.4.3',
		'wcag244'  => '2.4.4',
		'wcag245'  => '2.4.5',
		'wcag246'  => '2.4.6',
		'wcag247'  => '2.4.7',
		'wcag2411' => '2.4.11',
		'wcag251'  => '2.5.1',
		'wcag252'  => '2.5.2',
		'wcag253'  => '2.5.3',
		'wcag254'  => '2.5.4',
		'wcag311'  => '3.1.1',
		'wcag312'  => '3.1.2',
		'wcag321'  => '3.2.1',
		'wcag322'  => '3.2.2',
		'wcag323'  => '3.2.3',
		'wcag324'  => '3.2.4',
		'wcag331'  => '3.3.1',
		'wcag332'  => '3.3.2',
		'wcag333'  => '3.3.3',
		'wcag334'  => '3.3.4',
		'wcag411'  => '4.1.1',
		'wcag412'  => '4.1.2',
		'wcag413'  => '4.1.3',
	);

	/**
	 * Resolve a list of axe-core tags to a comma-joined list of WCAG SC numbers.
	 *
	 * @param string[] $tags Raw axe-core tags (may include non-wcag tags, e.g. "cat.forms").
	 * @return string Comma-joined SC numbers, e.g. "1.4.3, 2.4.4". Empty string if none matched.
	 */
	public static function resolve( array $tags ) {
		$matched = array();

		foreach ( $tags as $tag ) {
			if ( isset( self::MAP[ $tag ] ) ) {
				$matched[] = self::MAP[ $tag ];
			}
		}

		$matched = array_values( array_unique( $matched ) );
		sort( $matched, SORT_NATURAL );

		return implode( ', ', $matched );
	}
}
