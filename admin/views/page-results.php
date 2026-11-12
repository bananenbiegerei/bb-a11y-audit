<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap bb-a11y-wrap">
	<h1><?php esc_html_e( 'A11y Audit — Results', 'bb-a11y-audit' ); ?></h1>

	<p>
		<a
			class="button button-primary"
			href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bb_a11y_export_csv' ), 'bb_a11y_export_csv' ) ); ?>"
		>
			<?php esc_html_e( 'Export CSV', 'bb-a11y-audit' ); ?>
		</a>
	</p>

	<table class="widefat striped" id="bb-a11y-results-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Location', 'bb-a11y-audit' ); ?></th>
				<th><?php esc_html_e( 'Rule ID', 'bb-a11y-audit' ); ?></th>
				<th><?php esc_html_e( 'Impact', 'bb-a11y-audit' ); ?></th>
				<th><?php esc_html_e( 'WCAG', 'bb-a11y-audit' ); ?></th>
				<th><?php esc_html_e( 'Description', 'bb-a11y-audit' ); ?></th>
				<th><?php esc_html_e( 'Anmerkung', 'bb-a11y-audit' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'bb-a11y-audit' ); ?></th>
			</tr>
		</thead>
		<tbody id="bb-a11y-results-body">
			<tr><td colspan="7"><?php esc_html_e( 'Loading…', 'bb-a11y-audit' ); ?></td></tr>
		</tbody>
	</table>
</div>
