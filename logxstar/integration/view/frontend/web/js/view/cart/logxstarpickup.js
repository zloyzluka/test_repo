/* release 1.0.6*/
define(
    [
        'jquery',
        'uiComponent',
        'Magento_Checkout/js/model/quote',
        'uiRegistry',
        'Magento_Checkout/js/model/shipping-rates-validator',
        'Magento_Checkout/js/model/shipping-service',
        'Magento_Catalog/js/price-utils'
    ],
    function ($, Component, quote, registry, shippingRatesValidator, shippingService, priceUtils) {
        "use strict";
        var checkoutConfig = window.checkoutConfig;
        var selected_pickuppoint_address = {};
        var selected_method = null;
        var component = null;
        var first = false;
        var map;
        var mapMarkers = [];
        var mapinitialized = false;
        var mapscriptinitialized = false;
        var freeMessageTimer = false;

        return Component.extend({
            ajaxpickuppoint: checkoutConfig.logxstar.pickuppoint.ajaxgetpoints,
            checkDelay: 5000,
            isPickupPointsComplete: null,
            pickupPointsRequest: null,
            markers: [],
            current_method: null,
            current_method_name: null,
            postcode_field: null,
            last_response: null,
            initialize: function () {
                this._super();
                quote.shippingMethod.subscribe(this.shippingMethodUpdate, this);
                shippingService.isLoading.subscribe(this.addFreeShippingMessage, this);
                quote.totals.subscribe(this.addFreeShippingMessage, this);
                component = this;
            },
            shippingMethodUpdate: function (method) {
                var method_name = method.method_code;

                this.addFreeShippingMessage();

                if (typeof method_name != 'undefined' && method_name !== null && method_name.indexOf('lgxspickup') !== -1) {
                    selected_method = method;
                }
                if (this.current_method_name != method_name) {
                    this.last_response = null;
                    this.current_method_name = method_name;
                }
            },
            addFreeShippingMessage: function () {
                clearTimeout(freeMessageTimer);
                var freeMessageTimer = setTimeout(function(){
                    var freeshipping_data = window.checkoutConfig.logxstar.pickuppoint.freeshipping_data;
                    var shippingAddress = quote.shippingAddress();
                    if (typeof freeshipping_data[shippingAddress.countryId] != 'undefined') {
                        var free_message = freeshipping_data[shippingAddress.countryId]['free_message'];
                        var free_value = parseFloat(freeshipping_data[shippingAddress.countryId]['free_value']);
                        var includeTax = window.checkoutConfig.logxstar.pickuppoint.freeshipping_tax;
                        var totals = quote.getTotals();
                        var subtotal = parseFloat(totals().subtotal);
                        if (includeTax == '1') {
                            subtotal = parseFloat(totals().subtotal_incl_tax);
                        }
                        if (subtotal < free_value) {
                            var shipping_rates = shippingService.getShippingRates();
                            var rates = shipping_rates();
                            var skip = false;
                            var logxstar_method = 0;
                            var first_logxstar = $('[id^="s_method_"][value^="logxstar_"]').first().closest('.item');
                            var extra_value = free_value - subtotal;
                            var extra_value = priceUtils.formatPrice(extra_value, quote.getPriceFormat());
                            free_message = free_message.replace(/{{extra_value}}/gi, extra_value);

                            if (!$('#logxstar_free_message').length) {
                                first_logxstar.before('<div id="logxstar_free_message" style="color:red;margin-bottom:10px;">' + free_message + '</div>');
                            } else {
                                $('#logxstar_free_message').text(free_message);
                            }
                        }
                    }
                },1000);
            }
        });
    }
);
