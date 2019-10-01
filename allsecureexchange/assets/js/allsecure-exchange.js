(function($){
    var $paymentForm = $('#allsecure_exchange_seamless').closest('form');
    var $paymentFormSubmitButton = $("#place_order");
    var $paymentFormTokenInput = $('#allsecure_exchange_token');
    var $allsecureExchangeErrors = $('#allsecure_exchange_errors');
    var integrationKey = window.integrationKey;
    var initialized = false;

    var init = function() {
        if (integrationKey && !initialized) {
            $paymentFormSubmitButton.prop("disabled", true);
            allsecureExchangeSeamless.init(
                integrationKey,
                function () {
                    $paymentFormSubmitButton.prop("disabled", true);
                },
                function () {
                    $paymentFormSubmitButton.prop("disabled", false);
                });
        }
    };

    $paymentFormSubmitButton.on('click', function (e) {
        allsecureExchangeSeamless.submit(
            function (token) {
                $paymentFormTokenInput.val(token);
                $paymentForm.submit();
            },
            function (errors) {
                errors.forEach(function (error) {
                    $allsecureExchangeErrors.html(error.message);
                    console.error(error);
                });
            });
        return false;
    });

    var allsecureExchangeSeamless = function () {
        var payment;
        var validDetails;
        var validNumber;
        var validCvv;
        var _invalidCallback;
        var _validCallback;
        var $seamlessForm = $('#allsecure_exchange_seamless');
        var $seamlessCardHolderInput = $('#allsecure_exchange_seamless_card_holder', $seamlessForm);
        var $seamlessEmailInput = $('#allsecure_exchange_seamless_email', $seamlessForm);
        var $seamlessExpiryMonthInput = $('#allsecure_exchange_seamless_expiry_month', $seamlessForm);
        var $seamlessExpiryYearInput = $('#allsecure_exchange_seamless_expiry_year', $seamlessForm);
        var $seamlessCardNumberInput = $('#allsecure_exchange_seamless_card_number', $seamlessForm);
        var $seamlessCvvInput = $('#allsecure_exchange_seamless_cvv', $seamlessForm);

        var init = function (integrationKey, invalidCallback, validCallback) {
            _invalidCallback = invalidCallback;
            _validCallback = validCallback;

            if($seamlessForm.length > 0) {
                initialized = true;
            } else {
                return;
            }
            
            $seamlessCardNumberInput.height($seamlessCardHolderInput.css('height'));
            $seamlessCvvInput.height($seamlessCardHolderInput.css('height'));

            $seamlessForm.show();
            var style = {
                'border': $seamlessCardHolderInput.css('border'),
                'height': $seamlessCardHolderInput.css('height'),
                'padding': $seamlessCardHolderInput.css('padding'),
                'font-size': $seamlessCardHolderInput.css('font-size'),
                'color': $seamlessCardHolderInput.css('color'),
                'background': $seamlessCardHolderInput.css('background'),
            };
            payment = new PaymentJs("1.2");
            payment.init(integrationKey, $seamlessCardNumberInput.prop('id'), $seamlessCvvInput.prop('id'), function (payment) {
                payment.setNumberStyle(style);
                payment.setCvvStyle(style);
                payment.numberOn('input', function (data) {
                    validNumber = data.validNumber;
                    validate();
                });
                payment.cvvOn('input', function (data) {
                    validCvv = data.validCvv;
                    validate();
                });
            });
            $('input, select', $seamlessForm).on('input', validate);
        };

        var validate = function () {
            $allsecureExchangeErrors.html('');
            $('.form-group', $seamlessForm).removeClass('has-error');
            $seamlessCardNumberInput.closest('.form-group').toggleClass('has-error', !validNumber);
            $seamlessCvvInput.closest('.form-group').toggleClass('has-error', !validCvv);
            validDetails = true;
            if (!$seamlessCardHolderInput.val().length) {
                $seamlessCardHolderInput.closest('.form-group').addClass('has-error');
                validDetails = false;
            }
            if (!$seamlessExpiryMonthInput.val().length) {
                $seamlessExpiryMonthInput.closest('.form-group').addClass('has-error');
                validDetails = false;
            }
            if (!$seamlessExpiryYearInput.val().length) {
                $seamlessExpiryYearInput.closest('.form-group').addClass('has-error');
                validDetails = false;
            }
            if (validNumber && validCvv && validDetails) {
                _validCallback.call();
                return;
            }
            _invalidCallback.call();
        };

        var reset = function () {
            $seamlessForm.hide();
        };

        var submit = function (success, error) {
            payment.tokenize(
                {
                    card_holder: $seamlessCardHolderInput.val(),
                    month: $seamlessExpiryMonthInput.val(),
                    year: $seamlessExpiryYearInput.val(),
                    email: $seamlessEmailInput.val()
                },
                function (token, cardData) {
                    success.call(this, token);
                },
                function (errors) {
                    error.call(this, errors);
                }
            );
        };

        return {
            init: init,
            reset: reset,
            submit: submit,
        };
    }();

    init();
})(jQuery);
