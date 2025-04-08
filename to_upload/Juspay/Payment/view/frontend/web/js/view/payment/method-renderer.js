define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'smartgateway',
                component: 'Juspay_Payment/js/view/payment/method-renderer/juspay'
            }
        );
        return Component.extend({});
    }
);
