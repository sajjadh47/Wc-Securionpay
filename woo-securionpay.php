<?php
/**
 * Plugin Name: Payment Gateway for Woocommerce - SecurionPay
 * Author: Sajjad Hossain Sagor
 * Description: Integrate SecurionPay payment gateway to your Woocommerce Powered store.
 * Version: 1.0.3
 * Author URI: http://sajjadhsagor.com
 * Text Domain: woo-securionpay
 * Domain Path: languages
 */

if ( ! defined( 'ABSPATH' ) )
{
	exit;
}

// Define Gateway Name (Used as ID)

define( 'WOO_SECURIONPAY_GATEWAY', 'woo_securionpay_gateway' );

define( 'WOO_SECURIONPAY_ROOT_DIR', dirname( __FILE__ ) ); // Plugin root dir

define( 'WOO_SECURIONPAY_ROOT_URL', plugin_dir_url( __FILE__ ) ); // Plugin root url

/**
 * Load plugin textdomain.
 */
function woo_securionpay_load_textdomain()
{	
	load_plugin_textdomain( 'woo-securionpay', false, basename( dirname( __FILE__ ) ) . '/languages' );
}

add_action( 'init', 'woo_securionpay_load_textdomain' );

/**
 * Checking if Woocoomerce is either installed or active
 */
register_activation_hook( __FILE__, 'woo_securionpay_check_plugin_activation_status' );

add_action( 'admin_init', 'woo_securionpay_check_plugin_activation_status' );

function woo_securionpay_check_plugin_activation_status()
{
	if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) )
	{
		// Deactivate the plugin
		deactivate_plugins(__FILE__);

		// Throw an error in the wordpress admin console
		$error_message = __( 'SecurionPay for Woocommerce requires <a href="https://wordpress.org/plugins/woocommerce/">Woocommerce</a> plugin to be active! <a href="javascript:history.back()"> Go back & activate Woocommerce Plugin First.</a>', 'woo-securionpay' );

		wp_die( $error_message, "Woocommerce Plugin Not Found" );
	}
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function( $links )
{		
	$plugin_links = array( '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woo_securionpay_gateway' ) . '">' . __( 'Settings', 'woo-securionpay' ) . '</a>' );

	return array_merge( $plugin_links, $links );
} );
	

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'woo_securionpay_gateway_class' );

function woo_securionpay_gateway_class( $gateways )
{	
	$gateways[] = 'WC_Securionpay_Gateway';
	
	return $gateways;
}

// load the gateway class
require WOO_SECURIONPAY_ROOT_DIR . "/includes/class.wc.securionpay.php";

// if live mode but no SSL
add_action( 'admin_notices', function()
{	
	$WC_Securionpay_Gateway = new WC_Securionpay_Gateway();	

	if ( 'no' == $WC_Securionpay_Gateway->enabled )
	{
		return;
	}

	// Show message when in live mode and no SSL on the checkout page
	if ( $WC_Securionpay_Gateway->sandbox == 'yes' && get_option( 'woocommerce_force_ssl_checkout' ) == 'no' && ! class_exists( 'WordPressHTTPS' ) )
	{	
		echo '<div class="error"><p>' . sprintf( __( 'Securionpay is enabled, but the <a href="%s">force SSL option</a> is disabled; your checkout may not be secure! Please enable SSL and ensure your server has a valid SSL certificate.', 'woo-securionpay'), admin_url( 'admin.php?page=wc-settings&tab=advanced' ) ) . '</p></div>';
	}
} );

/**
 * woo_securionpay_get_card_type function
 * 
 * @param string $number
 * 
 * @return string
 */
function woo_securionpay_get_card_type( $number )
{
	if ( preg_match( '/^4\d{12}(\d{3})?(\d{3})?$/', $number ) )
	{
		return 'Visa';
	}
	elseif ( preg_match( '/^3[47]\d{13}$/', $number ) )
	{
		return 'American Express';
	}
	elseif ( preg_match( '/^(5[1-5]\d{4}|677189|222[1-9]\d{2}|22[3-9]\d{3}|2[3-6]\d{4}|27[01]\d{3}|2720\d{2})\d{10}$/', $number ) )
	{
		return 'MasterCard';
	}
	elseif ( preg_match( '/^(6011|65\d{2}|64[4-9]\d)\d{12}|(62\d{14})$/', $number ) )
	{
		return 'Discover';
	}
	elseif  (preg_match( '/^35(28|29|[3-8]\d)\d{12}$/', $number ) )
	{
		return 'JCB';
	}
	elseif ( preg_match( '/^3(0[0-5]|[68]\d)\d{11}$/', $number ) )
	{
		return 'Diners Club';
	}
}

/**
 * Helper function to convert amount to minor unit
 *
 * Charge amount in minor units of given currency.
 * For example 10€ is represented as "1000" and 10¥ is represented as "10".
 * @param $amount
 * @param $currency
 *
 * @return float|int
 */
function woo_securionpay_get_amount( $amount, $currency )
{
	// if it's Chinese yuan (¥) or japanese yen then no amount conversion
	switch ( strtolower( $currency ) )
	{	
		case strtolower( 'JPY' ):
		case strtolower( 'BIF' ):
		case strtolower( 'CLP' ):
		case strtolower( 'DJF' ):
		case strtolower( 'GNF' ):
		case strtolower( 'ISK' ):
		case strtolower( 'KMF' ):
		case strtolower( 'KRW' ):
		case strtolower( 'PYG' ):
		case strtolower( 'RWF' ):
		case strtolower( 'UGX' ):
		case strtolower( 'UYI' ):
		case strtolower( 'XAF' ):
			return $amount;
		break;
		
		default:
			return $amount * 100;
		break;
	}
}
