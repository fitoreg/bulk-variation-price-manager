/**
 * Bulk Variation Price Manager — Admin JS
 */
(function ($) {
	'use strict';

	var BVPM = {

		/**
		 * Initialize all event handlers.
		 */
		init: function () {
			this.bindSelectAll();
			this.bindCheckboxes();
			this.bindInlineEdit();
			this.bindToggleVariations();
			this.bindClearSale();
			this.bindBulkApply();
			this.bindBulkActionChange();
			this.bindNoticeDismiss();
		},

		// -----------------------------------------------------------
		// Checkbox / Select All
		// -----------------------------------------------------------

		bindSelectAll: function () {
			$('#bvpm-select-all').on('change', function () {
				var checked = $(this).prop('checked');
				$('.bvpm-product-check').prop('checked', checked);
				BVPM.updateBulkBar();
			});
		},

		bindCheckboxes: function () {
			$(document).on('change', '.bvpm-product-check', function () {
				BVPM.updateBulkBar();

				// Update select-all state.
				var total = $('.bvpm-product-check').length;
				var checked = $('.bvpm-product-check:checked').length;
				$('#bvpm-select-all').prop('checked', total === checked);
			});
		},

		updateBulkBar: function () {
			var count = $('.bvpm-product-check:checked').length;
			$('#bvpm-selected-num').text(count);

			if (count > 0) {
				$('#bvpm-bulk-bar').slideDown(150);
			} else {
				$('#bvpm-bulk-bar').slideUp(150);
			}
		},

		// -----------------------------------------------------------
		// Bulk action — show/hide value input
		// -----------------------------------------------------------

		bindBulkActionChange: function () {
			$('#bvpm-bulk-action').on('change', function () {
				var action = $(this).val();
				if (action === 'clear_sale') {
					$('#bvpm-bulk-value').hide();
				} else {
					$('#bvpm-bulk-value').show();
				}
			});
		},

		// -----------------------------------------------------------
		// Inline Editing
		// -----------------------------------------------------------

		bindInlineEdit: function () {
			$(document).on('click', '.bvpm-editable', function () {
				var $cell = $(this);

				// Already editing.
				if ($cell.find('input').length) {
					return;
				}

				var currentVal = $cell.data('value') || '';
				var productId = $cell.data('product-id');
				var field = $cell.data('field');
				var originalHtml = $cell.html();

				var $input = $('<input>', {
					type: 'number',
					class: 'bvpm-editable-input',
					value: currentVal,
					min: 0,
					step: '0.01',
				});

				$cell.html($input);
				$input.focus().select();

				// Save on blur or Enter.
				$input.on('blur keydown', function (e) {
					if (e.type === 'keydown' && e.which !== 13) {
						return;
					}

					e.preventDefault();
					var newVal = $input.val();

					// No change — restore original.
					if (newVal === String(currentVal)) {
						$cell.html(originalHtml);
						return;
					}

					// Show spinner.
					$cell.html('<span class="bvpm-spinner"></span>');

					$.post(bvpm.ajax_url, {
						action: 'bvpm_inline_save',
						nonce: bvpm.nonce,
						product_id: productId,
						field: field,
						value: newVal,
					}, function (response) {
						if (response.success) {
							var data = response.data;
							var displayVal;

							if (field === 'sale_price') {
								displayVal = data.sale_price !== '' ? BVPM.formatPrice(data.sale_price) : '—';
								$cell.data('value', data.sale_price);
							} else {
								displayVal = BVPM.formatPrice(data.regular_price);
								$cell.data('value', data.regular_price);
							}

							$cell.html(displayVal);

							// Update on-sale badge in the same row.
							var $row = $cell.closest('tr');
							var $badge = $row.find('.bvpm-col-onsale .bvpm-badge');
							if (data.on_sale) {
								$badge.removeClass('bvpm-badge-not-sale').addClass('bvpm-badge-on-sale').text(bvpm.i18n.yes);
							} else {
								$badge.removeClass('bvpm-badge-on-sale').addClass('bvpm-badge-not-sale').text(bvpm.i18n.no);
							}

							// Also update the other price cell in the row.
							if (field === 'regular_price') {
								var $saleCell = $row.find('.bvpm-editable[data-field="sale_price"]');
								if ($saleCell.length) {
									var saleDisplay = data.sale_price !== '' ? BVPM.formatPrice(data.sale_price) : '—';
									$saleCell.data('value', data.sale_price).html(saleDisplay);
								}
							}
						} else {
							$cell.html(originalHtml);
							BVPM.showNotice(response.data.message, 'error');
						}
					}).fail(function () {
						$cell.html(originalHtml);
						BVPM.showNotice(bvpm.i18n.error, 'error');
					});
				});
			});
		},

		// -----------------------------------------------------------
		// Toggle Variations
		// -----------------------------------------------------------

		bindToggleVariations: function () {
			$(document).on('click', '.bvpm-toggle-variations', function (e) {
				e.preventDefault();

				var productId = $(this).data('product-id');
				var $varRow = $('tr.bvpm-variations-row[data-parent-id="' + productId + '"]');

				if ($varRow.is(':visible')) {
					$varRow.hide();
					return;
				}

				$varRow.show();
				var $container = $varRow.find('.bvpm-variations-container');

				// Load if not loaded yet.
				if ($container.find('.bvpm-variations-table').length) {
					return;
				}

				$container.html('<div class="bvpm-variations-loading">' + bvpm.i18n.loading + '</div>');

				$.post(bvpm.ajax_url, {
					action: 'bvpm_load_variations',
					nonce: bvpm.nonce,
					product_id: productId,
				}, function (response) {
					if (response.success) {
						BVPM.renderVariations($container, response.data.variations, productId);
					} else {
						$container.html('<p>' + response.data.message + '</p>');
					}
				}).fail(function () {
					$container.html('<p>' + bvpm.i18n.error + '</p>');
				});
			});
		},

		renderVariations: function ($container, variations, parentId) {
			if (!variations.length) {
				$container.html('<p>' + BVPM.escHtml(bvpm.i18n.no_variations) + '</p>');
				return;
			}

			var html = '<table class="bvpm-variations-table">';
			html += '<thead><tr>';
			html += '<th>' + BVPM.escHtml(bvpm.i18n.variation) + '</th>';
			html += '<th>' + BVPM.escHtml(bvpm.i18n.sku) + '</th>';
			html += '<th>' + BVPM.escHtml(bvpm.i18n.regular_price) + '</th>';
			html += '<th>' + BVPM.escHtml(bvpm.i18n.sale_price) + '</th>';
			html += '<th>' + BVPM.escHtml(bvpm.i18n.on_sale) + '</th>';
			html += '<th>' + BVPM.escHtml(bvpm.i18n.actions) + '</th>';
			html += '</tr></thead><tbody>';

			$.each(variations, function (i, v) {
				var onSaleClass = v.on_sale ? 'bvpm-badge-on-sale' : 'bvpm-badge-not-sale';
				var onSaleText = v.on_sale ? bvpm.i18n.yes : bvpm.i18n.no;
				var regularDisplay = v.regular_price !== '' ? BVPM.formatPrice(v.regular_price) : '—';
				var saleDisplay = v.sale_price !== '' ? BVPM.formatPrice(v.sale_price) : '—';

				html += '<tr data-variation-id="' + v.id + '">';
				html += '<td>' + BVPM.escHtml(v.name || '#' + v.id) + '</td>';
				html += '<td>' + BVPM.escHtml(v.sku || '') + '</td>';
				html += '<td><span class="bvpm-editable" data-product-id="' + v.id + '" data-field="regular_price" data-value="' + BVPM.escAttr(v.regular_price) + '">' + regularDisplay + '</span></td>';
				html += '<td><span class="bvpm-editable" data-product-id="' + v.id + '" data-field="sale_price" data-value="' + BVPM.escAttr(v.sale_price) + '">' + saleDisplay + '</span></td>';
				html += '<td><span class="bvpm-badge ' + onSaleClass + '">' + onSaleText + '</span></td>';
				html += '<td><button type="button" class="button button-small bvpm-clear-sale-btn" data-product-id="' + v.id + '">' + BVPM.escHtml(bvpm.i18n.clear_sale) + '</button></td>';
				html += '</tr>';
			});

			html += '</tbody></table>';
			$container.html(html);
		},

		// -----------------------------------------------------------
		// Clear Sale
		// -----------------------------------------------------------

		bindClearSale: function () {
			$(document).on('click', '.bvpm-clear-sale-btn', function () {
				var $btn = $(this);
				var productId = $btn.data('product-id');
				var $row = $btn.closest('tr');

				$btn.prop('disabled', true).text(bvpm.i18n.saving);

				$.post(bvpm.ajax_url, {
					action: 'bvpm_clear_sale',
					nonce: bvpm.nonce,
					product_id: productId,
				}, function (response) {
					if (response.success) {
						var data = response.data;

						// Update sale price cell.
						var $saleCell = $row.find('.bvpm-editable[data-field="sale_price"]');
						if ($saleCell.length) {
							$saleCell.data('value', '').html('—');
						}

						// Update on-sale badge.
						var $badge = $row.find('.bvpm-badge-on-sale, .bvpm-badge-not-sale');
						$badge.removeClass('bvpm-badge-on-sale').addClass('bvpm-badge-not-sale').text(bvpm.i18n.no);

						BVPM.showNotice(data.message, 'success');

						// If this was a parent product row, also reload variations if visible.
						var $varRow = $('tr.bvpm-variations-row[data-parent-id="' + productId + '"]');
						if ($varRow.is(':visible')) {
							var $container = $varRow.find('.bvpm-variations-container');
							$container.html('<div class="bvpm-variations-loading">' + bvpm.i18n.loading + '</div>');
							$.post(bvpm.ajax_url, {
								action: 'bvpm_load_variations',
								nonce: bvpm.nonce,
								product_id: productId,
							}, function (resp) {
								if (resp.success) {
									BVPM.renderVariations($container, resp.data.variations, productId);
								}
							});
						}
					} else {
						BVPM.showNotice(response.data.message, 'error');
					}

					$btn.prop('disabled', false).text(bvpm.i18n.clear_sale);
				}).fail(function () {
					BVPM.showNotice(bvpm.i18n.error, 'error');
					$btn.prop('disabled', false).text(bvpm.i18n.clear_sale);
				});
			});
		},

		// -----------------------------------------------------------
		// Bulk Apply
		// -----------------------------------------------------------

		bindBulkApply: function () {
			$('#bvpm-bulk-apply').on('click', function () {
				var $checked = $('.bvpm-product-check:checked');
				var ids = [];

				$checked.each(function () {
					ids.push($(this).val());
				});

				if (!ids.length) {
					alert(bvpm.i18n.no_selection);
					return;
				}

				var action = $('#bvpm-bulk-action').val();
				if (!action) {
					alert(bvpm.i18n.no_action);
					return;
				}

				var value = $('#bvpm-bulk-value').val();
				if (action !== 'clear_sale' && (!value || parseFloat(value) <= 0)) {
					alert(bvpm.i18n.invalid_amount);
					return;
				}

				// Confirmation for large selections.
				if (ids.length >= 100) {
					var msg = bvpm.i18n.confirm_bulk.replace('%d', ids.length);
					if (!confirm(msg)) {
						return;
					}
				}

				var skipOnSale = $('#bvpm-skip-on-sale').is(':checked') ? 1 : 0;
				var dryRun = $('#bvpm-dry-run').is(':checked') ? 1 : 0;

				var $btn = $(this);
				$btn.prop('disabled', true).text(bvpm.i18n.saving);

				// Add loading overlay to checked rows.
				$checked.each(function () {
					$(this).closest('tr').addClass('bvpm-row-loading');
				});

				$.post(bvpm.ajax_url, {
					action: 'bvpm_bulk_update',
					nonce: bvpm.nonce,
					product_ids: ids,
					bulk_action: action,
					value: value,
					skip_on_sale: skipOnSale,
					dry_run: dryRun,
				}, function (response) {
					$('.bvpm-row-loading').removeClass('bvpm-row-loading');
					$btn.prop('disabled', false).text(bvpm.i18n.apply_to_selected);

					if (response.success) {
						BVPM.showNotice(response.data.message, 'success');

						if (response.data.dry_run && response.data.preview.length) {
							BVPM.showPreview(response.data.preview);
						} else if (!response.data.dry_run) {
							// Reload the page to reflect changes.
							$('#bvpm-preview').hide();
							window.location.reload();
						}
					} else {
						BVPM.showNotice(response.data.message, 'error');
					}
				}).fail(function () {
					$('.bvpm-row-loading').removeClass('bvpm-row-loading');
					$btn.prop('disabled', false).text(bvpm.i18n.apply_to_selected);
					BVPM.showNotice(bvpm.i18n.error, 'error');
				});
			});
		},

		// -----------------------------------------------------------
		// Preview Table (Dry Run)
		// -----------------------------------------------------------

		showPreview: function (preview) {
			var $body = $('#bvpm-preview-body');
			$body.empty();

			$.each(preview, function (i, item) {
				var row = '<tr>';
				row += '<td>' + BVPM.escHtml(item.name) + ' (#' + item.id + ')</td>';
				row += '<td>' + BVPM.formatPrice(item.old_regular) + '</td>';

				if (String(item.old_regular) !== String(item.new_regular)) {
					row += '<td class="bvpm-preview-changed">' + BVPM.formatPrice(item.new_regular) + '</td>';
				} else {
					row += '<td>' + BVPM.formatPrice(item.new_regular) + '</td>';
				}

				row += '<td>' + (item.old_sale !== '' ? BVPM.formatPrice(item.old_sale) : '—') + '</td>';

				if (String(item.old_sale) !== String(item.new_sale)) {
					row += '<td class="bvpm-preview-changed">' + (item.new_sale !== '' ? BVPM.formatPrice(item.new_sale) : '—') + '</td>';
				} else {
					row += '<td>' + (item.new_sale !== '' ? BVPM.formatPrice(item.new_sale) : '—') + '</td>';
				}

				row += '</tr>';
				$body.append(row);
			});

			$('#bvpm-preview').show();
		},

		// -----------------------------------------------------------
		// Notices
		// -----------------------------------------------------------

		showNotice: function (message, type) {
			var cls = type === 'error' ? 'bvpm-notice bvpm-notice-error' : 'bvpm-notice';
			var html = '<div class="' + cls + '">';
			html += '<button type="button" class="bvpm-notice-dismiss">&times;</button>';
			html += '<p>' + BVPM.escHtml(message) + '</p>';
			html += '</div>';

			$('#bvpm-notices').html(html);

			// Auto-dismiss after 5 seconds.
			setTimeout(function () {
				$('#bvpm-notices .bvpm-notice').fadeOut(300, function () {
					$(this).remove();
				});
			}, 5000);
		},

		bindNoticeDismiss: function () {
			$(document).on('click', '.bvpm-notice-dismiss', function () {
				$(this).closest('.bvpm-notice').fadeOut(200, function () {
					$(this).remove();
				});
			});
		},

		// -----------------------------------------------------------
		// Helpers
		// -----------------------------------------------------------

		formatPrice: function (price) {
			if (price === '' || price === null || typeof price === 'undefined') {
				return '—';
			}
			return parseFloat(price).toFixed(2);
		},

		escHtml: function (str) {
			if (!str) return '';
			var div = document.createElement('div');
			div.appendChild(document.createTextNode(str));
			return div.innerHTML;
		},

		escAttr: function (str) {
			if (str === null || typeof str === 'undefined') return '';
			return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
		},
	};

	$(document).ready(function () {
		BVPM.init();
	});

})(jQuery);
