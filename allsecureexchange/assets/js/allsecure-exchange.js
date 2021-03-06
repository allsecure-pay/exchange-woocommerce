(function($){
    var $paymentForm = $('#allsecure_exchange_seamless').closest('form');
    var $paymentFormSubmitButton = $("#place_order");
    var $paymentFormTokenInput = $('#allsecure_exchange_token');
	var $allsecureExchangeErrors = $('#allsecure_exchange_errors');
    var integrationKey = window.integrationKey;
    var initialized = false;
	
    var init = function() {
        if (integrationKey && !initialized) {
            $paymentFormSubmitButton.prop("disabled", false);
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
		if(jQuery("form[name='checkout'] input[name='payment_method']:checked").val() == 'allsecure_exchange_creditcard'){
    
		allsecureExchangeSeamless.submit(			
            function (token) {
				$allsecureExchangeErrors.html('');
                $paymentFormTokenInput.val(token);
                $paymentForm.submit();
            },
            function (errors) {

				$allsecureExchangeErrors.html('');
				$allsecureExchangeErrors.append('<ul class="woocommerce-error" style="margin: 0px;">');
				$.each(errors, function(key, value) {
					console.log(value.attribute);
					var fieldname = value.attribute;
					if (fieldname == 'number') {
						$('.woocommerce-error').append('<li><b>!</b> '+window.errorNumber+'</li>');
					} 
					if (fieldname ==  'year') {
						$('.woocommerce-error').append('<li><b>!</b> '+window.errorExpiry+'</li>');
					} 
					if (fieldname ==  'cvv' ) {
						$('.woocommerce-error').append('<li><b>!</b> '+window.errorCvv+'</li>');
					} 
					if (fieldname == 'card_holder') {		
						$('.woocommerce-error').append('<li><b>!</b> '+window.errorName+'</li>');
					}
					$allsecureExchangeErrors.attr("tabindex",-1).focus();
                });
				$allsecureExchangeErrors.append('</ul>');
            });
			return false;
		}
    });

    var allsecureExchangeSeamless = function () {
        var payment;
        var validDetails;
        var validNumber;
        var validCvv;
        var _invalidCallback;
        var _validCallback;
		var $allowedCards = window.allowedCards;
        var $seamlessForm = $('#allsecure_exchange_seamless');
        var $seamlessFirstNameInput = $('#billing_first_name');
		var $seamlessLastNameInput = $('#billing_last_name');
        var $seamlessEmailInput = $('#allsecure_exchange_seamless_email', $seamlessForm);
		var $seamlessExpiryInput = $('#allsecure_exchange_seamless_expiry', $seamlessForm);
        var $seamlessExpiryMonthInput = $('#allsecure_exchange_seamless_expiry_month', $seamlessForm);
        var $seamlessExpiryYearInput = $('#allsecure_exchange_seamless_expiry_year', $seamlessForm);
        var $seamlessCardNumberInput = $('#allsecure_exchange_seamless_card_number', $seamlessForm);
        var $seamlessCvvInput = $('#allsecure_exchange_seamless_cvv', $seamlessForm);
		var $seamlessCardHolderInput = $('#allsecure_exchange_seamless_card_holder', $seamlessForm);	

        var init = function (integrationKey, invalidCallback, validCallback) {
            _invalidCallback = invalidCallback;
            _validCallback = validCallback;

            if($seamlessForm.length > 0) {
                initialized = true;
            } else {
				$seamlessForm.hide();
                return;
            }
			
			$seamlessCardNumberInput.height($seamlessFirstNameInput.css('height'));
			$seamlessCvvInput.height($seamlessFirstNameInput.css('height'));
			
            var style = {
				'border': $seamlessCardHolderInput.css('border'),
				'background' : $seamlessCardHolderInput.css('background'),
				'border-radius': $seamlessCardHolderInput.css('border-radius'),
				'height': '100%',
				'width': '100%',
				'padding': $seamlessCardHolderInput.css('padding'),
				'font-size': $seamlessCardHolderInput.css('font-size'),
				'font-weight': $seamlessCardHolderInput.css('font-weight'),
				'font-family': $seamlessCardHolderInput.css('font-family'),
				'letter-spacing': '0.1px',
				'word-spacing': '1.7px',
				'color': $seamlessCardHolderInput.css('color'),
				'box-shadow': $seamlessCardHolderInput.css('box-shadow'),
				'position': $seamlessCardHolderInput.css('position'),
			};
			var nostyle = {
				'border': 'none',
				'border-style': 'none',
				'border-width': '0px',
				'box-shadow': 'none',
			};
			
            payment = new PaymentJs("1.2");
            payment.init(integrationKey, $seamlessCardNumberInput.prop('id'), $seamlessCvvInput.prop('id'), function (payment) {
				var numberFocused = false;
				var cvvFocused = false;
                payment.setNumberStyle(style);
                payment.setCvvStyle(style);
				payment.numberOn('focus', function (data) {
					numberFocused = false;
					payment.setNumberStyle(nostyle);
                });
				payment.cvvOn('focus', function (data) {
					cvvFocused = false;
					payment.setCvvStyle(nostyle);
                });
                payment.numberOn('input', function (data) {
					console.log(data.cardType);
                    validNumber = data.validNumber;
					validBrand = $allowedCards.includes(data.cardType);
					validate();
                });
                payment.cvvOn('input', function (data) {
                    validCvv = data.validCvv;
                    validate();
                });
				payment.numberOn('blur', function (data) {
					cvvFocused = false;
					payment.setNumberStyle(style);
                });
				payment.cvvOn('blur', function (data) {
					cvvFocused = false;
					payment.setCvvStyle(style);
                });
			});
			$seamlessForm.show();
            $('input, select', $seamlessForm).on('input', validate);
        };

        var validate = function () {
            $('.woocommerce-input-wrapper', $seamlessForm).removeClass('has-error');
            $seamlessCardNumberInput.closest('.woocommerce-input-wrapper').toggleClass('has-error', !validNumber);
            $seamlessCvvInput.closest('.woocommerce-input-wrapper').toggleClass('has-error', !validCvv);
            validDetails = true;
            
            if (!$seamlessExpiryMonthInput.val().length) {
                $seamlessExpiryInput.closest('.woocommerce-input-wrapper').addClass('has-error');
                validDetails = false;
            }
            if (!$seamlessExpiryYearInput.val().length) {
                $seamlessExpiryInput.closest('.woocommerce-input-wrapper').addClass('has-error');
                validDetails = false;
            }
			if (!$seamlessCardHolderInput.val().length) {
				$seamlessCardHolderInput.closest('.woocommerce-input-wrapper').addClass('has-error');
                validDetails = false;
            }
            if (validNumber && validCvv && validBrand && validDetails) {
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
