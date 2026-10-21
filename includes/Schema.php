<?php
namespace WPFlatInvoice;

defined( 'ABSPATH' ) || exit;

/**
 * Table names. WPFlat Invoice's own tables are wpflatinvoice_*; the code still asks for the short old names, and this maps
 * them: money tables to wpflatinvoice_*, Core's to wpflat_*, WPFlat Tasks' to wpflattasks_* (only when WPFlat Tasks is on).
 */
class Schema {

	/** WPFlat Invoice's own tables (short names). */
	const OWN = array( 'catalog', 'client_services', 'renewals', 'charges', 'invoices', 'invoice_items', 'invoice_installments', 'payments', 'email_log', 'client_billing' );

	const CORE = array( 'clients' => 'wpflat_clients', 'brands' => 'wpflat_departments', 'departments' => 'wpflat_departments', 'client_brands' => 'wpflat_client_departments', 'client_contacts' => 'wpflat_client_contacts', 'team_members' => 'wpflat_team_members', 'activity' => 'wpflat_activity', 'lookups' => 'wpflat_lookups', 'threads' => 'wpflat_threads', 'messages' => 'wpflat_messages' );

	public static function table( $name ) {
		global $wpdb;
		if ( 'services' === $name ) {
			$name = 'catalog';
		}
		if ( in_array( $name, self::OWN, true ) ) {
			return $wpdb->prefix . 'wpflatinvoice_' . $name;
		}
		if ( isset( self::CORE[ $name ] ) ) {
			return $wpdb->prefix . self::CORE[ $name ];
		}
		return $wpdb->prefix . $name;
	}

}
