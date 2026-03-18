<?php
/**
 * Price update logic for simple and variable products.
 *
 * @package BulkVariationPriceManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles all price update operations.
 */
class BVPM_Updater {

	/**
	 * Update a single product or variation price.
	 *
	 * @param int    $product_id Product or variation ID.
	 * @param string $field      Field name: 'regular_price' or 'sale_price'.
	 * @param string $value      New price value.
	 * @return bool True on success.
	 */
	public static function update_single_price( $product_id, $field, $value ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}

		$value = floatval( $value );

		if ( 'regular_price' === $field ) {
			$product->set_regular_price( $value );

			// If sale price is higher than new regular price, clear it.
			$sale = $product->get_sale_price();
			if ( '' !== $sale && floatval( $sale ) >= $value ) {
				$product->set_sale_price( '' );
			}
		} elseif ( 'sale_price' === $field ) {
			$regular = floatval( $product->get_regular_price() );
			if ( $value >= $regular || $value < 0 ) {
				return false;
			}
			$product->set_sale_price( $value );
		}

		$product->save();

		// Sync parent if this is a variation.
		$parent_id = $product->get_parent_id();
		if ( $parent_id ) {
			self::sync_parent( $parent_id );
		}

		return true;
	}

	/**
	 * Bulk update products.
	 *
	 * @param array  $product_ids Array of product IDs.
	 * @param string $action      Bulk action key.
	 * @param float  $value       Amount or percentage.
	 * @param bool   $skip_on_sale Whether to skip products/variations already on sale.
	 * @param bool   $dry_run     If true, calculate but do not save.
	 * @return array { updated: int, skipped: int, variations_updated: int, preview: array }
	 */
	public static function bulk_update( $product_ids, $action, $value, $skip_on_sale = false, $dry_run = false ) {
		$updated             = 0;
		$skipped             = 0;
		$variations_updated  = 0;
		$preview             = array();
		$value               = floatval( $value );

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( intval( $product_id ) );
			if ( ! $product ) {
				continue;
			}

			if ( $product->is_type( 'variable' ) ) {
				$children = $product->get_children();
				$parent_changed = false;

				foreach ( $children as $child_id ) {
					$variation = wc_get_product( $child_id );
					if ( ! $variation ) {
						continue;
					}

					$result = self::apply_action_to_product( $variation, $action, $value, $skip_on_sale, $dry_run );
					if ( null === $result ) {
						++$skipped;
					} else {
						++$variations_updated;
						$parent_changed = true;
						if ( $dry_run ) {
							$preview[] = $result;
						}
					}
				}

				if ( $parent_changed ) {
					++$updated;
					if ( ! $dry_run ) {
						self::sync_parent( $product->get_id() );
					}
				}
			} else {
				$result = self::apply_action_to_product( $product, $action, $value, $skip_on_sale, $dry_run );
				if ( null === $result ) {
					++$skipped;
				} else {
					++$updated;
					if ( $dry_run ) {
						$preview[] = $result;
					}
				}
			}
		}

		return array(
			'updated'             => $updated,
			'skipped'             => $skipped,
			'variations_updated'  => $variations_updated,
			'preview'             => $preview,
		);
	}

	/**
	 * Apply a bulk action to a single product or variation.
	 *
	 * @param WC_Product $product      Product or variation.
	 * @param string     $action       Action key.
	 * @param float      $value        Amount or percentage.
	 * @param bool       $skip_on_sale Skip if already on sale.
	 * @param bool       $dry_run      Preview only.
	 * @return array|null Null if skipped, preview data if dry run, empty array if saved.
	 */
	private static function apply_action_to_product( $product, $action, $value, $skip_on_sale, $dry_run ) {
		$product_id    = $product->get_id();
		$regular_price = $product->get_regular_price();
		$existing_sale = $product->get_sale_price();

		// Skip logic.
		if ( $skip_on_sale && '' !== $existing_sale && floatval( $existing_sale ) > 0 ) {
			return null;
		}

		$regular_float = '' !== $regular_price ? floatval( $regular_price ) : 0;
		$new_regular   = $regular_float;
		$new_sale      = '';

		switch ( $action ) {
			case 'sale_percent':
				$new_sale = round( $regular_float * ( 1 - $value / 100 ), 2 );
				break;

			case 'sale_fixed':
				$new_sale = round( $regular_float - $value, 2 );
				break;

			case 'clear_sale':
				$new_sale = '';
				break;

			case 'regular_increase':
				$new_regular = round( $regular_float * ( 1 + $value / 100 ), 2 );
				break;

			case 'regular_decrease':
				$new_regular = round( $regular_float * ( 1 - $value / 100 ), 2 );
				break;

			default:
				return null;
		}

		// Validate sale price.
		if ( is_numeric( $new_sale ) ) {
			if ( $new_sale >= $new_regular || $new_sale < 0 ) {
				return null;
			}
		}

		// Validate regular price.
		if ( $new_regular < 0 ) {
			return null;
		}

		if ( $dry_run ) {
			return array(
				'id'            => $product_id,
				'name'          => $product->get_name(),
				'old_regular'   => $regular_price,
				'new_regular'   => $new_regular,
				'old_sale'      => $existing_sale,
				'new_sale'      => $new_sale,
			);
		}

		// Re-fetch product fresh to avoid stale object cache.
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return null;
		}

		// Apply changes.
		if ( abs( $new_regular - $regular_float ) > 0.001 ) {
			$product->set_regular_price( strval( $new_regular ) );
		}

		if ( 'clear_sale' === $action ) {
			$product->set_sale_price( '' );
		} elseif ( is_numeric( $new_sale ) ) {
			$product->set_sale_price( strval( $new_sale ) );
		}

		$product->save();
		wc_delete_product_transients( $product_id );

		return array();
	}

	/**
	 * Clear sale price for a product or variation.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return bool True on success.
	 */
	public static function clear_sale( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}

		if ( $product->is_type( 'variable' ) ) {
			$children = $product->get_children();
			foreach ( $children as $child_id ) {
				$variation = wc_get_product( $child_id );
				if ( $variation ) {
					$variation->set_sale_price( '' );
					$variation->save();
				}
			}
			self::sync_parent( $product_id );
		} else {
			$product->set_sale_price( '' );
			$product->save();

			$parent_id = $product->get_parent_id();
			if ( $parent_id ) {
				self::sync_parent( $parent_id );
			}
		}

		return true;
	}

	/**
	 * Sync parent variable product prices and clear transients.
	 *
	 * @param int $parent_id Parent product ID.
	 */
	private static function sync_parent( $parent_id ) {
		$parent = wc_get_product( $parent_id );
		if ( $parent && $parent->is_type( 'variable' ) ) {
			WC_Product_Variable::sync( $parent_id );
			wc_delete_product_transients( $parent_id );
		}
	}
}
