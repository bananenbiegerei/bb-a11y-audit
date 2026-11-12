<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BB_A11y_Admin {

	private $hook_suffixes = array();

	public function register_menu() {
		$this->hook_suffixes['results'] = add_menu_page(
			__( 'A11y Audit', 'bb-a11y-audit' ),
			__( 'A11y Audit', 'bb-a11y-audit' ),
			'manage_options',
			'bb-a11y-audit',
			array( $this, 'render_results_page' ),
			'dashicons-universal-access-alt'
		);

		$this->hook_suffixes['results'] = add_submenu_page(
			'bb-a11y-audit',
			__( 'Results', 'bb-a11y-audit' ),
			__( 'Results', 'bb-a11y-audit' ),
			'manage_options',
			'bb-a11y-audit',
			array( $this, 'render_results_page' )
		);

		$this->hook_suffixes['locations'] = add_submenu_page(
			'bb-a11y-audit',
			__( 'Locations', 'bb-a11y-audit' ),
			__( 'Locations', 'bb-a11y-audit' ),
			'manage_options',
			'bb-a11y-audit-locations',
			array( $this, 'render_locations_page' )
		);

		$this->hook_suffixes['run-scan'] = add_submenu_page(
			'bb-a11y-audit',
			__( 'Run Scan', 'bb-a11y-audit' ),
			__( 'Run Scan', 'bb-a11y-audit' ),
			'manage_options',
			'bb-a11y-audit-scan',
			array( $this, 'render_run_scan_page' )
		);

		$this->hook_suffixes['history'] = add_submenu_page(
			'bb-a11y-audit',
			__( 'History', 'bb-a11y-audit' ),
			__( 'History', 'bb-a11y-audit' ),
			'manage_options',
			'bb-a11y-audit-history',
			array( $this, 'render_history_page' )
		);
	}

	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, $this->hook_suffixes, true ) ) {
			return;
		}

		wp_enqueue_style(
			'bb-a11y-admin',
			BB_A11Y_AUDIT_URL . 'assets/css/admin.css',
			array(),
			BB_A11Y_AUDIT_VERSION
		);

		$shared_data = array(
			'restUrl'      => esc_url_raw( rest_url( 'bb-a11y/v1' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'axeAssetUrl'  => BB_A11Y_AUDIT_URL . 'assets/js/axe.min.js',
			'defaultTags'  => array( 'wcag2a', 'wcag2aa', 'wcag21aa', 'best-practice' ),
		);

		if ( 'a11y-audit_page_bb-a11y-audit-locations' === $hook ) {
			wp_enqueue_script( 'bb-a11y-locations', BB_A11Y_AUDIT_URL . 'assets/js/admin-locations.js', array(), BB_A11Y_AUDIT_VERSION, true );
			wp_localize_script( 'bb-a11y-locations', 'BBA11yData', $shared_data );
		}

		if ( 'a11y-audit_page_bb-a11y-audit-scan' === $hook ) {
			wp_enqueue_script( 'bb-a11y-run-scan', BB_A11Y_AUDIT_URL . 'assets/js/admin-run-scan.js', array(), BB_A11Y_AUDIT_VERSION, true );
			wp_localize_script( 'bb-a11y-run-scan', 'BBA11yData', $shared_data );
		}

		if ( 'toplevel_page_bb-a11y-audit' === $hook ) {
			wp_enqueue_script( 'bb-a11y-results', BB_A11Y_AUDIT_URL . 'assets/js/admin-results.js', array(), BB_A11Y_AUDIT_VERSION, true );
			wp_localize_script( 'bb-a11y-results', 'BBA11yData', $shared_data );
		}
	}

	public function render_results_page() {
		require BB_A11Y_AUDIT_DIR . 'admin/views/page-results.php';
	}

	public function render_locations_page() {
		require BB_A11Y_AUDIT_DIR . 'admin/views/page-locations.php';
	}

	public function render_run_scan_page() {
		require BB_A11Y_AUDIT_DIR . 'admin/views/page-run-scan.php';
	}

	public function render_history_page() {
		require BB_A11Y_AUDIT_DIR . 'admin/views/page-history.php';
	}
}
