<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BB_A11y_Scans {

	private static function scans_table() {
		global $wpdb;
		return $wpdb->prefix . 'a11y_scans';
	}

	private static function violations_table() {
		global $wpdb;
		return $wpdb->prefix . 'a11y_violations';
	}

	/**
	 * Persist a new scan and its violations, carrying forward any manually
	 * edited Anmerkung notes from the most recent prior scan of the same
	 * location/rule.
	 *
	 * @param int   $location_id
	 * @param array $payload {
	 *     @type string $selector
	 *     @type string $scanned_url
	 *     @type string $axe_version
	 *     @type array  $tags_used
	 *     @type array  $results     Raw axe.run() results (decoded).
	 * }
	 * @return array { scan_id, violation_count }
	 */
	public static function save_scan_result( $location_id, $payload ) {
		global $wpdb;

		$location_id = (int) $location_id;
		$results     = isset( $payload['results'] ) ? $payload['results'] : array();
		$violations  = isset( $results['violations'] ) ? $results['violations'] : array();
		$passes      = isset( $results['passes'] ) ? $results['passes'] : array();
		$now         = current_time( 'mysql' );

		$scans_table      = self::scans_table();
		$violations_table = self::violations_table();

		$scan_note        = '';
		$scan_note_source = 'auto';

		if ( empty( $violations ) ) {
			$prior = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT note FROM {$scans_table}
					 WHERE location_id = %d AND note_source = 'manual' AND violation_count = 0
					 ORDER BY created_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$location_id
				)
			);
			if ( $prior ) {
				$scan_note        = $prior->note;
				$scan_note_source = 'manual';
			}
		}

		$wpdb->insert(
			$scans_table,
			array(
				'location_id'     => $location_id,
				'selector'        => isset( $payload['selector'] ) ? sanitize_text_field( $payload['selector'] ) : null,
				'scanned_url'     => esc_url_raw( $payload['scanned_url'] ),
				'axe_version'     => sanitize_text_field( $payload['axe_version'] ),
				'tags_used'       => sanitize_text_field( implode( ',', (array) $payload['tags_used'] ) ),
				'violation_count' => count( $violations ),
				'pass_count'      => count( $passes ),
				'raw_result'      => wp_json_encode( $results ),
				'note'            => $scan_note,
				'note_source'     => $scan_note_source,
				'note_updated_at' => 'manual' === $scan_note_source ? $now : null,
				'created_at'      => $now,
				'created_by'      => get_current_user_id(),
			)
		);

		$scan_id = $wpdb->insert_id;

		foreach ( $violations as $violation ) {
			$rule_id = sanitize_text_field( $violation['id'] );
			$tags    = isset( $violation['tags'] ) ? (array) $violation['tags'] : array();

			$prior = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT note FROM {$violations_table}
					 WHERE location_id = %d AND rule_id = %s AND note_source = 'manual'
					 ORDER BY created_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$location_id,
					$rule_id
				)
			);

			$help_text = isset( $violation['help'] ) ? sanitize_text_field( $violation['help'] ) : '';

			$note        = $prior ? $prior->note : $help_text;
			$note_source = $prior ? 'manual' : 'auto';

			$wpdb->insert(
				$violations_table,
				array(
					'scan_id'         => $scan_id,
					'location_id'     => $location_id,
					'rule_id'         => $rule_id,
					'impact'          => isset( $violation['impact'] ) ? sanitize_text_field( $violation['impact'] ) : null,
					'wcag_tags'       => sanitize_text_field( implode( ',', $tags ) ),
					'wcag_sc'         => BB_A11y_Wcag_Map::resolve( $tags ),
					'help_text'       => $help_text,
					'description'     => isset( $violation['description'] ) ? sanitize_textarea_field( $violation['description'] ) : '',
					'instance_count'  => isset( $violation['nodes'] ) ? count( $violation['nodes'] ) : 0,
					'node_targets'    => isset( $violation['nodes'] ) ? wp_json_encode( wp_list_pluck( $violation['nodes'], 'target' ) ) : null,
					'note'            => $note,
					'note_source'     => $note_source,
					'note_updated_at' => 'manual' === $note_source ? $now : null,
					'created_at'      => $now,
				)
			);
		}

		return array(
			'scan_id'         => $scan_id,
			'violation_count' => count( $violations ),
		);
	}

	public static function update_violation_note( $violation_id, $note ) {
		global $wpdb;
		return $wpdb->update(
			self::violations_table(),
			array(
				'note'            => sanitize_textarea_field( $note ),
				'note_source'     => 'manual',
				'note_updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $violation_id )
		);
	}

	public static function update_scan_note( $scan_id, $note ) {
		global $wpdb;
		return $wpdb->update(
			self::scans_table(),
			array(
				'note'            => sanitize_textarea_field( $note ),
				'note_source'     => 'manual',
				'note_updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $scan_id )
		);
	}

	public static function get_history( $location_id ) {
		global $wpdb;
		$table = self::scans_table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, selector, scanned_url, axe_version, violation_count, pass_count, created_at
				 FROM {$table} WHERE location_id = %d ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				(int) $location_id
			)
		);
	}

	public static function get_scan_violations( $scan_id ) {
		global $wpdb;
		$table = self::violations_table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE scan_id = %d ORDER BY impact DESC, rule_id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				(int) $scan_id
			)
		);
	}

	/**
	 * Build the merged results table: every known Location, either pending
	 * (never scanned), pass (latest scan had zero violations), or one row
	 * per (Location, Rule ID) from the latest scan.
	 */
	public static function get_merged_results() {
		global $wpdb;

		$locations_table  = $wpdb->prefix . 'a11y_locations';
		$scans_table       = self::scans_table();
		$violations_table  = self::violations_table();

		$locations = $wpdb->get_results( "SELECT * FROM {$locations_table} ORDER BY sort_order ASC, slug ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$rows = array();

		foreach ( $locations as $location ) {
			$latest_scan = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$scans_table} WHERE location_id = %d ORDER BY created_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$location->id
				)
			);

			if ( ! $latest_scan ) {
				$rows[] = array(
					'status'   => 'pending',
					'location' => $location,
				);
				continue;
			}

			if ( 0 === (int) $latest_scan->violation_count ) {
				$rows[] = array(
					'status'   => 'pass',
					'location' => $location,
					'scan'     => $latest_scan,
				);
				continue;
			}

			$violations = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$violations_table} WHERE scan_id = %d ORDER BY impact DESC, rule_id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$latest_scan->id
				)
			);

			foreach ( $violations as $violation ) {
				$rows[] = array(
					'status'    => 'violation',
					'location'  => $location,
					'scan'      => $latest_scan,
					'violation' => $violation,
				);
			}
		}

		return $rows;
	}
}
