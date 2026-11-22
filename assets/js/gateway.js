// (function ($) {
//   const stripe = Stripe(wcrd_checkout_params.publishable_key);
//   console.log();

//   let elements = null;
//   let paymentElement = null;
//   let clientSecret = null;

//   /**
//    * Initialize Stripe Elements (NO clientSecret yet)
//    */

//   async function createIntent() {
//     const res = await $.ajax({
//       url: wcrd_checkout_params.ajax_url,
//       type: "POST",
//       data: {
//         action: "create_payment_intent",
//       },
//     });

//     if (!res.success) {
//       throw new Error("Failed to create PaymentIntent");
//     }

//     return res.data.clientSecret;
//   }

//   async function initStripe() {
//     if (elements !== null) return;
//     $("#wcrd-stripe-spinner").removeClass("hidden");
//     try {
//       clientSecret = await createIntent();
//       console.log(clientSecret);

//       elements = stripe.elements({ clientSecret });

//       paymentElement = elements.create("payment", {
//         layout: "accordion",
//       });

//       paymentElement.mount("#wcrd-stripe-payment-element");
//       $("#wcrd-stripe-spinner").addClass("hidden");
//     } catch (err) {
//       console.error(err);
//       showMessage("Unable to load payment form.");
//       $("#wcrd-stripe-spinner").addClass("hidden");
//     }
//   }

//   /**
//    * When payment method is selected
//    */
//   $(document.body).on("change", 'input[name="payment_method"]', function () {
//     if ($(this).val() === "wcrd_stripe") {
//       initStripe();
//     }
//   });

//   $(document.body).on("update_checkout", function () {
//     initStripe();
//   });

//   let paymentConfirmed = false;

//   $(document.body).on("checkout_place_order_wcrd_stripe", function () {
//     if (paymentConfirmed) {
//       return true; // allow WooCommerce now
//     }

//     handlePayment();
//     return false;
//   });

//   async function handlePayment() {
//     try {
//       const result = await stripe.confirmPayment({
//         elements: elements,
//         confirmParams: {
//           return_url: wcrd_checkout_params.checkout_url,
//         },
//       });

//       if (result.error) {
//         showMessage(result.error.message);
//         return;
//       }

//       paymentConfirmed = true;

//       $("form.checkout").submit(); // retry checkout
//     } catch (err) {
//       console.error(err);
//       showMessage("Payment failed.");
//     }
//   }

//   function showMessage(message) {
//     const container = $("#wcrd-stripe-payment-message");
//     container.removeClass("hidden").text(message);
//   }
// })(jQuery);

(function ($) {
  const stripe = Stripe(wcrd_checkout_params.publishable_key);

  let elements = null;
  let paymentElement = null;
  let clientSecret = null;
  let isProcessing = false;

  /**
   * Create PaymentIntent
   */
  async function createIntent() {
    const res = await $.ajax({
      url: wcrd_checkout_params.ajax_url,
      type: "POST",
      dataType: "json",
      data: {
        action: "create_payment_intent",
      },
    });

    if (!res.success) {
      throw new Error(res.data?.message || "Failed to create PaymentIntent");
    }

    return res.data.clientSecret;
  }

  /**
   * Initialize Stripe Elements
   */
  async function initStripe() {
    if (elements !== null) return;

    $("#wcrd-stripe-spinner").removeClass("hidden");

    try {
      clientSecret = await createIntent();

      elements = stripe.elements({ clientSecret });

      paymentElement = elements.create("payment", {
        layout: "accordion",
      });

      paymentElement.mount("#wcrd-stripe-payment-element");
    } catch (err) {
      console.error(err);
      showMessage("Unable to load payment form.");
    }

    $("#wcrd-stripe-spinner").addClass("hidden");
  }

  /**
   * When gateway selected
   */
  $(document.body).on("change", 'input[name="payment_method"]', function () {
    if ($(this).val() === "wcrd_stripe") {
      initStripe();
    }
  });

  $(document.body).on("update_checkout", function () {
    initStripe();
  });

  /**
   * Handle checkout submit
   */
  $(document.body).on("checkout_place_order_wcrd_stripe", function () {
    // prevent multiple clicks
    if (isProcessing) return false;

    if (!elements) {
      showMessage("Payment form not ready.");
      return false;
    }

    isProcessing = true;

    stripe
      .confirmPayment({
        elements: elements,
        confirmParams: {
          return_url: wcrd_checkout_params.return_url,
        },
      })
      .then(function (result) {
        if (result.error) {
          showMessage(result.error.message);
          isProcessing = false;
        }

        // On success → Stripe handles redirect automatically
      });

    return false; // ❗ ALWAYS block WooCommerce submission
  });

  /**
   * UI helper
   */
  function showMessage(message) {
    const container = $("#wcrd-stripe-payment-message");
    container.removeClass("hidden").text(message);
  }
})(jQuery);
