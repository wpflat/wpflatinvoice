<?php
namespace WPFlatInvoice;

use WPFlatCore\Registry;

defined( 'ABSPATH' ) || exit;

/** WPFlat Invoice's connection to WPFlat Core: it describes itself, then hooks into the shared places. */
class Module {

	public static function init() {
		Install::maybe_upgrade();
		add_action( 'wpflatcore_register', array( __CLASS__, 'register' ) );
		add_action( 'wpflatcore_register_terms', array( __CLASS__, 'terms' ) );
		add_action( 'wpflatcore_register_resources', array( Resources_Def::class, 'register' ) );
		add_action( 'wpflatcore_register_link_types', array( Integrations::class, 'link_types' ) );
		add_action( 'wpflatcore_register_notifications', array( Notify::class, 'types' ) );
		add_action( 'wpflatcore_register_portal', array( Portal_Page::class, 'register' ) );
		add_action( 'wpflatcore_register_routes', array( Routes::class, 'register' ) );
		add_filter( 'wpflatcore_lookup_defaults', array( __CLASS__, 'lookups' ) );
		add_action( 'wpflatcore_daily', array( __CLASS__, 'daily' ), 10 );
		Integrations::init();
		Notify::init();
		Services\Reminders::init();
		Demo::init();
		if ( Registry::enabled( 'invoice' ) || ! did_action( 'wpflatcore_ready' ) ) {
			Public_Invoice::init();
		}
	}

	public static function register() {
		wpflatcore_register_module(
			array(
				'id'            => 'invoice',
				'label'         => 'Invoices',
				'version'       => WPFLATINVOICE_VERSION,
				'requires_core' => '0.6.0',
				'desc'          => 'Invoices, payments, a catalog and recurring billing for your clients, with emailed invoices and a public invoice page.',
				'icon'          => 'invoice',
				'route'         => '/billing',
				'routes'        => array( 'billing', 'invoices', 'catalog', 'renewals', 'charges' ),
				'cap'           => 'wpflatinvoice_billing',
				'caps'          => array( 'wpflatinvoice_billing' ),
				'resources'     => array( 'catalog', 'client-services', 'renewals', 'charges', 'invoices', 'payments' ),
				'tables'        => array_map( function ( $t ) {
					return 'wpflatinvoice_' . $t;
				}, Schema::OWN ),
				'optional_tables' => array( 'wpflatinvoice_email_log' ),
				'options'       => array( 'wpflatinvoice_settings', 'wpflatinvoice_invoice_checks' ),
				'attachment_columns' => array( 'wpflatinvoice_invoices' => array( 'attachment_id' ) ),
				'refs'          => array(
					'wpflatinvoice_client_services'       => array( 'client_id' => 'wpflat_clients', 'service_id' => 'wpflatinvoice_catalog' ),
					'wpflatinvoice_renewals'              => array( 'client_service_id' => 'wpflatinvoice_client_services', 'client_id' => 'wpflat_clients' ),
					'wpflatinvoice_charges'               => array( 'client_id' => 'wpflat_clients' ),
					'wpflatinvoice_invoices'              => array( 'client_id' => 'wpflat_clients' ),
					'wpflatinvoice_invoice_items'         => array( 'invoice_id' => 'wpflatinvoice_invoices' ),
					'wpflatinvoice_invoice_installments'  => array( 'invoice_id' => 'wpflatinvoice_invoices' ),
					'wpflatinvoice_payments'              => array( 'invoice_id' => 'wpflatinvoice_invoices' ),
					'wpflatinvoice_client_billing'        => array( 'client_id' => 'wpflat_clients' ),
				),
				'file_kinds'    => array(),
				'public_paths'  => array( 'invoice' ),
				'public_params' => array( 'bd_invoice' ),
				'roles'         => array(
					'wpflat_invoice_team' => array( 'label' => 'WPFlat Invoice Team', 'desc' => 'Creates and sends invoices, records payments and manages recurring billing.', 'caps' => array( 'wpflatinvoice_billing' ) ),
				),
				'client_manager_caps' => array( 'wpflatinvoice_billing' ),
				'manager_grants'      => array(
					'billing' => array( 'label' => 'Billing & invoices', 'caps' => array( 'wpflatinvoice_billing' ) ),
				),
				'menu'          => array(
					array( 'label' => 'Billing', 'route' => '/billing' ),
					array( 'label' => 'Invoices', 'route' => '/billing/invoices' ),
					array( 'label' => 'Catalog', 'route' => '/catalog' ),
				),
				'assets'        => array( 'script' => 'wpflatinvoice.js', 'style' => 'wpflatinvoice.css' ),
				'assets_dir'    => WPFLATINVOICE_DIR . 'build',
				'wp'            => array( 'dashicons-media-spreadsheet', 28 ),
			)
		);
	}

	public static function terms() {
		\WPFlatCore\Terminology::register( 'invoice', 'Invoice', 'Invoices' );
		\WPFlatCore\Terminology::register( 'catalog', 'Catalog item', 'Catalog' );
	}

	/** Lists WPFlat Invoice keeps in WPFlat Core's lists table (billing periods and how it's billed). */
	public static function lookups( $d ) {
		$d['billing_period']    = array(
			array( 'none', 'One-time', 0, '' ),
			array( 'monthly', 'Monthly', 1, '' ),
			array( 'quarterly', 'Quarterly', 3, '' ),
			array( 'semiannual', 'Semi-annually', 6, '' ),
			array( 'annual', 'Annually', 12, '' ),
			array( 'custom', 'Custom', 0, '' ),
		);
		$d['billing_treatment'] = array(
			array( 'included_in_retainer', 'Included in retainer', 0, '#2563eb' ),
			array( 'separately_billed', 'Billed separately', 0, '#16a34a' ),
			array( 'cost_only', 'Not billed (our cost)', 0, '#d97706' ),
			array( 'no_charge', 'Not billed (free)', 0, '#6b7280' ),
			array( 'custom', 'Custom', 0, '#7c3aed' ),
		);
		return $d;
	}

	/** Roll invoice statuses forward and flag renewals that are coming up. */
	public static function daily() {
		global $wpdb;
		if ( ! Registry::enabled( 'invoice' ) || \WPFlatCore\Backup\Importer::locked() ) {
			return;
		}
		$inv = Schema::table( 'invoices' );
		foreach ( (array) $wpdb->get_col( "SELECT id FROM $inv WHERE status IN ('sent','due','partially_paid','overdue')" ) as $id ) { // phpcs:ignore WordPress.DB
			Services\Invoices::refresh_status( (int) $id );
		}
		$ren  = Schema::table( 'renewals' );
		$days = (int) Settings::get( 'due_soon_days' );
		$soon = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . " +$days days" ) );
		$wpdb->query( $wpdb->prepare( "UPDATE $ren SET status='due' WHERE status='upcoming' AND due_date IS NOT NULL AND due_date <= %s", $soon ) ); // phpcs:ignore WordPress.DB
	}
}
