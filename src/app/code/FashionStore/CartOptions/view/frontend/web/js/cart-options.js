define([
    'jquery',
    'Magento_Checkout/js/checkout-data'
], function ($, checkoutData) {
    'use strict';

    return function (config, element) {
        var form = $(element),
            manualAddressBlock = form.find('[data-manual-address]'),
            selectedAddressField = form.find('[data-selected-address-id]');

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

        form.on('change', '[name="saved_address_option"]', syncAddressSelectionState);
        form.on('input change', 'input, textarea, select', persistSelection);
        syncAddressSelectionState();
        persistSelection();
    };
});