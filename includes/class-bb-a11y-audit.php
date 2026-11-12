<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BB_A11y_Audit {

	private static $instance = null;

	private $admin;
	private $rest_controller;
	private $csv_export;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->admin           = new BB_A11y_Admin();
		$this->rest_controller = new BB_A11y_Rest_Controller();
		$this->csv_export      = new BB_A11y_Csv_Export();
	}

	public function init() {
		add_action( 'admin_init', array( 'BB_A11y_Activator', 'maybe_upgrade' ) );
		add_action( 'admin_menu', array( $this->admin, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_assets' ) );
		add_action( 'admin_post_bb_a11y_save_settings', array( $this->admin, 'handle_settings_save' ) );
		add_action( 'rest_api_init', array( $this->rest_controller, 'register_routes' ) );

		$this->csv_export->register();
	}
}
