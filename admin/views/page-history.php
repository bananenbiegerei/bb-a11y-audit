<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$locations = BB_A11y_Locations::get_all();
$location_id = isset( $_GET['location_id'] ) ? (int) $_GET['location_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap bb-a11y-wrap">
	<h1><?php esc_html_e( 'A11y Audit — History', 'bb-a11y-audit' ); ?></h1>

	<form method="get">
		<input type="hidden" name="page" value="bb-a11y-audit-history" />
		<label for="bb-a11y-history-location"><?php esc_html_e( 'Location', 'bb-a11y-audit' ); ?></label>
		<select name="location_id" id="bb-a11y-history-location" onchange="this.form.submit()">
			<option value=""><?php esc_html_e( '— Select a location —', 'bb-a11y-audit' ); ?></option>
			<?php foreach ( $locations as $location ) : ?>
				<option value="<?php echo esc_attr( $location->id ); ?>" <?php selected( $location_id, $location->id ); ?>>
					<?php echo esc_html( $location->slug ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</form>

	<?php if ( $location_id ) : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'bb-a11y-audit' ); ?></th>
					<th><?php esc_html_e( 'Selector', 'bb-a11y-audit' ); ?></th>
					<th><?php esc_html_e( 'axe-core Version', 'bb-a11y-audit' ); ?></th>
					<th><?php esc_html_e( 'Violations', 'bb-a11y-audit' ); ?></th>
					<th><?php esc_html_e( 'Passes', 'bb-a11y-audit' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$history = BB_A11y_Scans::get_history( $location_id );
				if ( empty( $history ) ) :
					?>
					<tr><td colspan="5"><?php esc_html_e( 'No scans yet for this location.', 'bb-a11y-audit' ); ?></td></tr>
					<?php
				endif;
				foreach ( $history as $scan ) :
					?>
					<tr>
						<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', $scan->created_at ) ); ?></td>
						<td><?php echo esc_html( $scan->selector ? $scan->selector : '(whole page)' ); ?></td>
						<td><?php echo esc_html( $scan->axe_version ); ?></td>
						<td><?php echo esc_html( $scan->violation_count ); ?></td>
						<td><?php echo esc_html( $scan->pass_count ); ?></td>
					</tr>
					<?php
				endforeach;
				?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
