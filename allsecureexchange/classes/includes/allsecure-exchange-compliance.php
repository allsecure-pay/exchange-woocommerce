<?php
/* Add the gateway footer to WooCommerce */
	function allsecureexchange_footer() {
		$selected_allsecure = new WC_AllsecureExchange_CreditCard;
		$selectedBanner = $selected_allsecure->get_selected_banner();
		$selectedCards = $selected_allsecure->get_selected_cards();
		$selectedBank = $selected_allsecure->get_merchant_bank();
		if (strpos($selectedCards, 'VISA') !== false) { $visa =  '<img src="' . plugins_url(). '/allsecureexchange/assets/images/'.$selectedBanner.'/visa.svg">';} else $visa = '';
		if (strpos($selectedCards, 'MASTERCARD') !== false) { $mastercard = '<img src="' . plugins_url(). '/allsecureexchange/assets/images/'.$selectedBanner.'/mastercard.svg">';} else $mastercard = '';
		if (strpos($selectedCards, 'MAESTRO') !== false) { $maestro = '<img src="' . plugins_url(). '/allsecureexchange/assets/images/'.$selectedBanner.'/maestro.svg">';} else $maestro = '';
		if (strpos($selectedCards, 'AMEX') !== false) {$amex = '<img src="' . plugins_url(). '/allsecureexchange/assets/images/'.$selectedBanner.'/amex.svg">';} else $amex = '';
		if (strpos($selectedCards, 'DINERS') !== false) {$diners = '<img src="' . plugins_url(). '/allsecureexchange/assets/images/'.$selectedBanner.'/diners.svg">';} else $diners = '';
		if (strpos($selectedCards, 'JCB') !== false) {$jcb = '<img src="' . plugins_url(). '/allsecureexchange/assets/images/'.$selectedBanner.'/jcb.svg">';} else $jcb = '';
		if (strpos($selectedCards, 'DINACARD') !== false) {$dinacard = '<img src="' . plugins_url(). '/allsecureexchange/assets/images/'.$selectedBanner.'/dinacard.svg">';} else $dinacard = '';
		if (strpos($selectedCards, 'DISCOVER') !== false) {$discover = '<img src="' . plugins_url(). '/allsecureexchange/assets/images/'.$selectedBanner.'/discover.svg">';} else $discover = '';
		$allsecure  = '<a href="https://www.allsecure.rs" target="_new"><img src="' . plugins_url(). '/allsecureexchange/assets/images/'.$selectedBanner.'/allsecure.svg"></a>'; 
		if ($selectedBank == 'hbm') {
			$bankUrl = 'https://www.hipotekarnabanka.com/'; 
		} else if ($selectedBank == 'aik') {
			$bankUrl = 'https://www.aikbanka.rs/'; 
		} else if ($selectedBank == 'bib') {
			$bankUrl = 'https://www.bancaintesa.rs/'; 
		} else if ($selectedBank == 'nlb-mne') {
			$bankUrl = 'https://www.nlb.me/'; 
		} else if ($selectedBank == 'ckb') {
			$bankUrl = 'https://www.ckb.me/'; 
		} else {
			$bankUrl = '#';
		}
		$bank = '<a href="'.$bankUrl.'" target="_new" ><img src="' . plugins_url(). '/allsecureexchange/assets/images/'.$selectedBanner.'/'.$selectedBank.'.svg"></a>';
		$vbv = '<img src="' . plugins_url(). '/allsecureexchange/assets/images/'.$selectedBanner.'/visa_secure.svg">';
		$mcsc = '<img src="' . plugins_url(). '/allsecureexchange/assets/images/'.$selectedBanner.'/mc_idcheck.svg">';
		$allsecure_cards = $visa.''.$mastercard.''.$maestro.''.$diners.''.$amex.''.$jcb.''.$dinacard ;
		/* $allsecure_cards = $visa.''.$mastercard.''.$maestro.''.$diners.''.$amex.''.$jcb.''.$dinacard ; */
		if ($selectedBanner !== 'none') {
			if ($selectedBank == 'none')  {
				$allsecure_banner = '<div id="allsecure_exchange_banner"><div class="allsecure">'.$allsecure.'</div><div class="allsecure_threeds">'.$vbv.' '.$mcsc.'</div><div class="allsecure_cards">'.$allsecure_cards.'</div></div>';
			} else if ($selectedBank == 'bib') {	
				$allsecure_banner = '<div id="allsecure_exchange_banner"><div class="allsecure">'.$allsecure.'</div><div class="allsecure_threeds"><a href="https://rs.visa.com/pay-with-visa/security-and-assistance/protected-everywhere.html" target="_blank">'.$vbv.'</a> <a href="http://www.mastercard.com/rs/consumer/credit-cards.html" target="_blank">'.$mcsc.'</a></div><div class="allsecure_bank">'.$bank.'</div><div class="allsecure_cards">'.$allsecure_cards.'</div></div>';
			} else {
				$allsecure_banner = '<div id="allsecure_exchange_banner"><div class="allsecure">'.$allsecure.'</div><div class="allsecure_threeds">'.$vbv.' '.$mcsc.'</div><div class="allsecure_bank">'.$bank.'</div><div class="allsecure_cards">'.$allsecure_cards.'</div></div>';
			}	
			wp_enqueue_style( 'allsecure_style', plugins_url(). '/allsecureexchange/assets/css/allsecure-exchange-style.css', array(), null );
			echo  $allsecure_banner;
		}
	}
	add_filter('wp_footer', 'allsecureexchange_footer'); 