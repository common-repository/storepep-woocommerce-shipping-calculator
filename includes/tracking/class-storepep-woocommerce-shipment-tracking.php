<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Shipment Tracking Main Class.
 */
if( ! class_exists('Storepep_Woocommerce_Shipment_Tracking') ) {
	/**
	 * Shipment Tracking main class.
	 */
	class Storepep_Woocommerce_Shipment_Tracking{

		/**
		 * Constructor of Storepep_Woocommerce_Shipment_Tracking class.
		 */
		public function __construct() {
			add_action('rest_api_init', array( $this, 'sst_load_api' ), 100);
			add_action('woocommerce_view_order', array( $this, 'display_tracking_info_on_order_page' ) );
			add_action('woocommerce_email_order_meta', array( $this, 'add_tracking_info_to_email'), 20);
		}

		/**
		 * Initialize rest api for tracking.
		 */
		public function sst_load_api() {
			if( ! class_exists('Storepep_Shipment_Tracking_API') ) {
				require_once 'class-storepep-shipment-tracking-api.php';
			}
			$obj = new Storepep_Shipment_Tracking_API();
			$obj->register_routes();
		}

		/**
		 * Display the Tracking information on Customer MyAccount->Order page.
		 * @param $order_id integer Order Id.
		 */
		public function display_tracking_info_on_order_page($order_id) {
			$tracking_info = $this->get_tracking_message($order_id);
			if( ! empty($tracking_info) ) {
				echo $tracking_info;
			}
		}

		/**
		 * Get Tracking Message from Order
		 * @param $order_id integer Order Id.
		 * @return string Tracking info.
		 */
		public function get_tracking_message($order_id) {
			return get_post_meta($order_id, 'storepeptrackingmsg', true);
		}

		/**
		 * Add tracking Information to email being sent to Customer.
		 */
		public function add_tracking_info_to_email($order) {

			$order_status = $order->get_status();
			if( $order_status == 'completed' ) {
				if ( version_compare(WC()->version, '2.7.0', "<") ) {
					$order_id = $order->id;
				} else {
					$order_id = $order->get_id();
				}
				$shipping_title = apply_filters('storepep_shipment_tracking_email_shipping_title', __('Shipping Detail', 'storepep-woocommerce-shipping-calculator'), $order_id);
				$tracking_info 	= $this->get_tracking_message($order_id);
				echo '<h3>' . $shipping_title . '</h3><p>' . $tracking_info . '</p></br>';
			}
		}
	}
}