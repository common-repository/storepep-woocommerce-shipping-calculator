<?php
/*
	Plugin Name: StorePep WooCommerce Shipping Calculator
	Plugin URI: 
	Description: Get the WooCommerce shipping rates based on rules configured in your StorePep Account. Also get tracking information from StorePep into your WooCommerce Orders(On the Upgraded Subscription).
	Version: 1.0.5
	Author: StorePep
	Author URI: https://www.storepep.com/
	Copyright: StorePep
    Text Domain: storepep-woocommerce-shipping-calculator
	WC requires at least: 3.0.0
	WC tested up to: 3.4
*/


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/*
 * Common Classes.
 */
if( ! class_exists("Storepep_Shipping_Rates_Common") ) {
	require_once 'class-storepep-shipping-rates-common.php';
}

register_activation_hook( __FILE__, function() {
	$woocommerce_plugin_status = Storepep_Shipping_Rates_Common::woocommerce_active_check();	// True if woocommerce is active.
	if( $woocommerce_plugin_status === false ) {
		deactivate_plugins( basename( __FILE__ ) );
		wp_die( __("Oops! You tried installing the plugin to get woocommerce shipping rates without activating woocommerce. Please install and activate woocommerce and then try again .", "storepep-woocommerce-shipping-calculator" ), "", array('back_link' => 1 ));
	}
});

/**
 * Advance Debug Mode ( For Developer debug ).
 */
if ( ! defined( 'STOREPEP_ADVANCE_DEBUG' ) ) {
	define( 'STOREPEP_ADVANCE_DEBUG', false );
}

/**
 * Storepep shipping calculator root directory path.
 */
if ( ! defined( 'STOREPEP_WC_RATE_PLUGIN_ROOT_DIR' ) ) {
	define( 'STOREPEP_WC_RATE_PLUGIN_ROOT_DIR', __DIR__ );
}

/**
 * Storepep Shipping Calculator root file.
 */
if (!defined('STOREPEP_WC_RATE_PLUGIN_ROOT_FILE')) {
    define('STOREPEP_WC_RATE_PLUGIN_ROOT_FILE', __FILE__);
}

/**
 * Storepep rates api.
 */
if( ! defined("STOREPEP_WC_RATE_URL")  ) {
	define( "STOREPEP_WC_RATE_URL", "https://ship.storepep.com/v1/api/storepep/rates/" );
}

/**
 * Storepep account register api.
 */
if( ! defined("STOREPEP_WC_ACCOUNT_REGISTER_ENDPOINT")  ) {
	define( "STOREPEP_WC_ACCOUNT_REGISTER_ENDPOINT", "https://ship.storepep.com/v1/storepepconnector/register" );
}

/**
 * Storetype defined in storepep api, Required to identify the Store type (Woocommerce or Magento etc) in server.
 */
if( ! defined("STOREPEP_WC_STORE_TYPE")  ) {
	define( "STOREPEP_WC_STORE_TYPE", "S1" );			// S1 for woocommerce defined in storepep rates api
}

/**
 * WooCommerce Shipping Calculator.
 */
if( ! class_exists("Storepep_Woocommerce_Shipping") ) {
	/**
	 * Shipping Calculator Class.
	 */
	class Storepep_Woocommerce_Shipping {

		/**
		 * Constructor
		 */
		public function __construct() {
			// Handle links on plugin page
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'storepep_plugin_action_links' ) );
			// Initialize the shipping method
			add_action( 'woocommerce_shipping_init', array( $this, 'storepep_woocommerce_shipping_init' ) );
			// Register the shipping method
			add_filter( 'woocommerce_shipping_methods', array( $this, 'storepep_woocommerce_shipping_methods' ) );
			add_action( 'woocommerce_thankyou', array( $this, 'add_storepep_transaction_id_to_order_note' ) );
		}

		/**
		 * Plugin configuration.
		 */
		public static function storepep_plugin_configuration(){
            return array(
				'id' => 'storepep_woocommerce_shipping',
				'method_title' => __('StorePep Shipping Calculator', 'storepep-woocommerce-shipping-calculator' ),
				'method_description' => __("Seamlessly integrate all the top shipping carriers like FedEx, UPS, Stamps.com(USPS) and DHL. Display live shipping rates on the cart and checkout pages. You also have the flexibility to define flat rates and free shipping based on your business needs.", 'storepep-woocommerce-shipping-calculator' ).'<br/><br/>'.__( 'This plugin comes with a FREE StorePep.com account with 10,000 API calls per month. If you need Unlimited API access, Printing Shipping Labels in bulk or Live Shipment tracking notifications for customers, Subscribe to ', 'storepep-woocommerce-shipping-calculator' ).'<a href="https://www.storepep.com/#pricing" target="_blank">'.__( 'StorePep.com', 'storepep-woocommerce-shipping-calculator' ).'</a> with a minimal $9/month . ',
			);
		}

		/**
		 * Plugin action links on Plugin page.
		 */
		public function storepep_plugin_action_links( $links ) {
			$plugin_links = array(
				'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=shipping&section=storepep_woocommerce_shipping' ) . '">' . __( 'Settings', 'storepep-woocommerce-shipping-calculator' ) . '</a>',
				'<a href="https://www.storepep.com/">' . __( 'Documentation', 'storepep-woocommerce-shipping-calculator' ) . '</a>',
				'<a href="https://ship.storepep.com/signUp">' . __( 'Sign Up', 'storepep-woocommerce-shipping-calculator' ) . '</a>',
			);
			return array_merge( $plugin_links, $links );
		}

		/**
		 * Shipping Initialization.
		 */
		public function storepep_woocommerce_shipping_init() {
			if( ! class_exists("Storepep_Woocommerce_Shipping_Method") ) {
				require_once 'includes/class-storepep-woocommerce-shipping-method.php';
			}
			$shipping_obj = new Storepep_Woocommerce_Shipping_Method();
		}

		/**
		 * Register Shipping Method to woocommerce.
		 */
		public function storepep_woocommerce_shipping_methods( $methods ) {
			$methods[] = 'Storepep_Woocommerce_Shipping_Method';
			return $methods;
		}

		/**
		 * Add StorePep Transaction Id to Order note.
		 * @param $order_id int Order Id.
		 */
		public function add_storepep_transaction_id_to_order_note($order_id){
			$storepepTransactionId = WC()->session->get('storepep_shipping_transaction_id');
			if( ! empty($storepepTransactionId) ){
				$order = wc_get_order($order_id);
				$order->add_order_note( __( 'StorePep Transaction Id : ', '' ).$storepepTransactionId, 0, 1 );
			}
		}

	}
	
	new Storepep_Woocommerce_Shipping();
}

/**
 * Include Shipment Tracking Functionality.
 */
if( ! class_exists('Storepep_Woocommerce_Shipment_Tracking') ) {
	require_once 'includes/tracking/class-storepep-woocommerce-shipment-tracking.php';
}
new Storepep_Woocommerce_Shipment_Tracking();