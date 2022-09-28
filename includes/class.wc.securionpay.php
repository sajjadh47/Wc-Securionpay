<?php

use SecurionPay\SecurionPayGateway;
use SecurionPay\Exception\SecurionPayException;

/**
 * Securionpay Payment Gateway
 *
 * Integrates Securionpay Payment Gateway;
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class       WC_Securionpay_Gateway
 * @extends     WC_Payment_Gateway_CC
 * @version     1.0.0
 * @package     WooCommerce/Classes/Payment
 * @author      Sajjad Hossain Sagor
 */
add_action( 'plugins_loaded', 'wc_securionpay_gateway_init', 11 );

function wc_securionpay_gateway_init()
{
    class WC_Securionpay_Gateway extends WC_Payment_Gateway_CC
    {
        public function __construct()
        {
			$this->id = WOO_SECURIONPAY_GATEWAY; // payment gateway plugin ID
			$this->icon = WOO_SECURIONPAY_ROOT_URL . 'assets/logo.png'; // URL of the icon that will be displayed on checkout page near your gateway name
			$this->has_fields = true; // in case you need a custom credit card form
			$this->method_title = 'SecurionPay';
			$this->method_description = 'Integrate SecurionPay payment gateway to your Woocommerce Powered store.'; // will be displayed on the options page
		 	$this->cardtypes = $this->get_option( 'cardtypes' );
			
			// gateways supports simple payments, refunds & saved payment methods
			$this->supports = array(
				'products',
				'refunds',
				'tokenization',
				'add_payment_method',
				'default_credit_card_form'
			);
		 
			// Method with all the options fields
			$this->init_form_fields();
		 
			// Load the settings.
			$this->init_settings();
			$this->title = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );
			$this->enabled = $this->get_option( 'enabled' );
			$this->sandbox = 'yes' === $this->get_option( 'sandbox' );
			$this->secret_key = $this->sandbox ? $this->get_option( 'sandbox_secret_key' ) : $this->get_option( 'secret_key' );
		 	
		 	// Add test mode warning if sandbox
			if ( $this->sandbox == 'yes' )
			{
				$this->description  = trim( $this->description );
				$this->description .= __( 'TEST MODE ENABLED. Use test card number 4242424242424242 with any 3-digit CVC and a future expiration date.', 'woo-securionpay' );
			}
			
			// This action hook saves the settings
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields()
		{
			$this->form_fields = array(
				'enabled' => array(
					'title'       => __( 'Enable/Disable', 'woo-securionpay' ),
					'label'       => __( 'Enable SecurionPay Gateway', 'woo-securionpay' ),
					'type'        => 'checkbox',
					'default'     => 'no'
				),
				'title' => array(
					'title'       => 'Title',
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woo-securionpay' ),
					'default'     => __( 'SecurionPay Payment Gateway', 'woo-securionpay' ),
				),
				'description' => array(
					'title'       => __( 'Description', 'woo-securionpay' ),
					'type'        => 'textarea',
					'description' => __( 'This controls the description which the user sees during checkout.', 'woo-securionpay' ),
					'default'     =>  __( 'Pay with your credit card via SecurionPay payment gateway.', 'woo-securionpay' ),
				),
				'sandbox' => array(
					'title'       => __( 'Sandbox Mode', 'woo-securionpay' ),
					'label'       => __( 'Enable Sandbox Mode', 'woo-securionpay' ),
					'type'        => 'checkbox',
					'description' => __( 'Securionpay sandbox can be used to test payments.', 'woo-securionpay' ),
					'default'     => 'yes',
					'desc_tip'    => true,
				),
				'sandbox_secret_key' => array(
					'title'       => __( 'Sandbox Secret Key', 'woo-securionpay' ),
					'type'        => 'text'
				),
				'secret_key' => array(
					'title'       => __( 'Live Secret Key', 'woo-securionpay' ),
					'type'        => 'text'
				),
				'cardtypes' => array(
				'title'    => __( 'Accepted Cards', 'woo-securionpay' ),
				'type'     => 'multiselect',
				'class'    => 'chosen_select',
				'css'      => 'width: 350px;',
				'desc_tip' => __( 'Select the card types to accept.', 'woo-securionpay' ),
					'options'  => array(
						'visa'       => 'Visa',
						'mastercard' => 'MasterCard',
						'amex'       => 'American Express',
						'discover'   => 'Discover',
						'jcb'        => 'JCB',
						'diners'     => 'Diners Club',
					),
					'default' => array( 'visa', 'mastercard', 'amex' ),
				)
			);
		}

		/**
		 * get_icon function.
		 *
		 * @access public
		 * @return string
		 */
		public function get_icon()
		{	
			$icon = '';
			
			if( is_array( $this->cardtypes ) )
			{	
				$card_types = $this->cardtypes;
				
				foreach ( $card_types as $card_type )
				{
					$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/' . $card_type . '.png' ) . '" alt="' . $card_type . '" />';
				}
			}
			
			return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
		}

		/**
		 * Builds our payment fields area - including tokenization fields for logged
		 * in users, and the actual payment fields.
		 */
		public function payment_fields()
		{	
			if ( $this->description )
			{	
				echo wpautop( wp_kses_post( $this->description ) );
			}

			if ( $this->supports( 'tokenization' ) && is_checkout() )
			{	
				$this->tokenization_script();
				
				$this->saved_payment_methods();
				
				$this->form();
				
				$this->save_payment_method_checkbox();
			}
			else
			{
				$this->form();
			}
		}

		/**
		 * save_card function.
		 *
		 * @access public
		 * @param Object $response
		 * @return void
		 */
		public function save_card( $gateway, $exp_date_array )
		{
			global $current_user;
			
			get_currentuserinfo();

			// get currrent user email
			$email       = (string) $current_user->user_email;

			$card_number = intval( str_replace( ' ', '', sanitize_text_field( $_POST['woo_securionpay_gateway-card-number'] ) ) );

			$card_type   = woo_securionpay_get_card_type( $card_number );
			
			$exp_month   = trim( $exp_date_array[0] );
			
			$exp_year    = trim( $exp_date_array[1] );
			
			$exp_date    = $exp_month . substr( $exp_year, -2 );

			$user_card =  array(
		        'number' 	=> $card_number,
		        'cvc' 		=> intval( sanitize_text_field( $_POST['woo_securionpay_gateway-card-cvc'] ) ),
		        'expMonth'  => $exp_month,
		        'expYear'   => $exp_year
			);

			// make up the data to send to Gateway API
			$request = array(
			    'email' => $email,
			    'card'  => $user_card
			);

			// check if customer is already exists

			$customerId = get_user_meta( get_current_user_id(), '_cust_id', true );

			if ( ! empty( $customerId ) )
			{
				try
				{
					$Customer = $gateway->retrieveCustomer( $customerId );

					$customerID = $Customer->getId();

					// make up the data to send to Gateway API
					$update_customer = array(
					    'customerId' => $customerID,
					    'card' => $user_card
					);
					
					$customer = $gateway->updateCustomer( $update_customer );

					$token = new WC_Payment_Token_CC();

				    $last_inserted_card = array_pop( $customer->getCards() );

				    // charge id will be used as TransactionID for reference
		    		$cardToken = $last_inserted_card->getId();
					
					$token->set_token( $cardToken );
					$token->set_gateway_id( WOO_SECURIONPAY_GATEWAY );
					$token->set_card_type( strtolower( $card_type ) );
					$token->set_last4( substr( $card_number, -4) );
					$token->set_expiry_month( substr( $exp_date, 0, 2 ) );
					$token->set_expiry_year( '20' . substr( $exp_date, -2 ) );
					$token->set_user_id( get_current_user_id() );
					$token->save();

					return array( 'result' => 'success' );
				}
				catch ( SecurionPayException $e )
				{
					//something went wrong buddy!

					// handle error response - see https://securionpay.com/docs/api#error-object
				    $errorMessage = $e->getMessage();

				    if ( ! empty( $errorMessage ) )
				    {
						$error_msg = __( 'Error adding card : ', 'woo-securionpay' ) . $errorMessaget;
					}
					else
					{	
						$error_msg = __( 'Error adding card. Please try again.', 'woo-securionpay' );
					}

					return array( 'result' => 'error', 'message' => $error_msg );
				}
			}
			else
			{
				// request for new customer creation
				try
				{
				    // do something with customer object - see https://securionpay.com/docs/api#customer-object
				    $customer = $gateway->createCustomer( $request );

				    $customer_id = update_user_meta( get_current_user_id(), '_cust_id', $customer->getId() );

				    $token = new WC_Payment_Token_CC();

				    $last_inserted_card = array_pop( $customer->getCards() );

				    // charge id will be used as TransactionID for reference
		    		$cardToken = $last_inserted_card->getId();
					
					$token->set_token( $cardToken );
					$token->set_gateway_id( WOO_SECURIONPAY_GATEWAY );
					$token->set_card_type( strtolower( $card_type ) );
					$token->set_last4( substr( $card_number, -4) );
					$token->set_expiry_month( substr( $exp_date, 0, 2 ) );
					$token->set_expiry_year( '20' . substr( $exp_date, -2 ) );
					$token->set_user_id( get_current_user_id() );
					$token->save();

					return array( 'result' => 'success' );
				}
				catch ( SecurionPayException $e )
				{
					//something went wrong buddy!
				    
				    // handle error response - see https://securionpay.com/docs/api#error-object
				    $errorMessage = $e->getMessage();

				    if ( ! empty( $errorMessage ) )
				    {
						$error_msg = __( 'Error adding card : ', 'woo-securionpay' ) . $errorMessaget;
					}
					else
					{	
						$error_msg = __( 'Error adding card. Please try again.', 'woo-securionpay' );
					}
					
					return array( 'result' => 'error', 'message' => $error_msg );
				}
			}
		}

		/**
		 * Add payment method via account screen.
		 */
		public function add_payment_method()
		{
			// SecurionPay API key
			$api_key  = $this->secret_key;

			$exp_date_array = explode( "/", sanitize_text_field( $_POST['woo_securionpay_gateway-card-expiry'] ) );

			// load the securionpay library [https://github.com/securionpay/securionpay-php]
			require WOO_SECURIONPAY_ROOT_DIR . "/includes/vendor/autoload.php";

			// initiate the SecurionPay gateway library class
			$gateway = new SecurionPayGateway( $api_key );

			$result = $this->save_card( $gateway, $exp_date_array );

			if ( $result['result'] == 'success' )
			{	
				return array(
					'result'   => 'success',
					'redirect' => wc_get_endpoint_url( 'payment-methods' ),
				);
			}
			elseif ( $result['result'] == 'error' )
			{	
				wc_add_notice( $result['message'], 'error' ); return;
			}	
		}

		/**
		 * process_payment function.
		 *
		 * @access public
		 * @param mixed $order_id
		 * @return void
		 */
		public function process_payment( $order_id )
		{
			global $woocommerce;

			$order = wc_get_order( $order_id );
				
			$amount = woo_securionpay_get_amount( $order->get_total(), get_option('woocommerce_currency') );

			$card = '';

			if ( isset( $_POST['woo_securionpay_gateway-card-expiry'] ) )
			{
				$exp_date_array = explode( "/", sanitize_text_field( $_POST['woo_securionpay_gateway-card-expiry'] ) );
						
				$exp_month = trim( $exp_date_array[0] );
				
				$exp_year = trim( $exp_date_array[1] );
				
				$exp_date = $exp_month . substr( $exp_year, -2 );
			}

			// make up the data to send to Gateway API
			$request = array(
			    'amount' => $amount,
			    'currency' => get_option('woocommerce_currency'),
			    'card' => array(
			        'number' 		 => str_replace( ' ', '', sanitize_text_field( $_POST['woo_securionpay_gateway-card-number'] ) ),
			        'cvc' 		 	 => intval( $_POST['woo_securionpay_gateway-card-cvc'] ),
			        'expMonth' 		 => $exp_month,
			        'expYear' 		 => $exp_year
			    )
			);
				
			if ( isset( $_POST['wc-woo_securionpay_gateway-payment-token'] ) && 'new' !== $_POST['wc-woo_securionpay_gateway-payment-token'] )
			{	
				$token_id = wc_clean( $_POST['wc-woo_securionpay_gateway-payment-token'] );
				
				$card = WC_Payment_Tokens::get( $token_id );
				
				// Return if card does not belong to current user
				if ( $card->get_user_id() !== get_current_user_id() )
				{
					return;
				}

				$token = $card->get_token();

				$request['card'] = $token;

				$request['customerId'] = get_user_meta( get_current_user_id(), '_cust_id', true );
			}

			// SecurionPay API key
			$api_key  = $this->secret_key;

			// load the securionpay library [https://github.com/securionpay/securionpay-php]
			require WOO_SECURIONPAY_ROOT_DIR . "/includes/vendor/autoload.php";

			// initiate the SecurionPay gateway library class
			$gateway = new SecurionPayGateway( $api_key );

			// go for it... charge the amount and see if it has any chance...
			try
			{    
			    // the charge object after successfully charging a card
			    // do something with charge object - see https://securionpay.com/docs/api#charge-object
			    $charge = $gateway->createCharge( $request );

			    // charge id will be used as TransactionID for reference
	    		$chargeId = $charge->getId();

	    		$cardObj = $charge->getCard();

			    $order->payment_complete( $trans_id );

			    $order->reduce_order_stock();
				
				$woocommerce->cart->empty_cart();

				if ( ! empty( $card ) )
				{
					$exp_date = $card->get_expiry_month() . substr( $card->get_expiry_year(), -2 );
				}

				$amount_approved = number_format( $amount, '2', '.', '' );

				$order->add_order_note(
					sprintf(
						__( "Securionpay payment completed for %s. Transaction ID : %s", 'woo-securionpay' ), 
						$amount_approved,
						$chargeId
					)
				);
				
				$tran_meta = array(
					'transaction_id' => $chargeId,
					'cc_last4' => $cardObj->getLast4(),
					'cc_expiry' => $exp_date
				);

				add_post_meta( $order_id, '_securionpay_transaction', $tran_meta );

				// Save the card if possible
				if ( 'new' == sanitize_text_field( $_POST['wc-woo_securionpay_gateway-payment-token'] ) && is_user_logged_in() )
				{	
					$this->save_card( $gateway, $exp_date_array );
				}

				// Return thankyou redirect
				return array(
					'result' => 'success',
					'redirect' => $this->get_return_url( $order )
				);
			}
			catch ( Exception $e )
			{
				// handle error response - see https://securionpay.com/docs/api#error-object
				wc_add_notice( $e->getMessage(), 'error' );

				return array(
					'result'   => 'fail',
					'redirect' => ''
				);
			}
		}

		/**
		 * process_refund function.
		 *
		 * @access public
		 * @param int $order_id
		 * @param float $amount
		 * @param string $reason
		 * @return bool|WP_Error
		 */
		public function process_refund( $order_id, $amount = NULL, $reason = '' )
		{	
			$order = wc_get_order( $order_id );

			// SecurionPay API key
			$api_key  = $this->secret_key;

			$tran_meta = get_post_meta( $order_id, '_securionpay_transaction', true );

			$transaction_id = $tran_meta['transaction_id'];

			// if transaction id is not set then no refund can happen buddy!
			if ( empty( $transaction_id ) )
			{
				return;
			}

			if ( $amount > 0 )
			{
				try
				{
					// load the securionpay library [https://github.com/securionpay/securionpay-php]
					require WOO_SECURIONPAY_ROOT_DIR . "/includes/vendor/autoload.php";

					// initiate the SecurionPay gateway library class
					$gateway = new SecurionPayGateway( $api_key );

					// make up the data to send to Gateway API
					$request = array(
					    'chargeId' => $transaction_id,
					    'amount' => intval( $amount )
					);
					
					$refund = $gateway->refundCharge( $request );

				    // do something with charge object - see https://securionpay.com/docs/api#charge-object
				    $refundId = $refund->getId();
				    
				    $refunded_amount = number_format( $amount, '2', '.', '' );
						
					$order->add_order_note( sprintf( __( 'Securionpay refund completed for %s. Refund ID: %s', 'woo-securionpay' ), $refunded_amount, $refundId ) );
					
					return true;
				}
				catch ( Exception $e )
				{
					$order->add_order_note( $e->getMessage() );
				
					return new WP_Error( 'securionpay_error', $e->getMessage() );
				}
			}
			else
			{
				return false;
			}
		}

		/**
		 * Output field name HTML
		 *
		 * Gateways which support tokenization do not require names - we don't want the data to post to the server.
		 *
		 * @param  string $name
		 * @return string
		 */
		public function field_name( $name )
		{
			return ' name="' . esc_attr( $this->id . '-' . $name ) . '" ';
		}

    } // end \WC_Securionpay_Gateway class
}
