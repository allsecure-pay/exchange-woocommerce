(function($){
    var $paymentForm = $('#allsecure-payment-form').closest('form');
    var $paymentFormSubmitButton = $("#place_order");
    var $paymentFormTokenInput = $('#allsecurepay_transaction_token');
    var integrationKey = window.public_integration_key;
    var initialized = false;
    
    jQuery("form[name='checkout'] input[name='payment_method']").on('click', function (e) {
        if(jQuery("form[name='checkout'] input[name='payment_method']:checked").val() != 'allsecureexchange') {
            $paymentFormSubmitButton.prop("disabled", false);
        }
    });
	
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
                }
            );
        }
    };
	
    $paymentFormSubmitButton.on('click', function (e) {
	if(jQuery("form[name='checkout'] input[name='payment_method']:checked").val() == 'allsecureexchange') {
            var isValid = true;//allsecureExchangeSeamless.validate();
            if (isValid) {
                allsecureExchangeSeamless.submit(			
                    function (token) {
                        $paymentFormTokenInput.val(token);
                        $paymentForm.submit();
                    },
                    function (errors) {
                        jQuery.each(errors, function(key, value) {
                            var errorattribute = value.attribute;
                            var errorkey = value.key;
                            var errormessage = value.message;

                            if (errorattribute == 'integration_key') {
                                let message = '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><div class="woocommerce-error">'+errormessage+'</div></div>';
                                jQuery('.woocommerce-notices-wrapper:first').html(message);
                            } else if (errorattribute == 'number') {
                                jQuery('#allsecurepay_cc_number-error').show();
                            } else if (errorattribute ==  'cvv' ) {
                                jQuery('#allsecurepay_cc_cvv-error').show();
                            } else if (errorattribute == 'card_holder') {
                                if (errorkey == 'errors.blank') {
                                    jQuery('#allsecurepay_cc_name-required-error').show();
                                } else {
                                    jQuery('#allsecurepay_cc_name-invalid-error').show();
                                }
                            } else if (errorattribute == 'month') {
                                if (errorkey == 'errors.blank') {
                                    jQuery('#allsecurepay_expiration-required-error').show();
                                } else {
                                    jQuery('#allsecurepay_expiration-invalid-error').show();
                                }
                            } else if (errorattribute == 'year') {
                                if (errorkey == 'errors.blank') {
                                    jQuery('#allsecurepay_expiration_year-error').show();
                                } else {
                                    jQuery('#allsecurepay_expiration-invalid-error').show();
                                }
                            }
                        });  
                    }
                );
            }
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
	var $allowedCards = window.card_supported;
        var $seamlessForm = $('#allsecure-payment-form');
        var $seamlessFirstNameInput = $('#billing_first_name');
	var $seamlessExpiryInput = $('#allsecurepay_expiration_date', $seamlessForm);
        var $seamlessExpiryMonthInput = $('#allsecurepay_expiration_month', $seamlessForm);
        var $seamlessExpiryYearInput = $('#allsecurepay_expiration_year', $seamlessForm);
        var $seamlessCardNumberInput = $('#allsecurepay_cc_number', $seamlessForm);
        var $seamlessCvvInput = $('#allsecurepay_cc_cvv', $seamlessForm);
	var $seamlessCardHolderInput = $('#allsecurepay_cc_name', $seamlessForm);
        
        var $allowedBins = window.installment_bins;

        var init = function (integrationKey, invalidCallback, validCallback) {
            _invalidCallback = invalidCallback;
            _validCallback = validCallback;

            if($seamlessForm.length > 0) {
                initialized = true;
            } else {
                $seamlessForm.hide();
                return;
            }
            
            $seamlessCardNumberInput.height($seamlessFirstNameInput.innerHeight());
            $seamlessCardNumberInput.css({'border-color':$seamlessFirstNameInput.css('border-left-color'),'border-width': $seamlessFirstNameInput.css('border-width'),'border-style': $seamlessFirstNameInput.css('border-style')}); 
            $seamlessCvvInput.height($seamlessFirstNameInput.innerHeight());
            $seamlessCvvInput.css({'border-color':$seamlessFirstNameInput.css('border-left-color'),'border-width': $seamlessFirstNameInput.css('border-width'),'border-style': $seamlessFirstNameInput.css('border-style')}); 
	
            if ($('#allsecurepay_pay_installment_container').length > 0) {
                var $seamlessInstallmentNumberSelect = $('#allsecurepay_installment_number', $seamlessForm);
                $seamlessInstallmentNumberSelect.height($seamlessFirstNameInput.innerHeight());
                $seamlessInstallmentNumberSelect.css({'border-color':$seamlessFirstNameInput.css('border-left-color'),'border-width': $seamlessFirstNameInput.css('border-width'),'border-style': $seamlessFirstNameInput.css('border-style')}); 
            }
            
            var style = {
                    'border-radius': $seamlessCardHolderInput.css('border-radius'),
                    'border-style': 'none',
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
                    'background' : $seamlessCardHolderInput.css('background-color') + ' ' + $seamlessCardHolderInput.css('background-repeat') + ' ' + $seamlessCardHolderInput.css('background-position') ,
            };
            var nostyle = {
                    'border': 'none',
                    'border-style': 'none',
                    'border-width': '0px',
                    'box-shadow': 'none',
            };
			
            payment = new PaymentJs("1.3");
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
                    validNumber = data.validNumber;
                    validBrand = $allowedCards.includes(data.cardType);
                    installmentHandler(data);
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
            
            $('#allsecurepay_cc_name', $seamlessForm).on('input', validate);
            
            $('#allsecurepay_expiration_date', $seamlessForm).on('input', function(){
                expiryField();
                
                var expiryDate = jQuery("#allsecurepay_expiration_date").val();
                expiryDate = expiryDate.split("/");
                var month = expiryDate[0];
                var year = expiryDate[1];
                jQuery("#allsecurepay_expiration_month").val(month);
                jQuery("#allsecurepay_expiration_year").val(20 + year);
                
                validate();
            });
            
            $('#allsecurepay_pay_installment', $seamlessForm).on('click', function(){
                if ($(this).is(':checked')) {
                    $('#allsecurepay_installment_number_container').show();
                } else {
                    $('#allsecurepay_installment_number_container').hide();
                }
            });
         };
        
        var installmentHandler = function (data) {
            if ($('#allsecurepay_pay_installment_container').length > 0) {
                $allowedBins = $allowedBins.toString().split(",");
                var isInstallmentAllowed = $allowedBins.includes(data.firstSix);
                if (isInstallmentAllowed) {
                    var cardBin = data.firstSix;
                    $('#allsecurepay_pay_installment_container').show();
                    updateInstallmentNumbers(cardBin);
                } else {
                    $('#allsecurepay_pay_installment_container').hide();
                    $('#allsecurepay_installment_number_container').hide();
                    $('#allsecurepay_pay_installment').prop('checked', false);
                    $('#allsecurepay_installment_number').html('');
                }
            }
        };
        
        var updateInstallmentNumbers = function (cardBin) {
            $('#allsecurepay_installment_number').html('');
            var installment_numbers = window.allowed_installments[cardBin];
            if (installment_numbers.length > 0) {
                installment_numbers = installment_numbers.toString().split(",");
                var optionText = '';
                for (installment_number of installment_numbers) {
                    installment_number = installment_number.trim();
                    if (installment_number.length == 1) {
                        installment_number = '0'+installment_number;
                    }
                    var option = '<option value="'+installment_number+'">'+installment_number+'</option>';
                    optionText += option;
                }
                $('#allsecurepay_installment_number').html(optionText);
            }
        };

        var validate = function () {
            validDetails = true;
            
            var cardHolderNameRegex = /^[a-z ,.'-]+$/i;

            jQuery('#allsecurepay_cc_name-required-error').hide();
            jQuery('#allsecurepay_cc_name-invalid-error').hide();

            jQuery('#allsecurepay_expiration-required-error').hide();
            jQuery('#allsecurepay_expiration-invalid-error').hide();
            jQuery('#allsecurepay_expiration_year-error').hide();

            jQuery('#allsecurepay_cc_number-error').hide();
            jQuery('#allsecurepay_cc_cvv-error').hide();
            jQuery('#allsecurepay_cc_number-not-supported-error').hide();

            var cardHolderName = jQuery("#allsecurepay_cc_name").val();
            if(cardHolderName == "") {
                jQuery('#allsecurepay_cc_name-required-error').show()
                validDetails = false;
            } else if(!cardHolderNameRegex.test(cardHolderName)) {
                jQuery('#allsecurepay_cc_name-invalid-error').show()
                validDetails = false;
            }
            
            jQuery('#allsecurepay_cc_number-error').hide();
            jQuery('#allsecurepay_cc_cvv-error').hide();
            jQuery('#allsecurepay_cc_number-not-supported-error').hide();

            if (!validNumber) {
                jQuery('#allsecurepay_cc_number-error').show();
                validDetails = false;
            } else if (!validBrand) {
                jQuery('#allsecurepay_cc_number-not-supported-error').show();
                validDetails = false;
            }
            if (!validCvv) {
                jQuery('#allsecurepay_cc_cvv-error').show();
                validDetails = false;
            }

            jQuery('#allsecurepay_expiration-required-error').hide();
            jQuery('#allsecurepay_expiration-invalid-error').hide();
            jQuery('#allsecurepay_expiration_year-error').hide();

            var cardExpiryDate = jQuery("#allsecurepay_expiration_date").val();
            var cardExpiryMonth = jQuery("#allsecurepay_expiration_month").val();
            var cardExpiryYear = jQuery("#allsecurepay_expiration_year").val();
            
            var expiryValid = true;

            if(cardExpiryDate === "") {
                jQuery('#allsecurepay_expiration-required-error').show();
                validDetails = false;
                expiryValid = false;
            } else {
                if(cardExpiryMonth === "") {
                    jQuery('#allsecurepay_expiration-invalid-error').show();
                    validDetails = false;
                    expiryValid = false;
                } else if(cardExpiryYear === "") {
                    jQuery('#allsecurepay_expiration-invalid-error').show();
                    validDetails = false;
                    expiryValid = false;
                }
            }

            if (expiryValid) {
                var minMonth = new Date().getMonth() + 1;
                var minYear = new Date().getFullYear();
                var month = parseInt(cardExpiryMonth, 10);
                var year = parseInt(cardExpiryYear, 10);

                if ( !( 
                        (year > minYear) || 
                        ((year === minYear) && (month >= minMonth)) 
                      )
                ) {
                    jQuery('#allsecurepay_expiration-invalid-error').show();
                    validDetails = false;
                }
            }
            
            if (validNumber && validCvv && validBrand && validDetails) {
                _validCallback.call();
                return true;
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
                    year: $seamlessExpiryYearInput.val()
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
