<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap bb-a11y-wrap">
	<h1><?php esc_html_e( 'A11y Audit — Locations', 'bb-a11y-audit' ); ?></h1>

	<h2><?php esc_html_e( 'Known Locations', 'bb-a11y-audit' ); ?></h2>
	<table class="widefat striped" id="bb-a11y-locations-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Slug', 'bb-a11y-audit' ); ?></th>
				<th><?php esc_html_e( 'Label', 'bb-a11y-audit' ); ?></th>
				<th><?php esc_html_e( 'URL', 'bb-a11y-audit' ); ?></th>
				<th><?php esc_html_e( 'Default Selector', 'bb-a11y-audit' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'bb-a11y-audit' ); ?></th>
			</tr>
		</thead>
		<tbody id="bb-a11y-locations-body">
			<tr><td colspan="5"><?php esc_html_e( 'Loading…', 'bb-a11y-audit' ); ?></td></tr>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Add Location', 'bb-a11y-audit' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><label for="bb-a11y-new-slug"><?php esc_html_e( 'Slug', 'bb-a11y-audit' ); ?></label></th>
			<td><input type="text" id="bb-a11y-new-slug" placeholder="e.g. page-about-us" /></td>
		</tr>
		<tr>
			<th><label for="bb-a11y-new-label"><?php esc_html_e( 'Label', 'bb-a11y-audit' ); ?></label></th>
			<td><input type="text" id="bb-a11y-new-label" placeholder="e.g. About Us" /></td>
		</tr>
		<tr>
			<th><label for="bb-a11y-new-url"><?php esc_html_e( 'URL', 'bb-a11y-audit' ); ?></label></th>
			<td>
				<input type="url" id="bb-a11y-new-url" placeholder="<?php echo esc_attr( home_url( '/about-us/' ) ); ?>" class="regular-text" />
				<p class="description"><?php esc_html_e( 'Must be a URL on this site — scanning works by loading the page in a same-origin iframe, so other hosts cannot be scanned.', 'bb-a11y-audit' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="bb-a11y-new-selector"><?php esc_html_e( 'Default Selector', 'bb-a11y-audit' ); ?></label></th>
			<td><input type="text" id="bb-a11y-new-selector" placeholder="optional, e.g. .top-menu" /></td>
		</tr>
	</table>
	<p><button class="button button-primary" id="bb-a11y-add-location"><?php esc_html_e( 'Add Location', 'bb-a11y-audit' ); ?></button></p>

	<h2><?php esc_html_e( 'Auto-suggest from Templates', 'bb-a11y-audit' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Includes an estimate-aligned coverage checklist (header/footer with selectors, Woo cart/checkout/product, named pages like page-about-us / page-news, CPT archives). Add every “ready” row before running a full scan so CSV Location values match the sold template names.', 'bb-a11y-audit' ); ?>
	</p>
	<p><button class="button" id="bb-a11y-fetch-suggestions"><?php esc_html_e( 'Find Suggestions', 'bb-a11y-audit' ); ?></button></p>
	<div id="bb-a11y-suggestions"></div>
</div>
