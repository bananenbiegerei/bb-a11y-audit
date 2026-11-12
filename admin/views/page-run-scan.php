<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$locations       = BB_A11y_Locations::get_all();
$preselected_id  = isset( $_GET['location_id'] ) ? (int) $_GET['location_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap bb-a11y-wrap">
	<h1><?php esc_html_e( 'A11y Audit — Run Scan', 'bb-a11y-audit' ); ?></h1>

	<h2><?php esc_html_e( 'Batch Scan', 'bb-a11y-audit' ); ?></h2>
	<p>
		<label>
			<input type="checkbox" id="bb-a11y-select-all-locations" checked />
			<?php esc_html_e( 'Select all', 'bb-a11y-audit' ); ?>
		</label>
	</p>

	<table class="widefat striped" id="bb-a11y-batch-table">
		<thead>
			<tr>
				<th></th>
				<th><?php esc_html_e( 'Location', 'bb-a11y-audit' ); ?></th>
				<th><?php esc_html_e( 'Selector', 'bb-a11y-audit' ); ?></th>
				<th><?php esc_html_e( 'Status', 'bb-a11y-audit' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $locations ) ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'No locations yet. Add some on the Locations page.', 'bb-a11y-audit' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $locations as $location ) : ?>
				<tr data-location-id="<?php echo esc_attr( $location->id ); ?>">
					<td>
						<input
							type="checkbox"
							class="bb-a11y-batch-check"
							checked
							data-id="<?php echo esc_attr( $location->id ); ?>"
							data-url="<?php echo esc_url( $location->url ); ?>"
							data-selector="<?php echo esc_attr( $location->default_selector ); ?>"
						/>
					</td>
					<td><?php echo esc_html( $location->slug ); ?></td>
					<td><?php echo esc_html( $location->default_selector ? $location->default_selector : '(whole page)' ); ?></td>
					<td class="bb-a11y-batch-row-status">—</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p>
		<button class="button button-primary" id="bb-a11y-scan-selected" <?php disabled( empty( $locations ) ); ?>>
			<?php esc_html_e( 'Scan Selected Locations', 'bb-a11y-audit' ); ?>
		</button>
	</p>

	<div id="bb-a11y-batch-status" role="status"></div>

	<hr />

	<h2><?php esc_html_e( 'Manual / Single Scan', 'bb-a11y-audit' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Use this for a one-off scan with a custom CSS selector that you don\'t want saved as the location\'s default.', 'bb-a11y-audit' ); ?></p>

	<table class="form-table">
		<tr>
			<th><label for="bb-a11y-location-select"><?php esc_html_e( 'Location', 'bb-a11y-audit' ); ?></label></th>
			<td>
				<select id="bb-a11y-location-select">
					<option value=""><?php esc_html_e( '— Select a location —', 'bb-a11y-audit' ); ?></option>
					<?php foreach ( $locations as $location ) : ?>
						<option
							value="<?php echo esc_attr( $location->id ); ?>"
							data-url="<?php echo esc_url( $location->url ); ?>"
							data-selector="<?php echo esc_attr( $location->default_selector ); ?>"
							<?php selected( $preselected_id, $location->id ); ?>
						>
							<?php echo esc_html( $location->slug ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th><label for="bb-a11y-selector-input"><?php esc_html_e( 'CSS Selector (optional)', 'bb-a11y-audit' ); ?></label></th>
			<td><input type="text" id="bb-a11y-selector-input" class="regular-text" placeholder="e.g. .top-menu — leave blank to scan the whole page" /></td>
		</tr>
	</table>

	<p>
		<button class="button button-secondary" id="bb-a11y-load-page" disabled><?php esc_html_e( 'Load Page', 'bb-a11y-audit' ); ?></button>
		<button class="button button-secondary" id="bb-a11y-run-scan" disabled><?php esc_html_e( 'Run Scan', 'bb-a11y-audit' ); ?></button>
		<button class="button button-primary" id="bb-a11y-save-scan" disabled><?php esc_html_e( 'Save Scan', 'bb-a11y-audit' ); ?></button>
	</p>

	<div id="bb-a11y-scan-status" role="status"></div>

	<iframe id="bb-a11y-scan-frame" title="<?php esc_attr_e( 'Page to be scanned', 'bb-a11y-audit' ); ?>"></iframe>

	<div id="bb-a11y-scan-preview"></div>
</div>
