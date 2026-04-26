define([
    'jquery',
    'Magento_Checkout/js/checkout-data'
], function ($, checkoutData) {
    'use strict';

    return function (config, element) {
        var form = $(element),
            manualAddressBlock = form.find('[data-manual-address]'),
            selectedAddressField = form.find('[data-selected-address-id]'),
            checkoutSelectionField = form.find('[data-role="checkout-selection"]'),
            cartForm = $('#form-validate'),
            checkoutButton = $('.checkout.methods.items .action.primary.checkout');

        function populateAddressFields(addressData) {
            form.find('[name="shipping_firstname"]').val(addressData.firstname || '');
            form.find('[name="shipping_lastname"]').val(addressData.lastname || '');
            form.find('[name="shipping_telephone"]').val(addressData.telephone || '');
            form.find('[name="shipping_street_1"]').val(addressData.street_1 || '');
            form.find('[name="shipping_street_2"]').val(addressData.street_2 || '');
            form.find('[name="shipping_city"]').val(addressData.city || '');
            form.find('[name="shipping_region"]').val(addressData.region || '');
            form.find('[name="shipping_postcode"]').val(addressData.postcode || '');
            form.find('[name="shipping_country_id"]').val(addressData.country_id || 'VN');
        }

        function persistSelection() {
            var addressData = {
                    firstname: form.find('[name="shipping_firstname"]').val(),
                    lastname: form.find('[name="shipping_lastname"]').val(),
                    telephone: form.find('[name="shipping_telephone"]').val(),
                    street: [
                        form.find('[name="shipping_street_1"]').val(),
                        form.find('[name="shipping_street_2"]').val()
                    ],
                    city: form.find('[name="shipping_city"]').val(),
                    region: form.find('[name="shipping_region"]').val(),
                    postcode: form.find('[name="shipping_postcode"]').val(),
                    country_id: form.find('[name="shipping_country_id"]').val()
                },
                selectedPaymentMethod = form.find('[name="payment_method"]:checked').val();

            checkoutData.setShippingAddressFromData(addressData);
            checkoutData.setBillingAddressFromData(addressData);

            if (selectedPaymentMethod) {
                checkoutData.setSelectedPaymentMethod(selectedPaymentMethod);
            }
        }

        function syncAddressSelectionState() {
            var selectedOption = form.find('[name="saved_address_option"]:checked'),
                selectedCard = selectedOption.closest('.fashionstore-cart-options__address-card'),
                addressData = {};

            form.find('.fashionstore-cart-options__address-card').removeClass('is-selected');
            selectedCard.addClass('is-selected');

            if (!selectedOption.length || !selectedOption.val()) {
                selectedAddressField.val('');
                manualAddressBlock.removeClass('is-collapsed');
                persistSelection();

                return;
            }

            selectedAddressField.val(selectedOption.val());
            addressData = JSON.parse(selectedOption.attr('data-address') || '{}');
            populateAddressFields(addressData);
            manualAddressBlock.addClass('is-collapsed');
            persistSelection();
        }

        function getSelectedCartItems() {
            var selectedItems = [];

            cartForm.find('[data-role="fashionstore-cart-select"]:checked').each(function () {
                var checkbox = $(this),
                    itemId = parseInt(checkbox.attr('data-item-id') || checkbox.val(), 10),
                    qtyField = cartForm.find('#cart-' + itemId + '-qty'),
                    qtyValue = parseFloat(qtyField.val() || '1');

                if (!itemId || isNaN(itemId)) {
                    return;
                }

                if (isNaN(qtyValue) || qtyValue <= 0) {
                    qtyValue = 1;
                }

                selectedItems.push({
                    item_id: itemId,
                    qty: qtyValue
                });
            });

            return selectedItems;
        }

        function syncCheckoutSelection() {
            if (!checkoutSelectionField.length) {
                return [];
            }

            var selectedItems = getSelectedCartItems();

            checkoutSelectionField.val(JSON.stringify(selectedItems));

            if (checkoutButton.length) {
                checkoutButton.prop('disabled', selectedItems.length === 0);
            }

            return selectedItems;
        }

        form.on('change', '[name="saved_address_option"]', syncAddressSelectionState);
        form.on('input change', 'input, textarea, select', persistSelection);
        cartForm.on('change input', '[data-role="fashionstore-cart-select"], [data-role="cart-item-qty"]', syncCheckoutSelection);
        syncAddressSelectionState();
        persistSelection();
        syncCheckoutSelection();
    };
});