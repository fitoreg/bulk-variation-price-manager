<?php
/**
 * Product query and filtering logic.
 *
 * @package BulkVariationPriceManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles product fetching and filtering for the admin table.
 */
class BVPM_Query {

	/**
	 * Number of products per page.
	 *
	 * @var int
	 */
	const DEFAULT_PER_PAGE = 50;

	/**
	 * Allowed per-page options.
	 *
	 * @var int[]
	 */
	const PER_PAGE_OPTIONS = array( 50, 100, 200, 500 );

	/**
	 * Fetch products with filters applied.
	 *
	 * @param array $args Filter arguments.
	 * @return array { products: WC_Product[], total: int, pages: int, per_page: int }
	 */
	public static function get_products( $args = array() ) {
		$defaults = array(
			'page'         => 1,
			'per_page'     => self::DEFAULT_PER_PAGE,
			'search'       => '',
			'product_type' => '',
			'category'     => '',
			'on_sale'      => '',
		);

		$args     = wp_parse_args( $args, $defaults );
		$page     = max( 1, intval( $args['page'] ) );
		$per_page = intval( $args['per_page'] );

		if ( ! in_array( $per_page, self::PER_PAGE_OPTIONS, true ) ) {
			$per_page = self::DEFAULT_PER_PAGE;
		}

		$query_args = array(
			'limit'   => $per_page,
			'page'    => $page,
			'orderby' => 'title',
			'order'   => 'ASC',
			'return'  => 'objects',
			'status'  => array( 'publish', 'draft' ),
		);

		// Product type filter.
		if ( ! empty( $args['product_type'] ) ) {
			$query_args['type'] = sanitize_text_field( $args['product_type'] );
		}

		// Category filter.
		if ( ! empty( $args['category'] ) ) {
			$query_args['category'] = array( sanitize_text_field( $args['category'] ) );
		}

		// Search by name or SKU — use 'like_name' for wc_get_products() (NOT 's').
		if ( ! empty( $args['search'] ) ) {
			$search = sanitize_text_field( $args['search'] );

			// Check if searching by SKU first.
			$sku_product_id = wc_get_product_id_by_sku( $search );
			if ( $sku_product_id ) {
				$query_args['include'] = array( $sku_product_id );
			} else {
				$query_args['like_name'] = $search;
			}
		}

		// On sale filter — handled post-query for accuracy.
		$products = wc_get_products( $query_args );

		// For total count, run the same query without pagination.
		$count_args           = $query_args;
		$count_args['limit']  = -1;
		$count_args['return'] = 'ids';
		$all_ids              = wc_get_products( $count_args );
		$total                = is_array( $all_ids ) ? count( $all_ids ) : 0;

		// On sale filter (post-query).
		if ( ! empty( $args['on_sale'] ) && is_array( $all_ids ) ) {
			$on_sale_ids = wc_get_product_ids_on_sale();

			if ( 'yes' === $args['on_sale'] ) {
				$filtered_ids = array_intersect( $all_ids, $on_sale_ids );
			} else {
				$filtered_ids = array_diff( $all_ids, $on_sale_ids );
			}

			$total = count( $filtered_ids );

			// Paginate the filtered IDs.
			$offset       = ( $page - 1 ) * $per_page;
			$paged_ids    = array_slice( array_values( $filtered_ids ), $offset, $per_page );
			$products     = array();

			foreach ( $paged_ids as $pid ) {
				$product = wc_get_product( $pid );
				if ( $product ) {
					$products[] = $product;
				}
			}
		}

		return array(
			'products' => is_array( $products ) ? $products : array(),
			'total'    => $total,
			'pages'    => max( 1, ceil( $total / $per_page ) ),
			'per_page' => $per_page,
		);
	}

	/**
	 * Get all product categories for the filter dropdown.
	 *
	 * @return array Term objects.
	 */
	public static function get_categories() {
		return get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
	}

	/**
	 * Get variations for a variable product.
	 *
	 * @param int $product_id Parent product ID.
	 * @return array Variation data.
	 */
	public static function get_variations( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return array();
		}

		$variations = array();
		$children   = $product->get_children();

		foreach ( $children as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation ) {
				continue;
			}

			$attributes   = $variation->get_attributes();
			$attr_display = array();
			foreach ( $attributes as $attr_name => $attr_value ) {
				$taxonomy = str_replace( 'attribute_', '', $attr_name );
				$term     = get_term_by( 'slug', $attr_value, $taxonomy );
				$label    = $term ? $term->name : $attr_value;
				$attr_display[] = $label;
			}

			$regular_price = $variation->get_regular_price();
			$sale_price    = $variation->get_sale_price();

			$variations[] = array(
				'id'            => $child_id,
				'name'          => implode( ' / ', $attr_display ),
				'sku'           => $variation->get_sku(),
				'regular_price' => $regular_price,
				'sale_price'    => $sale_price,
				'on_sale'       => $variation->is_on_sale(),
			);
		}

		return $variations;
	}
}
