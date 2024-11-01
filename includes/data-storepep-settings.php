<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;	// exit if directly accessed
}

if( is_admin() && ! empty($_GET['section']) && $_GET['section'] == 'storepep_woocommerce_shipping' ) {
?>
	<script>
		window.addEventListener('load', function () {

			// To create button to get the account details
			let integrationId = jQuery('#woocommerce_storepep_woocommerce_shipping_integration_id').val();
			if( integrationId != undefined && integrationId.length == 0 ) {
			jQuery('.space_before_storepep_get_account_details_button').remove();	//	To avoid multiple times
			jQuery('#storepep_get_account_details').remove();	//	To avoid multiple times
			let createStorepepAccount = jQuery('<button class="button button-primary" type="button" id="storepep_get_account_details" >Signup StorePep.com & Get API Keys</button>');
				jQuery("#woocommerce_storepep_woocommerce_shipping_email_id").after(createStorepepAccount);			// Add button
				jQuery("#storepep_get_account_details").before('<space class="space_before_storepep_get_account_details_button">&nbsp;</space>');	// Add space
			}else{
				jQuery('.storepep_setup_shipping_rules').remove();	//	To avoid multiple times
				jQuery('.space_before_storepep_setup_shipping_rules_button').remove();	//	To avoid multiple times
				let setupStorepepShippingRules = jQuery('<a class="button button-primary storepep_setup_shipping_rules" type="button" id="storepep_setup_shipping_rules" href="https://ship.storepep.com/home/settings/frontendrates/automation" target="_blank">Setup Shipping Rules</a>');
				jQuery(".button-primary").after(setupStorepepShippingRules);			// Add button
				jQuery(".button-primary").before('<space class="space_before_storepep_setup_shipping_rules_button">&nbsp;</space>');	// Add space
			}

			// Call to Storepep Server to get the response
			jQuery( "#storepep_get_account_details" ).click(function() {
				jQuery(this).prop("disabled",true);
				let data = {};
				data.email 				= jQuery('#woocommerce_storepep_woocommerce_shipping_email_id').val();		// Email Id
				data.companyName		= "";
				data.UTCTimeZoneOffset	= "<?php echo get_option('gmt_offset') * 60 ?>";							// UTC time offset in minute
				data.TimeStamp 			= "<?php echo current_time('timestamp') ?>"									// Unix Time Stamp
				data.storeUrl			= "<?php echo get_site_url() ?>";											// Site Url
				data.storeName			= "<?php echo bloginfo() ?>";												// Site Name
				data.storeType			= "<?php echo STOREPEP_WC_STORE_TYPE ?>";									// Store Type to identify the requested platform
				jQuery.post(
					"<?php echo STOREPEP_WC_ACCOUNT_REGISTER_ENDPOINT ?>", data
				)
				.done( function(response) {
					if( response.success == false ) {
						alert(response.message);
					}
					else if( response.success == true ) {
						jQuery("#woocommerce_storepep_woocommerce_shipping_integration_id").val(response.apiDetails.integrationId);
						jQuery("#woocommerce_storepep_woocommerce_shipping_secret_key").val(response.apiDetails.secretKey);
						alert(response.message);
					}
					jQuery('#storepep_get_account_details').prop("disabled",false);
				} )
				.fail( function(result){
					alert('No response from StorePep Server. Something Went wrong');
					jQuery('#storepep_get_account_details').prop("disabled",false);
				});
				
			});
		});
	</script>
	
	<style>
		/* Style for Signup StorePep.com & Get API Keys button */
		
	</style>

<?php
}

$logged_in_user_email_id = null;
if( is_admin() && ! empty($_GET['section']) && $_GET['section'] == 'storepep_woocommerce_shipping' ) {
	$logged_in_user_email_id = Storepep_Shipping_Rates_Common::get_current_user_email_id();
}
// Settings
return array(
	'enabled'			=> array(
		'title'		   	=> __( 'Realtime Rates', 'storepep-woocommerce-shipping-calculator' ),
		'type'			=> 'checkbox',
		'label'			=> __( 'Enable', 'storepep-woocommerce-shipping-calculator' ),
		'default'		=> 'no',
	),
	'shipping_title'	=> array(
		'title'			=> __( 'Method Title', 'storepep-woocommerce-shipping-calculator' ),
		'type'			=> 'text',
		'default'		=>	'Shipping Rate',
		'description'	=> __( "Shipping Method Title." , 'storepep-woocommerce-shipping-calculator' ),
		'desc_tip'		=> true,
	),
	'email_id'	=> array(
		'title'			=> __( 'Email Id', 'storepep-woocommerce-shipping-calculator' ),
		'type'			=> 'text',
		'default'		=> $logged_in_user_email_id,
		'description'	=> __( "Required for StorePep Account . " , 'storepep-woocommerce-shipping-calculator' ),
		'desc_tip'		=> true,
	),
	'integration_id'	=> array(
		'title'			=> __( 'Integration Id', 'storepep-woocommerce-shipping-calculator' ),
		'type'			=> 'text',
		'description'	=> __( "Required for StorePep Account Authentication. Get it from your StorePep Account. " , 'storepep-woocommerce-shipping-calculator' ).'<a href="https://ship.storepep.com/home/settings/frontendrates/key" target="_blank">' . __( 'Get Integration Id', 'storepep-woocommerce-shipping-calculator' ) . '</a>',
		// 'desc_tip'		=> true,
	),
	'secret_key'		=> array(
		'title'			=> __( 'Secret Key', 'storepep-woocommerce-shipping-calculator' ),
		'type'			=> 'text',
		'description'	=> __( "Required for data encryption and decryption. Get it from your StorePep Account. ", 'storepep-woocommerce-shipping-calculator' ).'<a href="https://ship.storepep.com/home/settings/frontendrates/key" target="_blank">' . __( 'Get Secret Key', 'storepep-woocommerce-shipping-calculator' ) . '</a>',
		// 'desc_tip'		=> true,
	),
	'debug'		=> array(
		'title'		   	=> __( 'Debug Mode', 'storepep-woocommerce-shipping-calculator' ),
		'type'			=> 'checkbox',
		'label'			=> __( 'Enable', 'storepep-woocommerce-shipping-calculator' ),
		'description'	=> __( 'Enable debug mode to show debugging information on your cart/checkout.', 'storepep-woocommerce-shipping-calculator' ),
		'desc_tip'		=>	true,
		'default'		=> 'no',
	),
	'tax_calculation_mode'		=> array(
		'title'		   	=> __( 'Tax Calculation', 'storepep-woocommerce-shipping-calculator' ),
		'type'			=> 'select',
		'description'	=> __( 'Select Tax Calculation for shipping rates as your requirement.', 'storepep-woocommerce-shipping-calculator' ),
		'desc_tip'		=>	true,
		'default'		=> null,
		'options'	 => array(
				'per_order' 	=> __( 'Taxable', 'storepep-woocommerce-shipping-calculator' ),
				null			=> __( 'None', 'storepep-woocommerce-shipping-calculator' ),
		),
	),
	'fallback_rate'		=> array(
		'title'			=> __( 'Fallback Rate', 'storepep-woocommerce-shipping-calculator' ),
		'type'			=> 'text',
		'default'		=> '10',
		'description'	=> __( "If no rate returned by StorePep account then this fallback rate will be displayed. Shipping Method Title will be used as Service name.", 'storepep-woocommerce-shipping-calculator' ),
		'desc_tip'		=> true,
	),

);