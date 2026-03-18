<?php
/**
 * Admin menu and page rendering.
 *
 * @package BulkVariationPriceManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles admin menu registration, asset enqueuing, and page rendering.
 */
class BVPM_Admin {

	/**
	 * Constructor — register hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add submenu page under WooCommerce.
	 */
	public function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Bulk Price Manager', 'bvpm' ),
			__( 'Bulk Price Manager', 'bvpm' ),
			'manage_woocommerce',
			'bvpm-bulk-price-manager',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue CSS and JS on our admin page only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'woocommerce_page_bvpm-bulk-price-manager' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'bvpm-admin-css',
			BVPM_PLUGIN_URL . 'assets/css/bvpm-admin.css',
			array(),
			BVPM_VERSION
		);

		wp_enqueue_script(
			'bvpm-admin-js',
			BVPM_PLUGIN_URL . 'assets/js/bvpm-admin.js',
			array( 'jquery' ),
			BVPM_VERSION,
			true
		);

		wp_localize_script(
			'bvpm-admin-js',
			'bvpm',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'bvpm_nonce' ),
				'i18n'     => array(
					'confirm_bulk'    => __( 'You are about to update %d products. Continue?', 'bvpm' ),
					'saving'          => __( 'Saving...', 'bvpm' ),
					'saved'           => __( 'Saved', 'bvpm' ),
					'error'           => __( 'Error', 'bvpm' ),
					'no_selection'    => __( 'Please select at least one product.', 'bvpm' ),
					'no_action'       => __( 'Please select a bulk action.', 'bvpm' ),
					'loading'         => __( 'Loading variations...', 'bvpm' ),
				),
			)
		);
	}

	/**
	 * Render the admin page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bvpm' ) );
		}

		// Gather filter values from GET params.
		$filters = array(
			'page'         => isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1,
			'per_page'     => isset( $_GET['per_page'] ) ? intval( $_GET['per_page'] ) : BVPM_Query::DEFAULT_PER_PAGE,
			'search'       => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'product_type' => isset( $_GET['product_type'] ) ? sanitize_text_field( wp_unslash( $_GET['product_type'] ) ) : '',
			'category'     => isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '',
			'on_sale'      => isset( $_GET['on_sale'] ) ? sanitize_text_field( wp_unslash( $_GET['on_sale'] ) ) : '',
		);

		$data       = BVPM_Query::get_products( $filters );
		$categories = BVPM_Query::get_categories();

		if ( is_wp_error( $categories ) ) {
			$categories = array();
		}

		include BVPM_PLUGIN_DIR . 'templates/admin-page.php';
	}
}
