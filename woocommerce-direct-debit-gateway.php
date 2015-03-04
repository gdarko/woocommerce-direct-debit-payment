<?php
/*
Plugin Name: WooCommerce Direct Debits Gateway
Plugin URI: http://darkog.com/plugins/direct-debit/
Description: Integration for Direct Debit Payment Gateway
Version: 0.1
Author: Darko Gjorgjijoski
Author URI: http://darkog.com/
License: GPLv2
*/

/** Check if WooCommerce is active **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {


add_action('plugins_loaded', 'init_direct_debits_gateway', 0);
 
    function init_direct_debits_gateway() {
	/**
	 * Invoice Payment Gateway
	 *
	 */
	class WC_Gateway_Direct_Debits extends WC_Payment_Gateway {
	    /**
	     * Constructor for the gateway.
	     *
	     */
		public function __construct() {
	        $this->id		= 'directdebit';
	        $this->icon 		= apply_filters('woocommerce_invoice_icon', '');
	        $this->has_fields 	= true;
	        $this->method_title     = __( 'Direct Debit', 'dg_wc_directdebit' );
	
			// Load the form fields.
			$this->init_form_fields();
	
			// Load the settings.
			$this->init_settings();
	
			// Define user set variables
			$this->title = $this->settings['title'];
			$this->description = $this->settings['description'];
			$this->emailmsg = $this->settings['emailmsg'];
	
			// Actions
			add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
	    	add_action('woocommerce_thankyou_directdebit', array(&$this, 'thankyou_page'));

	    	/** Detecting WC version **/
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0', '<' ) ) {
			  add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
			} else {
			  add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			}
	
	    	// Customer Emails
	    	add_action('woocommerce_email_before_order_table', array(&$this, 'email_instructions'), 10, 2);
	    }
	    /**
	     * Initialise Gateway Settings Form Fields
	     *
	     */
	    function init_form_fields() {
	
	    	$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'dg_wc_directdebit' ),
					'type' => 'checkbox',
					'label' => __( 'Enable Direct Debit Payment', 'dg_wc_directdebit' ),
					'default' => 'yes'
							),
				'title' => array(
					'title' => __( 'Title', 'dg_wc_directdebit' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'dg_wc_directdebit' ),
					'default' => __( 'Direct Debit Payment', 'dg_wc_directdebit' )
							),
				'description' => array(
					'title' => __( 'Customer Message', 'dg_wc_directdebit' ),
					'type' => 'textarea',
					'description' => __( 'Enter the description that goes on order-review page after the order has been submited' ),
					'default' => __( 'Add some description here', 'dg_wc_directdebit' )
							),
				'emailmsg' => array(
					'title' => __( 'Order Email Message', 'dg_wc_directdebit' ),
					'type' => 'textarea',
					'description' => __( 'Enter the message that is sent on the order-confirmation email after the order has been submited' ),
					'default' => __( 'Add some description here', 'dg_wc_directdebit' )
							)
				);
	
	    }
		/**
		 * Admin Panel Options
		 * - Options for bits like 'title' and availability on a country-by-country basis
		 *
		 */
		public function admin_options() {
	
	    	?>
	    	<h3><?php _e('Direct Debit', 'dg_wc_directdebit'); ?></h3>
	    	<p><?php _e('Allows customer to enter the IBAN and BIC number and the proceed with payment. This method is also called Direct Debits.', 'dg_wc_directdebit'); ?></p>
	    	<table class="form-table">
	    	<?php
	    		// Generate the HTML For the settings form.
	    		$this->generate_settings_html();
	    	?>
			</table><!--/.form-table-->
	    	<?php
	    }
	    /**
	     * Output for the order received page.
	     *
	     */
		function thankyou_page() {
			if ( $emailmsg = $this->emailmsg )
	        	echo wpautop( wptexturize( $emailmsg ) );
		}
	
	    /**
	     * Add content to the WC emails.
	     *
	     */
		function email_instructions( $order, $sent_to_admin ) {
	    	if ( $sent_to_admin ) return;
	
	    	if ( $order->status !== 'on-hold') return;
	
	    	if ( $order->payment_method !== 'directdebit') return;
	
			if ( $emailmsg = $this->emailmsg )
	        	echo wpautop( wptexturize( $emailmsg ) );
		}

		/**
		 * Create custom fields for IBAN AND BIC
		 */
		public function payment_fields() {
			if ( $this->description ) { echo wpautop( $this->description ); } ?>
			
	         <fieldset id="direct-debits-form">
	         	<div class="row-form">
				    <label for="dg_iban_number"><?php _e( 'Bankkontonummer - IBAN:', 'dg_wc_directdebit' ); ?></label>
				    <input type="text" id="dg_iban_number" name="dg_iban_number" />
				</div>
				<div class="row-form">
					<label for="dg_bic_number"><?php _e( 'Swift - BIC:', 'dg_wc_directdebit' ); ?></label>
				    <input type="text" id="dg_bic_number" name="dg_bic_number" />
			    </div>
				
			</fieldset><?php
	    }
	    /**
	     * Process the payment and return the result
	     *
	     */
		function process_payment( $order_id ) {
			global $woocommerce;
	
			$order = new WC_Order( $order_id );
	
			// Mark as on-hold (we're awaiting the invoice)
			$order->update_status('on-hold', __('Awaiting payment', 'dg_wc_directdebit'));
	
			// Reduce stock levels
			$order->reduce_order_stock();
	
			// Remove cart
			$woocommerce->cart->empty_cart();
	
			// Empty awaiting payment session
			unset( $woocommerce->session->order_awaiting_payment );
	
			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);
		}
	}
		/**
		 * Add the gateway to WooCommerce
		 *
		 */
		function add_direct_debits_gateway( $methods ) {
			$methods[] = 'WC_Gateway_Direct_Debits';
			return $methods;
		}
		add_filter('woocommerce_payment_gateways', 'add_direct_debits_gateway' );
    }


	/**
	 * With the following code we add metabox to the order edit page with
	 * IBAN and BIC information.
	 */
	add_action( 'woocommerce_checkout_update_order_meta', 'my_woocommerce_payment_complete');
	function my_woocommerce_payment_complete( $order_id ){
		$iban_no = ( isset( $_POST['dg_iban_number'] ) )? $_POST['dg_iban_number'] : 0;
		$bic_no = ( isset( $_POST['dg_bic_number'] ) )? $_POST['dg_bic_number'] : 0;
		update_post_meta( $order_id, 'dg_iban_number', $iban_no );
		update_post_meta( $order_id, 'dg_bic_number', $bic_no );
	}
	// Register metabox
	add_action( 'add_meta_boxes',  'add_directdebit_metabox' );
	function add_directdebit_metabox(){
			 add_meta_box( 'direct-debit-metabox', 'IBAN and BIC Information',  'directdebit_information_render', 'shop_order', 'normal', 'default');
	}
	// Pull the $_POST info into the Order type item
	function directdebit_information_render(){
		global $post;
		$dg_iban_number = get_post_meta( $post->ID, 'dg_iban_number', true);
		$dg_bic_number = get_post_meta( $post->ID, 'dg_bic_number', true);

		$information_set = true;

		if(!isset($dg_iban_number) || empty($dg_iban_number) || $dg_iban_number == ""){
			echo "No IBAN information to show!<br/>";
			$information_set = false;
		}
		if(!isset($dg_bic_number)  || empty($dg_bic_number) || $dg_bic_number == "" ){
			echo "No BIC information to show!<br/>";
			$information_set = false;
		}
		if($information_set){
			echo "<h3>IBAN: " . $dg_iban_number . "</h3>" . "<h3>BIC: " . $dg_bic_number . "</h3>";
		}	
	}
}
