<?php
/*
  Plugin Name: Onebip Mobile Payment
  Description: Allows payments by mobile phone with Onebip. You will need an Onebip account, contact woocommerce@onebip.com.
  Author URI: http://corporate.onebip.com/
  Author: Onebip S.r.l.
  Version: 1.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('ONEBIP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ONEBIP_PLUGIN_PATH', plugin_dir_path(__FILE__));


/**
 * Initiate Onebip Mobile Payment once plugin is ready
 */
add_action('plugins_loaded', 'woocommerce_onebip_init');

function woocommerce_onebip_init() {

    class WC_Onebip extends WC_Payment_Gateway {

        public $domain;
        private $surcharge_version = array("VAT inc.","VAT excl. CCM","VAT excl. REV");


        /**
         * Constructor for the gateway.
         */
        public function __construct() {
            $this->domain = 'onebip';

            $this->log("123");

            $this->id = 'onebip';
            $this->icon = ONEBIP_PLUGIN_URL . 'assets/images/logo.png';
            $this->has_fields = false;
            $this->method_title = __('Pay by phone', $this->domain);
            $this->method_description = __('Pay with your mobile phone bill or your prepaid credits', $this->domain);

            // Define user set variables
            $this->icon = $this->get_option("icon");
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->description_text = $this->get_option('description_text');
            $this->api_key = $this->get_option('api_key');
            $this->username = $this->get_option('username');
            $this->countries = $this->get_option('countries');



            $this->vat_detail = get_option(
                'onebip_vat_detail',
                array(
                    array(
                        'country'   => $this->get_option( 'Country' ),
                        'vat' => $this->get_option( 'VAT in %' ),
                        'payout'      => $this->get_option( 'Payout in %' ),
                        'description'      => $this->get_option( 'Surcharge Fee Description' ),
                        'version'   => $this->get_option( 'Version' ),
                    ),
                )
            );


            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_vat_details' ) );
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
            add_action('woocommerce_api_'. strtolower("WC_Onebip"), array( $this, 'check_ipn_response' ) );

            add_action( 'woocommerce_cart_calculate_fees',array( $this, 'woocommerce_custom_surcharge'), 20,1 );

        }
        /**
         * Save account details table.
         */
        public function save_vat_details() {
            $vat_detail = array();
            //Check if CVS file is uploaded for import.
            if(isset($_FILES['csv_data']['name'])){
                if($_FILES['csv_data']['type'] == 'text/csv'){
                    $file = fopen($_FILES['csv_data']['tmp_name'],"r");
                    while (($data = fgetcsv($file)) !== FALSE) {
                        if($data[0] != "Country"){
                            //Check if country is already added.
                            $countries = array_column($vat_detail,"country");
                            if(!in_array($data[0], $countries)) {
                                if($this->validate_surcharge_row($data[ 0 ], $data[ 1 ], $data[ 2 ], $data[ 3 ], $data[ 4 ]  )) {
                                    $vat_detail[] = array(
                                        'country' => $data[0],
                                        'vat' => $data[1],
                                        'payout' => $data[2],
                                        'description' => $data[3],
                                        'version' => $data[4]
                                    );
                                }
                            }
                        }
                    }
                    fclose($file);
                }
            }
            // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification already handled in WC_Admin_Settings::save()
            if ( isset( $_POST['payment_country'] ) && isset( $_POST['payment_vat'] ) && isset( $_POST['payment_payout'] )
                && isset( $_POST['payment_description'] ) && isset( $_POST['payment_version'] )  ) {

                $country   = wc_clean( wp_unslash( $_POST['payment_country'] ) );
                $vat = wc_clean( wp_unslash( $_POST['payment_vat'] ) );
                $payout      = wc_clean( wp_unslash( $_POST['payment_payout'] ) );
                $description      = wc_clean( wp_unslash( $_POST['payment_description'] ) );
                $version      = wc_clean( wp_unslash( $_POST['payment_version'] ) );

                foreach ( $country as $i => $name ) {
                    if ( ! isset( $country[ $i ] ) ) {
                        continue;
                    }
                    $countries = array_column($vat_detail,"country");
                    if(!in_array($country[ $i ], $countries)){
                        if($this->validate_surcharge_row($country[ $i ], $vat[ $i ], $payout[ $i ], $description[ $i ], $version[ $i ]  )) {
                            $vat_detail[] = array(
                                'country'   => $country[ $i ],
                                'vat' => $vat[ $i ],
                                'payout'      => $payout[ $i ],
                                'description'      => $description[ $i ],
                                'version'      => $version[ $i ]
                            );
                        }
                    }
                }
            }
            // phpcs:enable
            update_option( 'onebip_vat_detail', $vat_detail );
        }

        private function validate_surcharge_row($country, $vat, $payout, $description, $version){

            if(strlen($country) > 2){
                $this->$this->log("Invalid country name. " . $country);
                return false;
            }
            if((int) $vat > 100){
                $this->$this->log("Invalid VAT value. " . $vat);
                return false;
            }
            if((int) $payout > 100){
                $this->$this->log("Invalid payout value. " . $payout);
                return false;
            }
            if(strlen($description) < 0){
                $this->$this->log("Description required.");
                return false;
            }
            if(!in_array(trim($version), $this->surcharge_version)){
                $this->log("Invalid version provided. " . trim($version));
                return false;
            }

            return true;
        }

        function woocommerce_custom_surcharge() {

            $surcharge_list = get_option('onebip_vat_detail');
            $this->log($surcharge_list);
            $country = @$_POST['country'];
            $this->log($country);
            $country_list = array_column($surcharge_list, "country");
            $this->log($country_list);

            $chosen_gateway = WC()->session->chosen_payment_method;
            $this->log($chosen_gateway);
            $this->log(in_array($country, $country_list) ? "true": "false");

            if($chosen_gateway == 'onebip' && in_array($country, $country_list)){
                $surcharge_data =  $surcharge_list[array_search($country, $country_list)];//   $this->vat_detail_file[$country];
                $cart_total = WC()->cart->cart_contents_total;
                $surcharge = 0;
                switch(trim($surcharge_data['version'])){
                    case "VAT incl.":
                        $surcharge = ($cart_total/$surcharge_data['payout']) * (1+$surcharge_data['vat']);
                        break;
                    case "VAT inc.":
                        $surcharge = ($cart_total/$surcharge_data['payout']) * (1+$surcharge_data['vat']);
                        break;
                    case "VAT excl. CCM":
                        $surcharge = ($cart_total/$surcharge_data['payout']);
                        break;
                    case "VAT excl. REV":
                        $surcharge = (($cart_total/(1+$surcharge_data['vat']))/$surcharge_data['payout']) * (1+$surcharge_data['vat'])  ;
                        break;
                    default:
                        $surcharge = 0;
                        break;
                }
                WC()->cart->add_fee( $surcharge_data[3], $surcharge );
            }
        }

        /**
         * Initialize Gateway Settings Form Fields.
         */
        public function init_form_fields() {
            $countries_obj   = new WC_Countries();
            $countries   = $countries_obj->__get('countries');

            $field_arr = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', $this->domain),
                    'type' => 'checkbox',
                    'label' => __('Enable mobile payments with Onebip (For an account contact woocommerce@onebip.com)', $this->domain),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', $this->domain),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', $this->domain),
                    'default' => $this->method_title,
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Instructions', $this->domain),
                    'type' => 'textarea',
                    'description' => __('Instructions that that the customer will see on your checkout page.', $this->domain),
                    'default' => $this->method_description,
                    'desc_tip' => true,
                ),
                'username' => array(
                    'title' => __('Username', $this->domain),
                    'type' => 'text',
                    'description' => __('The email address associated to your Onebip account', $this->domain),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'api_key' => array(
                    'title' => __('API Key', $this->domain),
                    'type' => 'text',
                    'description' => __('"API Key" entry under the "My Account" section on Onebip panel or provided by your commercial contact.', $this->domain),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'countries' => array(
                    'title' => __('Countries', $this->domain),
                    'type' => 'multiselect',
                    'default' => '',
                    'css' => 'height: 10rem;',
                    'description' => __('Selection of countries where you want to use Onebip.', $this->domain),
                    'default' => 'wc-completed',
                    'desc_tip' => true,
                    'options' => $this->getCountries()
                ),
                'description_text' => array(
                    'title' => __('Description', $this->domain),
                    'type' => 'textarea',
                    'description' => __('This will be displayed on the Onebip payment page and on the mobile phone invoice.', $this->domain),
                    'default' => __('Your purchase on ', $this->domain).get_bloginfo(),
                    'desc_tip' => true,
                ),
                'default_pending_message_text' => array(
                    'title' => __('Default Pending Status Message', $this->domain),
                    'type' => 'text',
                    'description' => __('This message will be displayed to the customer if the payment is in a pending status.', $this->domain),
                    'default' => $this->default_pending_status_text,
                    'desc_tip' => true,
                ),
                'default_completed_message_text' => array(
                    'title' => __('Default Completed Status Message', $this->domain),
                    'type' => 'text',
                    'description' => __('This message will be displayed to the customer if status of payment has been completed.', $this->domain),
                    'default' => $this->default_completed_status_text,
                    'desc_tip' => true,
                ),
                'default_failed_message_text' => array(
                    'title' => __('Default Failed Status Message', $this->domain),
                    'type' => 'text',
                    'description' => __('This message will be displayed to the customer the payment failed.', $this->domain),
                    'default' => $this->default_failed_status_text,
                    'desc_tip' => true,
                ),
                'default_cancelled_message_text' => array(
                    'title' => __('Default Cancelled Status Message', $this->domain),
                    'type' => 'text',
                    'description' => __('This message will be displayed to the customer if the payment has been cancelled.', $this->domain),
                    'default' => $this->default_cancelled_status_text,
                    'desc_tip' => true,
                ),
                'vat_detail' => array(
                    'type' => 'vat_details',
                ),
            );

            $this->form_fields = $field_arr;
        }

        /**
         * Generate account details html.
         *
         * @return string
         */
        public function generate_vat_details_html() {
            ob_start();
            include ONEBIP_PLUGIN_PATH . "template.php";
            return ob_get_clean();
        }


        /**
         * Process Gateway Settings Form Fields.
         */
        public function process_admin_options() {
            $this->init_settings();

            $post_data = $this->get_post_data();
            $logo_url = "";
            if($_FILES['logo']['tmp_name']){
                if(exif_imagetype($_FILES['logo']['tmp_name'])){
                    if ( ! function_exists( 'wp_handle_upload' ) ) require_once( ABSPATH . 'wp-admin/includes/file.php' );
                    $uploadedfile = $_FILES['logo'];
                    $upload_overrides = array( 'test_form' => false );
                    $movefile = wp_handle_upload( $uploadedfile, $upload_overrides );
                    if ( $movefile ) {
                        $logo_url = $movefile['url'];
                    }
                }
            }
            if($logo_url == ""){
                $previous_value = $this->get_option('icon');
                if($previous_value){
                    $logo_url = $previous_value;
                }else{
                    $logo_url = ONEBIP_PLUGIN_URL . 'assets/images/logo.png';
                }
            }
            $this->settings['icon'] = $logo_url;
//            if(isset($_FILES['woocommerce_onebip_vat_detail_file']['tmp_name']) && $_FILES['woocommerce_onebip_vat_detail_file']['tmp_name']){
//                $csv = array_map('str_getcsv', file($_FILES['woocommerce_onebip_vat_detail_file']['tmp_name']));
//
//            }
//            $vat_data = array();
//            foreach($csv as $value){
//                $vat_data[$value[0]] = $value;
//            }

            if (empty($post_data['woocommerce_onebip_username'])) {
                WC_Admin_Settings::add_error(__('Please enter Onebip account username', $this->domain));
            } elseif (empty($post_data['woocommerce_onebip_api_key'])) {
                WC_Admin_Settings::add_error(__('Please enter Onebip account API Key', $this->domain));
            } else {
                foreach ( $this->get_form_fields() as $key => $field ) {
                    $setting_value = $this->get_field_value( $key, $field, $post_data );
                    $this->settings[ $key ] = $setting_value;
                }
//                if($vat_data){
//                    $this->settings[ 'vat_detail_file' ] = $vat_data;
//                }else{
//                    $this->settings[ 'vat_detail_file' ] = $this->vat_detail_file;
//                }
                return update_option( $this->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ) );
            }
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page($order_id) {

            $style = "width: 100%;  margin-bottom: 1rem; background: #00ccb8; padding: 20px; color: #fff; font-size: 22px;";
            $order = new WC_Order($order_id);

            $translations_array = parse_ini_file(ONEBIP_PLUGIN_PATH . 'translation.txt', true);

            //Add Default Text to array
            $translations_array['default']['pending_status_message'] = $this->default_pending_status_text;
            $translations_array['default']['completed_status_message'] = $this->default_completed_status_text;
            $translations_array['default']['failed_status_message'] = $this->default_failed_status_text;
            $translations_array['default']['cancelled_status_message'] = $this->default_cancelled_status_text;

            $country_code = strtolower($order->get_billing_country());
            $translation = array();
            $translation_english = $translations_array['default'];
            if(array_key_exists($country_code, $translations_array)){
                $translation = $translations_array[$country_code];
                foreach($translation_english as $key => $value){
                    if(!array_key_exists($key, $translation)){
                        $translation[$key] = $value;
                    }
                }
            }else{
                $translation = $translations_array['default'];
            }

            $status = $order->get_status();
            if ($status == 'pending') {
                ?>
                <div class="payment-panel">
                    <div style="<?php echo $style?>">
                        <?php echo __($translation['pending_status_message'], $this->domain) ?>
                    </div>
                </div>
                <?php
            } else if ($status == 'completed') {
                ?>
                <div class="payment-panel">
                    <div style="<?php echo $style?>">
                        <?php echo __($translation['completed_status_message'], $this->domain) ?>
                    </div>
                </div>
                <?php
            } else if ($status == 'failed') {
                ?>
                <div class="payment-panel">
                    <div style="<?php echo $style?>">
                        <?php echo __($translation['failed_status_message'], $this->domain) ?>
                    </div>
                </div>
                <?php
            } else if ($status == 'cancelled') {
                ?>
                <div class="payment-panel">
                    <div style="<?php echo $style?>">
                        <?php echo __($translation['cancelled_status_message'], $this->domain) ?>
                    </div>
                </div>
                <?php
            }
        }

        public function check_ipn_response() {
            global $woocommerce;
            $this->log('call back');
            $headers = getallheaders();
            $this->log($headers);
            $json = file_get_contents('php://input');
            $body = json_decode($json, true);
            $this->log($body);
            $headerSignatureValue = $_SERVER['HTTP_X_ONEBIP_SIGNATURE'];

            $headerSignature = base64_encode(hash_hmac('sha256', $json, $this->api_key, $rawOutput = true));
            $this->log($headerSignatureValue);
            $this->log($headerSignature);

            $order_id = (int)($body['remote_txid']);
            $order = new WC_Order($order_id);

            if ($headerSignature == $headerSignatureValue) {
                $this->log('Signature matched');
                if ($body['what'] == 'BILLING_COMPLETED') {
                    $order->update_status('completed', __('Payment successful. Transaction Id: '.$body['transaction_id'], $this->domain));

                    $order->add_meta_data('Onebip_Transaction_Id', $body['transaction_id']);
                    $order->add_meta_data('Onebip_What', $body['what']);
                    $order->add_meta_data('Onebip_Currency', $body['currency']);
                    $order->add_meta_data('Onebip_Price', $body['price']);
                    $order->save_meta_data();

                    $woocommerce->cart->empty_cart();
                } else if ($body['what'] == 'BILLING_ABORTED') {
                    $order->update_status('failed', __('Payment Failed. Reason: '.$body['why'].'. Transaction Id: '.$body['transaction_id'], $this->domain));

                    $order->add_meta_data('Onebip_Transaction_Id', $body['transaction_id']);
                    $order->add_meta_data('Onebip_What', $body['what']);
                    $order->add_meta_data('Onebip_Why', $body['why']);
                    $order->add_meta_data('Onebip_Currency', $body['currency']);
                    $order->add_meta_data('Onebip_Price', $body['price']);
                    $order->save_meta_data();

                    $woocommerce->cart->empty_cart();
                } else {
                    $order->update_status('cancelled', __('Order Cancelled. Not valid response received.', $this->domain));
                }
                exit;}
            else {
                $order->update_status('cancelled', __('Order Cancelled. Signature missmatch potential Fraud attempt.', $this->domain));
            }
            exit;
        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment($order_id) {
            global $woocommerce;
            $order = wc_get_order($order_id);

            $order_data = $order->get_data();
            $order_total = $order->get_total();

            $params = array (
                "username" => $this->username,
                "description" => $this->description_text,
                "price" => $order_total * 100,
                "currency" => $order_data['currency'],
                "country" => $order_data['billing']['country'],
                "return_url" => $this->get_return_url( $order ),
                "notify_url" => site_url().'/?wc-api=wc_onebip',
                "remote_txid" => $order->get_id(),
                "customer_email" => $order_data['billing']['email'],
                "customer_account_id" => $order_data['billing']['email'],
                "customer_firstname" => $order_data['billing']['first_name'],
                "customer_lastname" => $order_data['billing']['last_name'],
                "customer_cell" => $order_data['billing']['phone'],
                "customer_country" => $order_data['billing']['country'],
                "product_url" => site_url(),
                "product_id" => "woocommerce_v1"
            );

            $product_name = '';
            foreach ($order->get_items() as  $item) {
                $product_name .= $item->get_name().';';
            }
            $params['product_name'] = $product_name;

            $url = "https://pay.onebip.com/purchases?";
            $querystring = http_build_query($params);
            $secret = $this->api_key;
            $signature = hash_hmac("sha256", $url . $querystring, $secret);
            $urlWithSignature = $url . $querystring . "&signature=". $signature;

            return array(
                'result' => 'success',
                'redirect' => $urlWithSignature
            );
        }

        public function getCountries()
        {
            $countriesObj = new WC_Countries();
            return $countriesObj->__get('countries');
        }

        public function log($content) {
            $debug = false;
            if ($debug == true) {
                $file = ONEBIP_PLUGIN_PATH.'debug.log';
                $fp = fopen($file, 'a+');
                fwrite($fp, "\n");
                fwrite($fp, date("Y-m-d H:i:s").": ");
                fwrite($fp, print_r($content, true));
                fclose($fp);
            }
        }
    }


    class OnebipOrderStatus
    {
        const COMPLETED = "completed";
        const FAILED = "failed";
        const PENDING = "pending";
        const REFUNDED = "refunded";
        const CANCELLED = "cancelled";
    }
}

add_filter('woocommerce_payment_gateways', 'add_onebip_gateway_class');
function add_onebip_gateway_class($methods) {
    $methods[] = 'WC_Onebip';
    return $methods;
}

add_filter( 'woocommerce_available_payment_gateways', 'enable_onebip_gateway' );
function enable_onebip_gateway( $available_gateways ) {
    if ( is_admin() ) return $available_gateways;

    if ( isset( $available_gateways['onebip'] )) {
        $settings = get_option('woocommerce_onebip_settings');

        if(empty($settings['username'])) {
            unset( $available_gateways['onebip'] );
        } elseif(empty($settings['api_key'])) {
            unset( $available_gateways['onebip'] );
        } elseif (!in_array(WC()->customer->get_billing_country(), $settings['countries'])) {
            unset( $available_gateways['onebip'] );
        }
    }
    return $available_gateways;
}

add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'onebip_setting_link');
function onebip_setting_link( $links ) {
    $links[] = '<a href="' .
        admin_url( 'admin.php?page=wc-settings&tab=checkout&section=onebip' ) .
        '">' . __('Settings') . '</a>';
    return $links;
}
