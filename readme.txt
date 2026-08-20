=== Profitly — Profit Analytics for WooCommerce ===
Contributors: standalonetech
Tags: profit, cost of goods, cogs, analytics, woocommerce
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See your WooCommerce store's real profit. Track cost of goods (COGS), gateway fees, and shipping costs to know what you actually earn per order.

== Description ==

**WooCommerce shows you revenue. Profitly shows you profit.**

Your WooCommerce reports tell you how much money came in. They do not tell you how much you actually kept after the cost of your products, payment gateway fees, and shipping. Most store owners are guessing at their real margins — and many discover too late that their "best-selling" product is barely breaking even.

Profitly fixes that. It captures the true cost behind every order and shows you real, per-order and per-product profit — right inside your WordPress admin. No spreadsheets, no exporting data to an external service, no monthly subscription. Your financial data stays on your own server.

= What Profitly does =

* **Cost of Goods (COGS) tracking** — Add your real cost per product and per variation. Bulk-import costs via CSV.
* **Real profit per order** — Revenue minus COGS, payment gateway fees, and shipping cost, calculated for every order.
* **Gross margin at a glance** — See each product's gross margin right in your Products list.
* **Profit reports** — Today, last 7 days, and last 30 days, with revenue, net profit, order count, and average margin, plus period-over-period comparison.
* **Top & bottom products** — Instantly see your 10 most profitable products and your 10 loss-making products.
* **Profit Target Planner** — Enter the profit you want to make and see the revenue, orders, and daily pace needed to get there, based on your own historical margin.
* **Dashboard widget** — A quick profit snapshot on your WordPress dashboard.
* **CSV export** — Export your order-level profit data for the last 30 days.

= The feature that sets Profitly apart: cost snapshotting =

This is the detail that most profit plugins get wrong, and it matters more than anything else.

When an order is placed, Profitly **snapshots the cost of goods onto that order**. If your supplier raises prices next month and you update your product costs, your *past* profit reports do **not** change. Your history stays accurate. This is essential for trustworthy bookkeeping — a profit report you cannot trust is worse than no report at all.

= Accurate financial math =

Profitly uses precise decimal arithmetic for all monetary calculations — no floating-point rounding errors that quietly throw your numbers off. Every figure is calculated the way an accountant would expect.

= Built for real shipping costs =

WooCommerce records what your customer *paid* for shipping — that is revenue, not cost. Profitly lets you record what *you* actually pay to fulfill an order, so your profit reflects reality. Choose the model that fits your store:

* **Carrier estimate** — Set an average shipping cost per shipping zone.
* **Customer-paid** — Use the shipping amount the customer paid (for stores that pass shipping through at cost).
* **Included / not applicable** — For digital products or stores where shipping is already in your product cost.

You can also override the shipping cost on any individual order.

= Plan around the profit you want =

The Profit Target Planner (Profitly → Profit Target) works backwards from a profit goal. Pick a planning period (this month, next month, this quarter, or a custom range) and a historical baseline (last 30 days, 90 days, or 12 months), enter your target profit, and Profitly shows the revenue and order count required — plus the daily pace, how that compares with your current trajectory, and how much less revenue you would need at a better margin.

It is a projection from your own Profitly data, not a full business forecast: it does not know about salaries, rent, software, taxes, or financing costs, and Profitly says so on the page.

= Refunds handled correctly =

When you refund an order, Profitly reduces revenue and the associated cost of goods — but it correctly keeps the payment gateway fee as a loss, because gateways typically don't refund their fee. Your net profit reflects what really happened.

= Works with modern WooCommerce =

* **HPOS compatible** — Fully supports WooCommerce High-Performance Order Storage from day one.
* **Blocks checkout compatible** — Works with both the classic and block-based checkout.
* **Your data stays yours** — Everything runs on your own site. Nothing is sent to an external server.

= Who Profitly is for =

* Store owners who offer free or flat-rate shipping and suspect it's eating their margin.
* Sellers running discounts and campaigns who need to know the real profit impact.
* Dropshippers and D2C brands tracking supplier costs against selling prices.
* Anyone who has ever asked, "I'm making sales — so why isn't there more money in the bank?"

== Installation ==

= Automatic installation =

1. Go to **Plugins → Add New** in your WordPress admin.
2. Search for "Profitly".
3. Click **Install Now**, then **Activate**.

= Manual installation =

1. Download the plugin ZIP file.
2. Go to **Plugins → Add New → Upload Plugin**.
3. Choose the ZIP file and click **Install Now**, then **Activate**.

= After activation =

1. Make sure **WooCommerce** is installed and active (Profitly requires it).
2. Open the new **Profitly** menu in your admin sidebar.
3. Go to **Profitly → Settings** and enter your payment gateway fees and shipping costs.
4. Add a **Cost of Goods** value to your products (on each product's edit screen, or bulk-import via CSV).
5. Your profit reports will begin populating as new orders come in.

**Note:** Profit is calculated from the cost of goods recorded at the time an order is placed. For accurate reporting, add your product costs before you start selling.

== Frequently Asked Questions ==

= Does Profitly require WooCommerce? =

Yes. Profitly is an extension for WooCommerce and will not function without it.

= Will changing a product's cost change my past profit reports? =

No. Profitly snapshots each product's cost onto the order at the moment it is placed. Updating a product's cost later affects only future orders — your historical reports stay accurate.

= Does my data get sent anywhere? =

No. All calculations happen on your own server, and all data is stored in your own WordPress database. Profitly does not send your financial data to any external service.

= Is Profitly compatible with High-Performance Order Storage (HPOS)? =

Yes. Profitly is fully HPOS compatible.

= How are payment gateway fees calculated? =

You enter your gateway's fee structure (a percentage, a fixed amount per transaction, or both) in **Profitly → Settings**. You choose whether the percentage applies to the order total, the subtotal plus shipping, or the product subtotal only. Profitly snapshots these values onto each order, so later fee changes don't rewrite your history.

= What's the difference between the shipping the customer pays and the shipping cost in Profitly? =

The amount your customer pays for shipping is revenue. The shipping cost in Profitly is what *you* pay to fulfill the order — the cost you owe your carrier. These are usually different numbers, and using the customer-paid amount as your cost would hide shipping losses. Profitly lets you record your real cost.

= Does Profitly handle product variations? =

Yes. You can set a cost of goods for each variation. If a variation has no cost set, Profitly falls back to the parent product's cost.

= Can I export my profit data? =

Yes. You can export your order-level profit data for the last 30 days as a CSV file, which opens cleanly in Excel and Google Sheets.

= Does Profitly work with multiple currencies? =

Profitly reports each order in the currency it was placed in and does not convert between currencies. It's designed for stores operating in a single base currency.

= Will Profitly slow down my store? =

No. Profit calculations for reports run in the admin area, not on your storefront, and reporting queries are optimized to handle large numbers of orders.

== Screenshots ==

1. Profit reports dashboard — revenue, net profit, order count, and average margin, with period-over-period comparison.
2. Cost of Goods field on the product edit screen.
3. Per-product gross margin column in the Products list.
4. Per-order profit breakdown showing revenue, COGS, gateway fee, shipping cost, and net profit.
5. Profit snapshot widget on the WordPress dashboard.
6. Settings — gateway fees, shipping cost model, and general options.

== Changelog ==

= 1.0.1 =
* Renamed the plugin to Profitly.
* Moved all admin styles and scripts into properly enqueued asset files (no more inline `<style>`/`<script>`), per WordPress.org guidelines.

= 1.0.0 =
* Initial release.
* Cost of Goods (COGS) tracking for products and variations.
* Cost snapshotting onto orders at creation time for accurate historical reporting.
* Per-order profit calculation including COGS, payment gateway fees, and shipping costs.
* Configurable gateway fee model (percentage, fixed, and calculation basis) per gateway.
* Configurable shipping cost model (carrier estimate per zone, customer-paid, or included).
* Refund handling that preserves gateway fees as a loss.
* Profit reports for today, last 7 days, and last 30 days with period comparison.
* Top 10 most profitable and top 10 loss-making product tables.
* Gross margin column in the Products list.
* Per-order profit breakdown metabox.
* WordPress dashboard profit widget.
* CSV export of order-level profit data.
* HPOS and block checkout compatibility.

== Upgrade Notice ==

= 1.0.1 =
Improvements and fixes following the initial release. Update recommended.
