<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scan profiles (which axe tags/rules run) and coverage targets (which
 * Location slugs an estimate-style audit expects to be able to scan).
 *
 * Profiles stay site-agnostic; the default "wcag" profile drops best-practice
 * landmark/region/heading noise that was flooding the Gob Squad CSV without
 * appearing on the sold estimate. Coverage targets use configurable slug
 * aliases so another site can map the same template names to local URLs.
 */
class BB_A11y_Scan_Config {

	const OPTION_PROFILE = 'bb_a11y_scan_profile';

	const PROFILE_WCAG = 'wcag';
	const PROFILE_FULL = 'full';

	/**
	 * Best-practice / landmark rules that dominate unpaid MODERATE noise.
	 * Disabled in the default WCAG profile; still available via "full".
	 */
	const NOISY_RULES = array(
		'region',
		'landmark-one-main',
		'landmark-main-is-top-level',
		'landmark-no-duplicate-banner',
		'landmark-no-duplicate-contentinfo',
		'landmark-no-duplicate-main',
		'landmark-unique',
		'landmark-banner-is-top-level',
		'landmark-contentinfo-is-top-level',
		'landmark-complementary-is-top-level',
		'page-has-heading-one',
		'heading-order',
		'empty-heading',
	);

	/**
	 * Axe rule IDs that map to sold estimate lines (GOB 2026/01 and similar).
	 * Used for docs / coverage UI — the WCAG tag set already includes them.
	 */
	const ESTIMATE_RULE_IDS = array(
		'aria-required-children',
		'aria-required-parent',
		'link-name',
		'image-alt',
		'color-contrast',
		'aria-progressbar-name',
		'nested-interactive',
		'autocomplete-valid',
		'aria-allowed-attr',
	);

	public static function get_profile() {
		$profile = get_option( self::OPTION_PROFILE, self::PROFILE_WCAG );
		return in_array( $profile, array( self::PROFILE_WCAG, self::PROFILE_FULL ), true )
			? $profile
			: self::PROFILE_WCAG;
	}

	public static function set_profile( $profile ) {
		$profile = sanitize_key( $profile );
		if ( ! in_array( $profile, array( self::PROFILE_WCAG, self::PROFILE_FULL ), true ) ) {
			$profile = self::PROFILE_WCAG;
		}
		update_option( self::OPTION_PROFILE, $profile, false );
		return $profile;
	}

	/**
	 * Tags passed to axe.run({ runOnly: { type: 'tag', values } }).
	 *
	 * @return string[]
	 */
	public static function get_tags() {
		if ( self::PROFILE_FULL === self::get_profile() ) {
			return array( 'wcag2a', 'wcag2aa', 'wcag21aa', 'best-practice' );
		}

		// WCAG 2.1 A+AA only — excludes best-practice landmark/region noise.
		return array( 'wcag2a', 'wcag2aa', 'wcag21aa' );
	}

	/**
	 * Per-rule overrides for axe.run({ rules: ... }).
	 *
	 * @return array<string, array{enabled: bool}>
	 */
	public static function get_rule_overrides() {
		if ( self::PROFILE_FULL === self::get_profile() ) {
			return array();
		}

		$rules = array();
		foreach ( self::NOISY_RULES as $rule_id ) {
			$rules[ $rule_id ] = array( 'enabled' => false );
		}
		return $rules;
	}

	/**
	 * Payload localized into admin JS for axe.run().
	 *
	 * @return array{profile: string, tags: string[], rules: array}
	 */
	public static function get_axe_client_config() {
		return array(
			'profile' => self::get_profile(),
			'tags'    => self::get_tags(),
			'rules'   => self::get_rule_overrides(),
		);
	}

	/**
	 * Coverage checklist: Location slugs an estimate-style audit should scan.
	 * Filterable so projects can remap aliases without forking the plugin.
	 *
	 * Each target:
	 * - slug: CSV Location column value
	 * - label: admin UI label
	 * - default_selector: optional CSS scope (header/footer isolation)
	 * - rules: estimate rule IDs expected on this template (documentation)
	 * - discover: how to resolve a URL on the live site
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_coverage_targets() {
		$targets = array(
			array(
				'slug'             => 'header',
				'label'            => __( 'Header (desktop menu)', 'bb-a11y-audit' ),
				'default_selector' => 'header, [role="banner"]',
				'rules'            => array( 'aria-required-children', 'aria-required-parent' ),
				'discover'         => array( 'type' => 'home' ),
			),
			array(
				'slug'             => 'footer',
				'label'            => __( 'Footer', 'bb-a11y-audit' ),
				'default_selector' => 'footer, [role="contentinfo"]',
				'rules'            => array( 'link-name' ),
				'discover'         => array( 'type' => 'home' ),
			),
			array(
				'slug'     => 'front-page',
				'label'    => __( 'Front Page', 'bb-a11y-audit' ),
				'rules'    => array( 'link-name', 'color-contrast', 'image-alt', 'aria-progressbar-name' ),
				'discover' => array( 'type' => 'front_page' ),
			),
			array(
				'slug'     => 'page-about-us',
				'label'    => __( 'About Us', 'bb-a11y-audit' ),
				'rules'    => array( 'image-alt' ),
				'discover' => array(
					'type'  => 'page_slugs',
					'slugs' => array( 'about-us', 'about', 'ueber-uns', 'uber-uns' ),
				),
			),
			array(
				'slug'     => 'page-newsletter',
				'label'    => __( 'Newsletter', 'bb-a11y-audit' ),
				'rules'    => array( 'color-contrast' ),
				'discover' => array(
					'type'  => 'page_slugs',
					'slugs' => array( 'newsletter', 'news-letter', 'subscribe' ),
				),
			),
			array(
				'slug'     => 'page-faq',
				'label'    => __( 'FAQ page', 'bb-a11y-audit' ),
				'rules'    => array( 'color-contrast' ),
				'discover' => array(
					'type'             => 'page_slugs',
					'slugs'            => array( 'faq', 'faqs', 'frequently-asked-questions' ),
					'fallback_archive' => array( 'faqs', 'faq' ),
				),
			),
			array(
				'slug'     => 'page-news',
				'label'    => __( 'News page', 'bb-a11y-audit' ),
				'rules'    => array( 'link-name', 'color-contrast', 'image-alt', 'aria-progressbar-name' ),
				'discover' => array(
					'type'             => 'page_slugs',
					'slugs'            => array( 'news', 'blog', 'aktuelles' ),
					'fallback_archive' => array( 'news', 'post' ),
				),
			),
			array(
				'slug'     => 'cart',
				'label'    => __( 'Cart', 'bb-a11y-audit' ),
				'rules'    => array( 'link-name' ),
				'discover' => array( 'type' => 'wc_cart' ),
			),
			array(
				'slug'     => 'single-product',
				'label'    => __( 'Single Product', 'bb-a11y-audit' ),
				'rules'    => array( 'link-name' ),
				'discover' => array(
					'type'      => 'cpt_single',
					'post_type' => 'product',
				),
			),
			array(
				'slug'     => 'single-projects',
				'label'    => __( 'Single Project', 'bb-a11y-audit' ),
				'rules'    => array( 'nested-interactive' ),
				'discover' => array(
					'type'       => 'cpt_single',
					'post_type'  => 'projects',
					'post_types' => array( 'projects', 'project' ),
				),
			),
			array(
				'slug'     => 'form-checkout',
				'label'    => __( 'Checkout', 'bb-a11y-audit' ),
				'rules'    => array( 'autocomplete-valid' ),
				'discover' => array( 'type' => 'wc_checkout' ),
			),
			array(
				'slug'     => 'archive-press',
				'label'    => __( 'Press archive', 'bb-a11y-audit' ),
				'rules'    => array( 'aria-allowed-attr' ),
				'discover' => array(
					'type'       => 'cpt_archive',
					'post_type'  => 'press',
					'post_types' => array( 'press' ),
				),
			),
			array(
				'slug'     => 'archive-projects',
				'label'    => __( 'Projects archive', 'bb-a11y-audit' ),
				'rules'    => array( 'link-name' ),
				'discover' => array(
					'type'       => 'cpt_archive',
					'post_type'  => 'projects',
					'post_types' => array( 'projects', 'project' ),
				),
			),
			array(
				'slug'     => 'archive',
				'label'    => __( 'Posts archive', 'bb-a11y-audit' ),
				'rules'    => array( 'link-name' ),
				'discover' => array( 'type' => 'posts_archive' ),
			),
		);

		/**
		 * Filter estimate-style coverage targets (slug → discovery mapping).
		 *
		 * @param array $targets Coverage target definitions.
		 */
		return apply_filters( 'bb_a11y_coverage_targets', $targets );
	}

	/**
	 * Resolve a coverage target to a same-site URL (or explain why not).
	 *
	 * @param array $target Target from get_coverage_targets().
	 * @return array{url: ?string, hint: string}
	 */
	public static function resolve_coverage_target( array $target ) {
		$discover = isset( $target['discover'] ) ? $target['discover'] : array();
		$type     = isset( $discover['type'] ) ? $discover['type'] : '';

		switch ( $type ) {
			case 'home':
				return array(
					'url'  => home_url( '/' ),
					'hint' => __( 'Uses the site home URL; pair with the default selector to isolate header/footer.', 'bb-a11y-audit' ),
				);

			case 'front_page':
				if ( 'page' === get_option( 'show_on_front' ) ) {
					$front_id = (int) get_option( 'page_on_front' );
					if ( $front_id ) {
						return array(
							'url'  => get_permalink( $front_id ),
							'hint' => __( 'Static front page from Settings → Reading.', 'bb-a11y-audit' ),
						);
					}
				}
				return array(
					'url'  => home_url( '/' ),
					'hint' => __( 'Reading settings use latest posts as the front page.', 'bb-a11y-audit' ),
				);

			case 'posts_archive':
				if ( 'page' === get_option( 'show_on_front' ) && get_option( 'page_for_posts' ) ) {
					return array(
						'url'  => get_permalink( (int) get_option( 'page_for_posts' ) ),
						'hint' => __( 'Posts page from Settings → Reading.', 'bb-a11y-audit' ),
					);
				}
				return array(
					'url'  => home_url( '/' ),
					'hint' => __( 'No dedicated posts page; home URL used as the posts archive.', 'bb-a11y-audit' ),
				);

			case 'page_slugs':
				$slugs = isset( $discover['slugs'] ) ? (array) $discover['slugs'] : array();
				$page  = self::find_published_page_by_slugs( $slugs );
				if ( $page ) {
					return array(
						'url'  => get_permalink( $page ),
						/* translators: %s: page slug */
						'hint' => sprintf( __( 'Matched published page “%s”.', 'bb-a11y-audit' ), $page->post_name ),
					);
				}
				if ( ! empty( $discover['fallback_archive'] ) ) {
					foreach ( (array) $discover['fallback_archive'] as $archive_pt ) {
						if ( 'post' === $archive_pt ) {
							if ( 'page' === get_option( 'show_on_front' ) && get_option( 'page_for_posts' ) ) {
								return array(
									'url'  => get_permalink( (int) get_option( 'page_for_posts' ) ),
									'hint' => __( 'No matching page; falling back to the posts page (keep slug page-* for estimate mapping).', 'bb-a11y-audit' ),
								);
							}
							continue;
						}
						if ( post_type_exists( $archive_pt ) ) {
							$obj = get_post_type_object( $archive_pt );
							if ( $obj && $obj->has_archive ) {
								$link = get_post_type_archive_link( $archive_pt );
								if ( $link ) {
									return array(
										'url'  => $link,
										/* translators: %s: post type */
										'hint' => sprintf(
											__( 'No matching page; falling back to “%s” archive (Location slug stays estimate-aligned).', 'bb-a11y-audit' ),
											$archive_pt
										),
									);
								}
							}
						}
					}
				}
				return array(
					'url'  => null,
					/* translators: %s: comma-separated slug list */
					'hint' => sprintf(
						__( 'No published page found for slugs: %s. Create/publish one or add the Location manually.', 'bb-a11y-audit' ),
						implode( ', ', $slugs )
					),
				);

			case 'wc_cart':
				if ( function_exists( 'wc_get_cart_url' ) ) {
					$url = wc_get_cart_url();
					if ( $url ) {
						return array(
							'url'  => $url,
							'hint' => __( 'WooCommerce cart URL.', 'bb-a11y-audit' ),
						);
					}
				}
				return array(
					'url'  => null,
					'hint' => __( 'WooCommerce cart unavailable (plugin inactive or cart page unset).', 'bb-a11y-audit' ),
				);

			case 'wc_checkout':
				if ( function_exists( 'wc_get_page_id' ) ) {
					$checkout_id = wc_get_page_id( 'checkout' );
					if ( $checkout_id && $checkout_id > 0 ) {
						return array(
							'url'  => get_permalink( $checkout_id ),
							'hint' => __( 'WooCommerce checkout page.', 'bb-a11y-audit' ),
						);
					}
				}
				return array(
					'url'  => null,
					'hint' => __( 'WooCommerce checkout unavailable (plugin inactive or checkout page unset).', 'bb-a11y-audit' ),
				);

			case 'cpt_single':
				$candidates = self::discover_post_type_candidates( $discover );
				if ( empty( $candidates ) ) {
					$wanted = isset( $discover['post_type'] ) ? $discover['post_type'] : '';
					return array(
						'url'  => null,
						/* translators: %s: post type name */
						'hint' => sprintf( __( 'Post type “%s” is not registered.', 'bb-a11y-audit' ), $wanted ),
					);
				}
				foreach ( $candidates as $post_type ) {
					$latest = get_posts(
						array(
							'post_type'      => $post_type,
							'post_status'    => 'publish',
							'posts_per_page' => 1,
							'orderby'        => 'date',
							'order'          => 'DESC',
						)
					);
					if ( ! empty( $latest ) ) {
						return array(
							'url'  => get_permalink( $latest[0] ),
							/* translators: 1: post title, 2: post type */
							'hint' => sprintf(
								__( 'Using latest published “%1$s” (%2$s).', 'bb-a11y-audit' ),
								$latest[0]->post_title,
								$post_type
							),
						);
					}
				}
				return array(
					'url'  => null,
					/* translators: %s: post type list */
					'hint' => sprintf(
						__( 'No published posts found for: %s', 'bb-a11y-audit' ),
						implode( ', ', $candidates )
					),
				);

			case 'cpt_archive':
				$candidates = self::discover_post_type_candidates( $discover );
				if ( empty( $candidates ) ) {
					$wanted = isset( $discover['post_type'] ) ? $discover['post_type'] : '';
					return array(
						'url'  => null,
						/* translators: %s: post type name */
						'hint' => sprintf( __( 'Post type “%s” is not registered.', 'bb-a11y-audit' ), $wanted ),
					);
				}
				foreach ( $candidates as $post_type ) {
					$obj = get_post_type_object( $post_type );
					if ( $obj && $obj->has_archive ) {
						$link = get_post_type_archive_link( $post_type );
						if ( $link ) {
							return array(
								'url'  => $link,
								/* translators: %s: post type */
								'hint' => sprintf( __( 'Custom post type archive (%s).', 'bb-a11y-audit' ), $post_type ),
							);
						}
					}
					$page = self::find_published_page_by_slugs( array( $post_type, $post_type . '-archive' ) );
					if ( $page ) {
						return array(
							'url'  => get_permalink( $page ),
							'hint' => __( 'CPT has no native archive; matched a page slug instead.', 'bb-a11y-audit' ),
						);
					}
				}
				return array(
					'url'  => null,
					/* translators: %s: post type list */
					'hint' => sprintf(
						__( 'No archive or matching page found for: %s', 'bb-a11y-audit' ),
						implode( ', ', $candidates )
					),
				);
		}

		return array(
			'url'  => null,
			'hint' => __( 'Unknown discovery type.', 'bb-a11y-audit' ),
		);
	}

	/**
	 * @param array $discover Discovery config with post_type and/or post_types.
	 * @return string[] Registered post type names to try, in order.
	 */
	private static function discover_post_type_candidates( array $discover ) {
		$names = array();
		if ( ! empty( $discover['post_types'] ) && is_array( $discover['post_types'] ) ) {
			$names = array_merge( $names, $discover['post_types'] );
		}
		if ( ! empty( $discover['post_type'] ) ) {
			$names[] = $discover['post_type'];
		}
		$names = array_values( array_unique( array_filter( array_map( 'strval', $names ) ) ) );
		return array_values(
			array_filter(
				$names,
				function ( $name ) {
					return post_type_exists( $name );
				}
			)
		);
	}

	/**
	 * @param string[] $slugs Candidate page paths/slugs.
	 * @return WP_Post|null
	 */
	public static function find_published_page_by_slugs( array $slugs ) {
		foreach ( $slugs as $slug ) {
			$slug = trim( (string) $slug, '/' );
			if ( '' === $slug ) {
				continue;
			}

			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $page && 'publish' === $page->post_status ) {
				return $page;
			}

			$matches = get_posts(
				array(
					'name'           => $slug,
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
				)
			);
			if ( ! empty( $matches ) ) {
				return $matches[0];
			}
		}

		return null;
	}

	/**
	 * Human-readable note for CSV/docs: advanced/text-contrast is Deque Pro only.
	 *
	 * @return string
	 */
	public static function unsupported_estimate_rules_note() {
		return __(
			'Estimate line “advanced/text-contrast” is an axe DevTools Pro rule and is not available in open-source axe-core. color-contrast remains the free equivalent for 1.4.3.',
			'bb-a11y-audit'
		);
	}
}
