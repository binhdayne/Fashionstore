define([
	'jquery',
	'Magento_Customer/js/customer-data'
], function ($, customerData) {
	'use strict';

	return function (config, element) {
		var $form = $(element),
			$buyNowButton = $form.find('[data-role="buy-now"]'),
			$returnUrl = $form.find('[data-role="buy-now-return-url"]'),
			$buyNowFlag = $form.find('[data-role="buy-now-flag"]'),
			$addToCartButton = $form.find('#product-addtocart-button'),
			$loginRequiredMessage = $form.find('[data-role="buy-now-login-required"]'),
			$sizeRequiredMessage = $form.find('[data-role="buy-now-size-required"]'),
			$loginLink = $form.find('[data-role="buy-now-login-link"]'),
			customer = customerData.get('customer'),
			originalAction = $form.attr('action') || '';

		if (!$buyNowButton.length || !$returnUrl.length || !$buyNowFlag.length) {
			return;
		}

		if ($loginLink.length && config.loginUrl) {
			$loginLink.attr('href', config.loginUrl);
		}

		function isCustomerLoggedIn() {
			var customerDataValue = customer();
			return !!(customerDataValue && customerDataValue.firstname);
		}

		function showLoginRequiredMessage() {
			if ($loginRequiredMessage.length) {
				$loginRequiredMessage.removeAttr('hidden').show();
			}
		}

		function hideLoginRequiredMessage() {
			if ($loginRequiredMessage.length) {
				$loginRequiredMessage.attr('hidden', 'hidden').hide();
			}
		}

		function showSizeRequiredMessage() {
			if ($sizeRequiredMessage.length) {
				$sizeRequiredMessage.removeAttr('hidden').show();
			}
		}

		function hideSizeRequiredMessage() {
			if ($sizeRequiredMessage.length) {
				$sizeRequiredMessage.attr('hidden', 'hidden').hide();
			}
		}

		function hasSelectedSize() {
			var $sizeSwatch = $form.find('.swatch-attribute[data-attribute-code="size"]').first(),
				$sizeField = $(),
				$sizeSelect,
				selectedValue;

			if ($sizeSwatch.length) {
				if ($sizeSwatch.find('.swatch-option.selected').length) {
					return true;
				}

				$sizeSelect = $sizeSwatch.find('select, input.super-attribute-select').first();
				if (!$sizeSelect.length) {
					$sizeSelect = $form.find('select[name^="super_attribute"]').first();
				}

				selectedValue = $sizeSelect.length ? $.trim(String($sizeSelect.val() || '')) : '';
				return selectedValue !== '';
			}

			$form.find('select.product-custom-option').each(function () {
				var $select = $(this),
					$field = $select.closest('.field'),
					labelText = $.trim($field.find('.label').first().text()).toLowerCase();

				if (labelText.indexOf('size') !== -1 || labelText.indexOf('kích cỡ') !== -1 || labelText.indexOf('kích thước') !== -1) {
					$sizeField = $field;
					return false;
				}

				return true;
			});

			if (!$sizeField.length) {
				$sizeField = $form.find('.field[data-attribute-code="size"]').first();
			}

			if (!$sizeField.length) {
				return true;
			}

			$sizeSelect = $sizeField.find('select').first();
			if (!$sizeSelect.length) {
				return true;
			}

			selectedValue = $.trim(String($sizeSelect.val() || ''));
			return selectedValue !== '';
		}

		function syncButtonState() {
			var isDisabled = $addToCartButton.length
				? ($addToCartButton.prop('disabled') || $addToCartButton.hasClass('disabled'))
				: false;

			$buyNowButton.prop('disabled', isDisabled).toggleClass('disabled', isDisabled);

			if (isCustomerLoggedIn()) {
				hideLoginRequiredMessage();
			}
		}

		function resetBuyNowState() {
			$returnUrl.val('');
			$buyNowFlag.val('0');
			if (originalAction) {
				$form.attr('action', originalAction);
			}
			syncButtonState();
		}

		function isFormValid() {
			if (typeof $form.validation !== 'function') {
				return true;
			}

			$form.validation();

			return $form.validation('isValid');
		}

		function submitBuyNow() {
			$buyNowButton.prop('disabled', true).addClass('disabled');
			if (config.buyNowUrl) {
				$form.attr('action', config.buyNowUrl);
			}
			$buyNowFlag.val('1');
			HTMLFormElement.prototype.submit.call($form[0]);
		}

		function hideAndPresetColor() {
			var $colorSelect = $(),
				$colorSwatches = $form.find('.swatch-attribute[data-attribute-code="color"] .swatch-option'),
				firstValue;

			$form.find('select.product-custom-option').each(function () {
				var $select = $(this),
					$field = $select.closest('.field'),
					labelText = $.trim($field.find('.label').first().text()).toLowerCase();

				if (labelText.indexOf('màu') !== -1 || labelText.indexOf('color') !== -1) {
					$colorSelect = $colorSelect.add($select);
				}
			});

			if ($colorSelect.length) {
				firstValue = $colorSelect.find('option').filter(function () {
					return $(this).val() !== '';
				}).first().val();

				if (firstValue && !$colorSelect.val()) {
					$colorSelect.val(firstValue).trigger('change');
				}

				$colorSelect.closest('.field, .swatch-attribute').hide();
			}

			if ($colorSwatches.length) {
				if (!$colorSwatches.filter('.selected').length) {
					$colorSwatches.first().trigger('click');
				}
				$colorSwatches.closest('.swatch-attribute').hide();
			}
		}

		function enhanceSizeAsButtons() {
			var $sizeField = $(),
				$sizeSelect,
				$renderTarget,
				$buttonList;

			$form.find('select.product-custom-option').each(function () {
				var $select = $(this),
					$field = $select.closest('.field'),
					labelText = $.trim($field.find('.label').first().text()).toLowerCase();

				if (labelText.indexOf('size') !== -1 || labelText.indexOf('kích cỡ') !== -1 || labelText.indexOf('kích thước') !== -1) {
					$sizeField = $field;
					return false;
				}

				return true;
			});

			if (!$sizeField.length) {
				$sizeField = $form.find('.field[data-attribute-code="size"], .swatch-attribute[data-attribute-code="size"]').first();
			}

			if (!$sizeField.length) {
				return;
			}

			$sizeSelect = $sizeField.find('select').first();
			if (!$sizeSelect.length) {
				return;
			}

			$sizeSelect.addClass('fashionstore-size-select-hidden');
			$sizeSelect.hide();
			$renderTarget = $sizeField.find('.control').first();
			if (!$renderTarget.length) {
				$renderTarget = $sizeField;
			}

			$renderTarget.find('.fashionstore-size-options').remove();
			$buttonList = $('<div class="fashionstore-size-options" data-role="size-options"></div>');

			$sizeSelect.find('option').each(function () {
				var value = $(this).val(),
					label = $.trim($(this).text()),
					$button;

				if (!value) {
					return;
				}

				$button = $('<button type="button" class="fashionstore-size-option"></button>');
				$button.text(label).attr('data-value', value);
				if ($(this).is(':disabled')) {
					$button.prop('disabled', true).addClass('disabled');
				}

				if ($sizeSelect.val() === value) {
					$button.addClass('selected');
				}

				$buttonList.append($button);
			});

			$renderTarget.append($buttonList);

			$buttonList.on('click', '.fashionstore-size-option', function () {
				var value = $(this).attr('data-value');
				if ($(this).prop('disabled')) {
					return;
				}

				$sizeSelect.val(value).trigger('change');
			});

			$sizeSelect.on('change', function () {
				var selectedValue = $(this).val();
				$buttonList.find('.fashionstore-size-option').removeClass('selected');
				$buttonList.find('.fashionstore-size-option[data-value="' + selectedValue + '"]').addClass('selected');
				syncButtonState();
			});
		}

		function refreshEnhancements() {
			hideAndPresetColor();
			enhanceSizeAsButtons();
			hideSizeRequiredMessage();
			syncButtonState();
		}

		refreshEnhancements();
		setTimeout(refreshEnhancements, 80);
		setTimeout(refreshEnhancements, 360);

		if (window.MutationObserver && $addToCartButton.length) {
			new MutationObserver(syncButtonState).observe($addToCartButton[0], {
				attributes: true,
				attributeFilter: ['disabled', 'class']
			});
		}

		$form.on('click', '[data-role="qty-increase"]', function () {
			var $qtyInput = $form.find('#qty'),
				currentQty;

			if (!$qtyInput.length) {
				return;
			}

			currentQty = parseFloat($qtyInput.val()) || 1;
			$qtyInput.val(currentQty + 1).trigger('change');
		});

		$form.on('click', '[data-role="qty-decrease"]', function () {
			var $qtyInput = $form.find('#qty'),
				minQty,
				currentQty,
				nextQty;

			if (!$qtyInput.length) {
				return;
			}

			minQty = parseFloat($qtyInput.attr('min')) || 1;
			currentQty = parseFloat($qtyInput.val()) || minQty;
			nextQty = Math.max(minQty, currentQty - 1);
			$qtyInput.val(nextQty).trigger('change');
		});

		$buyNowButton.on('click', function (event) {
			event.preventDefault();

			if ($(this).prop('disabled')) {
				return;
			}

			resetBuyNowState();

			if (!hasSelectedSize()) {
				showSizeRequiredMessage();
				return;
			}

			hideSizeRequiredMessage();

			if (!isCustomerLoggedIn()) {
				showLoginRequiredMessage();
				return;
			}

			if (!isFormValid()) {
				return;
			}

			submitBuyNow();
		});

		$form.on('submit', resetBuyNowState);
		$form.on('click', '#product-addtocart-button', function () {
			$buyNowFlag.val('0');
		});
		$form.on('change', '.swatch-attribute[data-attribute-code="size"] .swatch-input, .swatch-attribute[data-attribute-code="size"] select, .field[data-attribute-code="size"] select, select.product-custom-option', hideSizeRequiredMessage);
		$form.on('contentUpdated', refreshEnhancements);
		hideLoginRequiredMessage();
		hideSizeRequiredMessage();
	};
});
