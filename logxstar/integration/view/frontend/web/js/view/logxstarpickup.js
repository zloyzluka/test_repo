/* release 1.0.6*/
define(
    [
        'jquery',
        'uiComponent',
        'Magento_Checkout/js/model/quote',
        'uiRegistry',
        'Magento_Checkout/js/model/shipping-rates-validator',
        'Magento_Checkout/js/model/shipping-service',
        'Magento_Catalog/js/price-utils',
    ],
    function ( $, Component, quote, registry, shippingRatesValidator,shippingService, priceUtils ) {
        "use strict";

        var checkoutConfig = window.checkoutConfig;
        var component = null;
        var eventMethod = window.addEventListener ? "addEventListener" : "attachEvent";
        var eventer = window[eventMethod];
        var messageEvent = eventMethod == "attachEvent" ? "onmessage" : "message";

        var shippingPostCode = '';
        var shippingName = '';
        var selected_method = {};
        var iframeDomain = "https://os.logxstar.com";
        var iframeUrl = iframeDomain + "/seoshopapp/external/v1_1";
        var topPosition = 0;

        return Component.extend({
            
            initialize: function () {
                this._super();
                shippingService.isLoading.subscribe(this.addFreeShippingMessage, this);
                shippingService.isLoading.subscribe(this.hideShippingMethods, this);

                this.shippingMethodsUpdateFix(0);
                this.initIframeListener();
                this.initSelectMethods();
                component = this;

            },
            initSelectMethods() {
                jQuery(document).on('click',".table-checkout-shipping-method tr",function(){
                    var id = jQuery(this).find('input').attr('value');
                    var parts = id.split('__');
                    //var carrier_data = parts[2].split('_');
                    if(parts[parts.length-1] != 'lgxspickup' && parts[parts.length-1] != 'logxstardelivery') {
                        
                        return;
                    } else {
                        var carrier_type = 'pickup';
                        if(parts[parts.length-1] == 'logxstardelivery'){
                            carrier_type = 'lgxtframe';
                        } 
                    }
                    var carrier_code = parts[1];
                    var carrier_id   = parts[3];
                    
                    component.openiframe(carrier_code, carrier_id, carrier_type);
                
                });
            },

            initIframeListener() {
                eventer(messageEvent, function (e) {
                    if(e.origin == iframeDomain){
                        
                        var data = e.data;
                        
                        
                        if(data.id != undefined) {
                            selected_method = data; 
                            var el = jQuery('td[id*="__'+data.id+'__"].col.col-method');
                            topPosition = jQuery("#opc-shipping_method").position().top
                            jQuery(".table-checkout-shipping-method").find('.gui-field-content').remove();
                            jQuery('<small class="gui-field-content"><br/>'+data.text+'</small>').appendTo(el);

                        }
                        if(data.point_id != undefined) {
                            jQuery.ajax({
                            url:checkoutConfig.logxstar.pickuppoint.ajaxgetpoints_save,
                                data: {'point':data.point_id},
                                method:'POST'
                            }).done(function (response) {

                            }).fail(function (data) {

                            }).always(function () {
                               
                            });
                        }
                        if(data.date != undefined) {
                            $.ajax({
                                url:checkoutConfig.logxstar.pickuppoint.ajaxdeliverydate_save,
                                data: {'time':'','date':data.date,'carrier_id':data.id},
                                method:'POST'
                            }).done(function (response) {

                            }).fail(function (data) {

                            }).always(function () {

                            });
                            
                        }
                        if(data.close == true) {
                            component.closeiframe();  
                        }
                    }
                }, false);
            },
            
            
            shippingMethodsUpdateFix:function(i){ //fix onestepcheckout problem - shipping methods do not update on postcode change
                var self = this;
                
                if (i<30 && !self.postcode_field){
                    setTimeout(function(){
                        var tmp = jQuery('input[name="postcode"]');
                        if (!tmp.length){
                            self.shippingMethodsUpdateFix(i++);
                        } else {
                            self.postcode_field = tmp;
                            var e = registry.get('checkout.steps.shipping-step.shippingAddress.shipping-address-fieldset');
                            var d = e.elems();
                            shippingRatesValidator.bindChangeHandlers(d);
                        }
                    },1000);
                }
            },
            openiframe:function(carrier_code, carrier_id, carrier_type){
                component.initData(); 
                var data = {
                    'zipcode' : shippingPostCode,
                    'userName' : shippingName,
                    //'price':,
                    'type': carrier_type,
                    'carrier_id':carrier_id,
                    'carrier_code':carrier_code,
                };
                
                var iframeUrl = iframeDomain + "/seoshopapp/external/v1_1"+'?'+jQuery.param(data);
       
                jQuery('<iframe/>', {
                    id: 'logxstar-iframe',
                    'style':'position: fixed; border:none; margin:0; padding:0; overflow:hidden; z-index:999999; top:0; left:0; bottom:0; right:0; width: 100%; height:100%;',
                    src: iframeUrl
                }).appendTo('body');
                selected_method = {};
                window.scrollTo(0, 0);

                },
            closeiframe:function(){
                $([document.documentElement, document.body]).animate({
                    scrollTop: topPosition
                }, 500);

                $('#logxstar-iframe').remove();
            },
            initData:function(){
        
                if (typeof jQuery('input[name="postcode"]').val() != 'number') {
                    shippingPostCode = jQuery('input[name="postcode"]').val().replace(' ','').toUpperCase();
                } else {
                    shippingPostCode = jQuery('input[name="postcode"]').val();
                }
                shippingName = '';    
            },

            shippingMethodUpdate: function(method){
                
                
            },
            hideShippingMethods: function() {
                
            },
            addFreeShippingMessage: function(){
                clearTimeout(freeMessageTimer);
                var freeMessageTimer = setTimeout(function() {
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
                            var first_logxstar = $('[id^="s_method_logxstar_"]').first().closest('tr');
                            var extra_value = free_value - subtotal;
                            var extra_value = priceUtils.formatPrice(extra_value, quote.getPriceFormat());
                            free_message = free_message.replace(/{{extra_value}}/gi, extra_value);

                            if (!jQuery('#logxstar_free_message').length) {
                                first_logxstar.before('<tr><td colspan="4" style="color:red"  id="logxstar_free_message">' + free_message + '</td></tr>');
                            } else {
                                jQuery('#logxstar_free_message').text(free_message);
                            }
                        }
                    }
                },1000);
            }
            
        });
    }
);
