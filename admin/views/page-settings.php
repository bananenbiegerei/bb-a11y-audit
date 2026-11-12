<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$profile = BB_A11y_Scan_Config::get_profile();
$updated = isset( $_GET['updated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap bb-a11y-wrap">
	<h1><?php esc_html_e( 'A11y Audit — Settings', 'bb-a11y-audit' ); ?></h1>

	<?php if ( $updated ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'bb-a11y-audit' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="bb_a11y_save_settings" />
		<?php wp_nonce_field( 'bb_a11y_save_settings' ); ?>

		<h2><?php esc_html_e( 'Scan profile', 'bb-a11y-audit' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Controls which axe-core tags and rules run. The default WCAG profile matches sold estimate-style audits and keeps landmark/region MODERATE noise off.', 'bb-a11y-audit' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Profile', 'bb-a11y-audit' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input
								type="radio"
								name="bb_a11y_scan_profile"
								value="<?php echo esc_attr( BB_A11y_Scan_Config::PROFILE_WCAG ); ?>"
								<?php checked( $profile, BB_A11y_Scan_Config::PROFILE_WCAG ); ?>
							/>
							<strong><?php esc_html_e( 'WCAG focused (default)', 'bb-a11y-audit' ); ?></strong>
							— <?php esc_html_e( 'wcag2a + wcag2aa + wcag21aa. Disables best-practice landmark/region/heading-structure rules.', 'bb-a11y-audit' ); ?>
						</label>
						<br /><br />
						<label>
							<input
								type="radio"
								name="bb_a11y_scan_profile"
								value="<?php echo esc_attr( BB_A11y_Scan_Config::PROFILE_FULL ); ?>"
								<?php checked( $profile, BB_A11y_Scan_Config::PROFILE_FULL ); ?>
							/>
							<strong><?php esc_html_e( 'Full (include best-practice)', 'bb-a11y-audit' ); ?></strong>
							— <?php esc_html_e( 'Previous behaviour: also runs best-practice rules such as region, landmark-*, heading-order, page-has-heading-one.', 'bb-a11y-audit' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Estimate rule coverage', 'bb-a11y-audit' ); ?></h2>
		<p>
			<?php esc_html_e( 'Open-source axe-core can surface these estimate-aligned Rule IDs when the matching markup is present on a scanned Location:', 'bb-a11y-audit' ); ?>
		</p>
		<ul style="list-style: disc; margin-left: 1.5em;">
			<?php foreach ( BB_A11y_Scan_Config::ESTIMATE_RULE_IDS as $rule_id ) : ?>
				<li><code><?php echo esc_html( $rule_id ); ?></code></li>
			<?php endforeach; ?>
		</ul>
		<p class="description"><?php echo esc_html( BB_A11y_Scan_Config::unsupported_estimate_rules_note() ); ?></p>

		<p class="description">
			<?php esc_html_e( 'Location slugs for estimate templates are managed under Locations → Find Suggestions → Coverage checklist. Override discovery via the bb_a11y_coverage_targets filter if a site uses different page slugs.', 'bb-a11y-audit' ); ?>
		</p>

		<?php submit_button( __( 'Save settings', 'bb-a11y-audit' ) ); ?>
	</form>
</div>
