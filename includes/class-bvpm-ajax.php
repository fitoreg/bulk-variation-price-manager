<?php
/**
 * AJAX handlers for inline edits and bulk actions.
 *
 * @package BulkVariationPriceManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and handles all AJAX endpoints.
 */
class BVPM_Ajax {

	/**
	 * Constructor — register AJAX hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_bvpm_inline_save', array( $this, 'ajax_inline_save' ) );
		add_action( 'wp_ajax_bvpm_bulk_update', array( $this, 'ajax_bulk_update' ) );
		add_action( 'wp_ajax_bvpm_load_variations', array( $this, 'ajax_load_variations' ) );
		add_action( 'wp_ajax_bvpm_clear_sale', array( $this, 'ajax_clear_sale' ) );
	}

	/**
	 * Verify nonce and capability for all AJAX requests.
	 */
	private function verify_request() {
		check_ajax_referer( 'bvpm_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bulk-variation-price-manager' ) ) );
		}
	}

	/**
	 * Save a single inline price edit.
	 */
	public function ajax_inline_save() {
		$this->verify_request();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().
		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		$field      = isset( $_POST['field'] ) ? sanitize_text_field( wp_unslash( $_POST['field'] ) ) : '';
		$value      = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! $product_id || ! in_array( $field, array( 'regular_price', 'sale_price' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'bulk-variation-price-manager' ) ) );
		}

		$result = BVPM_Updater::update_single_price( $product_id, $field, $value );

		if ( $result ) {
			$product = wc_get_product( $product_id );
			wp_send_json_success(
				array(
					'message'       => __( 'Price updated.', 'bulk-variation-price-manager' ),
					'regular_price' => $product->get_regular_price(),
					'sale_price'    => $product->get_sale_price(),
					'on_sale'       => $product->is_on_sale(),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to update price. Ensure sale price is less than regular price.', 'bulk-variation-price-manager' ) ) );
		}
	}

	/**
	 * Process bulk price update on selected products.
	 */
	public function ajax_bulk_update() {
		$this->verify_request();

		$product_ids  = isset( $_POST['product_ids'] ) ? array_map( 'intval', (array) $_POST['product_ids'] ) : array();
		$action       = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$value        = isset( $_POST['value'] ) ? floatval( $_POST['value'] ) : 0;
		$skip_on_sale = ! empty( $_POST['skip_on_sale'] );
		$dry_run      = ! empty( $_POST['dry_run'] );

		if ( empty( $product_ids ) || empty( $action ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'bulk-variation-price-manager' ) ) );
		}

		$valid_actions = array( 'sale_percent', 'sale_fixed', 'clear_sale', 'regular_increase', 'regular_decrease' );
		if ( ! in_array( $action, $valid_actions, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid action.', 'bulk-variation-price-manager' ) ) );
		}

		$result = BVPM_Updater::bulk_update( $product_ids, $action, $value, $skip_on_sale, $dry_run );

		$message = $dry_run
			? sprintf(
				/* translators: 1: updated count, 2: variations count, 3: skipped count */
				__( 'Preview: %1$d products (%2$d variations) would be updated. %3$d skipped.', 'bulk-variation-price-manager' ),
				$result['updated'],
				$result['variations_updated'],
				$result['skipped']
			)
			: sprintf(
				/* translators: 1: updated count, 2: variations count, 3: skipped count */
				__( 'Updated %1$d products (%2$d variations). Skipped %3$d (already on sale).', 'bulk-variation-price-manager' ),
				$result['updated'],
				$result['variations_updated'],
				$result['skipped']
			);

		wp_send_json_success(
			array(
				'message'  => $message,
				'updated'  => $result['updated'],
				'skipped'  => $result['skipped'],
				'preview'  => $result['preview'],
				'dry_run'  => $dry_run,
			)
		);
	}

	/**
	 * Load variations for a variable product.
	 */
	public function ajax_load_variations() {
		$this->verify_request();

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product ID.', 'bulk-variation-price-manager' ) ) );
		}

		$variations = BVPM_Query::get_variations( $product_id );

		wp_send_json_success(
			array(
				'variations' => $variations,
				'product_id' => $product_id,
			)
		);
	}

	/**
	 * Clear sale price for a product or variation.
	 */
	public function ajax_clear_sale() {
		$this->verify_request();

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product ID.', 'bulk-variation-price-manager' ) ) );
		}

		$result = BVPM_Updater::clear_sale( $product_id );

		if ( $result ) {
			$product = wc_get_product( $product_id );
			wp_send_json_success(
				array(
					'message'       => __( 'Sale price cleared.', 'bulk-variation-price-manager' ),
					'regular_price' => $product->get_regular_price(),
					'sale_price'    => $product->get_sale_price(),
					'on_sale'       => $product->is_on_sale(),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to clear sale price.', 'bulk-variation-price-manager' ) ) );
		}
	}
}
