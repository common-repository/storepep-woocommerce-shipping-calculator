<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Common Functions class.
 */
if( ! class_exists("Storepep_Shipping_Rates_Common") ) {
	/**
	 * Holds Common Methods.
	 */
	class Storepep_Shipping_Rates_Common {

		/**
		 * Array of active plugins.
		 */
		private static $active_plugins;
		/**
		 * Current user details WP_User object.
		 */
		private static $current_user_details;
		/**
		 * Current User email id.
		 */
		private static $current_user_email_id;

		/**
		 * Initialize the active plugins.
		 */
		public static function init() {

			self::$active_plugins = (array) get_option( 'active_plugins', array() );

			if ( is_multisite() )
				self::$active_plugins = array_merge( self::$active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		}

		/**
		 * Check whether woocommerce is active or not.
		 * @return boolean True if woocommerce is active else false.
		 */
		public static function woocommerce_active_check() {

			if ( ! self::$active_plugins ) self::init();

			return in_array( 'woocommerce/woocommerce.php', self::$active_plugins ) || array_key_exists( 'woocommerce/woocommerce.php', self::$active_plugins );
		}

		/**
		 * Get current user details.
		 * @return object WP_User Object
		 */
		public static function get_current_user_details() {
			if( empty(self::$current_user_details) ) {
				self::$current_user_details = wp_get_current_user();
			}
			return self::$current_user_details;
		}

		/**
		 * Get current user email.
		 * @return string Current user email id.
		 */
		public static function get_current_user_email_id() {
			if( empty(self::$current_user_email_id) ) {
				$current_user_details = self::get_current_user_details();
				self::$current_user_email_id = $current_user_details->__get('user_email');
			}
			return self::$current_user_email_id;
		}
	}
}