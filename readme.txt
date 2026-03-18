=== Bulk Variation Price Manager ===
Contributors: fitoregashi
Tags: woocommerce, bulk pricing, variations, sale price, price manager
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bulk pricing manager for WooCommerce products and variations with a spreadsheet-style UI, inline editing, and bulk sale price setter.

== Description ==

Bulk Variation Price Manager gives WooCommerce store managers full control over product pricing in a fast, spreadsheet-style interface inside the WordPress admin.

**Key Features:**

* Spreadsheet-style product table with all products and their prices
* Inline editing — click any price cell to edit and save instantly via AJAX
* Expandable variation rows — click a variable product to see and edit all its variation prices
* Bulk actions — set sale prices by percentage or fixed amount, increase/decrease regular prices, or clear sales across hundreds of products at once
* Filters — filter by on-sale status, product type, category, or search by name/SKU
* Dry run mode — preview what changes would be made before saving
* Skip already-on-sale option — protect existing sale prices during bulk updates
* Properly syncs variable product parent prices and clears WooCommerce transient caches

**Built for Performance:**

* Paginated with configurable per-page (50, 100, 200, 500)
* Uses WooCommerce CRUD methods — no direct database queries
* Clears product transients after every update

**WooCommerce Compatible:**

* Works with both simple and variable products
* Properly propagates sale prices to all variations
* Syncs parent min/max prices after variation updates

== Installation ==

1. Upload the `bulk-variation-price-manager` folder to `wp-content/plugins/`.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Make sure WooCommerce is installed and active.
4. Navigate to **WooCommerce > Bulk Price Manager** in the admin menu.

== Frequently Asked Questions ==

= Does this work with variable products? =

Yes. When you apply a bulk action to a variable product, the plugin updates all of its variations individually and then syncs the parent product prices.

= Will this overwrite my existing sale prices? =

Only if you choose to. There is a "Skip products/variations that already have a sale price" checkbox that lets you protect existing sales during bulk updates.

= Can I preview changes before applying them? =

Yes. Check the "Dry run (preview only)" option before clicking "Apply to selected". You will see a preview table showing old and new prices without any data being saved.

= What permissions are required? =

Users need the `manage_woocommerce` capability, which is typically assigned to Shop Manager and Administrator roles.

= Does this plugin make external API calls? =

No. The plugin operates entirely within your WordPress installation and does not contact any external servers.

== Screenshots ==

1. The main Bulk Price Manager table with filters and inline editing.
2. Expanded variation rows showing individual variation prices.
3. Bulk action bar with percentage and fixed amount options.
4. Dry run preview table showing proposed changes.

== Changelog ==

= 1.0.0 =
* Initial release.
* Spreadsheet-style product table with inline price editing.
* Bulk actions: set sale price (% or fixed), clear sale, adjust regular price.
* Filters: on-sale status, product type, category, name/SKU search.
* Expandable variation sub-rows for variable products.
* Dry run preview mode.
* Skip already-on-sale option.
* Configurable products per page (50, 100, 200, 500).

== Upgrade Notice ==

= 1.0.0 =
Initial release.
