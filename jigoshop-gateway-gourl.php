<?php
/*
Plugin Name: 		GoUrl Jigoshop - Bitcoin Payment Gateway Processor
Plugin URI: 		https://gourl.io/bitcoin-payments-jigoshop.html
Description: 		Provides a <a href="https://gourl.io">GoUrl.io</a> Payment Gateway for Jigoshop 1.12+. Support product prices in Bitcoin/Altcoins directly and sends the amount straight to your business Bitcoin/Altcoin wallet. Convert your USD/EUR/etc prices to cryptocoins using Google/Cryptsy Exchange Rates. Direct Integration on your website, no external payment pages opens (as other payment gateways offer). Accept Bitcoin, Litecoin, Dogecoin, Speedcoin, Darkcoin, Vertcoin, Reddcoin, Feathercoin, Vericoin, Potcoin payments online. You will see the bitcoin/altcoin payment statistics in one common table on your website. No Chargebacks, Global, Secure. All in automatic mode.
Version: 			1.0.0
Author: 			GoUrl.io
Author URI: 		https://gourl.io
License: 			GPLv2
License URI: 		http://www.gnu.org/licenses/gpl-2.0.html
GitHub Plugin URI: 	https://github.com/cryptoapi/Bitcoin-Payments-Jigoshop
*/


if (!defined( 'ABSPATH' )) exit; // Exit if accessed directly in wordpress


add_action( 'plugins_loaded', 'gourl_jigoshop_gateway_load', 0 );


function gourl_jigoshop_gateway_load() 
{
	
	// Jigoshop 1.12+ required
	if (!class_exists('jigoshop_payment_gateway') || true === version_compare(JIGOSHOP_VERSION, '1.12', '<')) return;

	
	DEFINE('GOURLJI', "gourljigoshop");
	
	add_filter( 'jigoshop_payment_gateways', 			'gourl_jigoshop_gateway_add' );
	add_filter( 'plugin_action_links', 					'gourl_jigoshop_action_links', 10, 2 );
	add_filter( 'jigoshop_currencies', 					'gourl_jigoshop_currency' );
	add_filter( 'jigoshop_currency_symbol', 			'gourl_jigoshop_currency_symbol', 10, 2);
	
	
	
	
	/*
	 *	1. 
	 */
	function gourl_jigoshop_gateway_add( $methods ) 
	{
		if (!in_array('Jigoshop_Gateway_GoUrl', $methods)) {
			$methods[] = 'Jigoshop_Gateway_GoUrl';
		}
		return $methods;
	}

	

	
	/*
	 *	2. 
	 */
	function gourl_jigoshop_action_links($links, $file) 
	{
		static $this_plugin;
	
		if (false === isset($this_plugin) || true === empty($this_plugin)) {
			$this_plugin = plugin_basename(__FILE__);
		}
	
		if ($file == $this_plugin) {
			$settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=jigoshop_settings&tab=payment-gateways#gourl_gateway">'.__( 'Settings', GOURLJI ).'</a>';
			array_unshift($links, $settings_link);
			
			if (defined('GOURL'))
			{
				$unrecognised_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page='.GOURL.'payments&s=unrecognised">'.__( 'Unrecognised', GOURLJI ).'</a>';
				array_unshift($links, $unrecognised_link);
				$payments_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page='.GOURL.'payments&s=jigoshop">'.__( 'Payments', GOURLJI ).'</a>';
				array_unshift($links, $payments_link);
			}
		}
		
		return $links;
	}
	
	

	
	/*
	 *	3.
	*/
	
	function gourl_jigoshop_currency ( $currencies ) 
	{
		global $gourl; 
		
		if (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN') && is_object($gourl))
		{
			$arr = $gourl->coin_names(); 
		
			foreach ($arr as $k => $v)
				$currencies[$k] = __( "Cryptocurrency",  GOURLJI ) . " - " . __( ucfirst($v),  GOURLJI );
		}
		
		return $currencies;
	}
	
	
	
	
	/*
	 *	4.
	*/
	function gourl_jigoshop_currency_symbol ( $currency_symbol, $currency )
	{
		global $gourl;
	
		if (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN') && is_object($gourl))
		{
			$arr = $gourl->coin_names();
	
			if (isset($arr[$currency])) $currency_symbol = " " . $currency . " "; 
		}
	
		return $currency_symbol;
	}	
	
	
	
	
	/*
	 *	5. Payment Gateway Jigoshop Class 
	 */
	class Jigoshop_Gateway_GoUrl extends jigoshop_payment_gateway 
	{
		
		private $payments 			= array();
		private $coin_names			= array();
		private $languages 			= array();
		private $statuses 			= array('processing' => 'Processing Payment', 'on-hold' => 'On Hold', 'completed' => 'Completed');
		private $mainplugin_url		= "/wp-admin/plugin-install.php?tab=search&type=term&s=GoUrl+Bitcoin+Payment+Gateway+Downloads";
		
		
		/*
		 * 5.1
		*/
	    public function __construct() 
	    {
	    	global $gourl;
	    	
	    	parent::__construct();
	    	
			$this->id                 	= 'gourlpayments';
			$this->icon         	  	= plugin_dir_url( __FILE__ ).'gourlpayments.png';
			$this->has_fields         	= false;

			// Define user set variables
			$this->enabled      		= Jigoshop_Base::get_options()->get( GOURLJI.'enabled' );
			$this->title        		= Jigoshop_Base::get_options()->get( GOURLJI.'title' );
			$this->description  		= Jigoshop_Base::get_options()->get( GOURLJI.'description' );
			$this->emultiplier  		= Jigoshop_Base::get_options()->get( GOURLJI.'emultiplier' );
			$this->ostatus  			= Jigoshop_Base::get_options()->get( GOURLJI.'ostatus' );
			$this->ostatus2  			= Jigoshop_Base::get_options()->get( GOURLJI.'ostatus2' );
			$this->deflang  			= Jigoshop_Base::get_options()->get( GOURLJI.'deflang' );
			$this->defcoin  			= Jigoshop_Base::get_options()->get( GOURLJI.'defcoin' );
			$this->iconwidth  			= str_replace("px", "", Jigoshop_Base::get_options()->get( GOURLJI.'iconwidth' ));
			
			// GoUrl Crypto currency
			if (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN') && is_object($gourl))
			{ 
				$this->payments 		= $gourl->payments(); 		// Activated Payments
				$this->coin_names		= $gourl->coin_names(); 	// All Coins
				$this->languages		= $gourl->languages(); 		// All Languages
			}
					
			// Re-check
			if (!$this->title)								$this->title 		= __('GoUrl Bitcoin/Altcoins', GOURLJI);
			if (!$this->description)						$this->description 	= __('Pay with virtual currency', GOURLJI);
			if (!isset($this->statuses[$this->ostatus])) 	$this->ostatus  	= 'processing';
			if (!isset($this->statuses[$this->ostatus2])) 	$this->ostatus2 	= 'completed';
			if (!isset($this->languages[$this->deflang])) 	$this->deflang 		= 'en';
			
			if (!$this->emultiplier || !is_numeric($this->emultiplier) || $this->emultiplier < 0.01) 	$this->emultiplier = 1;
			if (!is_numeric($this->iconwidth) || $this->iconwidth < 30 || $this->iconwidth > 250) 		$this->iconwidth = 60;
				
			if ($this->defcoin && $this->payments && !isset($this->payments[$this->defcoin])) $this->defcoin = key($this->payments);
			elseif (!$this->payments)						$this->defcoin		= '';
			elseif (!$this->defcoin)						$this->defcoin		= key($this->payments);

			// Hooks
			add_action( 'thankyou_gourlpayments', array( $this, 'cryptocoin_payment' ) );
			
			return true;
	    }


	    
	    /*
	     * 5.2
	    */
	    protected function get_default_options() 
	    {
	    	global $gourl;
	    	
	    	$defaults 	= array();
	    	$coins		= "";
	    	
	    	if (class_exists('gourlclass') && defined('GOURL') && is_object($gourl))
	    	{
    			$this->payments 	= $gourl->payments(); 		// Activated Payments
    			$this->coin_names	= $gourl->coin_names(); 	// All Coins
    			$this->languages	= $gourl->languages(); 		// All Languages
    			
    			$coins 	= implode(", ", $this->payments);
	    		$url	= GOURL_ADMIN.GOURL."settings";
	    		$text 	= ($coins) ? $coins : __( '- Please setup -', GOURLJI );
	    		$url2	= GOURL_ADMIN.GOURL."payments&s=gourljigoshop";
	    		$url3	= GOURL_ADMIN.GOURL;
	    	}
	    	else
	    	{
	    		$url	= $this->mainplugin_url;
	    		$text 	= __( 'Please install GoUrl Bitcoin Gateway WP Plugin &#187;', GOURLJI );
	    		$url2	= $url;
	    		$url3	= $url;
	    	}
	    	
	    	$method_title       	 = "<div id='gourl_gateway'>" . __( 'GoUrl Bitcoin/Altcoins', GOURLJI ) . "</div>";
	    	$method_description  	 = "<img style='float:left; margin-right:15px' src='".plugin_dir_url( __FILE__ )."gourlpayments.png'>";
	    	$method_description  	.= __( '<a target="_blank" href="https://gourl.io/bitcoin-payments-jigoshop.html">Plugin Homepage &#187;</a>', GOURLJI ) . "<br>";
	    	$method_description  	.= __( '<a target="_blank" href="https://github.com/cryptoapi/Bitcoin-Payments-Jigoshop">Plugin on Github - 100% Free Open Source &#187;</a>', GOURLJI ) . "<br><br>";
	    	$method_description  	.= __( 'Accept Bitcoin, Litecoin, Dogecoin, Speedcoin, Darkcoin, Vertcoin, Reddcoin, Feathercoin, Vericoin, Potcoin payments online in Jigoshop.', GOURLJI ).'<br/>';
	    	
	    	// Requirements
	    	if (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN') && is_object($gourl))
	    	{
	    		if (true === version_compare(GOURL_VERSION, '1.2.3', '<'))
	    		{
	    			$method_description .= '<div class="error"><p>' .sprintf(__( '<b>Your GoUrl Bitcoin Gateway <a href="%s">Main Plugin</a> version is too old. Requires 1.2.3 or higher version. Please <a href="%s">update</a> to latest version.</b>  &#160; &#160; &#160; &#160; Information: &#160; <a href="https://gourl.io/bitcoin-wordpress-plugin.html">Plugin Homepage</a> &#160; &#160; &#160; <a href="https://wordpress.org/plugins/gourl-bitcoin-payment-gateway-paid-downloads-membership/">WordPress.org Plugin Page</a>', GOURLJI ), GOURL_ADMIN.GOURL, $this->mainplugin_url).'</p></div>';
	    		}
	    		elseif (true === version_compare(JIGOSHOP_VERSION, '1.12', '<'))
	    		{
	    			$method_description .= '<div class="error"><p><b>' .__( 'Your Jigoshop version is too old. The GoUrl payment plugin requires Jigoshop 1.12 or higher to function. Please contact your web server administrator for assistance.', GOURLJI ).'</b></p></div>';
	    		}
	    		else
	    		{
	    			$method_description .= __( 'If you use multiple stores, please create separate <a target="_blank" href="https://gourl.io/editrecord/coin_boxes/0">GoUrl Payment Box</a> (with unique payment box public/private keys) for each of your stores/websites. Do not use the same GoUrl Payment Box with the same public/private keys on your different websites/stores.', GOURLJI );
	    		}
	    		
	    		$method_description .= '<br/><br/>';
	    	}
	    	else
	    	{
	    		$method_description .= '<div class="error"><p>' .sprintf(__( '<b>You need to install GoUrl Bitcoin Gateway Main Plugin also. Go to - <a href="%s">Bitcoin Gateway plugin page</a></b> &#160; &#160; &#160; &#160; Information: &#160; <a href="https://gourl.io/bitcoin-wordpress-plugin.html">Plugin Homepage</a> &#160; &#160; &#160; <a href="https://wordpress.org/plugins/gourl-bitcoin-payment-gateway-paid-downloads-membership/">WordPress.org Plugin Page</a> ', GOURLJI ), $this->mainplugin_url).'</p></div>';
	    	}
	    		
	    	
	    	$defaults[] = array( 
	    			'name' 		=> $method_title, 
	    			'type' 		=> 'title', 
	    			'desc' => $method_description 
	    	);
	    
	    	
	    	$defaults[] = array(
	    			'id' 		=> GOURLJI.'enabled',
	    			'name'		=> __('Enable GoUrl Bitcoin/Altcoins Payments', GOURLJI),
	    			'desc' 		=> sprintf(__( 'Enable Bitcoin/Altcoins Payments in Jigoshop with <a href="%s">GoUrl Bitcoin Gateway</a>', GOURLJI ), $url3),
	    			'tip' 		=> '',
	    			'std' 		=> 'no',
	    			'type' 		=> 'checkbox',
	    			'choices'	=> array(
	    					'no'			=> __('No',  GOURLJI),
	    					'yes'			=> __('Yes',  GOURLJI)
	    			)
	    	);
	    
	    	$defaults[] = array(
	    			'id' 		=> GOURLJI.'title',
	    			'name'		=> __('Title', GOURLJI),
	    			'desc' 		=> __( 'Payment method title that the customer will see on your checkout', GOURLJI ),
	    			'tip' 		=> '',
	    			'std' 		=> __( 'Bitcoin/Altcoin', GOURLJI ),
	    			'type' 		=> 'midtext'
	    	);
	    
	    	$defaults[] = array(
	    			'id' 		=> GOURLJI.'description',
	    			'name'		=> __('Description', GOURLJI),
	    			'desc' 		=> __( 'Payment method description that the customer will see on your checkout', GOURLJI ),
	    			'tip' 		=> '',
	    			'std' 		=> trim(sprintf(__( 'Pay with virtual currency - %s', GOURLJI ), $coins), " -"),
	    			'type' 		=> 'textarea'
	    	);
	    
	    	$defaults[] = array(
	    			'id' 		=> GOURLJI.'emultiplier',
	    			'name'		=> __('Exchange Rate Multiplier', GOURLJI),
	    			'desc' 		=> sprintf(__('The system uses the multiplier rate with today LIVE cryptocurrency exchange rates (updated every 30 minutes) when calculating from fiat currency (USD, EUR, etc) to %s. <br />Example: <b>1.05</b> - will add an extra 5%% to the total price in bitcoin/altcoins, <b>0.85</b> - 15%% discount for the price in bitcoin/altcoins. Default: 1.00 ', GOURLJI ), $coins),
	    			'tip' 		=> '',
	    			'std' 		=> '1.00',
	    			'type' 		=> 'text'
	    	);
	    
	    	$defaults[] = array(
	    			'id' 		=> GOURLJI.'ostatus',
	    			'name'		=> __('Order Status - Cryptocoin Payment Received', GOURLJI ),
	    			'desc' 		=> sprintf(__("Payment received successfully from the customer. You will see the bitcoin/altcoin payment statistics in one common table <a href='%s'>'All Payments'</a> with details of all received payments.<br/>If you sell digital products / software downloads you can use the status 'Completed' showing that customer has instant access to your digital products", GOURLJI), $url2),
	    			'tip' 		=> '',
	    			'std' 		=> 'processing',
	    			'choices' 	=> $this->statuses,
	    			'type' 		=> 'select'
	    	);

	    	$defaults[] = array(
	    			'id' 		=> GOURLJI.'ostatus2',
	    			'name'		=> __('Order Status - Previously Received Payment Confirmed', GOURLJI ),
	    			'desc' 		=> __("About one hour after payment is received, the bitcoin transaction should get 6 confirmations (for other cryptocoins ~ 20-30min).<br>Transaction confirmation is needed to prevent double spending of the same money.", GOURLJI),
	    			'tip' 		=> '',
	    			'std' 		=> 'completed',
	    			'choices' 	=> $this->statuses,
	    			'type' 		=> 'select'
	    	);

	    	$defaults[] = array(
	    			'id' 		=> GOURLJI.'deflang',
	    			'name'		=> __('PaymentBox Language', GOURLJI ),
	    			'desc' 		=> __("Default Crypto Payment Box Localisation", GOURLJI),
	    			'tip' 		=> '',
	    			'std' 		=> 'en',
	    			'choices' 	=> ($this->languages?$this->languages:array( __("- not available -", GOURLJI ))),
	    			'type' 		=> 'select'
	    	);

	    	$defaults[] = array(
	    			'id' 		=> GOURLJI.'defcoin',
	    			'name'		=> __('PaymentBox Default Coin', GOURLJI ),
	    			'desc' 		=> sprintf(__( 'Default Coin in Crypto Payment Box. &#160; Activated Payments : <a href="%s">%s</a>', GOURLJI ), $url, $text),
	    			'tip' 		=> '',
	    			'std' 		=> key($this->payments),
	    			'choices' 	=> ($this->payments?$this->payments:array( __("- not available -", GOURLJI ))),
	    			'type' 		=> 'select'
	    	);

	    	$defaults[] = array(
	    			'id' 		=> GOURLJI.'iconwidth',
	    			'name'		=> __( 'Icon Width', GOURLJI ),
	    			'desc' 		=> __( 'Cryptocoin icons width in "Select Payment Method". Default 60px. Allowed: 30..250px', GOURLJI ),
	    			'tip' 		=> '',
	    			'std' 		=> '60px',
	    			'type' 		=> 'text'
	    	);
	    	
	    
	    	return $defaults;
	    }
	     
	
	    
	    
	    
	    /*
	     * 5.3
	    */
	    public function payment_fields() 
	    {
			if ($this->description) echo wpautop(wptexturize($this->description));
		}
	
	
		
		
	    /*
	     * 5.4
	    */
	    public function convert_currency($from_Currency, $to_Currency, $amount) 
		{
		    $amount = urlencode($amount);
		    $from_Currency = urlencode($from_Currency);
		    $to_Currency = urlencode($to_Currency);
		
		    $url = "https://www.google.com/finance/converter?a=".$amount."&from=".$from_Currency."&to=".$to_Currency;
		
		    $ch = curl_init();
		    $timeout = 20;
		    curl_setopt ($ch, CURLOPT_URL, $url);
		    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
		    curl_setopt ($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0)");
		    curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		    curl_setopt ($ch, CURLOPT_TIMEOUT, $timeout);
		    $rawdata = curl_exec($ch);
		    curl_close($ch);
		    $data = explode('bld>', $rawdata);
		    $data = explode($to_Currency, $data[1]);
		
		    return round($data[0], 2);
		}
	    
	    
	    
	    
	    
    /*
     * 5.5 Output for the order received page.
     */
    public function cryptocoin_payment( $order_id )
	{
		global $gourl;
		
		$order = new jigoshop_order( $order_id );
		
		$order_currency = get_post_meta($order->id, 'currency', true);
		if (!in_array(strlen($order_currency), array(3, 4)) || strtoupper($order_currency) != $order_currency) $order_currency = trim(Jigoshop_Base::get_options()->get('jigoshop_currency'));
		
		if ($order === false) throw new Exception('The GoUrl payment plugin was called to process a payment but could not retrieve the order details for order_id ' . $order_id . '. Cannot continue!');
		
		if ($order->status == "cancelled")
		{
			echo '<h2>' . __( 'Information', GOURLJI ) . '</h2>' . PHP_EOL;
			echo "<div class='error'>". __( 'This order&rsquo;s status is &ldquo;Cancelled&rdquo;&mdash;it cannot be paid for. Please contact us if you need assistance.', GOURLJI )."</div>";
		}
		elseif (!$this->payments || !$this->defcoin || true === version_compare(JIGOSHOP_VERSION, '1.12', '<') || true === version_compare(GOURL_VERSION, '1.2.3', '<') || 
				(array_key_exists($order_currency, $this->coin_names) && !array_key_exists($order_currency, $this->payments)))
		{
			echo '<h2>' . __( 'Information', GOURLJI ) . '</h2>' . PHP_EOL;
			echo  "<div class='error'>".sprintf(__( 'Sorry, but there was an error processing your order. Please try a different payment method or contact us if you need assistance. (GoUrl Bitcoin Plugin not configured - %s not activated)', GOURLJI ),(!$this->payments || !$this->defcoin?$this->title:$this->coin_names[$order_currency]))."</div>";
		}
		else 
		{ 	
			$plugin			= "gourljigoshop";
			$amount 		= $order->order_total; 	
			$currency 		= $order_currency; 
			$orderID		= 'order'.$order->id.'_'.str_replace('order_', '', $order->order_key);
			$userID			= $order->user_id;
			$period			= "NOEXPIRY";
			$language		= $this->deflang;
			$coin 			= $this->coin_names[$this->defcoin];
			$affiliate_key 	= "gourl";
			$crypto			= array_key_exists($currency, $this->coin_names);
			
			if (!$userID) $userID = "guest"; // allow guests to make checkout (payments)
			
			if ($currency != "USD" && !$crypto)
			{
				if ($currency == "TRL") $currency = "TRY"; // fix for Turkish Lyra
				$amount = $this->convert_currency($currency, "USD", $amount);
				if ($amount <= 0) 
				{
					echo '<h2>' . __( 'Information', GOURLJI ) . '</h2>' . PHP_EOL;
					echo "<div class='error'>".sprintf(__( 'Sorry, but there was an error processing your order. Please try later or use a different payment method. Cannot receive exchange rates for %s/USD', GOURLJI ), $currency)."</div>";
				}
				$currency = "USD";
			}
	
			if (!$crypto) $amount = $amount * $this->emultiplier;

			
			// Crypto Payment Box
			if ($amount > 0)
			{
				if (!class_exists('gourlclass') || !defined('GOURL') || !is_object($gourl)) 
				{
					echo '<h2>' . __( 'Information', GOURLJI ) . '</h2>' . PHP_EOL;
					echo "<div class='error'>".__( "Please try a different payment method. Admin need to install and activate wordpress plugin 'GoUrl Bitcoin Gateway' (https://gourl.io/bitcoin-wordpress-plugin.html) to accept Bitcoin/Altcoin Payments online", GOURLJI )."</div>";
				}
				elseif (!$userID) 
				{
					echo '<h2>' . __( 'Information', GOURLJI ) . '</h2>' . PHP_EOL;
					echo "<div align='center'><a href='".wp_login_url(get_permalink())."'>
							<img title='".__('You need to login or register on website first', GOURLJI )."' vspace='10'
							src='".plugins_url('/cryptobox_login2.png', __FILE__)."' width='527' height='242' border='0'></a></div>";
				}
				else 
				{
					// crypto payment gateway
					$result = $gourl->cryptopayments ($plugin, $amount, $currency, $orderID, $period, $language, $coin, $affiliate_key, $userID, $this->iconwidth);
					
					if (!$result["is_paid"]) echo '<h2>' . __( 'Pay Now', GOURLJI ) . '</h2>' . PHP_EOL;
					else echo "<br>";
					
					if ($result["error"]) echo "<div class='error'>".__( "Sorry, but there was an error processing your order. Please try a different payment method.", GOURLJI )."<br/>".$result["error"]."</div>";
					elseif (!$result["is_paid"] && strtotime($order->order_date) < strtotime("-1 day"))
					{
						echo "<div class='error'>". __( 'This unpaid order is expired. Please contact us if you need assistance.', GOURLJI )."</div>";
						if ($order->status == "new") $order->update_status('cancelled', __('Unpaid order expired. ', GOURLJI));
					}
					else
					{
						// display payment box or successful payment result
						echo $result["html_payment_box"];
						
						// payment received
						if ($result["is_paid"]) echo "<div align='center'>" . sprintf( __('%s Payment ID: #%s', GOURLJI), ucfirst($result["coinname"]), $result["paymentID"]) . "</div><br>";
					}
				}
			}
	    }

	    echo "<br>";
	    	    
	    return true;
	}
	    
	
	
	
	    
	    /*
	     * 5.6 Forward to checkout page
	     */
	    public function process_payment( $order_id ) 
	    {
	    	$order = new jigoshop_order( $order_id );
	    	
	    	$order->update_status('pending');
	    	
	    	$currency = trim(Jigoshop_Base::get_options()->get('jigoshop_currency'));
	    	
	    	update_post_meta( $order->id, 'currency', $currency );
	    	
	    	jigoshop_cart::empty_cart();
	    	
	    	$checkout_redirect = apply_filters( 'jigoshop_get_checkout_redirect_page_id', jigoshop_get_page_id('thanks') );
			$url = add_query_arg( 'key', $order->order_key, add_query_arg( 'order', $order->id, get_permalink( $checkout_redirect ) ) );
	    	
	    	$order->add_order_note(sprintf(__('Awaiting Cryptocurrency Payment. Full Order ID: <b>%s</b> with total %s', GOURLJI), 'order'.$order->id.'_'.str_replace('order_', '', $order->order_key), $order->order_total . " " . $currency));
	    	
	    	return array(
	    			'result' 	=> 'success',
	    			'redirect'	=> $url
	    	);
	    }
	    
	    
	    
	    
	    
	    /*
	     * 5.7 GoUrl Bitcoin Gateway - Instant Payment Notification
	     */
	    public function gourlcallback( $user_id, $order_id, $payment_details, $box_status) 
	    {
	    	if (!in_array($box_status, array("cryptobox_newrecord", "cryptobox_updated"))) return false;
	    	
	    	$origID = $order_id;
	    	
	    	if (strpos($order_id, "order") === 0) $order_id = substr($order_id, 5); else return false;
	    	
	    	if (!$user_id || $payment_details["status"] != "payment_received") return false;
	    	
	    	list($order_id, $order_key) = explode( '_', $order_id );
	    	
	    	$order = new jigoshop_order( $order_id );  if ($order === false) return false;

	    	
	    	$coinName 	= ucfirst($payment_details["coinname"]);
	    	$amount		= $payment_details["amount"] . " " . $payment_details["coinlabel"] . "&#160; ( $" . $payment_details["amountusd"] . " )";
	    	$payID		= $payment_details["paymentID"];
	    	$status		= ($payment_details["is_confirmed"]) ? $this->ostatus2 : $this->ostatus;
	    	$confirmed	= ($payment_details["is_confirmed"]) ? __('Yes', GOURLJI) : __('No', GOURLJI);
	    	
	    	
	    	// Security	    	
	    	$good = ('order_'.$order_key == $order->order_key) ? true : false;
			
	    	if ($good)
	    	{	
		    	// Completed
		    	if ($status == "completed") $order->payment_complete();
		    	
		    	// Update Status
		    	$order->update_status($status);
	    	}
	    	
	    	
	    	// New Payment Received
	    	if ($box_status == "cryptobox_newrecord") 
	    	{
	    		if ($good)
	    		{
		    		$checkout_redirect = apply_filters( 'jigoshop_get_checkout_redirect_page_id', jigoshop_get_page_id('thanks') );
		    		$url = add_query_arg( 'key', $order->order_key, add_query_arg( 'order', $order->id, get_permalink( $checkout_redirect ) ) );
		    		$order->add_order_note(sprintf(__('%s Payment Received for Order ID: <b>%s</b><br>%s<br>Payment <a href="%s">id %s</a> &#160; (<a href="%s">order page</a>)<br>Awaiting network confirmation...<br>', GOURLJI), $coinName, $origID, $amount, GOURL_ADMIN.GOURL."payments&s=payment_".$payID, $payID, $url."&gourlcryptocoin=".$payment_details["coinname"]));
		    		
		    		update_post_meta( $order->id, 'coinname', $coinName);
		    		update_post_meta( $order->id, 'amount', $payment_details["amount"] . " " . $payment_details["coinlabel"] );
		    		update_post_meta( $order->id, 'amountusd', $payment_details["amountusd"] . " USD" );
		    		update_post_meta( $order->id, 'userid', $payment_details["userID"] );
		    		update_post_meta( $order->id, 'country', get_country_name($payment_details["usercountry"]) );
		    		update_post_meta( $order->id, 'tx', $payment_details["tx"] );
		    		update_post_meta( $order->id, 'confirmed', $confirmed );
		    		update_post_meta( $order->id, 'details', $payment_details["paymentLink"] );
	    		}
	    		else
	    		{
	    			$order->add_order_note(sprintf(__('<b>IMPORTANT! %s Payment Received for Expired Order ID: %s</b><br><b>Please compare current order sum and paid sum below!</b><br>%s<br>Payment <a href="%s">id %s</a><br>Awaiting network confirmation...<br>', GOURLJI), $coinName, $origID, $amount, GOURL_ADMIN.GOURL."payments&s=payment_".$payID, $payID));
	    		}	
	    	}
	    	
	    	// Payment Confirmed
	    	if ($good && $box_status == "cryptobox_updated") update_post_meta( $order->id, 'confirmed', $confirmed );
	    	
	    	
	    	// Existing Payment confirmed (6+ confirmations)
	    	if ($payment_details["is_confirmed"]) $order->add_order_note(sprintf(__('%s Payment <a href="%s">id %s</a> Confirmed<br>', GOURLJI), $coinName, GOURL_ADMIN.GOURL."payments&s=payment_".$payID, $payID));

	    	return true;
	    }
	}
	// end class Jigoshop_Gateway_GoUrl
	
	
	
	
	
	
	/*
	 *  6. Instant Payment Notification Function - pluginname."_gourlcallback"
	 *  
	 *  This function will appear every time by GoUrl Bitcoin Gateway when a new payment from any user is received successfully. 
	 *  Function gets user_ID - user who made payment, current order_ID (the same value as you provided to bitcoin payment gateway), 
	 *  payment details as array and box status.
	 *  
	 *  The function will automatically appear for each new payment usually two times :  
	 *  a) when a new payment is received, with values: $box_status = cryptobox_newrecord, $payment_details[is_confirmed] = 0
	 *  b) and a second time when existing payment is confirmed (6+ confirmations) with values: $box_status = cryptobox_updated, $payment_details[is_confirmed] = 1.
	 *	
	 *  But sometimes if the payment notification is delayed for 20-30min, the payment/transaction will already be confirmed and the function will
	 *  appear once with values: $box_status = cryptobox_newrecord, $payment_details[is_confirmed] = 1
	 *  
	 *  Payment_details example - https://gourl.io/images/plugin2.png
	 *  Read more - https://gourl.io/affiliates.html#wordpress
	 */ 
	function gourljigoshop_gourlcallback ($user_id, $order_id, $payment_details, $box_status)
	{
		$gateways = jigoshop_payment_gateways::get_available_payment_gateways();
		
		if (!isset($gateways['gourlpayments'])) return;
		
		if (!in_array($box_status, array("cryptobox_newrecord", "cryptobox_updated"))) return false;
		
		// forward data to Jigoshop_Gateway_GoUrl
		$gateways['gourlpayments']->gourlcallback( $user_id, $order_id, $payment_details, $box_status);
		
		return true;
	}





}
// end gourl_jigoshop_gateway_load()             

