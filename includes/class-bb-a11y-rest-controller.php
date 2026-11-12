<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BB_A11y_Rest_Controller {

	const NAMESPACE_ = 'bb-a11y/v1';

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_,
			'/locations',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_locations' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_location' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/locations/suggestions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_suggestions' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/locations/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_location' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_location' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/locations/(?P<id>\d+)/history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_history' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/scans',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_scan' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/scans/(?P<id>\d+)/violations',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_scan_violations' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/scans/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_scan_note' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/violations/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_violation_note' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/results',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_results' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	public function check_permission() {
		return current_user_can( 'manage_options' );
	}

	public function get_locations() {
		return rest_ensure_response( BB_A11y_Locations::get_all() );
	}

	public function get_suggestions() {
		return rest_ensure_response( BB_A11y_Locations::suggest_locations() );
	}

	public function create_location( WP_REST_Request $request ) {
		$slug = sanitize_title( $request->get_param( 'slug' ) );
		$url  = esc_url_raw( $request->get_param( 'url' ) );

		if ( empty( $slug ) || empty( $url ) ) {
			return new WP_Error( 'bb_a11y_invalid', __( 'A slug and URL are required.', 'bb-a11y-audit' ), array( 'status' => 400 ) );
		}

		if ( BB_A11y_Locations::get_by_slug( $slug ) ) {
			return new WP_Error( 'bb_a11y_duplicate', __( 'A location with this slug already exists.', 'bb-a11y-audit' ), array( 'status' => 409 ) );
		}

		if ( ! BB_A11y_Locations::is_same_site_url( $url ) ) {
			return new WP_Error( 'bb_a11y_external_url', __( 'This plugin can only scan pages on this WordPress site. The URL you entered points at a different host.', 'bb-a11y-audit' ), array( 'status' => 400 ) );
		}

		$id = BB_A11y_Locations::create(
			array(
				'slug'             => $slug,
				'label'            => $request->get_param( 'label' ) ? $request->get_param( 'label' ) : $slug,
				'url'              => $url,
				'default_selector' => $request->get_param( 'default_selector' ),
				'source'           => $request->get_param( 'source' ) ? $request->get_param( 'source' ) : 'manual',
			)
		);

		return rest_ensure_response( BB_A11y_Locations::get( $id ) );
	}

	public function update_location( WP_REST_Request $request ) {
		$id       = (int) $request->get_param( 'id' );
		$location = BB_A11y_Locations::get( $id );

		if ( ! $location ) {
			return new WP_Error( 'bb_a11y_not_found', __( 'Location not found.', 'bb-a11y-audit' ), array( 'status' => 404 ) );
		}

		$data = array();
		foreach ( array( 'slug', 'label', 'url', 'default_selector', 'sort_order' ) as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				$data[ $field ] = $request->get_param( $field );
			}
		}

		if ( isset( $data['url'] ) && ! BB_A11y_Locations::is_same_site_url( $data['url'] ) ) {
			return new WP_Error( 'bb_a11y_external_url', __( 'This plugin can only scan pages on this WordPress site. The URL you entered points at a different host.', 'bb-a11y-audit' ), array( 'status' => 400 ) );
		}

		BB_A11y_Locations::update( $id, $data );

		return rest_ensure_response( BB_A11y_Locations::get( $id ) );
	}

	public function delete_location( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );
		BB_A11y_Locations::delete( $id );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public function get_history( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );
		return rest_ensure_response( BB_A11y_Scans::get_history( $id ) );
	}

	public function get_scan_violations( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );
		return rest_ensure_response( BB_A11y_Scans::get_scan_violations( $id ) );
	}

	public function create_scan( WP_REST_Request $request ) {
		$location_id = (int) $request->get_param( 'location_id' );
		$location    = BB_A11y_Locations::get( $location_id );

		if ( ! $location ) {
			return new WP_Error( 'bb_a11y_not_found', __( 'Location not found.', 'bb-a11y-audit' ), array( 'status' => 404 ) );
		}

		$results = $request->get_param( 'results' );
		if ( empty( $results ) || ! is_array( $results ) ) {
			return new WP_Error( 'bb_a11y_invalid', __( 'Missing scan results.', 'bb-a11y-audit' ), array( 'status' => 400 ) );
		}

		$outcome = BB_A11y_Scans::save_scan_result(
			$location_id,
			array(
				'selector'    => $request->get_param( 'selector' ),
				'scanned_url' => $request->get_param( 'url' ),
				'axe_version' => $request->get_param( 'axe_version' ),
				'tags_used'   => $request->get_param( 'tags_used' ),
				'results'     => $results,
			)
		);

		return rest_ensure_response( $outcome );
	}

	public function update_scan_note( WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$note = (string) $request->get_param( 'note' );
		BB_A11y_Scans::update_scan_note( $id, $note );
		return rest_ensure_response( array( 'updated' => true ) );
	}

	public function update_violation_note( WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$note = (string) $request->get_param( 'note' );
		BB_A11y_Scans::update_violation_note( $id, $note );
		return rest_ensure_response( array( 'updated' => true ) );
	}

	public function get_results() {
		$rows    = BB_A11y_Scans::get_merged_results();
		$mapped  = array();

		foreach ( $rows as $row ) {
			$entry = array(
				'status'      => $row['status'],
				'location_id' => (int) $row['location']->id,
				'slug'        => $row['location']->slug,
				'label'       => $row['location']->label,
			);

			if ( 'pass' === $row['status'] ) {
				$entry['scan_id'] = (int) $row['scan']->id;
				$entry['note']    = $row['scan']->note;
			} elseif ( 'violation' === $row['status'] ) {
				$violation               = $row['violation'];
				$entry['violation_id']   = (int) $violation->id;
				$entry['rule_id']        = $violation->rule_id;
				$entry['impact']         = $violation->impact;
				$entry['wcag']           = $violation->wcag_sc;
				$entry['description']    = $violation->description;
				$entry['instance_count'] = (int) $violation->instance_count;
				$entry['note']           = $violation->note;
			}

			$mapped[] = $entry;
		}

		return rest_ensure_response( $mapped );
	}
}
