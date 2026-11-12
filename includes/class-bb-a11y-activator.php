<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BB_A11y_Activator {

	public static function activate() {
		self::create_tables();
		update_option( 'bb_a11y_db_version', BB_A11Y_AUDIT_DB_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'bb_a11y_db_version' ) !== BB_A11Y_AUDIT_DB_VERSION ) {
			self::create_tables();
			update_option( 'bb_a11y_db_version', BB_A11Y_AUDIT_DB_VERSION );
		}
	}

	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$locations_table  = $wpdb->prefix . 'a11y_locations';
		$scans_table      = $wpdb->prefix . 'a11y_scans';
		$violations_table = $wpdb->prefix . 'a11y_violations';

		$sql = array();

		$sql[] = "CREATE TABLE {$locations_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			slug VARCHAR(191) NOT NULL,
			label VARCHAR(255) NOT NULL,
			url TEXT NOT NULL,
			default_selector VARCHAR(255) DEFAULT NULL,
			source VARCHAR(20) NOT NULL DEFAULT 'manual',
			sort_order INT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$scans_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			location_id BIGINT UNSIGNED NOT NULL,
			selector VARCHAR(255) DEFAULT NULL,
			scanned_url TEXT NOT NULL,
			axe_version VARCHAR(20) NOT NULL,
			tags_used VARCHAR(255) NOT NULL,
			violation_count INT UNSIGNED NOT NULL DEFAULT 0,
			pass_count INT UNSIGNED NOT NULL DEFAULT 0,
			raw_result LONGTEXT,
			note LONGTEXT,
			note_source VARCHAR(20) NOT NULL DEFAULT 'auto',
			note_updated_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY  (id),
			KEY location_id (location_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$violations_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			scan_id BIGINT UNSIGNED NOT NULL,
			location_id BIGINT UNSIGNED NOT NULL,
			rule_id VARCHAR(100) NOT NULL,
			impact VARCHAR(20) DEFAULT NULL,
			wcag_tags VARCHAR(255) DEFAULT NULL,
			wcag_sc VARCHAR(255) DEFAULT NULL,
			help_text TEXT,
			description TEXT,
			instance_count INT UNSIGNED NOT NULL DEFAULT 0,
			node_targets LONGTEXT,
			note LONGTEXT,
			note_source VARCHAR(20) NOT NULL DEFAULT 'auto',
			note_updated_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY scan_id (scan_id),
			KEY location_id (location_id),
			KEY rule_id (rule_id)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}
}
