<?php
/** Removes WPFlat Invoice data on uninstall, only when "Delete all WPFlat data when the plugin is deleted" is on in WPFlat Core's settings. */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$core = get_option( 'wpflatcore_settings', array() );
if ( empty( $core['delete_on_uninstall'] ) ) {
	return;
}

foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'wpflatinvoice_' ) . '%' ) ) as $table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' ); // phpcs:ignore WordPress.DB
}
foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s", $wpdb->esc_like( 'wpflatinvoice_' ) . '%' ) ) as $opt ) { // phpcs:ignore WordPress.DB
	delete_option( $opt );
}

