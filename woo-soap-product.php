<?php

/**
 * Plugin Name: WOOSOAPPRODUCT
 * Plugin URI: 
 * Description: A Plugin is Develop for WooCommerce which will communicate to Prima Solution Order Management System 
 * Version: 1.0.4
 * Author: xyz
 * Author URI: 
 * Developer: xyz
 * Developer URI: 
 * Text Domain: WooSoapPrimaProduct
 * Domain Path: /languages
 *
 * WC requires at least: 2.2
 * WC tested up to: 2.3
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */



if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( !class_exists( 'WOOSOAPPRODUCT' ) ) {

    class WOOSOAPPRODUCT{

        // url to soap api
        protected $soapapiUrl = '';

        function __construct(){

           
            register_activation_hook( __FILE__, array($this,'WooSoapPrimaProduct_activate') );
            register_deactivation_hook( __FILE__, array($this, 'WooSoapPrimaProduct_deactivate') );

            add_action('admin_print_scripts', array($this,'woosoapprimaproduct_admin_scripts'));
            add_action('admin_print_styles', array($this,'woosoapprimaproduct_admin_styles'));
            add_action( 'wp_enqueue_scripts', array($this,'prima_custom_script_load' ));
            add_action('admin_menu', array($this,'ad_woosoap_prima_product_actions'));
            add_action( 'admin_init', array( $this, 'page_init' ) );
            add_action( 'init', array($this,'prima_order_management_reset_session_fn' ));
            add_action( 'woocommerce_thankyou', array($this,'prima_order_management_fn' ));
            add_filter( 'woocommerce_order_item_quantity', array($this,'prima_custom_stock_reduction'), 10, 3);

            add_action( 'woocommerce_admin_order_data_after_order_details', array($this,'prima_display_order_flag_in_admin' ));
            add_action( 'woocommerce_process_shop_order_meta', array($this,'prima_save_extra_details'), 45, 2);
            
            add_action('woocommerce_product_options_general_product_data', array($this,'prima_woocommerce_simple_product_custom_field'));

            add_action('woocommerce_process_product_meta', array($this,'save_woocommerce_simple_product_val'));

            //single page
            add_filter( 'woocommerce_single_variation',array($this,'prima_product_stock_script' )); 
            
            //single product page
            add_action( 'woocommerce_before_single_product', array($this,'prima_product_details_fn'), 5);
            
            //order status form
            add_shortcode( 'custmerorderstatus', array($this,'prima_customer_order_status_fn' ));

            //oder status ajax
            add_action("wp_ajax_prima_order_status", array($this,"prima_order_status"));
            add_action("wp_ajax_nopriv_prima_order_status", array($this,"prima_order_status"));

            //add new order status
            add_action( 'init', array($this,'prima_new_order_statuses' ));



            //add_filter( 'woocommerce_order_item_display_meta_key',  array($this,'change_order_details_backorder_title', 20, 3 ));

           
            add_filter( 'wc_order_statuses', array($this,'prima_new_wc_order_statuses' ));

            $settings=get_option('my_option_name');
            $settings_val=$settings['prime_cron'];


            if(isset($settings_val) && $settings_val=='1'){
               
                //fisrt cron
                add_filter( 'cron_schedules', array($this,'prima_stock_manage_everyhalfhour' ));
                add_action( 'prima_stock_manage_everyhalfhour', array($this,'PrimaProductAvailableToSell' ));
                
               
                //second cron
                add_filter( 'cron_schedules', array($this,'prima_resyn_order_manage' ));
                add_action( 'prima_resyn_order_manage', array($this,'prima_unsyc_order_management_request_fn' ));

                

                //third cron
                add_filter( 'cron_schedules', array($this,'prima_syn_order_status' ));
                add_action( 'prima_syn_order_status', array($this,'prima_syc_order_status_fn' ),100);
                

                //fourth cron
                add_filter( 'cron_schedules', array($this,'prima_stock_manage_everyhalfhour_simpleproduct' ));
                add_action( 'prima_stock_manage_everyhalfhour_simpleproduct', array($this,'PrimaSimpleProductAvailableToSell' ));


                //first cron
                if ( ! wp_next_scheduled( 'prima_stock_manage_everyhalfhour' ) ) {
                    wp_schedule_event( time(), 'every_thirty_minutes', 'prima_stock_manage_everyhalfhour' );
                }


                

                //second cron
                if ( ! wp_next_scheduled( 'prima_resyn_order_manage' ) ) {
                    wp_schedule_event( time(), 'resynevery_thirty_minutes', 'prima_resyn_order_manage' );
                }

                //third cron
                if ( ! wp_next_scheduled( 'prima_syn_order_status' ) ) {
                    wp_schedule_event( time(), 'oderstatussynevery_thirty_minutes', 'prima_syn_order_status' );
                }

                //fourth cron
                if ( ! wp_next_scheduled( 'prima_stock_manage_everyhalfhour_simpleproduct' ) ) {
                    wp_schedule_event( time(), 'every_thirty_minutes_simpleproduct', 'prima_stock_manage_everyhalfhour_simpleproduct' );
                }


            }
            if(isset($settings_val) && $settings_val=='0'){


              
               //fisrt cron
                wp_clear_scheduled_hook('prima_stock_manage_everyhalfhour');

                //second cron
                wp_clear_scheduled_hook('prima_resyn_order_manage');

                //third cron
                wp_clear_scheduled_hook('prima_syn_order_status');

                //fouth cron
                wp_clear_scheduled_hook('prima_stock_manage_everyhalfhour_simpleproduct');

                
            }
            

        }

        
        /**
         * Checks if the WooCommerce plugin is activated
         */
       function WooSoapPrimaProduct_activate() {
             
            if ( current_user_can( 'activate_plugins' ) && ! class_exists( 'WooCommerce' ) ) {

                // deactivate the plugin.
                deactivate_plugins( plugin_basename( __FILE__ ) );

                $error_message = esc_html__( 'This plugin requires ', 'WooSoapPrimaProduct' ). 'WooCommerce' . esc_html__( ' plugin to be active.', 'WooSoapPrimaProduct' );
                die( $error_message ); 
            
            }
            
            //first cron
            if ( ! wp_next_scheduled( 'prima_stock_manage_everyhalfhour' ) ) {
                wp_schedule_event( time(), 'every_thirty_minutes', 'prima_stock_manage_everyhalfhour' );
            }

            

            //second cron
            if ( ! wp_next_scheduled( 'prima_resyn_order_manage' ) ) {
                wp_schedule_event( time(), 'resynevery_thirty_minutes', 'prima_resyn_order_manage' );
            }

            //third cron
            if ( ! wp_next_scheduled( 'prima_syn_order_status' ) ) {
                wp_schedule_event( time(), 'oderstatussynevery_thirty_minutes', 'prima_syn_order_status');
            }

            //fourth cron
            if ( ! wp_next_scheduled( 'prima_stock_manage_everyhalfhour_simpleproduct' ) ) {
                wp_schedule_event( time(), 'every_thirty_minutes_simpleproduct', 'prima_stock_manage_everyhalfhour_simpleproduct' );
            }

        } 

       function WooSoapPrimaProduct_deactivate()
        {
            //fisrt cron
            wp_clear_scheduled_hook('prima_stock_manage_everyhalfhour');

            //second cron
            wp_clear_scheduled_hook('prima_resyn_order_manage');

            //third cron
            wp_clear_scheduled_hook('prima_syn_order_status');

            //fouth cron
            wp_clear_scheduled_hook('prima_stock_manage_everyhalfhour_simpleproduct');
           
        }

        
        //fouth cron
        function prima_stock_manage_everyhalfhour_simpleproduct( $schedules ) {
            $schedules['every_thirty_minutes_simpleproduct'] = array(
                            'interval'  => 60*45,
                            'display'   => __( 'Every 45 Minutes', '' )
            );
            return $schedules;
        }


        //third cron
        
       function prima_syn_order_status( $schedules ) {
                $schedules['oderstatussynevery_thirty_minutes'] = array(
                                'interval'  => 60*30,
                                'display'   => __( 'Every 30 Minutes', '' )
                );
                return $schedules;
        }

        
        //second cron
        function prima_resyn_order_manage( $schedules ) {
                $schedules['resynevery_thirty_minutes'] = array(
                                'interval'  => 60*30,
                                'display'   => __( 'Every 30 Minutes', '' )
                );
                return $schedules;
        }

        //first cron
        function prima_stock_manage_everyhalfhour( $schedules ) {
                $schedules['every_thirty_minutes'] = array(
                                'interval'  => 60*90,
                                'display'   => __( 'Every 90 Minutes', '' )
                );
                return $schedules;
        }

        
        
       function prima_custom_script_load(){

            wp_enqueue_style( 'popup_style', plugins_url() . '/woo-soap-product/css/popup_style.css', array(), '1.0.0', 'all' );
            wp_enqueue_script( 'popup_effect', plugins_url() . '/woo-soap-product/js/popup_effect.js', array( 'jquery' ) );
            wp_localize_script( 'popup_effect', 'primaAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ))); 
            
        }


        function prima_woocommerce_simple_product_custom_field(){

            global $woocommerce, $post;
        

            echo '<div class="simple_product_status">';
            woocommerce_wp_text_input(
                array(
                    'id' => '_simple_product_status',
                    'label' => __('Product Status', 'woocommerce'),
                    'desc_tip' => 'true'
                )
            );
            echo '</div>';

        }


        function save_woocommerce_simple_product_val($post_id){

            $product = wc_get_product($post_id);
            $custom_simple_prod_val = isset($_POST['_simple_product_status']) ? $_POST['_simple_product_status'] : '';
            $product->update_meta_data('_simple_product_status', sanitize_text_field($custom_simple_prod_val));
            $product->save();

        }


        function change_order_details_backorder_title($key, $meta, $item){

            if ( 'Back-ordered' === $meta->key ) { $key = __( 'Pre-ordered', ''); }   
            return $key;

        }

        //add extra filed in order
        function prima_display_order_flag_in_admin( $order ){  
            $dataval=get_post_meta( $order->id, 'order_flag', true );
            $oval= isset($dataval)?$dataval:'0';
            woocommerce_wp_hidden_input( 
                array(
                    'id' => 'order_flag',
                    'value' => $oval
                )
            );
           /*  woocommerce_wp_text_input( 
                array(
                    'id' => 'order_flag',
                    'class' => 'short',
                    'label' => __( 'Order Flag', 'woocommerce' ),
                    'value' => get_post_meta( $order->id, 'order_flag', true )
                )
            ); */
        }
        
       function prima_save_extra_details( $post_id, $post ){
           if(isset($_POST[ 'order_flag' ])){
            update_post_meta( $post_id, 'order_flag', wc_clean( $_POST[ 'order_flag' ] ) );
           }else{
            update_post_meta( $post_id, 'order_flag', '0' );
           }
        }
        


      function prima_syc_order_status_fn(){
            
                $msg = '';
                $code = '';
            
                $orderdatas = wc_get_orders( array(
                    'limit'        => -1, 
                    'orderby'      => 'date',
                    'order'        => 'DESC'
                ));
            
                if(!empty($orderdatas)){
            
                    foreach($orderdatas as $orderdata){
            
                        
                        $order_id = !empty($orderdata->get_id())?$orderdata->get_id():'';
                        

                        $or_flag_val=(!empty(get_post_meta($orderdata->get_id(), 'order_flag', true)))?get_post_meta($orderdata->get_id(), 'order_flag', true):'0';
                        
                        if(!empty($order_id) && $orderdata->get_status()!='completed'){
            
                            $neworderdata = new WC_Order($order_id);

                            $orprex='201';
                            $newor_id=$orprex.$order_id;

                            $order_status_request_data  ='';
                            $order_status_request_data .='<?xml version="1.0"?>';
                            $order_status_request_data .='<Request RequestType="OrderStatus">';
                            $order_status_request_data .='<Data OrderNumber="'.$newor_id.'"></Data>';
                            $order_status_request_data .='</Request>';
                            $client = new SoapClient('http://trade.loake.co.uk:8080/wsalive/wsalive/wsdl?targetURI=urn:omlink');
                            $getresults = $client->wsomhandler($order_status_request_data);
                            $xmldatas = new SimpleXMLElement($getresults);
            
                            if(!empty($xmldatas) && !empty($xmldatas['RequestStatus']) && (string)$xmldatas['RequestStatus']=='OK'){
            
                                $or_status=(string)$xmldatas->Data['OrderStatus'];
            
                                if(!empty($or_status)){
                    
                                    $order_status=strtolower($or_status);
            
                                    if(!empty($order_status) && $order_status!='cancelled'){
            
                                        update_post_meta($order_id, 'order_flag', 1);
                                    }

                                    $neworderdata->update_status($order_status);
            
                                    $msg = 'order status display successfully';
                                    $code = 200;
                    
                                }
                                    
                            }
            
                            if(!empty($xmldatas['RequestError']) && $xmldatas['RequestStatus']=='ERROR'){
            
                                if(isset($or_flag_val) && $or_flag_val!='1'){
                                    update_post_meta($order_id, 'order_flag', 0);    
                                }
                               
                                $msg = 'order is not found';
                                $code =203;
            
                            }
            
            
                        }
            
            
                    }
            
                }else{
                   
                    $msg = 'order is not found';
                    $code =203;
            
                }
            
        
        }


         function prima_new_order_statuses() {
            register_post_status( 'wc-part-despatched', array(
                'label'                     => _x( 'Part Despatched', 'woocommerce'),
                'public'                    => true,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                        'show_in_admin_status_list' => true,
                        'label_count'               => _n_noop( 'Part Despatched <span class="count">(%s)</span>', 'Part Despatched <span class="count">(%s)</span>', 'woocommerce' )
                ) );
                register_post_status( 'wc-despatched', array(
                    'label'                     => _x( 'Despatched', 'woocommerce'),
                    'public'                    => true,
                    'exclude_from_search'       => false,
                    'show_in_admin_all_list'    => true,
                    'show_in_admin_status_list' => true,
                    'label_count'               => _n_noop( 'Despatched <span class="count">(%s)</span>', 'Despatched<span class="count">(%s)</span>', 'woocommerce' )
            ) );
        }
       
       function prima_new_wc_order_statuses( $order_statuses ) {
		
            $order_statuses['wc-part-despatched'] = _x( 'Part Despatched' , 'woocommerce');
            $order_statuses['wc-despatched'] = _x(  'Despatched' , 'woocommerce');
            return $order_statuses;
        }
        
        
        
       function prima_unsyc_order_management_request_fn(){
            

                $orders = wc_get_orders( array(
                    'limit'        => -1, 
                    'orderby'      => 'date',
                    'order'        => 'DESC',
                    'meta_key'     => 'order_flag',
                    'meta_value'   => 0, 
                    'meta_compare' => '=', 
                ));
                
                $prefix='201';

                if(!empty($orders)){
            
                    foreach($orders as $orderinfo){
            
                        $tranid = !empty($orderinfo->get_transaction_id())?$orderinfo->get_transaction_id():'';
                        
                        $or_flag_val=get_post_meta($orderinfo->get_id(), 'order_flag', true);

                        $order_status_request_data  ='';
                        $order_status_request_data .='<?xml version="1.0"?>';
                        $order_status_request_data .='<Request RequestType="OrderStatus">';
                        $order_status_request_data .='<Data OrderNumber="'.$prefix.$orderinfo->get_id().'"></Data>';
                        $order_status_request_data .='</Request>';
                        $client = new SoapClient('http://trade.loake.co.uk:8080/wsalive/wsalive/wsdl?targetURI=urn:omlink');
                        $getresults = $client->wsomhandler($order_status_request_data);
                        $xmldatas = new SimpleXMLElement($getresults);
                        if(!empty($xmldatas)){
                            $or_status=(string)$xmldatas->Data['OrderStatus'];
                            $status_or = strtolower($or_status);
                            $orderinfo->update_status( $status_or );
                        }else{
                            $or_status='';
                        }
                        

                        if( !empty($xmldatas) && !empty($tranid) && $or_flag_val!='1' && (string)$xmldatas['RequestStatus']!='OK'   && $or_status!='Cancelled'){
            
                            
                            $orderdata = wc_get_order($orderinfo->get_id());
            
                            $order_id = $orderdata->get_id();
                            $order_request_data='';
                            $prodpayment_method='';
                            $chargedata='';
                            $deliverycharge='';
            
                            if($orderdata->payment_method=='ppec_paypal'){
                                $prodpayment_method='PAYPAL';
                            }
            
                            if($orderdata->payment_method=='amazon_payments_advanced')
                            {
                                $prodpayment_method='AMAZON';
                            }
            
                            $od_prefix='201';
                            $neworder_id =$od_prefix.$orderdata->get_id();

                            $order_request_data .='<?xml version="1.0"?>
                            <Request RequestType="MultiNewOrder">
                            <Data UniqueId="'.$this->generateRandomString().'" CustDef="" Customer="" TPGUID="'.$neworder_id.'" OrderNumber="'.$neworder_id.'" HoldOrder="No" HoldOrderNarrative="" OrderChannel="MailOrder" CustomerTitle="" CustomerForename="'.$orderdata->get_shipping_first_name().'" CustomerSurname="'.$orderdata->get_shipping_last_name().'" AddrLine1="'.$orderdata->get_shipping_address_1().' '.$orderdata->get_shipping_address_2().'" AddrLine2="'.$orderdata->get_shipping_city().'" AddrLine3="'.$orderdata->get_shipping_state().'" AddrLine4="" AddrLine5="" AddrLine6="" PostCode="'.$orderdata->get_shipping_postcode().'" Country="'.$orderdata->get_shipping_country().'" CustomerEmail="'.$orderdata->get_billing_email().'" CustomerPhone="'.$orderdata->get_billing_phone().'" CustomerMobile="" CustomerFax="" CustomerRef="" VatInclusive="Yes" Gender="" ReceiveMail="No" ReceiveEmail="No" ReceiveText="No" ReceivePhone="No" ReceiveMobile="No" RentDetails="No" DateOfBirth="" DelivAddrNo="" DeliveryFAO="" EntitlementCheck="" InvcAddrNo="" OrderType="" PaymentTerms="" Salesman="" SeasonDDValidation="" Wardrobe="" WorksNo="" WREmpCode="" WREmpDept="" WREmpRef1="" WREmpRef2="" WREmpRole="" WROrdRef1="" WROrdRef2="" WROrdRef3="" WROrdRef4="">
            
                            <OrderHeaderData B2B="" Brand=""  SpecialInstructions="'.$orderdata->get_customer_note().'" ShippingInstructions="" SourceMedia="" OrderCategory="'.$prodpayment_method.'" OrderStatus="" Carrier="" CarrServ="" Currency="" OrderContact="" OrderDate="" OrderEmail="" OrderPaid="" PaymentOnOrder="" ProdConv="" Proforma="" Season="" SendToApproval=""/>
            
                                <OrderInvoiceData InvoiceTitle="" InvoiceForename="'.$orderdata->get_billing_first_name().'" InvoiceSurname="'.$orderdata->get_billing_last_name().'" InvoiceAddrLine1="'.$orderdata->get_billing_address_1().'  '.$orderdata->get_billing_address_2().'" InvoiceAddrLine2="'.$orderdata->get_billing_city().'" InvoiceAddrLine3="'.$orderdata->get_billing_state().'" InvoiceAddrLine4="" InvoiceAddrLine5="" InvoiceAddrLine6="" InvoicePostCode="'.$orderdata->get_billing_postcode().'" InvoiceCountry="'.$orderdata->get_billing_country().'" InvoiceEmail="'.$orderdata->get_billing_email().'" InvoicePhone="'.$orderdata->get_billing_phone().'" InvoiceMobile="" InvoiceFax="" InvoiceFAO="" />';
            
                                $num=1;
                                foreach( $orderdata->get_items() as $item ) {

                                    $parentproduct_sku = get_post_meta($item['product_id'], '_sku', true );
                                    $sm_product = wc_get_product($item['product_id'] );

                                    if($sm_product->get_type()=='simple'){


                                        $simpleproduct_matrixcode2=get_post_meta($item['product_id'], 'smipleprimamcode2', true);
                                        if(!empty($simpleproduct_matrixcode2)){
                
                                            $proMtrxCode2 = $simpleproduct_matrixcode2;
                
                                        }else{
                                            $proMtrxCode2 = '';
                                        }
                
                                        
                                        $simpleproduct_matrixcode1 = array_shift( wc_get_product_terms( $item['product_id'], 'pa_primamcode1', array( 'fields' => 'names' ) ) );
                
                
                                        if(!empty($simpleproduct_matrixcode1)){
                
                                            $variableproduct_matrixcode1 = $simpleproduct_matrixcode1;
                
                                        }else{
                                            $variableproduct_matrixcode1 = '';
                                        }
                
                                    }

                                    if($sm_product->get_type()=='variable'){
                                        
                                        
                                        $variableproduct_sku = get_post_meta($item['variation_id'], '_sku', true );
                                        $variableproduct_matrixcode1 = get_post_meta($item['variation_id'], 'primamcode1', true );
                                    
                                        if(!empty($variableproduct_matrixcode1)){
                
                                            $variableproduct_matrixcode1 = get_post_meta($item['variation_id'], 'primamcode1', true );
                
                                        }else{
                                            $variableproduct_matrixcode1 = '';
                                        }
                
                                        if(!empty($parentproduct_sku) && !empty($variableproduct_sku)){
                
                                            $proMtrxCode2=str_replace($parentproduct_sku,'',$variableproduct_sku);
                
                                        }else{
                                            $proMtrxCode2='';
                                        }
                                    } 


                                    
                                    $order_request_data .='<OrderLineData DueDate="" EnhancementToLine="" ExtTaxVal="" FIPSCode1="" FIPSCode2="" FIPSCode3="" FIPSCode4="" FIPSCode5="" FIPSCode6="" FIPSValue1="" FIPSValue2="" FIPSValue3="" FIPSValue4="" FIPSValue5="" FIPSValue6="" Inventory="" LineSalesman="" Reserve="" TrDisc="" VchGreetMessage="" VchPostCode="" Warehouse="" WorksNo="" OrderLine="'.$num.'" Product="'.$parentproduct_sku.'" MtrxCode1="'.$variableproduct_matrixcode1.'" MtrxCode2="'.$proMtrxCode2.'" MtrxCode3="" MtrxCode4="" Quantity="'.$item['quantity'].'" Price="'.$item['total'].'" Promotion="" PromotionValue="" WebPrmCode="" LineNarrative="" LineReference="" Voucher="No" VchMethod="" VchGreetcard="" VchMessage="" VchName="" VchAddrLine1="" VchAddrLine2="" VchAddrLine3="" VchAddrLine4="" VchAddrLine5="" VchAddrLine6="" VchCntry="" VchEmail=""/>';
                                    $num++;
                                }	
                                
                                $shipping_methods = $orderdata->get_shipping_methods();
                        
                                foreach($shipping_methods as $shipping_method){
            
                                    $instance_id=$shipping_method['instance_id'];
                                    
                                    if($instance_id==1){
                                        $chargedata='RM24';
                                        $deliverycharge=$shipping_method['total'];
                                    }
                                    if($instance_id==2){
                                        $chargedata='RM48';
                                        $deliverycharge=$shipping_method['total'];
                                    }
                                    if($instance_id==3){
                                        $chargedata='SD1';
                                        $deliverycharge=$shipping_method['total'];
                                    }
                                    
                                }
                                $order_request_data .='<OrderChargeData Charge="'.$chargedata.'" ChargeValue="'.$deliverycharge.'"/>';
            
                                $order_request_data .='<OrderPaymentData PayMethod="CSH" SubPayMethod="'.$prodpayment_method.'" AuthRef="" AuthDate="" AuthTime="" TranAmount="'.$orderdata->total.'" BankSort="" BankAccount="" ChequeNum="" PayProvider="NONE" PayProviderStatus="" PayAuthRef="'.$neworder_id.'" PayAuthId="'.$neworder_id.'" LineRef="'.$neworder_id.'" TokenID="" CAVV="" ECI="" ATSData="" TransactionID="'.$orderdata->transaction_id.'" AuthenticationStatus="" VchNumber="" FailureCount="" ExchCreditNo="" />
                            </Data>
                        </Request>';
                        
                            $client = new SoapClient('http://trade.loake.co.uk:8080/wsalive/wsalive/wsdl?targetURI=urn:omlink');
                            $result = $client->wsomhandler($order_request_data);
                            $xmldatas = new SimpleXMLElement($result);
            
                            /* $filepath=plugin_dir_path( __FILE__ ).'cronjobfile/resynorderinformationdata.txt';
                            $fp = fopen($filepath, "a") or die("Unable to open file!");
                            fwrite($fp, "\n ---------------------------------------------------\n");
                            fwrite($fp, date("Y-m-d H:i:s"));
                            fwrite($fp, "\n ---------------------------------------------------\n");
                            fwrite($fp, "\n Request Data :\n");
                            fwrite($fp, $order_request_data);
                            fwrite($fp, "\n Response Data :\n");
                            fwrite($fp, $result);
                            fclose($fp);


                            $filepath=plugin_dir_path( __FILE__ ).'cronjobfile/resnyordinfo.txt';
                            $fp = fopen($filepath, "a") or die("Unable to open file!");
                            fwrite($fp, "\n ---------------------------------------------------\n");
                            fwrite($fp, date("Y-m-d H:i:s"));
                            fwrite($fp, "\n ---------------------------------------------------\n");
                            fwrite($fp, "\n Request Data :\n");
                            fwrite($fp, $order_request_data);
                            fwrite($fp, "\n Response Data :\n");
                            fwrite($fp, $result);
                            fclose($fp); */
            

                            /* weekly log information for traction  */

                                $data = '';
                                $year = date("Y");
                                $month = date("m");
                                $day = date("D");

                                $directory = plugin_dir_path( __FILE__ )."primaomcornjob/resynchronize_order/$day/";

                                $data .= "\n ********************************** \n";
                                $data .=  date('Y-m-d H:i:s');
                                $data .= "\n ********************************** \n";
                                $data .= "\n Request Data :\n";
                                $data .= $order_request_data;
                                $data .= "\n Response Data :\n";
                                $data .= $result;
                                $data .= "\n ********* End ********* \n";

                                $f_name =  'resynorderinformationdata-'.date('Y-m-d').'.txt';

                                $filename = $directory.$f_name;

                                $beforeweekdate = date('Y-m-d');
                                $beforeweekdate = strtotime($beforeweekdate);
                                $beforeweekdate = strtotime("-7 day", $beforeweekdate);
                                $beforeweekfilename = 'resynorderinformationdata-'.date('Y-m-d', $beforeweekdate).'.txt';
                                $beforeweekpath=$directory.$beforeweekfilename;

                                if(!is_dir($directory)){
                                    
                                    mkdir($directory, 0775, true);

                                    if (!file_exists($filename)) {
                                        $fh = fopen($filename, 'w') or die("Can't create file");
                                    }
                                    
                                    $ret = file_put_contents($filename, $data, FILE_APPEND | LOCK_EX);

                                }else{

                                    if(file_exists($beforeweekpath)){

                                        unlink($beforeweekpath);
                                    }

                                    if (!file_exists($filename)) {
                                        $fh = fopen($filename, 'w') or die("Can't create file");
                                    }

                                    $ret = file_put_contents($filename, $data, FILE_APPEND | LOCK_EX);

                                }

                                
                            /* end week log information */   
                            

                            if(!empty($xmldatas)){
            
                                if(!empty($xmldatas['RequestError']) || $xmldatas['RequestStatus']=='ERROR'){
            
                                    echo $msg="Request Order is fail";
                                    
            
                                }else{
            
                                    foreach( $orderdata->get_items() as $item ) {
                                    
                                        $sm_product = wc_get_product($item['product_id'] );
                                       

                                        if($sm_product->get_type()=='variable'){

                                            
                                            if( isset($item['product_id']) && !empty($item['product_id']) ){
                
                                                $stock_quantity = $this->wc_get_variable_product_stock_quantity('raw',$item['product_id']);
                                
                                                if(!empty($stock_quantity) && $stock_quantity > 0){
                                
                                                    update_post_meta($item['product_id'], '_stock', $stock_quantity);
                                                    update_post_meta( $item['product_id'], '_stock_status', true);
                                                    update_post_meta( $item['product_id'], '_backorders', 'notify' );
                                                }
                                                else{
                                                    update_post_meta($item['product_id'], '_stock', 0);
                                                    update_post_meta($item['product_id'], '_stock_status', 'onbackorder');
                                                    update_post_meta( $item['product_id'], '_backorders', 'notify' );
                                                }
                                                
                                            }

                                        }
                                        if($sm_product->get_type()=='simple'){

                                            $stock_quantity = get_post_meta($item['product_id'], '_stock', true);
                            
                                            if(!empty($stock_quantity) && $stock_quantity > 0){
                            
                                                update_post_meta($item['product_id'], '_stock', $stock_quantity);
                                                update_post_meta( $item['product_id'], '_stock_status', true);
                                                update_post_meta( $item['product_id'], '_backorders', 'notify' );
                                            }
                                            else{
                                                update_post_meta($item['product_id'], '_stock', 0);
                                                update_post_meta($item['product_id'], '_stock_status', 'onbackorder');
                                                update_post_meta( $item['product_id'], '_backorders', 'notify' );
                                            }
                                            
                                        }
            
                                    }	
                                    update_post_meta($order_id, 'order_flag', 1);
                                    
                                }
                            }
            
            
                        }
                        
                        
                    }
            
                }
            
        
        }

        //single product page stock level update
        function prima_product_details_fn(){

                global $product;

                $order_request_data='';
                $variableprodmatrixcode1='';
                $variableprodmatrixcode2='';
                $prodsku = $product->sku;

                $all_attr=get_post_meta( $product->id,'_product_attributes',true);
                $thedata = array(
                    'pa_primamcode1'=> array(
                        'name'=>'pa_primamcode1',
                        'value'=>'',
                        'position'=>'6',
                        'is_visible' => '0',
                        'is_variation' => '0',
                        'is_taxonomy' => '1'
                        )
                    );
                
                $append_attr_data=array_merge($all_attr,$thedata);
                update_post_meta( $product->id,'_product_attributes',$append_attr_data);

                $sm_product = wc_get_product( $product->id );
                
                $product_variable = new WC_Product_Variable($product->id);

                if(!empty($product_variable->get_children())){

                    foreach ($product_variable->get_children() as  $var_prodid) {
                        update_post_meta( $var_prodid, '_stock_status', 1);
                        update_post_meta( $var_prodid, '_backorders', 'notify' );
                    }


                }


                 $attr_val_data = array_shift( wc_get_product_terms( $product_variable->get_id(), 'pa_primamcode1', array( 'fields' => 'names' ) ) );

                $product_variations = $product_variable->get_available_variations();
                
                
                
                
                $smprodstatus=get_post_meta($sm_product->get_id(),'_simple_product_status',true);

                $order_request_data .='<?xml version="1.0"?>';
                $order_request_data .='<Request RequestType="MultiAvailableToSell">';
                $order_request_data .='<Data>';
                
                if($sm_product->get_type()=='simple'){

                    $simpleproductattr_val_data = array_shift( wc_get_product_terms( $sm_product->get_id(), 'pa_primamcode1', array( 'fields' => 'names' ) ) );

                    if(!empty($sm_product->get_id())){

                      $variableprodmatrixcode1 = $simpleproductattr_val_data;

                    }

                    $simplematrixcode2val=get_post_meta($sm_product->get_id(),'smipleprimamcode2',true);

                    if(!empty($simplematrixcode2val)){

                      $variableprodmatrixcode2=$simplematrixcode2val;

                    }

                    if(!empty($prodsku) && !empty($variableprodmatrixcode1) && !empty($variableprodmatrixcode2)){

                        $order_request_data .='<sku Product="'.$prodsku.'" MtrxCode1="'.$variableprodmatrixcode1.'" MtrxCode2="'.$variableprodmatrixcode2.'" MtrxCode3="" MtrxCode4=""/>';

                    }
                    

                }
                if($sm_product->get_type()=='variable'){

                    foreach($product_variations as $provariation){


                        if(!empty($provariation['variation_id'])){

                        //$variableprodmatrixcode1 = get_post_meta($provariation['variation_id'],'primamcode1',true);
                        //$variableprodmatrixcode1 = get_post_meta($provariation['variation_id'],'attribute_pa_primamcode1',true);
                        $variableprodmatrixcode1 = $attr_val_data;
                        
                        
                        }

                        if(!empty($provariation['sku'])){

                            $variableprodmatrixcode2=str_replace($prodsku,'',$provariation['sku']);
                        
                        }

                        if(!empty($prodsku) && !empty($variableprodmatrixcode1) && !empty($variableprodmatrixcode2)){

                            $order_request_data .='<sku Product="'.$prodsku.'" MtrxCode1="'.$variableprodmatrixcode1.'" MtrxCode2="'.$variableprodmatrixcode2.'" MtrxCode3="" MtrxCode4=""/>';

                        }

                    }
                }    

                $order_request_data .='</Data>';
                $order_request_data .='</Request>';

                //echo $order_request_data;
                
                $client = new SoapClient('http://trade.loake.co.uk:8080/wsalive/wsalive/wsdl?targetURI=urn:omlink');
                $result = $client->wsomhandler($order_request_data);
                $xmldatas = new SimpleXMLElement($result);

                    if(!empty($xmldatas)){
                    
                        foreach($xmldatas->Data->SKU as $xmldata){

                            $proQty=(int)$xmldata['AvailableQuantity'];
                            $availableDate = isset($xmldata['AvailableDate'])?$xmldata['AvailableDate']:'';
                            $MtrxCode1=(string)$xmldata['MtrxCode1'];        
                            $proSku=$xmldata['Product'].$xmldata['MtrxCode2'];
                            $proSkuQty[] = array($proSku=>$proQty);
                            $proMtrxCode1[] = array($proSku=>$MtrxCode1);	
                            $proAvailableDates[] = array($proSku=>(string)$availableDate);	
                                
                        }
                        

                        if($sm_product->get_type()=='simple'){
                            $simplematrixcode2val=get_post_meta($sm_product->get_id(),'smipleprimamcode2',true);

                            $newprovsku = $sm_product->get_sku().$simplematrixcode2val;
                            foreach($proMtrxCode1 as $MtrxCode1Data) {
                                    
                                if(array_key_exists($newprovsku,$MtrxCode1Data)){
                                
                                    update_post_meta( $sm_product->get_id(), '_backorders', 'no' );
                                    update_post_meta($sm_product->get_id(), 'primamcode1', $MtrxCode1Data[$newprovsku]);
                                    update_post_meta($sm_product->get_id(), 'attribute_pa_primamcode1', strtolower($MtrxCode1Data[$newprovsku]));
                                    wp_set_object_terms( $sm_product->get_id(), $MtrxCode1Data[$newprovsku], 'pa_primamcode1',true);

                                }

                            }

                            foreach($proAvailableDates as $proAvailableDate){

                                if(array_key_exists($provsku,$proAvailableDate)){

                                    $pro_available_dates=isset($proAvailableDate[$newprovsku])?$proAvailableDate[$newprovsku]:'';
                                    update_post_meta($sm_product->get_id(), 'prima_product_available_date', $pro_available_dates);
                                    
                                }
                
                            }

                            foreach ($proSkuQty as $SkuQtyData) {
                                    
                                if(array_key_exists($newprovsku,$SkuQtyData)){

                                    $vproqty=$SkuQtyData[$newprovsku];
                                    
                                    if(!empty($vproqty) && $vproqty > 0){
            
                                        if($smprodstatus!='1'){

                                            /* $sm_product->set_stock_quantity($vproqty);
                                            $sm_product->set_manage_stock(true);
                                            $sm_product->set_stock_status(true);
                                            $sm_product->save(); */

                                            update_post_meta($sm_product->get_id(), '_stock', $vproqty);
                                            if($vproqty > 0){

                                                update_post_meta( $sm_product->get_id(), '_stock_status', 1);

                                            }else{
                                                update_post_meta($sm_product->get_id(), '_stock_status', 'onbackorder');
                                            }
                                            
                                            update_post_meta( $sm_product->get_id(), '_backorders', 'notify' );

                                          

                                        }
                                    }
                                    else
                                    {
                                        
                                    update_post_meta($sm_product->get_id(), '_stock', 0);
                                    update_post_meta($sm_product->get_id(), '_stock_status', 'onbackorder');
                                    update_post_meta($sm_product->get_id(), '_backorders', 'notify' );
                                        
                                    }

                                     

                                }
                                    
                                
                            }


                        }

                        if($sm_product->get_type()=='variable'){

                            foreach($product_variations as $variation){

                                
                                if(!empty($variation['sku'])){

                                    $prodvid=$variation['variation_id'];
                                    $provsku=$variation['sku'];
                                    
                                    
                                    foreach($proMtrxCode1 as $MtrxCode1Data) {
                                        
                                        if(array_key_exists($provsku,$MtrxCode1Data)){
                                        
                                            update_post_meta($variation['variation_id'], 'primamcode1', $MtrxCode1Data[$provsku]);
                                            update_post_meta($prodvid, 'attribute_pa_primamcode1', strtolower($MtrxCode1Data[$provsku]));
                                            wp_set_object_terms( $product->id, $MtrxCode1Data[$provsku], 'pa_primamcode1',true);

                                            update_post_meta( $variation['variation_id'], '_stock_status', 1);
                                            update_post_meta($variation['variation_id'], '_backorders', 'notify' );
                                            
                                        }

                                    }	

                                    foreach($proAvailableDates as $proAvailableDate){

                                        if(array_key_exists($provsku,$proAvailableDate)){

                                            $pro_available_dates=isset($proAvailableDate[$provsku])?$proAvailableDate[$provsku]:'';
                                            update_post_meta($variation['variation_id'], 'prima_product_available_date', $pro_available_dates);
                                            
                                        }
                        
                                    }
                                    
                                     foreach ($proSkuQty as $SkuQtyData) {
                                        
                                        if(array_key_exists($provsku,$SkuQtyData)){

                                            $vproqty=$SkuQtyData[$provsku];

                                            
                                            /* $variation_obj = new  WC_Product_Variation($variation['variation_id']);
                                            $variation_obj->set_stock_quantity($vproqty);
                                            $variation_obj->set_manage_stock(true);
                                            $variation_obj->set_stock_status(true);
                                            $variation_obj->save(); */ 
                                           
                                            update_post_meta($variation['variation_id'], '_stock', $vproqty );
                                            update_post_meta($variation['variation_id'], ' _manage_stock', 'yes' );
                                            if($vproqty > 0){
                                                update_post_meta( $variation['variation_id'], '_stock_status', 1);
                                            }else{
                                                update_post_meta($variation['variation_id'], '_stock_status', 'onbackorder');
                                            }
                                            update_post_meta($variation['variation_id'], '_backorders', 'notify' );

                                        }        
                                        
                                    } 
                                    
                                    

                                }
    
                                
                            }
                        
                        }  
                    
                    
                        if(!empty($product->id)){

                                $stock_quantity = $this->wc_get_variable_product_stock_quantity('raw',$product->id);
            
                                if(!empty($stock_quantity) && $stock_quantity > 0){
            
                                    update_post_meta($product->id, '_stock', $stock_quantity);
                                    update_post_meta( $product->id, '_stock_status', 1);
                                    update_post_meta( $product->id, '_backorders', 'notify' );
                                    
                                }
                                else
                                {
                                    update_post_meta($product->id, '_stock', 0);
                                    update_post_meta($product->id, '_stock_status', 'onbackorder');
                                    update_post_meta( $product->id, '_backorders', 'notify' );
                                    
                                }
                                
                        }


                }
                
                
                    
                /* $filepath=plugin_dir_path( __FILE__ ).'cronjobfile/singleproductstockupdate.txt';
                $fp = fopen($filepath, "a") or die("Unable to open file!");
                fwrite($fp, "\n ---------------------------------------------------\n");
                fwrite($fp, date("Y-m-d H:i:s"));
                fwrite($fp, "\n ---------------------------------------------------\n");
                fwrite($fp, "\n Request Data :\n");
                fwrite($fp, $order_request_data);
                fwrite($fp, "\n Response Data :\n");
                fwrite($fp, $result);
                fclose($fp); */ 

                /* weekly log information for traction  */

                $data = '';
                $year = date("Y");
                $month = date("m");
                $day = date("D");

                $directory = plugin_dir_path( __FILE__ )."primaomcornjob/regular_order/$day/";

                $data .= "\n ********************************** \n";
                $data .=  date('Y-m-d H:i:s');
                $data .= "\n ********************************** \n";
                $data .= "\n Request Data :\n";
                $data .= $order_request_data;
                $data .= "\n Response Data :\n";
                $data .= $result;
                $data .= "\n ********* End ********* \n";

                $f_name =  'singleproductstockupdate-'.date('Y-m-d').'.txt';

                $filename = $directory.$f_name;

                $beforeweekdate = date('Y-m-d');
                $beforeweekdate = strtotime($beforeweekdate);
                $beforeweekdate = strtotime("-7 day", $beforeweekdate);
                $beforeweekfilename = 'singleproductstockupdate-'.date('Y-m-d', $beforeweekdate).'.txt';
                $beforeweekpath=$directory.$beforeweekfilename;

                if(!is_dir($directory)){
                    
                    mkdir($directory, 0775, true);

                    if (!file_exists($filename)) {
                        $fh = fopen($filename, 'w') or die("Can't create file");
                    }
                    
                    $ret = file_put_contents($filename, $data, FILE_APPEND | LOCK_EX);

                }else{

                    if(file_exists($beforeweekpath)){

                        unlink($beforeweekpath);
                    }

                    if (!file_exists($filename)) {
                        $fh = fopen($filename, 'w') or die("Can't create file");
                    }

                    $ret = file_put_contents($filename, $data, FILE_APPEND | LOCK_EX);

                }
                /* end week log information */   


            }
            
           function prima_customer_order_status_fn() {

                $formdata = '';
            
                $formdata .= '<form id="frmorder" name="frmorder" method="post"><center>
                <input type="button" id="display_popup" value="Order Status">
                <div id="popup_box">
                 <input type="button" id="cancel_button" value="X">
                 <input type = "text" id = "prima_product_orderid" placeholder = "Customer Order Id" name = "prima_product_orderid">
                 <input type = "hidden" id = "action"  name = "action" value="prima_order_status">
                 <input type="button" id="submit_button" value="Submit"><br/>
                 <div id="costatus">Customer Order Status : <div id="dataval"></div></div>
                </div></center></form>';
            
                 return $formdata;
             
            }
            

            function PrimaSimpleProductAvailableToSell()
            {

                ini_set('max_execution_time', '6000');
                ini_set('memory_limit', '2048M');
                ini_set('default_socket_timeout', '12000');
                
                /* $filepath=plugin_dir_path( __FILE__ ).'cronjobfile/simpleproductcontent.txt';
                $fp = fopen($filepath, "a") or die("Unable to open file!");
                fwrite($fp, "\n ---------------------------------------------------\n");
                fwrite($fp, date("Y-m-d H:i:s"));
                fwrite($fp, "\n ---------------------------------------------------\n"); */
                
                $product_request_data='';
                $cntstock=0;
                $client = new SoapClient('http://trade.loake.co.uk:8080/wsalive/wsalive/wsdl?targetURI=urn:omlink');

                $product_request_data .='<?xml version="1.0"?><Request RequestType="ProductAvailableToSell"><Data ProductFrom="" ProductTo=""></Data></Request>';
                $result = $client->wsomhandler($product_request_data);
                
                /* fwrite($fp, "\n Request Data :\n");
                fwrite($fp, $product_request_data);
                fwrite($fp, "\n Response Data :\n");
                fwrite($fp, $result);
                fclose($fp); */


                /* weekly log information for traction  */

                $data = '';
                $year = date("Y");
                $month = date("m");
                $day = date("D");

                $directory = plugin_dir_path( __FILE__ )."primaomcornjob/simpleproduct/$day/";

                $data .= "\n ********************************** \n";
                $data .=  date('Y-m-d H:i:s');
                $data .= "\n ********************************** \n";
                $data .= "\n Request Data :\n";
                $data .= $product_request_data;
                $data .= "\n Response Data :\n";
                $data .= $result;
                $data .= "\n ********* End ********* \n";

                $f_name =  'simpleproductcontent-'.date('Y-m-d').'.txt';

                $filename = $directory.$f_name;

                $beforeweekdate = date('Y-m-d');
                $beforeweekdate = strtotime($beforeweekdate);
                $beforeweekdate = strtotime("-7 day", $beforeweekdate);
                $beforeweekfilename = 'simpleproductcontent-'.date('Y-m-d', $beforeweekdate).'.txt';
                $beforeweekpath=$directory.$beforeweekfilename;

                if(!is_dir($directory)){
                    
                    mkdir($directory, 0775, true);

                    if (!file_exists($filename)) {
                        $fh = fopen($filename, 'w') or die("Can't create file");
                    }
                    
                    $ret = file_put_contents($filename, $data, FILE_APPEND | LOCK_EX);

                }else{

                    if(file_exists($beforeweekpath)){

                        unlink($beforeweekpath);
                    }

                    if (!file_exists($filename)) {
                        $fh = fopen($filename, 'w') or die("Can't create file");
                    }

                    $ret = file_put_contents($filename, $data, FILE_APPEND | LOCK_EX);

                }

                
            /* end week log information */   


                 //create folder
                
                 $filepath=plugin_dir_path( __FILE__ ).'primaomcornjob/';
                
                 $namef='primomproduct';
 
                 $ext = ".xml";
 
                 $filename = $filepath.'/'.$namef.$ext;
 
                 $file = fopen($filename,"w+");
 
                 fwrite($file, $result);
 
                 fclose($file);
 
                 chmod($file,0777);

                $newfilepath=plugin_dir_path( __FILE__ ).'primaomcornjob/'. $namef.$ext;

                $filedata = file_get_contents($newfilepath);

                $xmldatas = new SimpleXMLElement($filedata);

                $proSkuQty=array();
                $proMtrxCode1=array();

                if(!empty($xmldatas)){


                    foreach($xmldatas->Data->sku as $xmldata){
                
                        $proQty=(int)$xmldata['AvailableQuantity'];
                        $MtrxCode1=(string)$xmldata['MtrxCode1'];        
                        $proSku=$xmldata['Product'].$xmldata['MtrxCode2'];
                        $proSkuQty[] = array($proSku=>$proQty);
                        $proMtrxCode1[] = array($proSku=>$MtrxCode1);
                        $proMtrxCode2[] = array((string)$xmldata['Product']=>(string)$xmldata['MtrxCode2']);	
                
                    }
                
                    $args = array(
                        'post_type' => 'product',
                        'numberposts' => -1,
                    );
                    $products = get_posts( $args );
                
                    foreach($products as $product):
                
                        $product_s = wc_get_product( $product->ID );

                
                        if ($product_s->product_type == 'simple') {
                
                            $prodsid=$product_s->get_id();

                            $smprodstatus=get_post_meta($product_s->get_id(),'_simple_product_status',true);

                            update_post_meta( $product_s->get_id(), '_backorders', 'notify' );

                            $prodattr=$product_s->get_attributes('pa_primamcode1');
                           
                            $prodattr=array_shift( wc_get_product_terms( $prodsid, 'pa_primamcode1', array( 'fields' => 'names' ) ) );            
                           
                            $prosimplesku=$product_s->get_sku();
                            
                            foreach ($proMtrxCode2 as $proMtrxCode2data) {
                
                                if(array_key_exists($prosimplesku,$proMtrxCode2data)){
                                   
                                    update_post_meta($prodsid, 'smipleprimamcode2', $proMtrxCode2data[$prosimplesku]);
                
                                }
                
                            }
                
                
                            foreach ($proSkuQty as $SkuQtyData) {
                
                               $simplematrixcode2val=get_post_meta($prodsid,'smipleprimamcode2',true);
                
                               $newskudata=$product_s->get_sku().$simplematrixcode2val;
                
                                if(array_key_exists($newskudata,$SkuQtyData)){
                
                                    if($smprodstatus!='1'){
                                    
                                        $vproqty=$SkuQtyData[$newskudata];
                                        $product_s->set_stock_quantity($vproqty);
                                        $product_s->set_manage_stock(true);
                                        $product_s->set_stock_status(true);
                                        $product_s->save();

                                    }
                
                                }
                
                            }
                
                        }
                
                        
                    endforeach;    
                    
                
                }

                


            }

           
            

        
            function PrimaProductAvailableToSell()
            {

                ini_set('max_execution_time', '6000');
                ini_set('memory_limit', '2048M');
                ini_set('default_socket_timeout', '12000'); 
                
                /* $filepath=plugin_dir_path( __FILE__ ).'cronjobfile/productcontent.txt';
                $fp = fopen($filepath, "a") or die("Unable to open file!");
                fwrite($fp, "\n ---------------------------------------------------\n");
                fwrite($fp, date("Y-m-d H:i:s"));
                fwrite($fp, "\n ---------------------------------------------------\n"); */
                
                $product_request_data='';
                $cntstock=0;
                $client = new SoapClient('http://trade.loake.co.uk:8080/wsalive/wsalive/wsdl?targetURI=urn:omlink');

                $product_request_data .='<?xml version="1.0"?><Request RequestType="ProductAvailableToSell"><Data ProductFrom="" ProductTo=""></Data></Request>';
                $result = $client->wsomhandler($product_request_data);
                
                /* fwrite($fp, "\n Request Data :\n");
                fwrite($fp, $product_request_data);
                fwrite($fp, "\n Response Data :\n");
                fwrite($fp, $result);
                fclose($fp); */


                /* weekly log information for traction  */

                $data = '';
                $year = date("Y");
                $month = date("m");
                $day = date("D");

                $directory = plugin_dir_path( __FILE__ )."primaomcornjob/product/$day/";

                $data .= "\n ********************************** \n";
                $data .=  date('Y-m-d H:i:s');
                $data .= "\n ********************************** \n";
                $data .= "\n Request Data :\n";
                $data .= $product_request_data;
                $data .= "\n Response Data :\n";
                $data .= $result;
                $data .= "\n ********* End ********* \n";

                $f_name =  'productcontent-'.date('Y-m-d').'.txt';

                $filename = $directory.$f_name;

                $beforeweekdate = date('Y-m-d');
                $beforeweekdate = strtotime($beforeweekdate);
                $beforeweekdate = strtotime("-7 day", $beforeweekdate);
                $beforeweekfilename = 'productcontent-'.date('Y-m-d', $beforeweekdate).'.txt';
                $beforeweekpath=$directory.$beforeweekfilename;

                if(!is_dir($directory)){
                    
                    mkdir($directory, 0775, true);

                    if (!file_exists($filename)) {
                        $fh = fopen($filename, 'w') or die("Can't create file");
                    }
                    
                    $ret = file_put_contents($filename, $data, FILE_APPEND | LOCK_EX);

                }else{

                    if(file_exists($beforeweekpath)){

                        unlink($beforeweekpath);
                    }

                    if (!file_exists($filename)) {
                        $fh = fopen($filename, 'w') or die("Can't create file");
                    }

                    $ret = file_put_contents($filename, $data, FILE_APPEND | LOCK_EX);

                }

                
            /* end week log information */   

                //create folder
                
                $filepath=plugin_dir_path( __FILE__ ).'primaomcornjob/';
                
                $namef='primomproduct';

                $ext = ".xml";

                $filename = $filepath.'/'.$namef.$ext;

                $file = fopen($filename,"w+");

                fwrite($file, $result);

                fclose($file);

                chmod($file,0777);


                $newfilepath=plugin_dir_path( __FILE__ ).'primaomcornjob/'. $namef.$ext;

                $filedata = file_get_contents($newfilepath);

                $xmldatas = new SimpleXMLElement($filedata);

                $proSkuQty=array();
                $proMtrxCode1=array();
                
                if(!empty($xmldatas)){

                    foreach($xmldatas->Data->sku as $xmldata){

                        $proQty=(int)$xmldata['AvailableQuantity'];
                        $MtrxCode1=(string)$xmldata['MtrxCode1'];        
                        $proSku=$xmldata['Product'].$xmldata['MtrxCode2'];
                        $proSkuQty[] = array($proSku=>$proQty);
                        $proMtrxCode1[] = array($proSku=>$MtrxCode1);	

                    }
                    
                    if(!empty($proSkuQty) && !empty($proMtrxCode1))
                    {
                        $args = array(
                            'post_type' => 'product',
                            'numberposts' => -1,
                        );
                        $products = get_posts( $args );

                        foreach($products as $product):
                            
                            $product_s = wc_get_product( $product->ID );
                           
                            $all_attr=get_post_meta( $product->id,'_product_attributes',true);
                                $thedata = array(
                                    'pa_primamcode1'=> array(
                                        'name'=>'pa_primamcode1',
                                        'value'=>'',
                                        'position'=>'6',
                                        'is_visible' => '0',
                                        'is_variation' => '0',
                                        'is_taxonomy' => '1'
                                        )
                                    );
                                
                            $append_attr_data=array_merge($all_attr,$thedata);
                            update_post_meta( $product->id,'_product_attributes',$append_attr_data);

                            if ($product_s->product_type == 'variable') {
                                
                                $variations = $product_s->get_available_variations();
                                
                                foreach($variations as $variation){

                                    if(!empty($variation['sku'])){

                                        $prodvid=$variation['variation_id'];
                                        $provsku=$variation['sku'];
                                        
                                        foreach ($proSkuQty as $SkuQtyData) {
                                            
                                            if(array_key_exists($provsku,$SkuQtyData)){

                                                $vproqty=$SkuQtyData[$provsku];
                                                
                                                $variation_obj = new  WC_Product_Variation($variation['variation_id']);

                                                $variation_obj->set_stock_quantity($vproqty);
                                                $variation_obj->set_manage_stock(true);
                                                $variation_obj->set_stock_status(true);
                                                $variation_obj->save();

                                                $cntstock++;

                                            }
                                                
                                            
                                        }
                                        $product_attributes_data = array();
                                        foreach ($proMtrxCode1 as $MtrxCode1Data) {

                                            if(array_key_exists($provsku,$MtrxCode1Data)){

                                                update_post_meta($prodvid, 'primamcode1', $MtrxCode1Data[$provsku]);

                                                update_post_meta($prodvid, 'attribute_pa_primamcode1', strtolower($MtrxCode1Data[$provsku]));

                                            }

                                        }
                                        

                                    }
                                    
                                    
                                }
                                
                                if(!empty($product->ID)){

                                    $stock_quantity = $this->wc_get_variable_product_stock_quantity('raw',$product->ID);
    
                                    if(!empty($stock_quantity) && $stock_quantity > 0){
    
                                        update_post_meta($product->ID, '_stock', $stock_quantity);
                                        update_post_meta( $product->ID, '_stock_status', true);
                                        update_post_meta( $product->ID, '_backorders', 'notify' );
                                       
                                        
                                    }
                                    else{
                                        update_post_meta($product->ID, '_stock', 0);
                                        update_post_meta($product->ID, '_stock_status', 'onbackorder');
                                        update_post_meta( $product->ID, '_backorders', 'notify' );
                                    }
                                    
                                }
                            
                            }
                            
                        endforeach;
                        
                    }

                    
                            
                }
                

            }


       function woosoapprimaproduct_admin_scripts(){

            if (isset($_GET['page']) && $_GET['page'] == 'woosoapprimaproduct_setting'){

                wp_enqueue_script('jquery');
                //add custom common scripts
                wp_enqueue_script('admin-script', plugins_url() . '/woo-soap-product/js/adminscript.js', array('jquery'));
                
            }

        }

       function woosoapprimaproduct_admin_styles(){
            if (isset($_GET['page']) && $_GET['page'] == 'woosoapprimaproduct_setting'){
               
                //add custom style
                wp_enqueue_style('admin-style', plugins_url() . '/woo-soap-product/css/adminstyle.css');
            }
        }

      function wc_get_variable_product_stock_quantity( $output = 'raw', $product_id = 0 ){

            global $wpdb, $product;

          

            if(!empty($product_id) && $product_id > 0){
        
                $product =  wc_get_product($product_id);
          
                if( $product->product_type == 'variable' ){
        
                    $stock_quantity = $wpdb->get_var("
                        SELECT SUM(pm.meta_value)
                        FROM {$wpdb->prefix}posts as p
                        JOIN {$wpdb->prefix}postmeta as pm ON p.ID = pm.post_id
                        WHERE p.post_type = 'product_variation'
                        AND p.post_status = 'publish'
                        AND p.post_parent = '$product_id'
                        AND pm.meta_key = '_stock'
                        AND pm.meta_value IS NOT NULL
                    ");
                
                }
        
            }
            else
            {
                $stock_quantity=0;
            }
            return $stock_quantity;
            
        }
        
        function prima_order_management_reset_session_fn(){

                session_start();
            
                /* if( isset($_SESSION['vflag']) && !is_wc_endpoint_url( 'order-received' ) ){  
                    $_SESSION['vflag']=0;
                } */
        
        }

       function generateRandomString($length = 8) {
	
            $characters = '0123456789';
            $randomString = '';
            
            for ($i = 0; $i < $length; $i++) {
              $randomString .= $characters[rand(0, strlen($characters) - 1)];
            }
        
            return $randomString;
        }

        //order functionality 
       function prima_order_management_fn($order_id) {

        session_start();
        
        if(!isset($_SESSION['vflag'])){

            $_SESSION['vflag']=0;

        }

        if(isset($_SESSION['vflag']) && $_SESSION['vflag']!=$order_id){

            $_SESSION['vflag']=0;

        }

        if(isset($_SESSION['vflag']) && $_SESSION['vflag']==0){

            $orderdata = wc_get_order($order_id);
            $order_request_data='';
            $prodpayment_method='';
            $chargedata='';
            $deliverycharge='';
            
            if($orderdata->payment_method=='ppec_paypal'){
                $prodpayment_method='PAYPAL';
            }

            if($orderdata->payment_method=='amazon_payments_advanced')
            {
                $prodpayment_method='AMAZON';
            }

            $od_prefix='201';
            $neworder_id =$od_prefix.$orderdata->get_id();

            $order_request_data .='<?xml version="1.0"?>
            <Request RequestType="MultiNewOrder">
            <Data UniqueId="'.$this->generateRandomString().'" CustDef="" Customer="" TPGUID="'.$neworder_id.'" OrderNumber="'.$neworder_id.'" HoldOrder="No" HoldOrderNarrative="" OrderChannel="MailOrder" CustomerTitle="" CustomerForename="'.$orderdata->get_shipping_first_name().'" CustomerSurname="'.$orderdata->get_shipping_last_name().'" AddrLine1="'.$orderdata->get_shipping_address_1().' '.$orderdata->get_shipping_address_2().'" AddrLine2="'.$orderdata->get_shipping_city().'" AddrLine3="'.$orderdata->get_shipping_state().'" AddrLine4="" AddrLine5="" AddrLine6="" PostCode="'.$orderdata->get_shipping_postcode().'" Country="'.$orderdata->get_shipping_country().'" CustomerEmail="'.$orderdata->get_billing_email().'" CustomerPhone="'.$orderdata->get_billing_phone().'" CustomerMobile="" CustomerFax="" CustomerRef="" VatInclusive="Yes" Gender="" ReceiveMail="No" ReceiveEmail="No" ReceiveText="No" ReceivePhone="No" ReceiveMobile="No" RentDetails="No" DateOfBirth="" DelivAddrNo="" DeliveryFAO="" EntitlementCheck="" InvcAddrNo="" OrderType="" PaymentTerms="" Salesman="" SeasonDDValidation="" Wardrobe="" WorksNo="" WREmpCode="" WREmpDept="" WREmpRef1="" WREmpRef2="" WREmpRole="" WROrdRef1="" WROrdRef2="" WROrdRef3="" WROrdRef4="">

            <OrderHeaderData B2B="" Brand=""  SpecialInstructions="'.$orderdata->get_customer_note().'" ShippingInstructions="" SourceMedia="" OrderCategory="'.$prodpayment_method.'" OrderStatus="" Carrier="" CarrServ="" Currency="" OrderContact="" OrderDate="" OrderEmail="" OrderPaid="" PaymentOnOrder="" ProdConv="" Proforma="" Season="" SendToApproval=""/>

                <OrderInvoiceData InvoiceTitle="" InvoiceForename="'.$orderdata->get_billing_first_name().'" InvoiceSurname="'.$orderdata->get_billing_last_name().'" InvoiceAddrLine1="'.$orderdata->get_billing_address_1().'  '.$orderdata->get_billing_address_2().'" InvoiceAddrLine2="'.$orderdata->get_billing_city().'" InvoiceAddrLine3="'.$orderdata->get_billing_state().'" InvoiceAddrLine4="" InvoiceAddrLine5="" InvoiceAddrLine6="" InvoicePostCode="'.$orderdata->get_billing_postcode().'" InvoiceCountry="'.$orderdata->get_billing_country().'" InvoiceEmail="'.$orderdata->get_billing_email().'" InvoicePhone="'.$orderdata->get_billing_phone().'" InvoiceMobile="" InvoiceFax="" InvoiceFAO="" />';

                $num=1;
                foreach( $orderdata->get_items() as $item ) {

                    $parentproduct_sku = get_post_meta($item['product_id'], '_sku', true );


                    $sm_product = wc_get_product($item['product_id'] );
                                       
                    if($sm_product->get_type()=='simple'){

                        $simpleproduct_matrixcode2=get_post_meta($item['product_id'], 'smipleprimamcode2', true);
                        if(!empty($simpleproduct_matrixcode2)){

                            $proMtrxCode2 = $simpleproduct_matrixcode2;

                        }else{
                            $proMtrxCode2 = '';
                        }

                        
                        $simpleproduct_matrixcode1 = array_shift( wc_get_product_terms( $item['product_id'], 'pa_primamcode1', array( 'fields' => 'names' ) ) );


                        if(!empty($simpleproduct_matrixcode1)){

                            $variableproduct_matrixcode1 = $simpleproduct_matrixcode1;

                        }else{
                            $variableproduct_matrixcode1 = '';
                        }

                    }

                    if($sm_product->get_type()=='variable'){
                        
                        $variableproduct_sku = get_post_meta($item['variation_id'], '_sku', true );
                        $variableproduct_matrixcode1 = get_post_meta($item['variation_id'], 'primamcode1', true );
                    
                        if(!empty($variableproduct_matrixcode1)){

                            $variableproduct_matrixcode1 = get_post_meta($item['variation_id'], 'primamcode1', true );

                        }else{
                            $variableproduct_matrixcode1 = '';
                        }

                        if(!empty($parentproduct_sku) && !empty($variableproduct_sku)){

                            $proMtrxCode2=str_replace($parentproduct_sku,'',$variableproduct_sku);

                        }else{
                            $proMtrxCode2='';
                        }
                    }   
                    
                    $order_request_data .='<OrderLineData DueDate="" EnhancementToLine="" ExtTaxVal="" FIPSCode1="" FIPSCode2="" FIPSCode3="" FIPSCode4="" FIPSCode5="" FIPSCode6="" FIPSValue1="" FIPSValue2="" FIPSValue3="" FIPSValue4="" FIPSValue5="" FIPSValue6="" Inventory="" LineSalesman="" Reserve="" TrDisc="" VchGreetMessage="" VchPostCode="" Warehouse="" WorksNo="" OrderLine="'.$num.'" Product="'.$parentproduct_sku.'" MtrxCode1="'.$variableproduct_matrixcode1.'" MtrxCode2="'.$proMtrxCode2.'" MtrxCode3="" MtrxCode4="" Quantity="'.$item['quantity'].'" Price="'.$item['total'].'" Promotion="" PromotionValue="" WebPrmCode="" LineNarrative="" LineReference="" Voucher="No" VchMethod="" VchGreetcard="" VchMessage="" VchName="" VchAddrLine1="" VchAddrLine2="" VchAddrLine3="" VchAddrLine4="" VchAddrLine5="" VchAddrLine6="" VchCntry="" VchEmail=""/>';
                    $num++;
                }	
                
                $shipping_methods = $orderdata->get_shipping_methods();
        
                foreach($shipping_methods as $shipping_method){

                    $instance_id=$shipping_method['instance_id'];
                    
                    if($instance_id==1){
                        $chargedata='RM24';
                        $deliverycharge=$shipping_method['total'];
                    }
                    if($instance_id==2){
                        $chargedata='RM48';
                        $deliverycharge=$shipping_method['total'];
                    }
                    if($instance_id==3){
                        $chargedata='SD1';
                        $deliverycharge=$shipping_method['total'];
                    }
                    
                }
                $order_request_data .='<OrderChargeData Charge="'.$chargedata.'" ChargeValue="'.$deliverycharge.'"/>';

                $order_request_data .='<OrderPaymentData PayMethod="CSH" SubPayMethod="'.$prodpayment_method.'" AuthRef="" AuthDate="" AuthTime="" TranAmount="'.$orderdata->total.'" BankSort="" BankAccount="" ChequeNum="" PayProvider="NONE" PayProviderStatus="" PayAuthRef="'.$neworder_id.'" PayAuthId="'.$neworder_id.'" LineRef="'.$neworder_id.'" TokenID="" CAVV="" ECI="" ATSData="" TransactionID="'.$orderdata->transaction_id.'" AuthenticationStatus="" VchNumber="" FailureCount="" ExchCreditNo="" />
            </Data>
        </Request>';
        //echo $order_request_data;
            $client = new SoapClient('http://trade.loake.co.uk:8080/wsalive/wsalive/wsdl?targetURI=urn:omlink');
            $result = $client->wsomhandler($order_request_data);
            $xmldatas = new SimpleXMLElement($result);

            /* $filepath=plugin_dir_path( __FILE__ ).'cronjobfile/orderinformationdata.txt';
            $fp = fopen($filepath, "a") or die("Unable to open file!");
            fwrite($fp, "\n ---------------------------------------------------\n");
            fwrite($fp, date("Y-m-d H:i:s"));
            fwrite($fp, "\n ---------------------------------------------------\n");
            fwrite($fp, "\n Request Data :\n");
            fwrite($fp, $order_request_data);
            fwrite($fp, "\n Response Data :\n");
            fwrite($fp, $result);
            fclose($fp); */

            /* weekly log information for traction  */

                $data = '';
                $year = date("Y");
                $month = date("m");
                $day = date("D");

                $directory = plugin_dir_path( __FILE__ )."primaomcornjob/regular_order/$day/";

                $data .= "\n ********************************** \n";
                $data .=  date('Y-m-d H:i:s');
                $data .= "\n ********************************** \n";
                $data .= "\n Request Data :\n";
                $data .= $order_request_data;
                $data .= "\n Response Data :\n";
                $data .= $result;
                $data .= "\n ********* End ********* \n";

                $f_name =  'orderinformationdata-'.date('Y-m-d').'.txt';

                $filename = $directory.$f_name;

                $beforeweekdate = date('Y-m-d');
                $beforeweekdate = strtotime($beforeweekdate);
                $beforeweekdate = strtotime("-7 day", $beforeweekdate);
                $beforeweekfilename = 'orderinformationdata-'.date('Y-m-d', $beforeweekdate).'.txt';
                $beforeweekpath=$directory.$beforeweekfilename;

                if(!is_dir($directory)){
                    
                    mkdir($directory, 0775, true);

                    if (!file_exists($filename)) {
                        $fh = fopen($filename, 'w') or die("Can't create file");
                    }
                    
                    $ret = file_put_contents($filename, $data, FILE_APPEND | LOCK_EX);

                }else{

                    if(file_exists($beforeweekpath)){

                        unlink($beforeweekpath);
                    }

                    if (!file_exists($filename)) {
                        $fh = fopen($filename, 'w') or die("Can't create file");
                    }

                    $ret = file_put_contents($filename, $data, FILE_APPEND | LOCK_EX);

                }


            /* end week log information */    


            if(!empty($xmldatas)){

                if(!empty($xmldatas['RequestError']) || $xmldatas['RequestStatus']=='ERROR'){

                    echo $msg="Request Order is fail";
                    update_post_meta($order_id, 'order_flag', 0);

                }else{

                    foreach( $orderdata->get_items() as $item ) {
                    
                        $sm_product = wc_get_product($item['product_id'] );
                       
                               
                        if($sm_product->get_type()=='variable'){

                            if( isset($item['product_id']) && !empty($item['product_id']) ){

                                $stock_quantity = $this->wc_get_variable_product_stock_quantity('raw',$item['product_id']);
                
                                if(!empty($stock_quantity) && $stock_quantity > 0){
                
                                    update_post_meta($item['product_id'], '_stock', $stock_quantity);
                                    update_post_meta( $item['product_id'], '_stock_status', true);
                                    update_post_meta( $item['product_id'], '_backorders', 'notify' );
                                }
                                else{
                                    update_post_meta($item['product_id'], '_stock', 0);
                                    update_post_meta($item['product_id'], '_stock_status', 'onbackorder');
                                    update_post_meta( $item['product_id'], '_backorders', 'notify' );
                                }
                                
                            }

                        }
                        if($sm_product->get_type()=='simple'){

                            $stock_quantity = get_post_meta($item['product_id'], '_stock', true);
            
                            if(!empty($stock_quantity) && $stock_quantity > 0){
            
                                update_post_meta($item['product_id'], '_stock', $stock_quantity);
                                update_post_meta( $item['product_id'], '_stock_status', true);
                                update_post_meta( $item['product_id'], '_backorders', 'notify' );
                            }
                            else{
                                update_post_meta($item['product_id'], '_stock', 0);
                                update_post_meta($item['product_id'], '_stock_status', 'onbackorder');
                                update_post_meta( $item['product_id'], '_backorders', 'notify' );
                            }
                            
                        }


                    }	
                    update_post_meta($order_id, 'order_flag', 1);
                    $_SESSION['vflag']=$order_id;
                }
            }
        
        }
       // echo $order_request_data;

    }
     
   function prima_custom_stock_reduction( $quantity, $order, $item ) {
	
        $multiplier = $item->get_product()->get_meta( '_stock_multiplier' );
        if ( empty( $multiplier ) && $item->get_product()->is_type( 'variation' ) ) {
            $product = wc_get_product( $item->get_product()->get_parent_id() );
            $multiplier = $product->get_meta( '_stock_multiplier' );
        }
        if ( ! empty( $multiplier ) ) {
            $quantity = $multiplier * $quantity;
        }
    
        return $quantity;
    }
    
    function prima_order_status(){

	
        $msg='';
        $code='';
        $result='';
    
        $order_id = !empty($_REQUEST['prima_product_orderid'])?$_REQUEST['prima_product_orderid']:'';
    
        if(!empty($order_id)){
    
            $orprex='201';
            $newor_id=$orprex.$order_id;

            $order_status_request_data  ='';
            $order_status_request_data .='<?xml version="1.0"?>';
            $order_status_request_data .='<Request RequestType="OrderStatus">';
            $order_status_request_data .='<Data OrderNumber="'.$newor_id.'"></Data>';
            $order_status_request_data .='</Request>';
    
            $client = new SoapClient('http://trade.loake.co.uk:8080/wsalive/wsalive/wsdl?targetURI=urn:omlink');
            $getresults = $client->wsomhandler($order_status_request_data);
            $xmldatas = new SimpleXMLElement($getresults);
            
            if(!empty($xmldatas) && (string)$xmldatas['RequestStatus']=='OK'){
    
                $result=(string)$xmldatas->Data['OrderStatus'];
                if(!empty($result)){
    
                    $result=(string)$xmldatas->Data['OrderStatus'];
                    $msg = 'order status display sucessfully';
                    $code = 200;
    
                }
                    
            }
            
            if(!empty($xmldatas['RequestError']) || $xmldatas['RequestStatus']=='ERROR'){
    
                    try{
                        $order = new WC_Order( $order_id );
                        $result = $order->get_status();
                        $msg = 'order status display sucessfully';
                        $code =200;
                    }catch(Exception $e){
                        $result = 'order is not found';
                        $code =203;
                    }
                    
            }
    
        }
        else
        {
            $msg = 'please Enter Your Order Id';
            $code =205;
    
        }
    
        echo json_encode(array('resultdata'=>$result,'msg'=>$msg,'code'=>$code));
    
        die();
    
    }
    

    function prima_product_stock_script(){


            global $post;
            
            $stockstatus='';

            if( ! is_product() ) return;

            $product = wc_get_product($post->ID);

            if( ! $product->is_type( 'variable' ) ) return;

            $product_variable = new WC_Product_Variable($product->id);

            $attributes = array('pa_size');

            if($product->get_stock_quantity()>0 && !empty($product->get_manage_stock()) && $product->get_manage_stock()==1){

                update_post_meta($post->ID, '_stock_status', 1);
                update_post_meta( $post->ID, '_backorders', 'notify' );

            }

           
           
            foreach($product->get_visible_children( ) as $variation_id ) {
                
                $variation_obj = wc_get_product($variation_id);

                $vct=0; 
                foreach($product->get_available_variation( $variation_id )['attributes'] as $key => $value_id ){
                    $taxonomy = str_replace( 'attribute_', '', $key );
                   
                    if( in_array( $taxonomy, $attributes) ){

                        $avail_date = get_post_meta($variation_id, 'prima_product_available_date', true);

                        $new_avail_date = str_replace('/', '-', $avail_date );
                        $prod_avail_date = date('d-m-Y',strtotime($new_avail_date));

                        if($prod_avail_date=='01-01-1970' || empty($prod_avail_date)){
                            $prod_avail_date='';
                        }
                        
                        $date2=date('d-m-Y');
                        $numdays='';
                        if(!empty($prod_avail_date)){
                            $date2=date('d-m-Y');
                            $date1=$prod_avail_date;

                            $today = strtotime($date2);
                            $expiration_date=strtotime($date1);

                            if($expiration_date > $today){
                            $numdays=abs((strtotime($date1)-strtotime($date2))/60/60/24);	
                            }if($expiration_date < $today){
                                $numdays='-1';
                            }
                        }
                        
                        $data[ $variation_id ]['pmprod_val'] = get_term_by( 'slug', $value_id, $taxonomy )->slug;
                        $data[ $variation_id ][$taxonomy] = get_term_by( 'slug', $value_id, $taxonomy )->name;	
                        $data[ $variation_id ]['prod_qty'] = $variation_obj->get_stock_quantity();
                        $data[ $variation_id ]['prod_available_date'] = $avail_date;
                        $data[ $variation_id ]['prod_available_day'] = $numdays;

                        if(!empty($variation_obj->get_manage_stock()) && $variation_obj->get_manage_stock()==1 ){

                            

                            if( $variation_obj->get_stock_quantity()==0 && $avail_date!='' && $numdays>60){

                                update_post_meta( $variation_id, '_stock_status', true);
                                update_post_meta( $variation_id, '_backorders', 'no' );
                               
                            }

                            if($variation_obj->get_stock_quantity()==0 && $avail_date!='' && $numdays<60){

                                update_post_meta($variation_id, '_stock_status', 'onbackorder');
                                update_post_meta( $variation_id, '_backorders', 'notify' );
        
                            }
                            
                        }
                       

                    }


                    
                }
            }

            

            if(!empty($product->get_manage_stock()) && $product->get_manage_stock()==1){

            
            if(!empty($product->get_stock_quantity()) && $product->get_stock_quantity()>0){

                $stockstatus = '';

            }else{
                $stockstatus = 'Out Of Stock';
            }

            echo '<label><span id="prodstockstatus">'.$stockstatus.'</span></label>';
            }else{ ?>

            <script type="text/javascript">
                jQuery( document ).ready(function($) {
                    
                    $('button.single_add_to_cart_button.button.alt.ajax_add_to_cart.progress-btn').addClass('disabled1');

                });
            </script>

            <?php

            }

            ?>
                <script type="text/javascript">
                    (function($){
            
                        var variationsData = <?php echo json_encode($data); ?>,
                            productTitle = $('#prodstockstatus').text(),
                            //size = 'pa_size';
                            qty = 'prod_qty';
                        console.log(variationsData);
                            var expire = [];
                            $.each( variationsData, function( index, value ){

                                if((value['prod_qty']=='0'|| value['prod_qty']=='') && value['prod_available_date']!='' && value['prod_available_day']>60 || value['prod_available_day']=='-1'){

                                    expire.push(value['pmprod_val']);

                                }

                                if(value['prod_available_date']=='' && (value['prod_qty']=='0'|| value['prod_qty']=='')){

                                    expire.push(value['pmprod_val']);
                                        
                                }

                            });

                            for(var i=0; i < expire.length; i++){

                               // $("#pa_size option:contains('"+expire[i]+"')").remove();
                                $("#pa_size option[value='"+expire[i]+"']").remove();

                            }

                            
                        function update_the_product_stock_status( productTitle, variationsData, qty ){

                            

                            $.each( variationsData, function( index, value ){

                                if( index == $('input.variation_id').val() ){

                                
                                    if( value[qty]!='' && value[qty] > 0 ){

                                        $('#prodstockstatus').text('');
                                        
                                    }else{	

                                        
                                        if(value['prod_available_date']!='' && value['prod_available_day']<=60 && value['prod_available_day']!='-1'){
            
                                        //  $('#prodstockstatus').text(value['pa_size']+' (Out of Stock)Due in '+value['prod_available_date']+') - Pre-Order Now'); 
                                            $('#prodstockstatus').text('Pre-Order Available - Due in '+value['prod_available_date']+'');
                                            
                                        }

                                        if(value['prod_available_date']==''){

                                            $('#prodstockstatus').text('Out Of Stock');

                                        }

                                        
                                    }
                                    return false;
                                } else {
                                    $('#prodstockstatus').text(productTitle);
                                }
                            });
                        }

                        
                        setTimeout(function(){
                            update_the_product_stock_status( productTitle, variationsData, qty );
                        }, 300);

                        $('select').blur( function(){
                            update_the_product_stock_status( productTitle, variationsData, qty );
                        });

                    })(jQuery);
                </script>
            <?php
        }

       
    function ad_woosoap_prima_product_actions()
    {
        add_options_page(
            'Prima Cron Settings', 
            'Prima Cron Settings', 
            'manage_options', 
            'woosoapprimaproduct_setting', 
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Options page callback
     */
    function create_admin_page()
    {
        // Set class property
        $this->options = get_option( 'my_option_name' );
        ?>
        <div class="wrap">
            <h1>Prima Cron Settings</h1>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'my_option_group' );
                do_settings_sections( 'woosoapprimaproduct_setting' );
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    function page_init()
    {  
        

        register_setting(
            'my_option_group', 
            'my_option_name', 
            array( $this, 'sanitize' ) 
        );

        add_settings_section(
            'setting_section_id', 
            'Cron Settings', 
            array( $this, 'print_section_info' ), 
            'woosoapprimaproduct_setting' 
        ); 
        add_settings_field(
            'prime_cron', 
            'Cron Setting', 
            array( $this, 'title_callback' ), 
            'woosoapprimaproduct_setting', 
            'setting_section_id'
        );      
    }

    
    function sanitize( $input )
    {
        $new_input = array();
        if( isset( $input['id_number'] ) )
            $new_input['id_number'] = absint( $input['id_number'] );

         if( isset( $input['prime_cron'] ) )
            $new_input['prime_cron'] = sanitize_text_field( $input['prime_cron'] ); 
    
        return $new_input;
    }

    function print_section_info()
    {
        print 'Enter your settings below:';
    }

   
    function title_callback()
    {
        $redio = $this->options['prime_cron'];
        printf(
            '<input type="radio" name="my_option_name[prime_cron]" value="1" '. checked( 1, $redio, false ) .'>Yes<br>
                <input type="radio" name="my_option_name[prime_cron]" value="0" '. checked( 0, $redio, false ) .'>No'
        ); 
    }


    }
}

$woosoapproduct = new WOOSOAPPRODUCT();