<?php
/**
 * Plugin Name: WPFlat Invoice
 * Description: Invoices, payments, a catalog and recurring billing for your clients, with emailed invoices and a public invoice page. Needs WPFlat Core. Move to WPFlat Finance later and your invoices come with you.
 * Version: 0.4.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Digitalzooks
 * License: GPL-2.0-or-later
 * Text Domain: wpflatinvoice
 */

defined( 'ABSPATH' ) || exit;

define( 'WPFLATINVOICE_VERSION', '0.4.1' );
define( 'WPFLATINVOICE_FILE', __FILE__ );
define( 'WPFLATINVOICE_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPFLATINVOICE_URL', plugin_dir_url( __FILE__ ) );

add_action(
	'plugins_loaded',
	function () {
		if ( ! defined( 'WPFLATCORE_VERSION' ) ) {
			add_action(
				'admin_notices',
				function () {
					if ( current_user_can( 'activate_plugins' ) ) {
						echo '<div class="notice notice-error"><p><strong>WPFlat Invoice</strong> needs the <strong>WPFlat Core</strong> plugin. Install and activate WPFlat Core, then WPFlat Invoice will start.</p></div>';
					}
				}
			);
			return;
		}
		if ( defined( 'WPFLATFINANCE_VERSION' ) ) { // WPFlat Finance already includes everything this plugin does
			add_action(
				'admin_notices',
				function () {
					if ( current_user_can( 'activate_plugins' ) ) {
						echo '<div class="notice notice-info"><p><strong>WPFlat Invoice</strong> is switched off because <strong>WPFlat Finance</strong> is active and already includes invoicing. Your invoices are in WPFlat Finance. You can delete WPFlat Invoice.</p></div>';
					}
				}
			);
			return;
		}
		spl_autoload_register(
			function ( $class ) {
				if ( 0 === strpos( $class, 'WPFlatInvoice\\' ) ) {
					$path = WPFLATINVOICE_DIR . 'includes/' . str_replace( '\\', '/', substr( $class, 14 ) ) . '.php';
					if ( is_readable( $path ) ) {
						require_once $path;
					}
				}
			}
		);
		WPFlatInvoice\Module::init();
	},
	1
);
