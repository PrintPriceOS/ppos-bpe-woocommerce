<?php
/**
 * Uninstall handler — runs when the plugin is deleted via WordPress admin.
 *
 * @package PrintPricePro_BPE
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Options.
delete_option( 'ppp_bpe_options' );
delete_option( 'ppp_bpe_base_product_id' );
delete_option( 'ppp_bpe_license_data' );
delete_option( 'ppp_bpe_usage_data' );

// Scheduled events.
wp_clear_scheduled_hook( 'ppp_bpe_daily_license_check' );

// Uploaded PDF files.
$wp_upload  = wp_upload_dir();
$upload_dir = $wp_upload['basedir'] . '/ppp-bpe-files';
if ( is_dir( $upload_dir ) ) {
	ppp_bpe_delete_directory( $upload_dir );
}

// Order meta and item meta are intentionally preserved so that
// existing WooCommerce orders retain their print specification
// history even after the plugin is removed.

/**
 * Recursively deletes a directory and its contents.
 *
 * @param string $dir Absolute path to the directory.
 */
function ppp_bpe_delete_directory( string $dir ): void {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$items = array_diff( scandir( $dir ), array( '.', '..' ) );
	foreach ( $items as $item ) {
		$path = $dir . '/' . $item;
		if ( is_dir( $path ) ) {
			ppp_bpe_delete_directory( $path );
		} else {
			wp_delete_file( $path );
		}
	}
	rmdir( $dir );
}
