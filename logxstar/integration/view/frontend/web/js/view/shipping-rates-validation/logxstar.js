/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/shipping-rates-validator',
        'Magento_Checkout/js/model/shipping-rates-validation-rules',
        '../../model/shipping-rates-validator/logxstar',
        '../../model/shipping-rates-validation-rules/logxstar'
    ],
    function (
        Component,
        defaultShippingRatesValidator,
        defaultShippingRatesValidationRules,
        flatrateShippingRatesValidator,
        flatrateShippingRatesValidationRules
    ) {
        "use strict";
        defaultShippingRatesValidator.registerValidator('logxstar', flatrateShippingRatesValidator);
        defaultShippingRatesValidationRules.registerRules('logxstar', flatrateShippingRatesValidationRules);
        return Component;
    }
);
