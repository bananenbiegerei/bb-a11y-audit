<?php
/**
 * Plugin Name: BB A11y Audit
 * Description: In-admin accessibility auditing using axe-core. Maintain a list of page locations, scan them in-browser, and export results as CSV.
 * Version: 1.0.0
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * Author: Bananenbiegerei
 * License: GPL-2.0-or-later
 * Text Domain: bb-a11y-audit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BB_A11Y_AUDIT_VERSION', '1.0.0' );
define( 'BB_A11Y_AUDIT_DB_VERSION', '1.0.0' );
define( 'BB_A11Y_AUDIT_FILE', __FILE__ );
define( 'BB_A11Y_AUDIT_DIR', plugin_dir_path( __FILE__ ) );
define( 'BB_A11Y_AUDIT_URL', plugin_dir_url( __FILE__ ) );

require_once BB_A11Y_AUDIT_DIR . 'includes/class-bb-a11y-activator.php';
require_once BB_A11Y_AUDIT_DIR . 'includes/class-bb-a11y-wcag-map.php';
require_once BB_A11Y_AUDIT_DIR . 'includes/class-bb-a11y-locations.php';
require_once BB_A11Y_AUDIT_DIR . 'includes/class-bb-a11y-scans.php';
require_once BB_A11Y_AUDIT_DIR . 'includes/class-bb-a11y-rest-controller.php';
require_once BB_A11Y_AUDIT_DIR . 'includes/class-bb-a11y-csv-export.php';
require_once BB_A11Y_AUDIT_DIR . 'includes/class-bb-a11y-admin.php';
require_once BB_A11Y_AUDIT_DIR . 'includes/class-bb-a11y-audit.php';

register_activation_hook( __FILE__, array( 'BB_A11y_Activator', 'activate' ) );

BB_A11y_Audit::instance()->init();
