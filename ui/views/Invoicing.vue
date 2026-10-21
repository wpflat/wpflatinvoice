<script setup>
import { computed } from 'vue';
import { PageHeader, sdk, store } from '../core';
import Billing from './Billing.vue';
import Invoices from './Invoices.vue';
import Catalog from './Catalog.vue';

const props = defineProps( { section: { type: String, default: '' }, sub: { type: String, default: '' }, query: { type: Object, default: () => ( {} ) } } );

const section = computed( () => ( props.section === 'catalog' ? 'catalog' : 'billing' ) );
const BILLING = () => ({ list: [ 'Billing', sdk.fmt( 'Everything you charge {clients} — retainers, subscriptions and one-off bills. Tick any mix for one {client} and create a single invoice.' ) ], invoices: [ sdk.tp( 'invoice' ), 'What you have billed, and what is still to be paid.' ] });
// Old URLs: /billing/recurring and /billing/one-off now open the one Billing list, pre-filtered.
const OLD_TYPE = { recurring: 'all', renewals: 'all', 'one-off': 'one_off', charges: 'one_off' };
const billSub = computed( () => ( props.sub === 'invoices' ? 'invoices' : 'list' ) );
const billType = computed( () => OLD_TYPE[ props.sub ] || store.route.query.type || 'all' );
const head = computed( () => ( {
	billing: BILLING()[ billSub.value ],
	catalog: [ sdk.tp( 'catalog' ), 'What you sell: services and products, with their prices.' ],
}[ section.value ] ) );
</script>

<template>
	<div class="bd-invoice">
		<PageHeader eyebrow="Invoices" :title="head[ 0 ]" :description="head[ 1 ]" />
		<template v-if="section === 'billing'">
			<Invoices v-if="billSub === 'invoices'" embedded use-route-filters />
			<Billing v-else :type="billType" />
		</template>
		<Catalog v-else />
	</div>
</template>
