<?php
namespace WPFlatInvoice;

use WPFlatCore\Services\Demo as Core_Demo;

defined( 'ABSPATH' ) || exit;

/** WPFlat Invoice's part of the sample data: catalog, prices on the sample project, invoices with payments, and expenses. */
class Demo {

	public static function init() {
		add_action( 'wpflatcore_demo_load', array( __CLASS__, 'load' ), 20 );
	}

	public static function load( $ctx ) {
		global $wpdb;
		if ( ! \WPFlatCore\Registry::enabled( 'invoice' ) ) {
			return;
		}
		$web = $ctx['web'];
		$mkt = $ctx['marketing'];
		$c   = $ctx['clients'];
		foreach ( $c as $id ) {
			Core_Demo::add( 'wpflatinvoice_client_billing', array( 'client_id' => $id, 'currency' => 'NGN' ), 'client_id' );
		}
		Core_Demo::add( 'wpflatinvoice_catalog', array( 'name' => 'Website design (Sample)', 'type' => 'service', 'kind' => 'one_off', 'pricing' => 'fixed', 'default_price' => 650000, 'default_cost' => 250000, 'default_currency' => 'NGN', 'department_id' => $web ) );
		Core_Demo::add( 'wpflatinvoice_catalog', array( 'name' => 'Website hosting (Sample)', 'type' => 'service', 'kind' => 'recurring', 'default_billing_period' => 'annual', 'pricing' => 'fixed', 'default_price' => 85000, 'default_cost' => 30000, 'default_currency' => 'NGN', 'department_id' => $web ) );
		Core_Demo::add( 'wpflatinvoice_catalog', array( 'name' => 'Branded mug (Sample)', 'type' => 'product', 'kind' => 'one_off', 'pricing' => 'unit', 'unit_label' => 'mug', 'default_price' => 3500, 'default_cost' => 1800, 'default_currency' => 'NGN', 'department_id' => $mkt ) );
		$invs = array(
			array( $c[0], 'SAMPLE-0001', 'Website design — deposit', 325000, 325000, -30, -16, $web ),
			array( $c[1], 'SAMPLE-0002', 'Monthly retainer', 150000, 60000, -12, -2, $mkt ),
			array( $c[2], 'SAMPLE-0003', 'Website hosting (annual)', 85000, 0, -3, 11, $web ),
			array( $c[0], 'SAMPLE-0004', 'Website design — balance', 325000, 0, 0, 14, $web ),
		);
		foreach ( $invs as $v ) {
			$id = Core_Demo::add( 'wpflatinvoice_invoices', array( 'client_id' => $v[0], 'invoice_number' => $v[1], 'title' => $v[2], 'amount' => $v[3], 'subtotal' => $v[3], 'currency' => 'NGN', 'issue_date' => Core_Demo::day( $v[5] ), 'due_date' => Core_Demo::day( $v[6] ), 'status' => 'sent', 'sent_at' => current_time( 'mysql' ), 'discount_type' => 'none', 'department_id' => $v[7], 'notes' => 'Sample data' ) );
			Core_Demo::add( 'wpflatinvoice_invoice_items', array( 'invoice_id' => $id, 'source_type' => 'manual', 'kind' => 'fixed', 'description' => $v[2], 'quantity' => 1, 'unit_price' => $v[3], 'amount' => $v[3], 'department_id' => $v[7] ) );
			if ( $v[4] > 0 ) {
				Core_Demo::add( 'wpflatinvoice_payments', array( 'invoice_id' => $id, 'client_id' => $v[0], 'amount' => $v[4], 'currency' => 'NGN', 'paid_on' => Core_Demo::day( $v[5] + 3 ), 'reference' => 'Sample payment', 'department_id' => $v[7] ) );
			}
			Services\Invoices::refresh_status( $id );
		}
	}
}
