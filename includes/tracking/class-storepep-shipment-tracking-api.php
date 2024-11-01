<?php

/**
 * REST API Order Notes controller
 *
 * Handles requests to the wc/storepep/v1.
 * @author   StorePep
 * @category API
 */
if (!defined('ABSPATH')) {
    exit;
}

if( ! class_exists('Storepep_Shipment_Tracking_API') ) {
	/**
	 * Tracking REST API controller class.
	 * @package WooCommerce/API
	 * @extends WC_REST_Controller
	 */
	class Storepep_Shipment_Tracking_API extends WC_REST_Controller {

		/**
		 * Endpoint namespace.
		 *
		 * @var string
		 */
		protected $namespace = 'wc/storepep/v1';

		/**
		 * Route base.
		 *
		 * @var string
		 */
		protected $rest_base = 'storepep/updatetracking';

		/**
		 * Post type.
		 *
		 * @var string
		 */
		protected $post_type = 'shop_order';

		/**
		 * Register the routes for order notes.
		 */
		public function register_routes() {
			register_rest_route($this->namespace, '/' . $this->rest_base, array(
				'args' =>
				array(
					'order_id' => array(
						'description' => __('The order ID.', 'woocommerce'),
						'type' => 'integer',
					),
				),
				array(
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => array($this, 'create_item'),
					'permission_callback' => array($this, 'create_item_permissions_check'),
					'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
				),
				'schema' => array($this, 'get_public_item_schema'),
			));
		}

		/**
		 * Check if a given request has write access.
		 *
		 * @param  WP_REST_Request $request Full details about the request.
		 *
		 * @return bool|WP_Error
		 */
		public function create_item_permissions_check($request) {
			if (!wc_rest_check_post_permissions($this->post_type, 'create')) {
				return new WP_Error('woocommerce_rest_cannot_create', __('Sorry, you are not allowed to create resources.', 'woocommerce'), array('status' => rest_authorization_required_code()));
			}

			return true;
		}

		/**
		 * Create a single order note.
		 *
		 * @param WP_REST_Request $request Full details about the request.
		 * @return WP_Error|WP_REST_Response
		 */
		public function create_item($request) {
			$wp_date_format = get_option('date_format');
			$body           = $request->get_body();
			$body           = (array) json_decode($body);

			if (empty($body)) {
				/* translators: %s: post type */
				return new WP_Error("woocommerce_rest_{$this->post_type}_exists", sprintf(__('You passed empty data %s.', 'woocommerce'), $this->post_type), array('status' => 400));
			}
			$output = '';
			foreach ($body as $tracking) {
				$carrier_arr                        = null;
				$update_result                      = false;
				$trackingnumber_as_link             = null;
				$tracking_numbers_comma_seperated   = null;

				$tracking           = (array) $tracking;
				$order_id           = isset($tracking['order_id']) ? trim($tracking['order_id']) : '';

				if( ! empty($tracking['trackingdetails']) && is_array($tracking['trackingdetails']) ) {
					foreach( $tracking['trackingdetails'] as $trackingdetail ) {
						$shippingdate   = $trackingdetail->shippingdate;
						$shippingdate   = Date( $wp_date_format, $shippingdate);
						$carrier_arr[]  = $trackingdetail->carrier;
						$trackingnumber = $trackingdetail->trackingnumber;
						$current_track_link = "<a href='".$trackingdetail->trackingurl."'>".$trackingnumber."</a>";
						$trackingnumber_as_link = empty($trackingnumber_as_link) ? $current_track_link : $trackingnumber_as_link.", $current_track_link";
						$tracking_numbers_comma_seperated = empty($tracking_numbers_comma_seperated) ? $trackingnumber : $tracking_numbers_comma_seperated.', '. $trackingnumber;
					}
				}

				$order          = wc_get_order($order_id);
				$carrier_arr    = array_unique($carrier_arr);
				$carrier        = implode( ", ", $carrier_arr );

				if ($order && !empty($carrier) && !empty($tracking_numbers_comma_seperated)) {
					// Auto fill tracking info.
					$this->update_tracking_details($order_id, $tracking_numbers_comma_seperated, $carrier, $shippingdate);
					$message = $this->get_message($trackingnumber_as_link, $carrier, $shippingdate);
					$output .= '<br>' . 'Order #' . $order_id . __(' updated successfully.', 'woocommerce');
					$update_result = update_post_meta($order_id, 'storepeptrackingmsg', $message);
				}
			}

			if( $update_result !== false ) {
				$resp_data = array(
					"success"   =>  true
				);
				$order->add_order_note($message);
				$order->update_status('completed');
				$resp_data = json_encode($resp_data);
				$response = new WP_REST_Response($resp_data, 200);
			}
			else {
				$resp_data = array(
					"success"   =>  false
				);
				$resp_data = json_encode($resp_data);
				$response = new WP_REST_Response($resp_data, 200);
			}
			return apply_filters("woocommerce_rest_prepare_{$this->post_type}", $response, $resp_data, $request);
		}

		/**
		 * Update Tracking details in Order.
		 * @param $order_id int Order Id.
		 * @param $trackingnumber string Tracking Number.
		 * @param $carrier string Shipping Carrier.
		 * @param $shipping_date string Shipping Date.
		 */
		function update_tracking_details($order_id, $trackingnumber, $carrier, $shipping_date) {
			$carrier = sanitize_title($carrier);
			$shipment_source_data = array(
				'trackingnumber' => $trackingnumber,
				'carrier' => $carrier,
				'shippingdate' => $shipping_date
			);
			update_post_meta($order_id, 'storepep_wc_shipment_source', $shipment_source_data);
		}
		
		/**
		 * Get Tracking info Message.
		 * @param $trackingnumber string Tracking Number.
		 * @param $carrier string Shipping Carrier.
		 * @param $shipping_date string Shipping data.
		 */
		public function get_message($trackingnumber, $carrier, $shippingdate)
		{
			$message="Your order was shipped on ".$shippingdate." via ".$carrier.". To track shipment, please follow the shipment ID(s) ".$trackingnumber;
			return $message;
		}

	}
}