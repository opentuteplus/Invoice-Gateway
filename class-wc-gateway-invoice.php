<?php
/*
Plugin Name: OpenTute+ Invoice Gateway
Description: Custom payment gateway to allow for 'invoice' payment method with immediate access to virtual purchases.It will add a follow up email upon payment over due.
Author: OpenTute+
Author URI: http://opentuteplus.com
Version: 1.0
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define('IG_PLUGIN_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );
define('IG_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

/**
 * Invoice Payment Gateway.
 *
 * Provides a Invoice Payment Gateway, mainly for testing purposes.
 */
add_action('plugins_loaded', 'init_invoice_gateway_class');
function init_invoice_gateway_class(){

	class WC_Gateway_Invoice extends WC_Payment_Gateway {

	    /**
	     * Constructor for the gateway.
	     */
		public function __construct() {
			$this->id                 = 'invoice';
			$this->icon               = apply_filters('woocommerce_invoice_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'Invoice', 'woocommerce' );
			$this->method_description = __( 'Allows invoice payments. Why would you take invoices in this day and age? Well you probably wouldn\'t but it does allow you to make test purchases for trying our products/services etc.', 'woocommerce' );

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
			$this->order_status = $this->get_option( 'order_status', 'completed' );

			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	    	add_action( 'woocommerce_thankyou_invoice', array( $this, 'thankyou_page' ) );

	    	// Customer Emails
	    	add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

	    	add_action( 'wp_footer', array($this, 'add_js_select_invoice') );

	    	// follow email schedule
			add_action( 'order_create_by_invoice_gateway', array( $this, 'schedule_follow_up_email' ), 10, 1 );
			add_action( 'process_follow_up_email', array($this, 'process_follow_up_email'), 10, 1 );
	    }

	    /**
	     * Initialise Gateway Settings Form Fields.
	     */
	    public function init_form_fields() {

	    	$this->form_fields = array(
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Invoice Payment', 'woocommerce' ),
					'default' => 'yes'
				),
				'title' => array(
					'title'       => __( 'Title', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default'     => __( 'Invoice Payment', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'order_status' => array(
					'title'       => __( 'Order Status', 'woocommerce' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( 'Choose whether status you wish after checkout.', 'woocommerce' ),
					'default'     => 'wc-completed',
					'desc_tip'    => true,
					'options'     => wc_get_order_statuses()
				),
				'description' => array(
					'title'       => __( 'Description', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
					'default'     => __( 'Use our services without paying for 21 days.', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'instructions' => array(
					'title'       => __( 'Instructions', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woocommerce' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			);
	    }

	    /**
	     * Output for the order received page.
	     */
		public function thankyou_page() {
			if ( $this->instructions )
	        	echo wpautop( wptexturize( $this->instructions ) );
		}

	    /**
	     * Add content to the WC emails.
	     *
	     * @access public
	     * @param WC_Order $order
	     * @param bool $sent_to_admin
	     * @param bool $plain_text
	     */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
	        if ( $this->instructions && ! $sent_to_admin && 'invoice' === $order->payment_method && $order->has_status( 'on-hold' ) ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}

		public function payment_fields(){

			if ( $description = $this->get_description() ) {
				echo wpautop( wptexturize( $description ) );
			}

			?>
			<div id="invoice_input">
				<div class="" style="display: inline-block;">
					<input id="invoice_to_details" type="radio" class="input-radio" name="invoice_to" value="details">
					<label for="invoice_to_details" class=""><?php _e('My Contact Details'); ?></label>
				</div>
				<div class="hidden-field" data-show="details" style="">
					<p class="form-row form-row">
						<label for="invoice_details_phone" class=""><?php _e('Phone'); ?></label>
						<input type="text" class="input-text" name="invoice_details[phone]" id="invoice_details_phone" placeholder="<?php _e('Phone'); ?>" value="" style="width:inherit;">
					</p>
					<p class="form-row form-row">
						<label for="invoice_details_email" class=""><?php _e('Email'); ?></label>
						<input type="text" class="input-text" name="invoice_details[email]" id="invoice_details_email" placeholder="<?php _e('Email'); ?>" value="" style="width:inherit;">
					</p>
				</div>
			</div>
			<?php
		}

		public function add_js_select_invoice(){
			?>
			<script>
			(function($){
				$(document).on('change', '#invoice_input input[type=radio]', function() {
				    // alert(this.value);
				    $('#invoice_input').find('.hidden-field').hide();
				    $('#invoice_input').find("[data-show='" + this.value + "']").show();
				});
			})(jQuery);
			</script>
			<?php
		}

	    /**
	     * Process the payment and return the result.
	     *
	     * @param int $order_id
	     * @return array
	     */
		public function process_payment( $order_id ) {

			$order = wc_get_order( $order_id );

			$status = 'wc-' === substr( $this->order_status, 0, 3 ) ? substr( $this->order_status, 3 ) : $this->order_status;

			// Mark as on-hold (we're awaiting the invoice)
			$order->update_status( $status, __( 'Order created by invoice payment.', 'woocommerce' ) );

			// Reduce stock levels
			$order->reduce_order_stock();

			// Remove cart
			WC()->cart->empty_cart();

			// allow others
			do_action( 'order_create_by_invoice_gateway', $order );

			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);
		}

		public function schedule_follow_up_email($order){
			wp_schedule_single_event( time() + ( 21 * DAY_IN_SECONDS ) , 'process_follow_up_email', array($order) );
		}

		public function process_follow_up_email($order){
			// get order status
			$status = $order->get_status();
			if($status == 'completed')
				return;

			do_action( 'invoice_gateway_overdue', $order, $status );
		}
	}
}

add_filter( 'woocommerce_payment_gateways', 'add_invoice_gateway_class' );
function add_invoice_gateway_class( $methods ) {
	$methods[] = 'WC_Gateway_Invoice';
	return $methods;
}

add_action('woocommerce_checkout_process', 'process_invoice_payment');
function process_invoice_payment(){

	if($_POST['payment_method'] != 'invoice')
		return;

	if( !isset($_POST['invoice_to']) || empty($_POST['invoice_to']) )
		wc_add_notice( __( 'Please choose Who will be invoiced?' ), 'error' );

	$invoice_to = sanitize_text_field( $_POST['invoice_to'] );

	if( !isset( $_POST['invoice_'. $invoice_to] ) || empty( $_POST['invoice_'. $invoice_to] ) )
		wc_add_notice( __( 'Please complete your invoice detail' ), 'error' );

	// echo "<pre>";
	// print_r($_POST);
	// echo "</pre>";
	//exit();

}

/**
 * Update the order meta with field value
 */
add_action( 'woocommerce_checkout_update_order_meta', 'invoice_payment_update_order_meta' );
function invoice_payment_update_order_meta( $order_id ) {

	if($_POST['payment_method'] != 'invoice')
		return;

	if( !isset($_POST['invoice_to']) || empty($_POST['invoice_to']) )
		return;

	$invoice_to = sanitize_text_field( $_POST['invoice_to'] );
	if( !isset( $_POST['invoice_'. $invoice_to] ) || empty( $_POST['invoice_'. $invoice_to] ) )
		return;

	update_post_meta( $order_id, 'invoice_to', $invoice_to );
	update_post_meta( $order_id, 'invoice_info', $_POST['invoice_'. $invoice_to] );

	// echo "<pre>";
	// print_r($_POST);
	// echo "</pre>";
	// exit();
}

/**
 * Display field value on the order edit page
 */
add_action( 'woocommerce_admin_order_data_after_billing_address', 'my_custom_checkout_field_display_admin_order_meta', 10, 1 );
function my_custom_checkout_field_display_admin_order_meta($order){
    $method = get_post_meta( $order->id, '_payment_method', true );
    if($method != 'invoice')
    	return;

    $invoice_to = get_post_meta( $order->id, 'invoice_to', true );
    $invoice_info = get_post_meta( $order->id, 'invoice_info', true );

    echo '<p><strong>'.__('Invoice to ').':</strong> ' . ucfirst($invoice_to) . '</p>';

    if(!empty($invoice_info) && is_array($invoice_info)):
    foreach ($invoice_info as $key => $info) {
    	echo '<p><strong>'. sprintf( __('Invoice %s '), $key ) .':</strong> ' . $info . '</p>';
    }
    endif;


    // echo "<pre>";
    // print_r($invoice_info);
    // echo "</pre>";
}

/**
 *  Add a custom email to the list of emails WooCommerce should load
 *
 * @since 0.1
 * @param array $email_classes available email classes
 * @return array filtered available email classes
 */
function add_invoice_woocommerce_email( $email_classes ) {
	// include our custom email class
	require_once( 'includes/class-wc-invoice-order-email.php' );
	require_once( 'includes/class-wc-invoice-order-overdue-email.php' );
	// add the email class to the list of email classes that WooCommerce loads
	$email_classes['WC_Invoice_Order_Email'] = new WC_Invoice_Order_Email();
	$email_classes['WC_Invoice_Order_Overdue_Email'] = new WC_Invoice_Order_Overdue_Email();
	return $email_classes;
}
add_filter( 'woocommerce_email_classes', 'add_invoice_woocommerce_email' );
