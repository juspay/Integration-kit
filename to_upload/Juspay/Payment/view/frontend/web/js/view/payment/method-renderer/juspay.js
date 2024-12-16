define([
  "Magento_Checkout/js/view/payment/default",
  "Magento_Checkout/js/model/quote",
  "jquery",
  "ko",
  "Magento_Checkout/js/model/payment/additional-validators",
  "Magento_Checkout/js/action/set-payment-information",
  "mage/url",
  "Magento_Customer/js/model/customer",
  "Magento_Checkout/js/action/place-order",
  "Magento_Checkout/js/model/full-screen-loader",
  "Magento_Ui/js/model/messageList",
  "Magento_Checkout/js/model/shipping-save-processor",
  "Magento_Ui/js/modal/modal",
], function (
  Component,
  quote,
  $,
  ko,
  additionalValidators,
  setPaymentInformationAction,
  url,
  customer,
  placeOrderAction,
  fullScreenLoader,
  messageList,
  shippingSaveProcessor,
  modal
) {
  "use strict";

  return Component.extend({
    defaults: {
      template: "Juspay_Payment/payment/juspay",
      juspayServiceLoaded: false,
      juspay_response: [],
    },

    getClientId: function () {
      return window.checkoutConfig.payment.juspay.client_id;
    },

    context: function () {
      return this;
    },

    getCode: function () {
      return "juspay";
    },

    isActive: function () {
      return true;
    },

    juspayPayment: function (context, event) {
      if (!additionalValidators.validate()) {
        return false;
      }

      var self = this,
        billing_address;

      fullScreenLoader.startLoader();
      this.messageContainer.clear();

      this.amount = quote.totals()["base_grand_total"] * 100;
      billing_address = quote.billingAddress();

      this.user = {
        name: billing_address.firstname + " " + billing_address.lastname,
        contact: billing_address.telephone,
      };

      if (!customer.isLoggedIn()) {
        this.user.email = quote.guestEmail;
      } else {
        this.user.email = customer.customerData.email;
      }

      $.ajax({
        type: "POST",
        url: url.build(
          "juspay_payment/standard/order?" +
            generateSecureRandomString(10)
        ),
        data: {
          email: this.user.email,
          billing_address: JSON.stringify(quote.billingAddress()),
        },
        success: function (response) {
          fullScreenLoader.stopLoader();
          var redirectUrl = response.redirect_url;

          if (redirectUrl) {
            window.location.href = redirectUrl;
          } else {
            console.error("Redirect URL not found in the response.");
          }
        },
        error: function (xhr, status, error) {
          fullScreenLoader.stopLoader();
          console.error("Error executing order: ", error);
        },
      });

      return;
    },
  });
});

function generateSecureRandomString(length) {
    const array = new Uint8Array(length);
    window.crypto.getRandomValues(array);
    return Array.from(array, byte => byte.toString(36)).join('').substring(0, length);
}
