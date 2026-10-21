import { h } from 'vue';
import { Receipt } from 'lucide-vue-next';
import { sdk } from './core';
import { canBilling, currencyOptions, defaultCurrency, fmtMoney } from './util';
import Invoicing from './views/Invoicing.vue';
import InvoiceEditor from './views/InvoiceEditor.vue';
import ClientBillingTab from './slots/ClientBillingTab.vue';
import ClientHistory from './slots/ClientHistory.vue';
import ClientStats from './slots/ClientStats.vue';
import ClientOverview from './slots/ClientOverview.vue';
import HomeStats from './slots/HomeStats.vue';
import MoneySettings from './settings/MoneySettings.vue';

/** WPFlat Invoice registers its screens and what it adds to WPFlat Core's pages. Nothing runs unless the module is on. */
const M = 'invoice';
sdk.registerIcon( 'invoice', Receipt );
sdk.registerFormatter( 'money', ( amount, currency ) => fmtMoney( amount, currency ) );

/** A route screen that shows one section of the invoicing pages. */
const page = ( section, sub = '' ) => ( { props: [ 'sub', 'section', 'query' ], render() { return h( Invoicing, { section: this.section || section, sub: this.sub || sub, query: this.query } ); } } );

sdk.registerRoute( { path: '/billing', module: M, component: page( 'billing' ) } );
sdk.registerRoute( { path: '/billing/:sub', module: M, component: page( 'billing' ) } ); // invoices, recurring, one-off
sdk.registerRoute( { path: '/billing/invoices/:id', module: M, component: InvoiceEditor } ); // "new" opens a blank one
sdk.registerRoute( { path: '/invoices', module: M, component: page( 'billing', 'invoices' ) } );
sdk.registerRoute( { path: '/renewals', module: M, component: page( 'billing', 'recurring' ) } );
sdk.registerRoute( { path: '/charges', module: M, component: page( 'billing', 'one-off' ) } );
sdk.registerRoute( { path: '/catalog', module: M, component: page( 'catalog' ) } );

sdk.registerSlot( 'client.tabs', { id: 'billing', module: M, order: 20, label: 'Billing', component: ClientBillingTab, when: canBilling } );
sdk.registerSlot( 'client.stats', { id: 'invoice', module: M, order: 10, component: ClientStats, when: canBilling } );
sdk.registerSlot( 'client.overview', { id: 'invoice', module: M, order: 10, component: ClientOverview, when: canBilling } );
sdk.registerSlot( 'client.history', { id: 'invoice', module: M, order: 10, component: ClientHistory, when: canBilling } );
sdk.registerSlot( 'home.stats', { id: 'invoice', module: M, order: 10, component: HomeStats, when: canBilling } );
sdk.registerSlot( 'settings.tabs', { id: 'invoice', module: M, order: 30, label: 'Invoices', component: MoneySettings, when: canBilling } );

// Fields WPFlat Invoice adds to a client's form.
sdk.extend( 'client.fields', {
	id: 'invoice-currency',
	module: M,
	order: 40,
	when: canBilling,
	default: () => defaultCurrency(),
	field: { name: 'currency', label: 'Billing currency', type: 'select', required: true, options: currencyOptions, help: 'Charges and invoices default to this currency.' },
} );

// Lists the owner can edit under Settings → Lists & options.
sdk.extend( 'lookup.types', { id: 'billing_period', module: M, order: 60, label: 'Billing periods' } );
sdk.extend( 'lookup.types', { id: 'billing_treatment', module: M, order: 61, label: 'How it’s billed' } );
sdk.extend( 'lookup.fields', {
	id: 'invoice-months',
	module: M,
	order: 60,
	when: ( ctx ) => ctx.type === 'billing_period',
	field: { name: 'months', label: 'Months between bills', type: 'number', step: '1', help: '0 for one-time or custom.' },
} );

// What shows in the Activity list.
[ [ 'invoices', 'invoice' ], [ 'payments', null, 'Payments' ], [ 'charges', null, 'One-off bills' ], [ 'renewals', null, 'Recurring bills' ] ].forEach( ( [ value, term, label ], i ) => {
	sdk.extend( 'activity.types', { id: 'invoice-' + value, module: M, order: 60 + i, type: { value, label: term ? sdk.tp( term ) : label } } );
} );
