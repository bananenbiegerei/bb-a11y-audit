<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BB_A11y_Locations {

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'a11y_locations';
	}

	public static function get_all() {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY sort_order ASC, slug ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function get_by_slug( $slug ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Scanning relies on same-origin iframe access (direct DOM/contentWindow
	 * reads, no postMessage) -- a Location pointed at any other host can
	 * never actually be scanned by this plugin, so it's rejected up front
	 * rather than failing confusingly later in the Run Scan iframe.
	 */
	public static function is_same_site_url( $url ) {
		$target_host = wp_parse_url( $url, PHP_URL_HOST );
		$site_host   = wp_parse_url( home_url(), PHP_URL_HOST );

		return $target_host && $site_host && strtolower( $target_host ) === strtolower( $site_host );
	}

	public static function create( $data ) {
		global $wpdb;
		$table = self::table();
		$now   = current_time( 'mysql' );

		$wpdb->insert(
			$table,
			array(
				'slug'             => sanitize_title( $data['slug'] ),
				'label'            => sanitize_text_field( $data['label'] ),
				'url'              => esc_url_raw( $data['url'] ),
				'default_selector' => isset( $data['default_selector'] ) ? sanitize_text_field( $data['default_selector'] ) : null,
				'source'           => isset( $data['source'] ) ? sanitize_key( $data['source'] ) : 'manual',
				'sort_order'       => isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0,
				'created_at'       => $now,
				'updated_at'       => $now,
			)
		);

		return $wpdb->insert_id;
	}

	public static function update( $id, $data ) {
		global $wpdb;
		$table  = self::table();
		$fields = array();

		if ( isset( $data['slug'] ) ) {
			$fields['slug'] = sanitize_title( $data['slug'] );
		}
		if ( isset( $data['label'] ) ) {
			$fields['label'] = sanitize_text_field( $data['label'] );
		}
		if ( isset( $data['url'] ) ) {
			$fields['url'] = esc_url_raw( $data['url'] );
		}
		if ( array_key_exists( 'default_selector', $data ) ) {
			$fields['default_selector'] = sanitize_text_field( $data['default_selector'] );
		}
		if ( isset( $data['sort_order'] ) ) {
			$fields['sort_order'] = (int) $data['sort_order'];
		}

		$fields['updated_at'] = current_time( 'mysql' );

		return $wpdb->update( $table, $fields, array( 'id' => (int) $id ) );
	}

	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;

		$violations_table = $wpdb->prefix . 'a11y_violations';
		$scans_table       = $wpdb->prefix . 'a11y_scans';
		$table             = self::table();

		$wpdb->delete( $violations_table, array( 'location_id' => $id ) );
		$wpdb->delete( $scans_table, array( 'location_id' => $id ) );
		$wpdb->delete( $table, array( 'id' => $id ) );

		return true;
	}

	/**
	 * Build categorized candidate Locations: fixed site landmarks, the active
	 * theme's page templates (via get_page_templates()), and public CPTs.
	 * These are suggestions only -- nothing here is written to the database.
	 *
	 * @return array { special: [...], templates: [...], post_types: [...] }
	 */
	public static function suggest_locations() {
		$existing_slugs = wp_list_pluck( self::get_all(), 'slug' );

		return array(
			'special'    => self::suggest_special_locations( $existing_slugs ),
			'templates'  => self::discover_page_templates( $existing_slugs ),
			'post_types' => self::discover_post_types( $existing_slugs ),
		);
	}

	/**
	 * Fixed landmarks that have no page template or CPT of their own.
	 */
	private static function suggest_special_locations( $existing_slugs ) {
		$suggestions = array();

		$add = function( $slug, $label, $url ) use ( &$suggestions, $existing_slugs ) {
			if ( ! $url || in_array( $slug, $existing_slugs, true ) ) {
				return;
			}
			$suggestions[] = array(
				'slug'  => $slug,
				'label' => $label,
				'url'   => $url,
			);
		};

		// Header / footer: no template of their own, heuristic pick of the homepage.
		$add( 'header', 'Header', home_url( '/' ) );
		$add( 'footer', 'Footer', home_url( '/' ) );

		// Home / front page, based on the reading settings.
		if ( 'page' === get_option( 'show_on_front' ) ) {
			$front_id = (int) get_option( 'page_on_front' );
			if ( $front_id ) {
				$add( 'front-page', 'Front Page', get_permalink( $front_id ) );
			}
			$posts_id = (int) get_option( 'page_for_posts' );
			if ( $posts_id ) {
				$add( 'home', 'Blog Home', get_permalink( $posts_id ) );
			}
		} else {
			$add( 'home', 'Home', home_url( '/' ) );
		}

		// WooCommerce special-cased fixed endpoints.
		if ( function_exists( 'wc_get_cart_url' ) ) {
			$add( 'cart', 'Cart', wc_get_cart_url() );
		}
		if ( function_exists( 'wc_get_page_id' ) ) {
			$checkout_id = wc_get_page_id( 'checkout' );
			if ( $checkout_id && $checkout_id > 0 ) {
				$add( 'form-checkout', 'Checkout', get_permalink( $checkout_id ) );
			}
		}

		return $suggestions;
	}

	/**
	 * Scan the active theme's page templates via get_page_templates() --
	 * this returns every template the theme declares (Template Name: header
	 * in classic themes, Title: in block-theme /templates/ files), not just
	 * ones already assigned to a page, so unused templates show up too.
	 *
	 * Named templates get one suggestion per published page using them
	 * (matches the "page-{slug}" convention for one-off pages like About Us
	 * or FAQ); the "default" template collapses to a single generic "page"
	 * suggestion since many ordinary pages typically share it.
	 */
	private static function discover_page_templates( $existing_slugs ) {
		$templates                            = get_page_templates();
		$templates[ __( 'Default (no custom template)', 'bb-a11y-audit' ) ] = 'default';

		$results = array();

		foreach ( $templates as $name => $file ) {
			$query_args = array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			);

			if ( 'default' === $file ) {
				$query_args['meta_query'] = array(
					'relation' => 'OR',
					array(
						'key'     => '_wp_page_template',
						'value'   => 'default',
						'compare' => '=',
					),
					array(
						'key'     => '_wp_page_template',
						'compare' => 'NOT EXISTS',
					),
				);
			} else {
				$query_args['meta_key']   = '_wp_page_template';
				$query_args['meta_value'] = $file;
			}

			$pages = get_posts( $query_args );

			if ( 'default' === $file ) {
				if ( empty( $pages ) ) {
					continue;
				}
				$slug = 'page';
				if ( in_array( $slug, $existing_slugs, true ) ) {
					continue;
				}
				$results[] = array(
					'template_name' => $name,
					'slug'          => $slug,
					/* translators: %s: representative page title */
					'label'         => sprintf( __( 'Standard Page (%s)', 'bb-a11y-audit' ), $pages[0]->post_title ),
					'url'           => get_permalink( $pages[0] ),
				);
				continue;
			}

			if ( empty( $pages ) ) {
				// Theme declares this template, but no published page uses it yet.
				$results[] = array(
					'template_name' => $name,
					'slug'          => null,
					'label'         => null,
					'url'           => null,
				);
				continue;
			}

			foreach ( $pages as $page ) {
				$slug = 'page-' . $page->post_name;
				if ( in_array( $slug, $existing_slugs, true ) ) {
					continue;
				}
				$results[] = array(
					'template_name' => $name,
					'slug'          => $slug,
					'label'         => $page->post_title,
					'url'           => get_permalink( $page ),
				);
			}
		}

		return $results;
	}

	/**
	 * One suggestion per single/archive view for every public custom post
	 * type (and the built-in "post" type, whose archive is really the blog
	 * index or the page assigned in Settings > Reading).
	 */
	private static function discover_post_types( $existing_slugs ) {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$results    = array();

		foreach ( $post_types as $post_type ) {
			if ( in_array( $post_type->name, array( 'page', 'attachment' ), true ) ) {
				continue;
			}

			$latest = get_posts(
				array(
					'post_type'      => $post_type->name,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);

			$single_slug = 'single-' . $post_type->name;
			if ( ! empty( $latest ) && ! in_array( $single_slug, $existing_slugs, true ) ) {
				$results[] = array(
					'post_type' => $post_type->name,
					'kind'      => 'single',
					'slug'      => $single_slug,
					/* translators: %s: post type label */
					'label'     => sprintf( __( 'Single: %s', 'bb-a11y-audit' ), $post_type->label ),
					'url'       => get_permalink( $latest[0] ),
				);
			}

			if ( 'post' === $post_type->name ) {
				$archive_slug = 'archive';
				$archive_url  = ( 'page' === get_option( 'show_on_front' ) && get_option( 'page_for_posts' ) )
					? get_permalink( get_option( 'page_for_posts' ) )
					: home_url( '/' );
			} elseif ( $post_type->has_archive ) {
				$archive_slug = 'archive-' . $post_type->name;
				$archive_url  = get_post_type_archive_link( $post_type->name );
			} else {
				continue;
			}

			if ( ! in_array( $archive_slug, $existing_slugs, true ) ) {
				$results[] = array(
					'post_type' => $post_type->name,
					'kind'      => 'archive',
					'slug'      => $archive_slug,
					/* translators: %s: post type label */
					'label'     => sprintf( __( 'Archive: %s', 'bb-a11y-audit' ), $post_type->label ),
					'url'       => $archive_url,
				);
			}
		}

		return $results;
	}
}
