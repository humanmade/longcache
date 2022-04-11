<?php

namespace Longcache\Admin;

use Altis\Cloud;
use Exception;

const MENU_SLUG = 'longcache';

/**
 * Bootstrap function for all admin functions.
 *
 * @return void
 */
function bootstrap() : void {
	add_action( 'admin_menu', __NAMESPACE__ . '\\register_admin_page' );
	add_action( 'admin_init', __NAMESPACE__ . '\\check_on_invalidate_urls_submit' );
}


function register_admin_page() : void {
	add_submenu_page(
		'options-general.php',
		_x( 'Longcache', 'settings page title', 'longcache' ),
		_x( 'Longcache', 'settings menu title', 'longcache' ),
		'manage_options',
		MENU_SLUG,
		__NAMESPACE__ . '\\render_settings_page'
	);
}

function render_settings_page() : void {
	settings_errors( 'longcache' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'longcache' );
			do_settings_sections( 'longcache' );
			// output save settings button
			// submit_button( __( 'Save Changes', 'longcache' ) );
			?>
		</form>

		<h1><?php _( 'Invalidate URLs', 'longcache' ) ?></h1>
		<form method="post">
			<label>
				URLs to invalidate (one per line.)
				<textarea class="large-text code" rows=10 name="longcache_urls"></textarea>
			</label>
			<p class="description">
				Use <code>*</code> as a wildcard, wildcards can only be at the end of a URL. A maximum of <?php echo esc_html( Cloud\PATHS_INVALIDATION_LIMIT ) ?> absolute URLs or <?php echo esc_html( Cloud\WILDCARD_INVALIDATION_LIMIT ) ?> wildcard URLs can be issued per request.
			</p>
			<?php
			wp_nonce_field( 'longcache.invalidate-urls' );
			submit_button( __( 'Invalidate', 'longcache' ) );
			?>
		</form>
	</div>
	<?php
}

/**
 * Check if the invalidate URLs form was submitted.
 *
 * @return void
 */
function check_on_invalidate_urls_submit() {
	if ( isset( $_POST['longcache_urls'] ) && check_admin_referer( 'longcache.invalidate-urls' ) ) {
		$urls = array_filter( array_map( 'sanitize_text_field', array_map( 'trim', explode( "\n", $_POST['longcache_urls'] ) ) ) );

		try {
			$result = Cloud\purge_cdn_paths( $urls );
		} catch ( Exception $e ) {
			$result = false;
		}

		if ( $result === true ) {
			add_settings_error( 'logcache', 'invalidated', __( 'Invalidate request successful.'), 'success' );
		} else {
			add_settings_error( 'logcache', 'invalidated', __( 'There was a problem issueing the invalidation request.'), 'error' );
		}
	}
}
