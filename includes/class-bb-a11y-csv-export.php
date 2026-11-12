<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BB_A11y_Csv_Export {

	public function register() {
		add_action( 'admin_post_bb_a11y_export_csv', array( $this, 'handle_export_request' ) );
	}

	public function handle_export_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'bb-a11y-audit' ), 403 );
		}

		check_admin_referer( 'bb_a11y_export_csv' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="a11y-audit-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );

		// UTF-8 BOM so Excel renders umlauts correctly instead of mangling them.
		fwrite( $out, "\xEF\xBB\xBF" );

		fputcsv( $out, array( 'Location', 'Rule ID', 'Impact', 'WCAG', 'Description', 'Anmerkung' ), ';' );

		foreach ( BB_A11y_Scans::get_merged_results() as $row ) {
			if ( 'pending' === $row['status'] ) {
				fputcsv( $out, array( $row['location']->slug, '', '', '', '', '' ), ';' );
				continue;
			}

			if ( 'pass' === $row['status'] ) {
				fputcsv(
					$out,
					array(
						$row['location']->slug,
						'✓ PASS',
						'',
						'',
						'No accessibility issues found',
						$row['scan']->note,
					),
					';'
				);
				continue;
			}

			$violation = $row['violation'];
			$count     = (int) $violation->instance_count;
			$desc      = $count . ' instance' . ( 1 === $count ? '' : 's' ) . ' - ' . $violation->description;

			fputcsv(
				$out,
				array(
					$row['location']->slug,
					$violation->rule_id,
					$violation->impact ? strtoupper( $violation->impact ) : '',
					$violation->wcag_sc,
					$desc,
					$violation->note,
				),
				';'
			);
		}

		fclose( $out );
		exit;
	}
}
