<?php
 
add_filter( 'woocommerce_order_item_display_meta_key', 'change_shipping_note_title', 20, 3 );
function change_shipping_note_title( $key, $meta, $item ) {

	if ( 'Back-ordered' === $meta->key ) { $key = __( 'Pre-ordered', ''); }   
    return $key;

}

add_filter('woocommerce_before_single_product','custom_primaom_product_details_fn',999,1);

function custom_primaom_product_details_fn(){
	
	global $product;

	$sm_product = wc_get_product( $product->id );

	if($sm_product->get_stock_quantity()>0 && !empty($sm_product->get_manage_stock()) && $sm_product->get_manage_stock()==1){

		update_post_meta($product->id, '_stock_status', 1);
		update_post_meta( $product->id, '_backorders', 'notify' );

	}

}

add_action('woocommerce_thankyou', 'primaom_deactivalte_plugin_order_data_fn',10, 1);

function primaom_deactivalte_plugin_order_data_fn($order_id){

    if( !is_plugin_active( 'woo-soap-product/woo-soap-product.php' ) ) {

        $primaom_orderinfo = wc_get_order( $order_id );
        $primaom_orderinfo->update_status( 'processing' );  
        update_post_meta($order_id, 'order_flag', 0);
        
    }

}