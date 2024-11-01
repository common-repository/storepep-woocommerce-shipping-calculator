<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Shipping Method Class. Responsible for handling rates.
 */
if( ! class_exists("Storepep_Woocommerce_Shipping_Method") ) {
	class Storepep_Woocommerce_Shipping_Method extends WC_Shipping_Method {
		
		/**
		 * Weight Unit.
		 */
		public static $weight_unit;
		/**
		 * Dimension Unit.
		 */
		public static $dimension_unit;
		/**
		 * Currency code.
		 */
		public static $currency_code;
		/**
		 * Integration Id.
		 */
		public static $integration_id;
		/**
		 * Secret Key.
		 */
		public static $secret_key;

		/**
		 * boolean true if debug mode is enabled.
		 */
		public static $debug;
		/**
		 * StorePep transaction id returned by StorePep Server.
		 */
		public static $storepepTransactionId;
		/**
		 * Fall back rate.
		 */
		public static $fallback_rate;
		/**
		 * Tax Calculation for Shipping rates.
		 */
		public static $tax_calculation_mode;


		/**
		 * Constructor.
		 */
		public function __construct() {
			$plugin_configuration 				= Storepep_Woocommerce_Shipping::storepep_plugin_configuration();
			$this->id							= $plugin_configuration['id'];
			$this->method_title					= $plugin_configuration['method_title'];
			$this->method_description 			= $plugin_configuration['method_description'];
			$this->init();
			// Save settings in admin
			add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		/**
		 * Initialize the settings.
		 */
		private function init() {
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			$this->title					= $this->method_title;
			$this->enabled 					= isset($this->settings['enabled']) ? $this->settings['enabled'] : 'no';
			self::$integration_id			= isset($this->settings['integration_id']) ? $this->settings['integration_id'] : null;
			self::$secret_key				= isset($this->settings['secret_key']) ? $this->settings['secret_key'] : null;
			self::$debug					= ( isset($this->settings['debug']) && $this->settings['debug']=='yes' ) ? true : false;
			self::$fallback_rate			= ! empty( $this->settings['fallback_rate']) ? $this->settings['fallback_rate'] : null;
			$this->shipping_title			= ! empty($this->settings['shipping_title']) ? $this->settings['shipping_title'] : 'Shipping Rate';
			self::$tax_calculation_mode		= ! empty($this->settings['tax_calculation_mode']) ? $this->settings['tax_calculation_mode'] : false;
		}
		
		/**
		 * Settings Form fileds.
		 */
		public function init_form_fields() {
			$this->form_fields  = include( 'data-storepep-settings.php' );
		}

		/**
		 * Encrypt the data.
		 * @param $key string secret key used for encoding
		 * @param $data object Json encoded data.
		 * @param string Encrypted data in hexadecimal.
		 */
		public static function encrypt_data($key,$data)
		{
			$encryptionMethod = "AES-256-CBC";
			$iv = substr($key, 0, 16);
			if (version_compare(phpversion(), '5.3.2', '>')) {
				$encryptedMessage = openssl_encrypt($data, $encryptionMethod,$key,OPENSSL_RAW_DATA,$iv);                    
			}else
			{
				$encryptedMessage = openssl_encrypt($data, $encryptionMethod,$key,OPENSSL_RAW_DATA);                    
			}
			return bin2hex($encryptedMessage);
		}

		/**
		 * Decrypt the data.
		 * @param $key string secret key used for encoding
		 * @param $data string Binary encoded data.
		 * @return string Decrypted data.
		 */
		public static function decrypt_data($key,$data)
		{
			$encryptionMethod = "AES-256-CBC";
			$iv =  substr($key, 0, 16);
			
			if (version_compare(phpversion(), '5.3.2', '>')) {
				$decrypted_data = openssl_decrypt($data, $encryptionMethod,$key,OPENSSL_RAW_DATA,$iv);                    
			}else
			{
				$decrypted_data = openssl_decrypt($data, $encryptionMethod,$key,OPENSSL_RAW_DATA);                    
			}
			return $decrypted_data;
		}

		/**
		 * Calculate shipping.
		 */
		public function calculate_shipping( $package = array() ) {

			self::debug( __('StorePep Debug Mode is On.', 'storepep-woocommerce-shipping-calculator') );
			if( empty(self::$integration_id) || empty(self::$secret_key) ) {
				self::debug( __('StorePep Integration Id or Secret Key Missing.', 'storepep-woocommerce-shipping-calculator') );
				return;
			}
			$this->found_rates = array();

			if( empty(self::$weight_unit) ) {
				self::$weight_unit 		= get_option('woocommerce_weight_unit');
			}
			if( empty(self::$dimension_unit) ) {
				self::$dimension_unit 	= get_option('woocommerce_dimension_unit');
			}
			if( empty(self::$currency_code) ) {
				self::$currency_code  	= get_woocommerce_currency();
			}

			$formatted_package	= self::get_formatted_data($package);
			self::debug( 'StorePep Request Package: <a href="#" class="debug_reveal">Reveal</a><pre class="debug_info" style="background:#EEE;border:1px solid #DDD;padding:5px;">' . print_r( $formatted_package, true ) . '</pre>' );
			$encrypted_data 	= self::encrypt_data( self::$secret_key, json_encode($formatted_package) );
			// Format accepted in Storepep server
			$data 				= array(
					'data'			=>	$encrypted_data,
					'storeType'		=>	STOREPEP_WC_STORE_TYPE,
					'siteUrl'		=>	get_site_url()
			);

			// Required to get the debug info from api
			if( self::$debug ) {
				$data['isDebug']	= true;
			}

			$json_encoded_data 	=	json_encode($data);
			$response 			= $this->get_rates_from_server($json_encoded_data);

			if( $response !== false ) {
				$this->process_result($response);
			}
			// Handle Fallback rates if no rates returned
			if( empty($this->found_rates) && ! empty(self::$fallback_rate) ){
				$shipping_method_detail = new stdClass();
				$shipping_method_detail->ruleName 		= $this->shipping_title;
				$shipping_method_detail->displayName 	= $this->shipping_title;
				$shipping_method_detail->rate 			= self::$fallback_rate;
				$shipping_method_detail->ruleName 		= $this->shipping_title;
				$shipping_method_detail->ruleId 		= null;
				$shipping_method_detail->serviceId 		= null;
				$shipping_method_detail->carrierId 		= 'fallback_rate';
				$this->prepare_rate( $shipping_method_detail );
			}
			$this->add_found_rates();
		}

		/**
		 * Get formatted data from woocommerce cart package.
		 * @param $package array Package.
		 * @return array Formatted package.
		 */
		public static function get_formatted_data( $package ) {
			foreach( $package['contents'] as $key => $line_item ) {
				$data_to_send['cart'][] = array(
					'key'			=>	$key,
					'product_id'	=>	$line_item['product_id'],
					'variation_id'	=>	$line_item['variation_id'],
					'quantity'		=>	$line_item['quantity'],
					'line_total'	=>	$line_item['line_total'],
					'product_data'	=>	array(
						'name'			=>	$line_item['data']->get_name(),
						'id'			=>	! empty($line_item['variation_id']) ? $line_item['variation_id'] : $line_item['product_id'],
						'type'			=>	$line_item['data']->get_type(),
						'sku'			=>	$line_item['data']->get_sku(),
						'price'			=>	$line_item['data']->get_price(),
						'weight'		=>	$line_item['data']->get_weight(),
						'weight_unit'	=>	self::$weight_unit,
						'dimension_unit'=>	self::$dimension_unit,
						'dimensions'	=>	array(
							'length'		=>	$line_item['data']->get_length(),
							'width'			=>	$line_item['data']->get_width(),
							'height'		=>	$line_item['data']->get_height(),
						),
						'shipping_class'	=> $line_item['data']->get_shipping_class(),
						'shipping_class_id'	=> $line_item['data']->get_shipping_class_id(),
						'shipping_required'	=> $line_item['data']->needs_shipping(),
						'parent_id'			=> $line_item['data']->get_parent_id(),
						'category_ids'		=> $line_item['data']->get_category_ids(),					

					),
				);
			}
			$data_to_send['currency']		= self::$currency_code;
			$data_to_send['cart_subtotal'] 	= $package['cart_subtotal'];
			$data_to_send['destination']	= $package['destination'];
			$data_to_send['reference_id']	= uniqid();
			// Add 
			WC()->session->set( 'ph_storepep_rates_unique_id', $data_to_send['reference_id'] );
			return $data_to_send;
		}

		/**
		 * Get the rates from Storepep Server.
		 * @param $data string Encrypted data
		 * @return
		 */
		public function get_rates_from_server( $data ) {

			if(STOREPEP_ADVANCE_DEBUG) {
				self::debug( 'StorePep Request Data Advance debug: <a href="#" class="debug_reveal">Reveal</a><pre class="debug_info" style="background:#EEE;border:1px solid #DDD;padding:5px;">'.print_r( $data, true ). '</pre>' );
			}
			// Get the response from server.
			$response = wp_remote_post( STOREPEP_WC_RATE_URL ,
				array(
					'headers'	=>	array(
						'authorization'	=>	"Bearer ".self::$integration_id,
						'Content-Type'	=>	"application/json",
					),
					'timeout'	=>	20,
					'body'		=>	$data,
				)
			);

			if(STOREPEP_ADVANCE_DEBUG) {
				self::debug( 'StorePep Response Advance debug: <a href="#" class="debug_reveal">Reveal</a><pre class="debug_info" style="background:#EEE;border:1px solid #DDD;padding:5px;">'.print_r( $response, true ). '</pre>' );
			}

			// WP_error while getting the response
			if ( is_wp_error( $response ) ) {
				$error_string = $response->get_error_message();
				self::debug( 'StorePep Response: <a href="#" class="debug_reveal">Reveal</a><pre class="debug_info" style="background:#EEE;border:1px solid #DDD;padding:5px;">' .__( 'WP Error : ').print_r( $error_string, true ). '</pre>' );
				return false;
			}

			// Successful response
			if( $response['response']['code'] == '200' ) {
				$body = $response['body'];
				$body = json_decode($body);
				return $body;
			}
			else {
				self::debug( 'StorePep Response: <a href="#" class="debug_reveal">Reveal</a><pre class="debug_info" style="background:#EEE;border:1px solid #DDD;padding:5px;">' .__( 'Error Code : ').print_r( $response['response']['code'], true ).'<br/>' .__( 'Error Message : ') . print_r( $response['response']['message'], true ) .'</pre>' );
				return false;
			}
		}

		/**
		 * Add debug info to the Front end.
		 */
		public static function debug( $message, $type = 'notice' ) {
			if ( self::$debug  ) {
				wc_add_notice( $message, $type );
			}
		}

		/**
		 * Process the Response body received from server.
		 */
		public function process_result( $body ) {
			if( $body->success && ! empty($body->data) ) {
				// Decrypt the response
				$decrypted_data =self::decrypt_data(self::$secret_key, hex2bin($body->data));
				$json_decoded_data = json_decode( $decrypted_data);		// Json decode the decrypted response
				self::$storepepTransactionId = $json_decoded_data->storepepTransactionId;
				WC()->session->set( 'storepep_shipping_transaction_id', self::$storepepTransactionId );
				self::debug( 'StorePep Response: <a href="#" class="debug_reveal">Reveal</a><pre class="debug_info" style="background:#EEE;border:1px solid #DDD;padding:5px;">' .__( 'StorePep Transaction Id : ').print_r( self::$storepepTransactionId, true ).'<br/><br/>'.print_r( $json_decoded_data->info, true ) . '</pre>' );
				$rates_arr = $json_decoded_data->rates;					// Array of rates
				if( is_array($rates_arr) ) {
					foreach( $rates_arr as $rate ) {
						self::prepare_rate( $rate);
					}
				}
			}
		}

		/**
		 * Prepare the rates.
		 * @param $shipping_method_detail object Rate returned from API.
		 */
		public function prepare_rate( $shipping_method_detail ){
			$rate_name 	= $shipping_method_detail->displayName;
			$rate_cost 	= $shipping_method_detail->rate;
			$rate_id 	= $this->id.':'.$shipping_method_detail->ruleId.$shipping_method_detail->serviceId;

			$this->found_rates[$rate_id] = array(
				'id'			=> $rate_id,
				'label'			=> $rate_name,
				'cost'			=> $rate_cost,
				'taxes'			=>	! empty(self::$tax_calculation_mode) ? '' : false,
				'calc_tax'		=>	self::$tax_calculation_mode,
				'meta_data'		=> array(
					'ph_storepep_shipping_rates'	=>	array(
						'ruleId'	=>	$shipping_method_detail->ruleId,					// Rule Identifier in storepep account
						'uniqueId'	=>	WC()->session->get('ph_storepep_rates_unique_id'),	// Unique Id used while communicating with server
						'serviceId'	=>	$shipping_method_detail->serviceId,					//	FEDEX_GROUND, UPS_GROUND, D	etc.
						'carrierId'	=>	$shipping_method_detail->carrierId,					//	Fedex, UPS etc
						'storepepTransactionId'	=>	self::$storepepTransactionId,
					),
				),	
			);
		}

		/**
		 * Add found rates to woocommerce shipping rate.
		 */
		public function add_found_rates() {
			foreach ( $this->found_rates as $key => $rate ) {
				$this->add_rate( $rate );
			}
		}
	}
}