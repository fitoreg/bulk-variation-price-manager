<?php
/**
 * Plugin Name: Bulk Variation Price Manager
 * Plugin URI:  https://github.com/fitoreg/bulk-variation-price-manager
 * Description: Bulk pricing manager for WooCommerce products and variations with spreadsheet-style UI, inline editing, and bulk sale price setter.
 * Version:     1.0.0
 * Author:      Fitore Gashi
 * Author URI:  https://example.com
 * License:     GPL-2.0
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bvpm
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 7.0
 *
 * @package BulkVariationPriceManager
 */

defined( 'ABSPATH' ) || exit;

define( 'BVPM_VERSION', '1.0.0' );
define( 'BVPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BVPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BVPM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if WooCommerce is active before initializing.
 */
function bvpm_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'bvpm_woocommerce_missing_notice' );
		return;
	}

	require_once BVPM_PLUGIN_DIR . 'includes/class-bvpm-query.php';
	require_once BVPM_PLUGIN_DIR . 'includes/class-bvpm-updater.php';
	require_once BVPM_PLUGIN_DIR . 'includes/class-bvpm-ajax.php';
	require_once BVPM_PLUGIN_DIR . 'includes/class-bvpm-admin.php';

	new BVPM_Admin();
	new BVPM_Ajax();
}
add_action( 'plugins_loaded', 'bvpm_init' );

/**
 * Admin notice when WooCommerce is not active.
 */
function bvpm_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Bulk Variation Price Manager requires WooCommerce to be installed and active.', 'bvpm' ); ?></p>
	</div>
	<?php
}

/**
 * Clean up on uninstall.
 */
register_uninstall_hook( __FILE__, 'bvpm_uninstall' );

/**
 * Uninstall callback — remove any options added by the plugin.
 */
function bvpm_uninstall() {
	delete_option( 'bvpm_version' );
}
