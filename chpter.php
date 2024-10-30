<?php

// In order to prevent direct access to the plugin
defined('ABSPATH') or die("I am an awesome Plugin but I don't do much when called directly!");

 /*
 Plugin Name: chpter.
 Plugin URI: https://app.chpter.co/
 Description: chpter enables your woocommerce website to accept M-Pesa and Card payments. Also you can recover your lost revenue by capturing abandoned carts.
 Version: 3.1.3
 License: GPL-2.0+
 Author: Kelvin Kiprotich
 Author URI: https://kipling.marichtech.com/
 Text domain: chpter.
 */


//Add the css 
add_action( 'wp_enqueue_scripts', 'chpter_add_scripts' );
function chpter_add_scripts() {
    wp_enqueue_style( 'GoogleFont', 'https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@600&display=swap');
    wp_enqueue_style( 'GoogleSourceFonts', 'https://fonts.googleapis.com/css2?family=Source+Sans+Pro&display=swap');
    wp_enqueue_style( 'ChpterPluginStyle', 'https://cdn.jsdelivr.net/gh/kiplingkelvin/chpter-wp-cdn/chpter.css');
    wp_enqueue_script('ChpterPluginJs', 'https://cdn.jsdelivr.net/gh/kiplingkelvin/chpter-wp-cdn/chpter.js');
 }

 


 add_action('plugins_loaded', 'chpter_payment_init');

 add_action( 'init', function() {
     add_rewrite_rule( '^/scanner/?([^/]*)/?', 'index.php?scanner_action=1', 'top' );
 });

function chpter_payment_init()
{
    if (!class_exists('WC_Payment_Gateway'))
        return;

    class WC_Gateway_Chpter extends WC_Payment_Gateway 
    {
        /**
         *  Plugin constructor
         */

        public function __construct()
        {
            chpter_plugin_init();

             // Basic settings
             $this->id                 = 'chpter';
             $this->has_fields         = false;
             $this->method_title       = __( 'chpter 3.0', 'woocommerce' );
             $this->method_description = __( 'Enable customers to make payments to your business through M-Pesa or Card ' );

             // load the settings
             $this->init_form_fields();
             $this->init_settings();

             // Define variables set by the user in the admin section
             $this->title            =  $this->get_option('pluginName','chpter. payments');
             $this->description      = '🔒​ Secure 📱 Mpesa and 💳 Card Payments by chpter.';

            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            } else {
                add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
            }
            
            add_action( 'woocommerce_api_callback', array( $this, 'chpter_webhook' ) );
        }



        /**
         *Initialize form fields that will be displayed in the admin section.
         */
        public function init_form_fields()
        {
            $this->form_fields = array(
                array(
                    'title'       =>  __( 'PLUGIN SETTINGS', 'woocommerce' ),
                    'type'        => 'title', // Custom type for the header
                ),

                'pluginName' => array(
                    'title'       =>  __( 'Plugin Name', 'woocommerce' ),
                    'default'     => __( 'chpter. payments', 'woocommerce'),
                    'type'        => 'text',
                ),

                'functionalityType' => array(
                    'title'       =>  __( 'Functionality', 'woocommerce' ),
                    'description' => __( 'Select the plugin feature you want. (Default functionality is payments)', 'woocommerce' ),
                    'type'        => 'select',
                    'default'   =>  __( 'Payments', 'woocommerce' ),
                    'options'	=> array( 
                        'Payments'	=>__( 'Payments', 'woocommerce' ), 
                        'Abandoned Cart' =>__(  'Abandoned Cart', 'woocommerce' ),
                    ),
                ),

                array(
                    'title'       =>  __( 'PAYMENTS SETTINGS', 'woocommerce' ),
                    'type'        => 'title', // Custom type for the header
                ),

                'token' => array(
                    'title'       =>  __( 'Public Api Key', 'woocommerce' ),
                    'description' => __( 'Add your Public Key.', 'woocommerce' ),
                    'default'     => __( '', 'woocommerce'),
                    'type'        => 'text',
                ),

                'orderStatus' => array(
                    'title'       =>  __( 'Order Status', 'woocommerce' ),
                    'description' => __( 'Select the Status you want your Orders to be when Payment is Successful. (Default selected state is Completed)', 'woocommerce' ),
                    'type'        => 'select',
                    'default'   =>  __( 'Completed', 'woocommerce' ),
                    'options'	=> array( 
                        'Processing'	=>__( 'Processing', 'woocommerce' ), 
                        'Completed'		=>__(  'Completed', 'woocommerce' ),
                    ),
                ),
            );

             // Check if functionalityType is 'Payments' and add the header conditionally
            if ($this->get_option('functionalityType') === 'Abandoned Cart') {

                $this->form_fields['abandonedCartHeading'] = array(
                    'title' =>  __( 'ABANDONED CART SETTINGS', 'woocommerce' ),
                    'type'  => 'title',
                );

                $this->form_fields['abandonedCartCutOffTime'] = array(
                    'title'       =>  __( 'Cart abandoned cut-off time', 'woocommerce' ),
                    'description' => __( 'Note: Consider cart abandoned after above entered minutes of item being added to cart and order not placed.', 'woocommerce' ),
                    'default'     => __( 10, 'woocommerce'),
                    'type'        => 'number',
                    'custom_attributes' => array(
                        'step' => '1',
                        'min'  => '10',
                    ),
                );
            }

             $payload = array(
            "feature" => $this->get_option("functionalityType"),
            );
            chpter_update_config($payload);
        }

        public function getAbandonedCartCutOffTime() {
            return $this->get_option("abandonedCartCutOffTime", 10);
        }

        /**
         * Generates the HTML for admin settings page
         */

        public function admin_options()
        {
            /** 
             *The heading and paragraph below are the ones that appear on the Payment Gateway settings
             */

            echo '<h3>chpter. payments and abandoned cart recovery plugin</h3>';
            echo '<table class="form-table">';
            $this->generate_settings_html( );
            echo '</table>';

        }


        public function process_payment( $order_id ) {
            $mpesaApiUrl = "https://api.chpter.co/v1/woocommerce/mpesa-payment";
            $cardApiUrl = "https://api.chpter.co/v1/woocommerce/card-payment";

            global $woocommerce;
            $order = wc_get_order( $order_id );

            $payment_type = get_post_meta($order_id, 'paymentmethod', true);
            $tel =  get_post_meta($order_id, 'mpesaphonenumber', true);
            if($payment_type == 'MPesa'){
                if($tel == ''){
                    wc_add_notice( "Enter your M-Pesa Number", 'error' );
                    return;
                }
            }else{
                $tel = $order->get_billing_phone();
            }

            $tel = str_replace( array(' ', '<', '-', '>', '&', '{', '}', '*', "+", '!', '@', '#', "$", '%', '^', '&'), "", $tel );
            $updatedPhone = "254".substr($tel, -9);
            
            $items = $woocommerce->cart->get_cart();
            $cart_products_objects = [];
            foreach($items as $item => $values) { 
                $_product =  wc_get_product( $values['data']->get_id()); 
                
                //If product is downloadable show download button
                if ($_product->is_downloadable('yes')) {
                    foreach( $_product->get_downloads() as $key_download_id => $download ) {
                        ## Using WC_Product_Download methods (since WooCommerce 3)
                        $download_link = $download->get_file(); // File Url
                    }
                } 

                $cart_products_objects[] = (object) [
                    //Sanitize all cart data before creating an array
                    "product_name" => sanitize_text_field($_product->get_title()),
                    "quantity" => (int)(sanitize_text_field($values['quantity'])),
                    "unit_price" => (float)sanitize_text_field(get_post_meta($values['product_id'] , '_price', true) ? get_post_meta($values['product_id'] , '_price', true) : $order->total),
                    "digital_link" => sanitize_text_field($download_link),
                ];
            } 

            $payload = array(
                    "customer_details" =>
                    array(
                            "full_name" =>  sanitize_text_field($order->get_billing_first_name() ? $order->get_billing_first_name() :'') ." ". sanitize_text_field($order->get_billing_last_name()? $order->get_billing_last_name() :''),
                            "location" =>  sanitize_text_field($order->get_billing_city() ? $order->get_billing_city() :''),
                            "email" =>  sanitize_email($order->get_billing_email() ? $order->get_billing_email() :''),
                            "phone_number" =>  wc_sanitize_phone_number($updatedPhone ? $updatedPhone :''),
                        ),
                        "products" => $cart_products_objects ? $cart_products_objects : array( array("product_name" => 'WooCommerce Product',"quantity" => 1,"unit_price" => (float)$order->total, "digital_link"=>'')),
                        "amount" =>
                        array(
                            "currency" => sanitize_text_field(strtolower(get_woocommerce_currency())),
                            "delivery_fee" => (float)sanitize_text_field($order->get_shipping_total()? $order->get_shipping_total() :0),
                            "discount_fee" => (float)sanitize_text_field( $order->get_discount_total()? $order->get_discount_total() :0),
                            "total" => (float)sanitize_text_field($order->get_total()),
                            
                        ),
                        "callback_details" => array(
                            "order_id" => sanitize_text_field($order_id),
                            "shop_url"  => sanitize_url(get_site_url( null, '', null )),
                            "callback_url" => sanitize_url(get_site_url( null, '', null )."/?wc-api=callback")
                        )
            );

            $decodedResp = $this->api_post_request($payment_type == "Card" ? $cardApiUrl : $mpesaApiUrl,$payload);
            
            if(isset($decodedResp["success"])){
                
                if($decodedResp["success"]){
                    //Card payment
                    if ($payment_type == "Card") {
                        if(isset($decodedResp["checkout_url"])){
                            return array(
                                'result' => 'success',
                                'redirect' => $decodedResp["checkout_url"]
                            ); 
                        }
                    }

                    //MPesa payment
                    if ($payment_type == "MPesa") {

                        if($this->get_option( 'orderStatus' ) == "Completed"){
                            $order->update_status('wc-completed'); 
                        }
                    
                        if($this->get_option( 'orderStatus' ) == "Processing"){
                            $order->update_status('wc-processing'); 
                        }
                        // Remove cart
                        $woocommerce->cart->empty_cart();

                        // Return thank you page redirect
                        return array(
                            'result' => 'success',
                            'redirect' => $this->get_return_url( $order )
                        ); 
                    }   
                }

                if($decodedResp["success"] == false){
                    $order->update_status( 'failed' );
                    wc_add_notice( $decodedResp["message"], 'error' );
                    return;
                }
            }

            wc_add_notice( "Process failed please retry", 'error' );
            return;
        }


        public function api_post_request($apiUrl, $arrayObj){
            $url = sanitize_text_field($apiUrl);
            $token = sanitize_text_field($this->get_option( 'token' ));
            $header = array("Content-Type"=> "application/json", "Api-Key" => $token);

            $args = array(
                'method'      => 'POST',
                'timeout'     => 180,
                'blocking'    => true,
                'headers'     =>  $header,
                'body'        => json_encode($arrayObj),
            );

            $response = wp_remote_post( $url, $args);

            if ( is_wp_error( $response ) ) {
                $error_message = $response->get_error_message();
                return null;
            } else { 
                return  json_decode(wp_remote_retrieve_body($response), true);
            }
        }

        /** Webhook */
        public function chpter_webhook() { 
            $data = json_decode(file_get_contents('php://input'), true);

            if($data['Success']){
                $order = wc_get_order((int) $data['transaction_reference'] );
                $order->update_status('wc-completed'); 
            }
        }

    }
}

/**
 * Add Gateway to WooCommerce
 **/

function chpter_gateway_class( $methods ) {
    $methods[] = 'WC_Gateway_Chpter';
    return $methods;
}

if(!add_filter( 'woocommerce_payment_gateways', 'chpter_gateway_class' )){
    die;
}

add_action( 'woocommerce_gateway_description', 'chpter_select_field' , 20, 2 );
function chpter_select_field( $description, $payment_id ){
    if( 'chpter' === $payment_id ){
        ob_start();
        echo '
            <div class="form-container">
                <div class="field-container">
                        <input type="hidden" id="paymentmethod" name="paymentmethod" value="MPesa"/>
                    
                        <div id="Group_6097_carddp">
                            <div class="mpesa-content">
                            📱
                            </div>

                            <div id="Carddp-mpesa">
                                <span id="tile-heading">MPesa</span>
                            </div>
                        </div>

                        <div id="Group_60977_carddp">
                            <div class="card-content">
                                💳
                            </div>

                            <div id="Carddp-card">
                                <span id="tile-heading">Card</span>
                            </div>
                        </div>

                        <div id="Group_60977_coddp">
                            <div class="cod-content">
                            💵
                            </div>

                            <div id="Carddp-cod">
                                <span id="tile-heading">COD</span>
                            </div>
                        </div>
                </div>
                <div id="coddetails">
                    <div class="field-container">
                        <div id="Card_Information_coddp">
                            <span id="cod_message_title">Cash On Delivery</span>
                            <span id="cod_message_body">Once you click place order, your order will be <br/> processed. You will receive an SMS with a <br/>payment link to complete the purchase.</span>
                        </div>
                    </div>
                </div>
                <div id="mpesadetails">
                    <div class="field-container">
                        <div id="Card_Information_carddp">
                            <span>M-Pesa Phone Number</span>
                        </div>
                        <div  class="ui labeled input" id="phonenumberdp" >
                            <div class="ui basic label" id="phonenumber254dp" >254</div>
                            <input class="ui labeled input" id="phonenumber_inputdp" type="number" name="mpesaphonenumber" placeholder="700 000 000 / 100 000 000" isvalid="false" onKeyPress="if(this.value.length==12) return false;" autocomplete="off" required >
                        </div>
                    </div>
                </div>
                <div id="bnkdtails">
                    <div class="field-container">
                    <div id="Card_Information_coddp">
                        <span id="cod_message_title">Card Payment</span>
                        <span id="cod_message_body"> Ready to complete the checkout? Click place order<br/> to proceed. You will be redirected to a secure page<br/> to complete the purchase.</span>
                    </div>
                    </div>
                </div>
            </div>';
    
        $description .= ob_get_clean(); 

        ?>

        <script type="text/javascript">
            jQuery(function($) {
                $('#Group_60977_coddp').css("visibility", "hidden");
                $('#bnkdtails').fadeOut("fast", function() {})
                $('#coddetails').fadeOut("fast", function() {})
                $('input[name=paymentmethod]').val('MPesa');
                $('#Group_6097_carddp').css('border-color', '#377DE8');

                //On Card click perform
                $('#Group_60977_carddp').click(function(e) {
                    $('#mpesadetails').fadeOut("fast", function() {})
                    $('#coddetails').fadeOut("fast", function() {})
                    $('#bnkdtails').css("visibility", "visible");
                    $('#bnkdtails').fadeIn("fast", function() {})
                    $('input[name=paymentmethod]').val('Card');
                    $('#Group_6097_carddp').css("border-color", "#0a0a0a");
                    $('#Group_60977_carddp').css('border-color', '#377DE8');
                });

                //On Mpesa click perform
                $('#Group_6097_carddp').click(function(e) {
                    $('#bnkdtails').fadeOut("fast", function() {})
                    $('#coddetails').fadeOut("fast", function() {})
                    $('#mpesadetails').fadeIn("fast", function() {})
                    $('input[name=paymentmethod]').val('MPesa');
                    $('#Group_6097_carddp').css('border-color', '#377DE8');
                    $('#Group_60977_carddp').css("border-color", "#0a0a0a");
                });
                
            });
        </script>
        <?php 
    }

    return $description;
}

add_action('woocommerce_checkout_update_order_meta', 'chpter_save_our_fields');

function chpter_save_our_fields( $order_id ){
	if( ! empty( $_POST[ 'paymentmethod' ] ) ) {
		update_post_meta( $order_id, 'paymentmethod', wc_clean( $_POST[ 'paymentmethod' ] ) );
	} 

    if( ! empty( $_POST[ 'mpesaphonenumber' ] ) ) {
		update_post_meta( $order_id, 'mpesaphonenumber', wc_clean( $_POST[ 'mpesaphonenumber' ] ) );
	}
}

function chpter_checkout_listener() {
    $cart_hash = WC()->cart->get_cart_hash();
    // Prepare the data to send.
    $payload = array(
        'event' => 'checkout_key',
    );

    $customer = WC()->customer->get_data();
    if($customer["id"] != 0){
        $payload["customer_data"] = json_encode($customer);
    }

    if (isset($_POST['phone'])){
        if(strlen($_POST['phone']) > 8){
            $payload["phone"] = "+254".substr($_POST['phone'], -9);
            $payload["status"] = 'abandoned';
        }
    }

    if (isset($_POST['email'])){
        $payload["email"] = sanitize_text_field($_POST['email']);
        $payload["status"] = 'abandoned';
    }

    if (isset($_POST['first_name'])){
        $payload["first_name"] = sanitize_text_field($_POST['first_name']);
    }

    if (isset($_POST['last_name'])){
        $payload["last_name"] = sanitize_text_field($_POST['last_name']);
    }

    chpter_update_tracking_data("chpter_abandon_cart", $payload, $cart_hash);

    wp_die();
}

add_action('wp_ajax_chpter_checkout_listener', 'chpter_checkout_listener');
add_action('wp_ajax_nopriv_chpter_checkout_listener', 'chpter_checkout_listener');

// Hook into the WooCommerce cart initialization
function chpter_cart_initialization() {
    $cart_hash = WC()->cart->get_cart_hash();

    // Prepare the data to send.
    $payload = array(
        'event' => 'cart_initialized',
        'currency' =>  strtoupper(sanitize_text_field(get_woocommerce_currency())),
        'cart_contents' => json_encode(WC()->cart->get_cart()),
        'cart_total' => WC()->cart->get_cart_contents_total(),
        'cart_hash' => $cart_hash,
    );

    $customer = WC()->customer->get_data();
    if(isset($customer)){
        if($customer["id"] != 0){
            $payload["customer_data"] = json_encode($customer);
        }
    }
    
    chpter_insert_tracking_data("chpter_abandon_cart", $payload);
}
add_action('woocommerce_after_cart', 'chpter_cart_initialization');

// Hook into the WooCommerce order creation
function chpter_track_order_creation($order_id) {
    chpter_remove_tracking_data(WC()->cart->get_cart_hash());
}
add_action('woocommerce_checkout_update_order_meta', 'chpter_track_order_creation');

function chpter_create_tables() {
    global $wpdb;
    $chpter_config = $wpdb->prefix . 'chpter_config';
    $chpter_abandon_cart = $wpdb->prefix . 'chpter_abandon_cart';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $chpter_config (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        feature enum('Payments','Abandoned Cart') NOT NULL DEFAULT 'Payments',
        store varchar(255) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;
    
    CREATE TABLE $chpter_abandon_cart (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        event varchar(255) NOT NULL,
        status enum('normal','abandoned','completed','lost') NOT NULL DEFAULT 'normal',
        cart_total decimal(10,2) NOT NULL,
        cart_hash varchar(255) NOT NULL,
        currency varchar(255) NOT NULL,
        cart_contents JSON NOT NULL,
        customer_data JSON NULL,
        phone varchar(255) NULL,
        email varchar(255) NULL,
        first_name varchar(255) NULL,
        last_name varchar(255) NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function chpter_plugin_init() {
    chpter_create_tables();

    $store = sanitize_url(get_site_url( null, '', null ));

    global $wpdb;
    $table_name = $wpdb->prefix . 'chpter_config';
    $existing_record = $wpdb->get_row($wpdb->prepare("SELECT feature FROM $table_name WHERE store = %s",$store));
    if ($existing_record != null) {
        if($existing_record->feature == "Abandoned Cart"){
            chpter_abandon_cart_checkout_update();  
        }
    }else{
        $payload = array(
            'feature'=> 'payments',
            'store'=> $store,
        );
        chpter_insert_config($payload);
    }
}

// Define the remove_payment_gateway function outside of chpter_abandon_cart_checkout_update
function chpter_remove_payment_gateway( $available_gateways ) {
    if (is_checkout()) {
        // Replace 'payment_gateway_id' with the ID of the payment gateway you want to remove
        unset($available_gateways['chpter']);
    }
    return $available_gateways;
}

function chpter_checkout_reorder( $checkout_fields ) {
    $checkout_fields[ 'billing' ][ 'billing_phone' ][ 'priority' ] = 4;
    $checkout_fields[ 'billing' ][ 'billing_email' ][ 'priority' ] = 5;
    return $checkout_fields;
}

function chpter_abandon_cart_checkout_update() {
    // Add filter for removing the payment gateway
    add_filter('woocommerce_available_payment_gateways', 'chpter_remove_payment_gateway');

    // Add filter for checkout field reorder
    add_filter( 'woocommerce_checkout_fields', 'chpter_checkout_reorder' );
}

// Function to insert data into the table
function chpter_insert_tracking_data($table_name, $payload) {
    global $wpdb;
    $table_name = $wpdb->prefix . $table_name;

    $existing_record = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table_name WHERE cart_hash = %s", $payload["cart_hash"]));
    if ($existing_record != null) {
       return;
    }
    $created =$wpdb->insert($table_name,$payload);
    if ($created === false) {
        // Handle the create error $wpdb->last_error;
    }
    return;
}

function chpter_update_tracking_data($table_name, $payload, $cart_hash) {
    global $wpdb;
    $table_name = $wpdb->prefix . $table_name;
    $where = array('cart_hash' => $cart_hash);
    $updated = $wpdb->update($table_name, $payload, $where);
    if ($updated === false) {
        // Handle the update error
    }
}

function chpter_remove_tracking_data($cart_hash) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'chpter_abandon_cart';
    $where = array('cart_hash' => $cart_hash);
    $wpdb->delete($table_name, $where);
}

function chpter_insert_config($payload) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'chpter_config';
    $wpdb->insert($table_name,$payload);
}
function chpter_update_config($payload) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'chpter_config';
    $where = array('id' => 1);
    $wpdb->update($table_name, $payload, $where);
}

function chpter_cron_intervals($schedules) {
    $schedules['every_five_minutes'] = array(
        'interval' => 700,
        'display'  => __('Every 5 minutes', 'textdomain')
    );
    return $schedules;
}
add_filter('cron_schedules', 'chpter_cron_intervals');

function schedule_chpter_cron_job() {
    if (!wp_next_scheduled('chpter_abandon_cart_cron_event')) {
        wp_schedule_event(time(), 'every_five_minutes', 'chpter_abandon_cart_cron_event');
    }
}

add_action('wp', 'schedule_chpter_cron_job');

function chpter_process_abandoned_carts() {
    $plugin = new WC_Gateway_Chpter();
    $cut_off_time = (int)$plugin->getAbandonedCartCutOffTime();

    global $wpdb;
    $table_name = $wpdb->prefix . 'chpter_abandon_cart';
    $existing_records = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE updated_at <= NOW() - INTERVAL %d MINUTE AND status = %s", $cut_off_time, "abandoned"));
    if ($existing_records) {
        chpter_abandon_cart_recovery($existing_records);
       return;
    }
}
add_action('chpter_abandon_cart_cron_event', 'chpter_process_abandoned_carts');

function chpter_abandon_cart_recovery($records) {
    $url = 'https://api.app.chpter.co/dale/v1/woocommerce/checkouts/callback-url';

    $headers = array(
        "Content-Type"=> "application/json", 
        "X-Chpter-Shop-Url" => sanitize_url(get_site_url( null, '', null )),
        "X-Chpter-Source" => "Plugin",
    );

    foreach ($records as $record) {
        $data = array(
            "event"=> $record->event,
            "first_name"=> $record->first_name,
            "last_name"=> $record->last_name,
            "phone"=> $record->phone,
            "email"=> $record->email,
            "cart_hash"=> $record->cart_hash,
            "currency"=> $record->currency,
            "cart_total"=> (float)$record->cart_total,
            "cart"=> json_decode($record->cart_contents),
            "customer"=> json_decode($record->customer_data),
            "updated_at"=> $record->updated_at,
        );

        $response = wp_remote_post($url, array(
            'body' => json_encode($data),
            'headers' =>  $headers,
        ));
    
        if (wp_remote_retrieve_response_code($response) == 200){
            chpter_remove_tracking_data($record->cart_hash);
        }
    }
}

/** 
*
* Everything great has an ending
* But not all endings are great
* kipling
*/

?>