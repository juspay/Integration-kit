// app/code/Juspay/Payment/view/frontend/web/js/cancel-handler.js
define(['jquery','mage/url'], function($, url) {
  'use strict';

  function cancelOrder() {
    $.ajax({
      url: url.build('juspay_payment/standard/cancel'),
      type: 'POST'
    });
  }

  // On initial load, if we’re already on checkout#payment, cancel any pending order:
  $(function() {
    if (location.pathname.indexOf('/checkout') === 0 && location.hash === '#payment') {
      cancelOrder();
    }
  });

  // Also catch back/forward navigation:
  window.addEventListener('popstate', function() {
    if (location.pathname.indexOf('/checkout') === 0 && location.hash === '#payment') {
      cancelOrder();
    }
  });
});
