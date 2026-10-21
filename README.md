# WPFlat Invoice

**Invoicing, simply.** Send a professional invoice in minutes, see who has paid, and let repeat clients bill themselves. No accounting jargon, no bloated finance system, just invoices that get you paid. Runs inside your WordPress, on your own data.

Part of the WPFlat suite; needs WPFlat Core.

- **Invoices:** line items, tax, discount, instalments, currencies, numbering, a public invoice page, emailed invoices with To and CC to the right contacts, payment reminders (automatic before, on and after the due date, following instalments, or sent by hand) and a thank-you email after each payment, all to the same people.
- **Payments:** record full or part payments; invoices move between draft, sent, partly paid, paid and overdue on their own.
- **Catalog:** the services and products you sell, with prices.
- **Recurring billing:** retainers, subscriptions, renewals and one-off charges that turn into invoices.

No expenses, payroll or profit reports. Need those later? Install **WPFlat Finance** and activate it: it takes over your invoices, payments, catalog and settings (same numbers, same public links), and switches WPFlat Invoice off. WPFlat Invoice and WPFlat Finance never run together.

## Install

Download or clone this repository, put the `wpflatinvoice` folder in `wp-content/plugins/`, and activate it (activate WPFlat Core first). The built scripts in `build/` are committed, so nothing needs compiling to use it.

## Develop

```
npm install
npm run build      # rebuilds build/ from ui/
```

PHP lives in `includes/`, the Vue source in `ui/`. The shared test suites and deploy tools live in the WPFlat workspace (`wpflat-suite/` next to this folder).

License: GPL-2.0-or-later.
