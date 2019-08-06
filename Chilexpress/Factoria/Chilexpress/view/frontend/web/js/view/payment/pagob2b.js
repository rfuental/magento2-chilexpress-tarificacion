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
                type: 'pagob2b',
                component: 'Factoria_Chilexpress/js/view/payment/method-renderer/pagob2b-method'
            }
        );
        return Component.extend({});
    }
);