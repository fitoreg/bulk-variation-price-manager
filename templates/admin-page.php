<?php
/**
 * Admin page template — Bulk Price Manager.
 *
 * @package BulkVariationPriceManager
 *
 * @var array $data       { products: WC_Product[], total: int, pages: int }
 * @var array $filters    Current filter values.
 * @var array $categories Product categories.
 */

defined( 'ABSPATH' ) || exit;

$products     = $data['products'];
$total        = $data['total'];
$total_pages  = $data['pages'];
$current_page = max( 1, $filters['page'] );
$base_url     = admin_url( 'admin.php?page=bvpm-bulk-price-manager' );
?>
<div class="wrap bvpm-wrap">
	<h1><?php esc_html_e( 'Bulk Price Manager', 'bulk-variation-price-manager' ); ?></h1>

	<!-- Admin notice container (populated by JS) -->
	<div id="bvpm-notices"></div>

	<!-- Filters -->
	<div class="bvpm-filters">
		<form method="get" action="<?php echo esc_url( $base_url ); ?>">
			<input type="hidden" name="page" value="bvpm-bulk-price-manager" />

			<select name="on_sale">
				<option value=""><?php esc_html_e( 'All products', 'bulk-variation-price-manager' ); ?></option>
				<option value="yes" <?php selected( $filters['on_sale'], 'yes' ); ?>><?php esc_html_e( 'On sale only', 'bulk-variation-price-manager' ); ?></option>
				<option value="no" <?php selected( $filters['on_sale'], 'no' ); ?>><?php esc_html_e( 'Not on sale', 'bulk-variation-price-manager' ); ?></option>
			</select>

			<select name="product_type">
				<option value=""><?php esc_html_e( 'All types', 'bulk-variation-price-manager' ); ?></option>
				<option value="simple" <?php selected( $filters['product_type'], 'simple' ); ?>><?php esc_html_e( 'Simple', 'bulk-variation-price-manager' ); ?></option>
				<option value="variable" <?php selected( $filters['product_type'], 'variable' ); ?>><?php esc_html_e( 'Variable', 'bulk-variation-price-manager' ); ?></option>
			</select>

			<select name="category">
				<option value=""><?php esc_html_e( 'All categories', 'bulk-variation-price-manager' ); ?></option>
				<?php if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) : ?>
					<?php foreach ( $categories as $cat ) : ?>
						<option value="<?php echo esc_attr( $cat->slug ); ?>" <?php selected( $filters['category'], $cat->slug ); ?>>
							<?php echo esc_html( $cat->name ); ?>
						</option>
					<?php endforeach; ?>
				<?php endif; ?>
			</select>

			<input
				type="text"
				name="s"
				value="<?php echo esc_attr( $filters['search'] ); ?>"
				placeholder="<?php esc_attr_e( 'Search by name or SKU...', 'bulk-variation-price-manager' ); ?>"
				class="bvpm-search-input"
			/>

			<select name="per_page">
				<?php foreach ( BVPM_Query::PER_PAGE_OPTIONS as $option ) : ?>
					<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $data['per_page'], $option ); ?>>
						<?php
						printf(
							/* translators: %d: number of products per page */
							esc_html__( '%d per page', 'bulk-variation-price-manager' ),
							$option
						);
						?>
					</option>
				<?php endforeach; ?>
			</select>

			<button type="submit" class="button"><?php esc_html_e( 'Apply', 'bulk-variation-price-manager' ); ?></button>
		</form>
	</div>

	<!-- Bulk Actions Bar -->
	<div class="bvpm-bulk-bar" id="bvpm-bulk-bar" style="display: none;">
		<span class="bvpm-selected-count">
			<?php esc_html_e( 'Selected:', 'bulk-variation-price-manager' ); ?> <strong id="bvpm-selected-num">0</strong>
		</span>

		<select id="bvpm-bulk-action">
			<option value=""><?php esc_html_e( '-- Select action --', 'bulk-variation-price-manager' ); ?></option>
			<option value="sale_percent"><?php esc_html_e( 'Set sale price (% off regular)', 'bulk-variation-price-manager' ); ?></option>
			<option value="sale_fixed"><?php esc_html_e( 'Set sale price (fixed amount off)', 'bulk-variation-price-manager' ); ?></option>
			<option value="clear_sale"><?php esc_html_e( 'Clear sale price', 'bulk-variation-price-manager' ); ?></option>
			<option value="regular_increase"><?php esc_html_e( 'Set regular price (% increase)', 'bulk-variation-price-manager' ); ?></option>
			<option value="regular_decrease"><?php esc_html_e( 'Set regular price (% decrease)', 'bulk-variation-price-manager' ); ?></option>
		</select>

		<input
			type="number"
			id="bvpm-bulk-value"
			min="0"
			step="0.01"
			placeholder="<?php esc_attr_e( '% or amount', 'bulk-variation-price-manager' ); ?>"
			class="bvpm-bulk-value-input"
		/>

		<label class="bvpm-skip-label">
			<input type="checkbox" id="bvpm-skip-on-sale" />
			<?php esc_html_e( 'Skip products/variations already on sale', 'bulk-variation-price-manager' ); ?>
		</label>

		<label class="bvpm-dry-run-label">
			<input type="checkbox" id="bvpm-dry-run" />
			<?php esc_html_e( 'Dry run (preview only)', 'bulk-variation-price-manager' ); ?>
		</label>

		<button type="button" class="button button-primary" id="bvpm-bulk-apply">
			<?php esc_html_e( 'Apply to selected', 'bulk-variation-price-manager' ); ?>
		</button>
	</div>

	<!-- Dry Run Preview -->
	<div id="bvpm-preview" class="bvpm-preview" style="display: none;">
		<h3><?php esc_html_e( 'Dry Run Preview', 'bulk-variation-price-manager' ); ?></h3>
		<table class="widefat bvpm-preview-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Product', 'bulk-variation-price-manager' ); ?></th>
					<th><?php esc_html_e( 'Old Regular', 'bulk-variation-price-manager' ); ?></th>
					<th><?php esc_html_e( 'New Regular', 'bulk-variation-price-manager' ); ?></th>
					<th><?php esc_html_e( 'Old Sale', 'bulk-variation-price-manager' ); ?></th>
					<th><?php esc_html_e( 'New Sale', 'bulk-variation-price-manager' ); ?></th>
				</tr>
			</thead>
			<tbody id="bvpm-preview-body"></tbody>
		</table>
	</div>

	<!-- Product count -->
	<div class="bvpm-table-info">
		<span>
			<?php
			printf(
				/* translators: %d: total number of products */
				esc_html__( '%d products found', 'bulk-variation-price-manager' ),
				$total
			);
			?>
		</span>
	</div>

	<!-- Product Table -->
	<table class="widefat bvpm-table" id="bvpm-table">
		<thead>
			<tr>
				<th class="bvpm-col-check"><input type="checkbox" id="bvpm-select-all" /></th>
				<th class="bvpm-col-name"><?php esc_html_e( 'Product Name', 'bulk-variation-price-manager' ); ?></th>
				<th class="bvpm-col-sku"><?php esc_html_e( 'SKU', 'bulk-variation-price-manager' ); ?></th>
				<th class="bvpm-col-type"><?php esc_html_e( 'Type', 'bulk-variation-price-manager' ); ?></th>
				<th class="bvpm-col-regular"><?php esc_html_e( 'Regular Price', 'bulk-variation-price-manager' ); ?></th>
				<th class="bvpm-col-sale"><?php esc_html_e( 'Sale Price', 'bulk-variation-price-manager' ); ?></th>
				<th class="bvpm-col-onsale"><?php esc_html_e( 'On Sale?', 'bulk-variation-price-manager' ); ?></th>
				<th class="bvpm-col-variations"><?php esc_html_e( 'Variations', 'bulk-variation-price-manager' ); ?></th>
				<th class="bvpm-col-status"><?php esc_html_e( 'Status', 'bulk-variation-price-manager' ); ?></th>
				<th class="bvpm-col-actions"><?php esc_html_e( 'Actions', 'bulk-variation-price-manager' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $products ) ) : ?>
				<tr>
					<td colspan="10" class="bvpm-no-products">
						<?php esc_html_e( 'No products found.', 'bulk-variation-price-manager' ); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $products as $product ) : ?>
					<?php
					$product_id      = $product->get_id();
					$is_variable     = $product->is_type( 'variable' );
					$regular_price   = $is_variable ? '' : $product->get_regular_price();
					$sale_price      = $is_variable ? '' : $product->get_sale_price();
					$on_sale         = $product->is_on_sale();
					$status          = $product->get_status();
					$edit_url        = get_edit_post_link( $product_id, 'raw' );
					$view_url        = get_permalink( $product_id );
					$variation_count = $is_variable ? count( $product->get_children() ) : 0;

					if ( ! $edit_url ) {
						$edit_url = admin_url( 'post.php?post=' . $product_id . '&action=edit' );
					}
					?>
					<tr class="bvpm-product-row" data-product-id="<?php echo esc_attr( $product_id ); ?>">
						<td class="bvpm-col-check">
							<input type="checkbox" class="bvpm-product-check" value="<?php echo esc_attr( $product_id ); ?>" />
						</td>
						<td class="bvpm-col-name">
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
						</td>
						<td class="bvpm-col-sku"><?php echo esc_html( $product->get_sku() ); ?></td>
						<td class="bvpm-col-type">
							<span class="bvpm-badge bvpm-badge-type"><?php echo esc_html( $product->get_type() ); ?></span>
						</td>
						<td class="bvpm-col-regular">
							<?php if ( ! $is_variable ) : ?>
								<span
									class="bvpm-editable"
									data-product-id="<?php echo esc_attr( $product_id ); ?>"
									data-field="regular_price"
									data-value="<?php echo esc_attr( $regular_price ); ?>"
								><?php echo '' !== $regular_price ? wp_kses_post( wc_price( $regular_price ) ) : '&mdash;'; ?></span>
							<?php else : ?>
								<span class="bvpm-variable-hint"><?php esc_html_e( 'See variations', 'bulk-variation-price-manager' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="bvpm-col-sale">
							<?php if ( ! $is_variable ) : ?>
								<span
									class="bvpm-editable"
									data-product-id="<?php echo esc_attr( $product_id ); ?>"
									data-field="sale_price"
									data-value="<?php echo esc_attr( $sale_price ); ?>"
								><?php echo '' !== $sale_price ? wp_kses_post( wc_price( $sale_price ) ) : '&mdash;'; ?></span>
							<?php else : ?>
								<span class="bvpm-variable-hint">&mdash;</span>
							<?php endif; ?>
						</td>
						<td class="bvpm-col-onsale">
							<?php if ( $on_sale ) : ?>
								<span class="bvpm-badge bvpm-badge-on-sale"><?php esc_html_e( 'Yes', 'bulk-variation-price-manager' ); ?></span>
							<?php else : ?>
								<span class="bvpm-badge bvpm-badge-not-sale"><?php esc_html_e( 'No', 'bulk-variation-price-manager' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="bvpm-col-variations">
							<?php if ( $is_variable ) : ?>
								<a href="#" class="bvpm-toggle-variations" data-product-id="<?php echo esc_attr( $product_id ); ?>">
									<?php echo esc_html( $variation_count ); ?>
								</a>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
						<td class="bvpm-col-status">
							<span class="bvpm-badge bvpm-badge-status-<?php echo esc_attr( $status ); ?>">
								<?php echo esc_html( ucfirst( $status ) ); ?>
							</span>
						</td>
						<td class="bvpm-col-actions">
							<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small" title="<?php esc_attr_e( 'Edit', 'bulk-variation-price-manager' ); ?>">
								<?php esc_html_e( 'Edit', 'bulk-variation-price-manager' ); ?>
							</a>
							<button
								type="button"
								class="button button-small bvpm-clear-sale-btn"
								data-product-id="<?php echo esc_attr( $product_id ); ?>"
								title="<?php esc_attr_e( 'Clear Sale', 'bulk-variation-price-manager' ); ?>"
							>
								<?php esc_html_e( 'Clear Sale', 'bulk-variation-price-manager' ); ?>
							</button>
							<a href="<?php echo esc_url( $view_url ); ?>" class="button button-small" target="_blank" title="<?php esc_attr_e( 'View', 'bulk-variation-price-manager' ); ?>">
								<?php esc_html_e( 'View', 'bulk-variation-price-manager' ); ?>
							</a>
						</td>
					</tr>
					<!-- Variation sub-rows inserted here by JS -->
					<tr class="bvpm-variations-row" data-parent-id="<?php echo esc_attr( $product_id ); ?>" style="display: none;">
						<td colspan="10" class="bvpm-variations-container">
							<div class="bvpm-variations-loading"><?php esc_html_e( 'Loading variations...', 'bulk-variation-price-manager' ); ?></div>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- Pagination -->
	<?php if ( $total_pages > 1 ) : ?>
		<div class="bvpm-pagination">
			<?php
			$pagination_args = array(
				'base'      => add_query_arg( 'paged', '%#%' ),
				'format'    => '',
				'current'   => $current_page,
				'total'     => $total_pages,
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
			);

			// Preserve filter params in pagination links.
			foreach ( $filters as $key => $val ) {
				if ( 'page' !== $key && '' !== $val ) {
					$param_key = 's' === $key ? 's' : $key;
					if ( 'search' === $key ) {
						$param_key = 's';
					} elseif ( 'page' === $key ) {
						continue;
					} else {
						$param_key = $key;
					}
					$pagination_args['add_args'][ $param_key ] = $val;
				}
			}

			echo wp_kses_post( paginate_links( $pagination_args ) );
			?>
		</div>
	<?php endif; ?>
</div>
