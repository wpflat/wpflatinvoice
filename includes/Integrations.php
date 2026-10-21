<?php
namespace WPFlatInvoice;

use WPFlatCore\Links;
use WPFlatCore\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * What WPFlat Invoice adds to a client's record: the billing currency and retainer. It keeps them in its own table and shows them to people
 * who may bill, so WPFlat Core stays free of money.
 */
class Integrations {

	public static function init() {
		add_filter( 'wpflatcore_decorate_clients', array( __CLASS__, 'clients' ) );
		add_action( 'wpflatcore_saved_clients', array( __CLASS__, 'client_saved' ), 10, 3 );
		add_action( 'wpflatcore_client_deleted', array( __CLASS__, 'client_deleted' ) );
		add_filter( 'wpflatcore_search', array( __CLASS__, 'search' ), 10, 2 );
		add_filter( 'wpflatcore_message_contexts', array( __CLASS__, 'message_contexts' ), 10, 2 );
		add_filter( 'wpflatcore_message_context_name', array( __CLASS__, 'message_context_name' ), 10, 3 );
		add_filter( 'wpflatcore_home', array( __CLASS__, 'home' ) );
		add_filter( 'wpflatcore_meta', array( __CLASS__, 'meta' ) );
	}

	private static function fin() {
		return current_user_can( 'manage_options' ) || current_user_can( 'wpflatinvoice_billing' );
	}

	private static function on() {
		return Registry::enabled( 'invoice' );
	}

	private static function upsert( $table, $key, $id, array $data ) {
		global $wpdb;
		$tbl = Schema::table( $table );
		$id  = (int) $id;
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT $key FROM $tbl WHERE $key=%d", $id ) ) ) { // phpcs:ignore WordPress.DB
			$wpdb->update( $tbl, $data, array( $key => $id ) );
		} else {
			$wpdb->insert( $tbl, array( $key => $id ) + $data );
		}
	}

	private static function in( array $rows ) {
		return implode( ',', array_map( 'intval', wp_list_pluck( $rows, 'id' ) ) );
	}

	/* ---- clients: billing currency and retainer ---- */

	public static function clients( $rows ) {
		global $wpdb;
		if ( ! self::on() || ! $rows ) {
			return $rows;
		}
		$by = array();
		foreach ( (array) $wpdb->get_results( 'SELECT * FROM ' . Schema::table( 'client_billing' ) . ' WHERE client_id IN (' . self::in( $rows ) . ')', ARRAY_A ) as $b ) { // phpcs:ignore WordPress.DB
			$by[ (int) $b['client_id'] ] = $b;
		}
		$def = Settings::get( 'default_currency' );
		foreach ( $rows as &$r ) {
			$b                   = isset( $by[ (int) $r['id'] ] ) ? $by[ (int) $r['id'] ] : array();
			$r['currency']       = ! empty( $b['currency'] ) ? $b['currency'] : $def;
			$r['retainer_amount'] = isset( $b['retainer_amount'] ) ? (float) $b['retainer_amount'] : 0;
			$r['retainer_period'] = isset( $b['retainer_period'] ) ? $b['retainer_period'] : '';
		}
		return $rows;
	}

	public static function client_saved( $row, $existing, $params ) {
		if ( ! self::on() ) {
			return;
		}
		$data = array();
		if ( isset( $params['currency'] ) ) {
			$c               = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) $params['currency'] ), 0, 3 ) );
			$data['currency'] = '' !== $c ? $c : Settings::get( 'default_currency' );
		} elseif ( ! $existing ) {
			$data['currency'] = Settings::get( 'default_currency' );
		}
		if ( isset( $params['retainer_amount'] ) ) {
			$data['retainer_amount'] = round( (float) str_replace( ',', '', (string) $params['retainer_amount'] ), 2 );
		}
		if ( isset( $params['retainer_period'] ) ) {
			$data['retainer_period'] = sanitize_key( $params['retainer_period'] );
		}
		if ( $data ) {
			self::upsert( 'client_billing', 'client_id', $row['id'], $data );
		}
	}

	public static function client_deleted( $client_id ) {
		global $wpdb;
		$wpdb->delete( Schema::table( 'client_billing' ), array( 'client_id' => (int) $client_id ) );
	}

	/* ---- team: pay ---- */

	const PAY_FIELDS = array( 'salary_amount', 'salary_period', 'currency', 'bank_name', 'account_name', 'account_number' );

	/* ---- WPFlat Tasks: a project's price, a phase's budget, a task's pay ---- */

	/* ---- link types ---- */

	public static function link_types() {
		Links::register_type( 'invoice', array( 'module' => 'invoice', 'label' => 'Invoice', 'plural' => 'Invoices', 'resolve' => array( __CLASS__, 'resolve_invoice' ), 'search' => array( __CLASS__, 'search_invoices' ) ) );
		Links::register_type( 'catalog_item', array( 'module' => 'invoice', 'label' => 'Catalog item', 'plural' => 'Catalog', 'resolve' => array( __CLASS__, 'resolve_catalog' ), 'search' => array( __CLASS__, 'search_catalog' ) ) );
	}

	public static function resolve_invoice( $id ) {
		global $wpdb;
		$i = $wpdb->get_row( $wpdb->prepare( 'SELECT id, invoice_number, title, status FROM ' . Schema::table( 'invoices' ) . ' WHERE id=%d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $i ? array( 'title' => $i['invoice_number'] . ( $i['title'] ? ' — ' . $i['title'] : '' ), 'url' => '#/billing/invoices/' . (int) $id, 'sub' => ucfirst( str_replace( '_', ' ', $i['status'] ) ) ) : null;
	}

	public static function search_invoices( $q, $n ) {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( $q ) . '%';
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, invoice_number, title, status FROM ' . Schema::table( 'invoices' ) . ' WHERE invoice_number LIKE %s OR title LIKE %s ORDER BY id DESC LIMIT %d', $like, $like, $n ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return array_map( function ( $r ) {
			return array( 'id' => (int) $r['id'], 'title' => $r['invoice_number'] . ( $r['title'] ? ' — ' . $r['title'] : '' ), 'sub' => $r['status'] );
		}, (array) $rows );
	}

	public static function resolve_catalog( $id ) {
		global $wpdb;
		$c = $wpdb->get_row( $wpdb->prepare( 'SELECT id, name, type FROM ' . Schema::table( 'catalog' ) . ' WHERE id=%d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $c ? array( 'title' => $c['name'], 'url' => '#/catalog', 'sub' => ucfirst( $c['type'] ) ) : null;
	}

	public static function search_catalog( $q, $n ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, name, type FROM ' . Schema::table( 'catalog' ) . ' WHERE name LIKE %s ORDER BY name LIMIT %d', '%' . $wpdb->esc_like( $q ) . '%', $n ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return array_map( function ( $r ) {
			return array( 'id' => (int) $r['id'], 'title' => $r['name'], 'sub' => $r['type'] );
		}, (array) $rows );
	}


	/* ---- the browser app: WPFlat Invoice's settings, and money on the department pages ---- */

	/** WPFlat Invoice's settings reach the browser app in meta.settings: the safe ones for everybody, the full set for people who bill. */
	public static function meta( $m ) {
		if ( ! self::on() ) {
			return $m;
		}
		$own = Settings::get();
		$pub = array();
		foreach ( array( 'currencies', 'default_currency', 'base_currency', 'tax_label' ) as $k ) {
			$pub[ $k ] = $own[ $k ];
		}
		if ( current_user_can( 'wpflatinvoice_billing' ) || current_user_can( 'manage_options' ) ) {
			$pub = array_intersect_key( $own, Settings::defaults() );
		}
		$m['settings'] = array_merge( isset( $m['settings'] ) ? (array) $m['settings'] : array(), $pub );
		return $m;
	}

	/* ---- search, messages, Home ---- */

	public static function search( $out, $q ) {
		global $wpdb;
		if ( ! self::on() || ( ! current_user_can( 'wpflatinvoice_billing' ) && ! current_user_can( 'manage_options' ) ) ) {
			return $out;
		}
		$like = '%' . $wpdb->esc_like( $q ) . '%';
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT i.id, i.invoice_number, i.title, c.name AS client_name FROM ' . Schema::table( 'invoices' ) . ' i LEFT JOIN ' . Schema::table( 'clients' ) . ' c ON c.id=i.client_id WHERE i.invoice_number LIKE %s OR i.title LIKE %s ORDER BY i.id DESC LIMIT 6', $like, $like ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
			$out[] = array( 'type' => 'invoice', 'kind' => \WPFlatCore\Terminology::label( 'invoice' ), 'id' => (int) $r['id'], 'label' => $r['invoice_number'], 'sub' => trim( $r['title'] . ' · ' . $r['client_name'], ' ·' ), 'route' => '/billing/invoices/' . (int) $r['id'] );
		}
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, name, type FROM ' . Schema::table( 'catalog' ) . ' WHERE name LIKE %s ORDER BY name LIMIT 5', $like ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
			$out[] = array( 'type' => 'catalog', 'kind' => \WPFlatCore\Terminology::label( 'catalog' ), 'id' => (int) $r['id'], 'label' => $r['name'], 'sub' => $r['type'], 'route' => '/catalog' );
		}
		return $out;
	}

	public static function message_contexts( $list, $client_id ) {
		global $wpdb;
		if ( ! self::on() ) {
			return $list;
		}
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, invoice_number, title FROM ' . Schema::table( 'invoices' ) . " WHERE client_id=%d AND status NOT IN ('draft','cancelled') ORDER BY id DESC LIMIT 30", (int) $client_id ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
			$list[] = array( 'type' => 'invoice', 'id' => (int) $r['id'], 'name' => $r['invoice_number'] . ( $r['title'] ? ' — ' . $r['title'] : '' ), 'group' => 'Invoices' );
		}
		return $list;
	}

	public static function message_context_name( $name, $type, $id ) {
		global $wpdb;
		if ( 'invoice' !== $type || ! self::on() ) {
			return $name;
		}
		return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT invoice_number FROM ' . Schema::table( 'invoices' ) . ' WHERE id=%d', $id ) ); // phpcs:ignore WordPress.DB
	}

	/** Home: money owed to the business, what to invoice, and overdue invoices to chase. */
	public static function home( $home ) {
		global $wpdb;
		if ( ! self::on() || ( ! current_user_can( 'wpflatinvoice_billing' ) && ! current_user_can( 'manage_options' ) ) ) {
			return $home;
		}
		$inv    = Schema::table( 'invoices' );
		$today  = current_time( 'Y-m-d' );
		$owed   = array();
		foreach ( (array) $wpdb->get_results( "SELECT currency, SUM(amount-amount_paid) b, COUNT(*) n FROM $inv WHERE status IN ('sent','due','partially_paid','overdue') GROUP BY currency", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
			$owed[ $r['currency'] ] = array( 'amount' => round( (float) $r['b'], 2 ), 'count' => (int) $r['n'] );
		}
		$home['stats']['money_owed'] = $owed;
		$late = (array) $wpdb->get_results( $wpdb->prepare( "SELECT i.id, i.invoice_number, i.due_date, i.currency, i.amount-i.amount_paid AS bal, c.name AS client_name FROM $inv i LEFT JOIN " . Schema::table( 'clients' ) . " c ON c.id=i.client_id WHERE i.status='overdue' ORDER BY i.due_date LIMIT 8" ), ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( $late as $r ) {
			$home['todo'][] = array( 'kind' => 'invoice', 'title' => 'Chase ' . $r['invoice_number'], 'sub' => $r['client_name'] . ' owes ' . $r['currency'] . ' ' . number_format( (float) $r['bal'], 2 ), 'link' => '/billing/invoices/' . (int) $r['id'], 'days' => $r['due_date'] ? (int) floor( ( strtotime( $r['due_date'] ) - strtotime( $today ) ) / DAY_IN_SECONDS ) : 0 );
		}
		$soon = (array) $wpdb->get_results( $wpdb->prepare( "SELECT i.id, i.invoice_number, i.due_date, c.name AS client_name FROM $inv i LEFT JOIN " . Schema::table( 'clients' ) . " c ON c.id=i.client_id WHERE i.status IN ('sent','due','partially_paid') AND i.due_date >= %s ORDER BY i.due_date LIMIT 8", $today ), ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( $soon as $r ) {
			$home['coming_up'][] = array( 'kind' => 'invoice', 'title' => $r['invoice_number'] . ' falls due', 'sub' => $r['client_name'], 'link' => '/billing/invoices/' . (int) $r['id'], 'days' => (int) floor( ( strtotime( $r['due_date'] ) - strtotime( $today ) ) / DAY_IN_SECONDS ) );
		}
		$home['setup'][] = array( 'key' => 'catalog', 'label' => 'Add what you sell to the catalog', 'to' => '/catalog', 'done' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::table( 'catalog' ) ) > 0 ); // phpcs:ignore WordPress.DB
		$home['setup'][] = array( 'key' => 'payment', 'label' => \WPFlatCore\Terminology::fmt( 'Add how {clients} can pay you' ), 'to' => '/settings?tab=invoice', 'done' => count( (array) Settings::get( 'payment_methods' ) ) > 0 );
		return $home;
	}
}
