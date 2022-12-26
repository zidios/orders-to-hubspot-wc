<?php

/**
 * Plugin Name: WooCommerce orders to HubSpot
 * Description: Send orders to HubSpot
 * Author:      Vladimir Udachin
 * Version:     1.0.0
 *
 * Requires PHP: 7.4
 *
 * License:     MIT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;

}
require_once(plugin_dir_path( __FILE__ ) . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php");

function wcoth_add_settings_page() {
	add_options_page( 'WooCommerce orders to HubSpot settings page', 'WC orders to HubSpot', 'manage_options', 'wc-oth-plugin', 'wcoth_render_settings_page' );
}
add_action( 'admin_menu', 'wcoth_add_settings_page' );

function wcoth_render_settings_page() {
	?>
	<h2>WooCommerce orders to HubSpot settings</h2>
	<form action="options.php" method="post">
		<?php
		settings_fields( 'wcoth_options' );
		do_settings_sections( 'wcoth' ); ?>
		<input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Save' ); ?>" />
	</form>
	<?php
}

add_filter( 'plugin_action_links_wcoth/orders-to-hubspot-wc.php', 'wcoth_settings_link' );
function wcoth_settings_link( $links ) {
	$url = esc_url( add_query_arg(
		'page',
		'wcoth-plugin',
		get_admin_url() . 'admin.php'
	) );
	$settings_link = "<a href='$url'>" . __( 'Settings' ) . '</a>';
	array_push(
		$links,
		$settings_link
	);
	return $links;
}

function wcoth_register_settings() {
	register_setting( 'wcoth_options', 'wcoth_options', 'wcoth_options_validate' );
	add_settings_section( 'wcoth_general_settings', 'General', 'wcoth_general_section_text', 'wcoth' );

	add_settings_field( 'wcoth_setting_access_token', 'Access token', 'wcoth_setting_access_token', 'wcoth', 'wcoth_general_settings' );
	add_settings_field( 'wcoth_setting_use_webhook', 'Activate webhook', 'wcoth_setting_use_webhook', 'wcoth', 'wcoth_general_settings' );
}
add_action( 'admin_init', 'wcoth_register_settings' );

function wcoth_options_validate( $input ) {

	//TODO: Make all validations

	return $input;
}
function wcoth_general_section_text() {
	$options = get_option( 'wcoth_options' );
	if(!empty($options['wcoth_access_token']) && !empty($options['wcoth_use_webhook'])){
		echo '<p><strong>Webhook URL:</strong> ' . plugin_dir_url( __FILE__ ) . 'webhook.php</p>';
	} else {
		echo '<p>Please fill all setting fields to get a webhook URL</p>';
	}

}

function wcoth_setting_access_token() {
	$options = get_option( 'wcoth_options' );
	echo "<input id='wcoth_setting_access_token' name='wcoth_options[wcoth_access_token]' type='text' value='" . esc_attr( $options['wcoth_access_token'] ) . "' />";
}

function wcoth_setting_use_webhook() {
	$options = get_option( 'wcoth_options' );
    $wcoth_use_webhook_checked = !empty($options['wcoth_use_webhook']) ? 'checked' : '';
	echo "<input id='wcoth_setting_use_webhook' name='wcoth_options[wcoth_use_webhook]' type='checkbox' " . $wcoth_use_webhook_checked . " />";
}

add_action('woocommerce_thankyou', 'wcoth_process_order', 10, 1);

function build_deal_name($order_id, $address_1, $address_2, $city, $state, $postcode, $country){
    $result = null;
    if(!empty($order_id)){
        $result = '#'. intval($order_id) .'.';
        $address_str = '';
        $address_array = array();
        if(!empty($address_1)) array_push($address_array, $address_1);
	    if(!empty($address_2)) array_push($address_array, $address_2);
	    if(!empty($city)) array_push($address_array, $city);
	    if(!empty($state)) array_push($address_array, $state);
	    if(!empty($postcode)) array_push($address_array, $postcode);
	    if(!empty($country)) array_push($address_array, $country);
        if(count($address_array) > 0){
	        $address_str = implode(', ', $address_array);
        }
        if(strlen($address_str) > 1){
            $result = $result . ' Shipping address: ' . $address_str;
        }
    }
    return $result;
}
function wcoth_process_order( $order_id ) {
	if ( ! $order_id )
		return;
	require_once(plugin_dir_path( __FILE__ ) . DIRECTORY_SEPARATOR . "HubspotOperations.class.php");

    $order = wc_get_order( $order_id );
    $orderDetails = $order->get_data();
    if(!empty($orderDetails) && !empty($orderDetails['billing'])){
        $email = $orderDetails['billing']['email'];
	    $first_name = $orderDetails['billing']['first_name'];
	    $last_name = $orderDetails['billing']['last_name'];
	    $company = $orderDetails['billing']['company'];
	    $address_1 = $orderDetails['billing']['address_1'];
	    $address_2 = $orderDetails['billing']['address_2'];
	    $city = $orderDetails['billing']['city'];
	    $state = $orderDetails['billing']['state'];
	    $postcode = $orderDetails['billing']['postcode'];
	    $country = $orderDetails['billing']['country'];
	    $phone = $orderDetails['billing']['phone'];
	    $hub = new HubspotOperations();
        $order_items = array();
        $order_total = $order->get_total();
        $order_total = floatval($order_total);
	    foreach ( $order->get_items() as $item_id => $item ) {
            $line_item_data = $item->get_data();
		    $product_quantity = (!empty($line_item_data) && !empty($line_item_data['quantity'])) ? intval($line_item_data['quantity']) : 1;
		    // Get the product object
		    $product = $item->get_product();

		    // Get the product Id
		    $product_id = $product->get_id();

		    // Get the product name
		    $product_name = $item->get_name();
		    $product_attrs = array();
            $product_price = 0;

		    switch (get_class($product)){
			    case 'WC_Product_Variable':
				    $product_variations = $product->get_available_variations();
				    foreach ($product_variations as $variation){
					    if(!empty($variation['display_price'])) array_push($product_prices, $variation['display_price']);
					    if(!empty($variation['attributes'])){
						    reset($variation['attributes']);
						    array_push($product_attrs, current($variation['attributes']));
					    }
				    }
				    break;
			    case 'WC_Product_Simple':
				    $product_price = $product->get_price();
				    $product_attrib = $product->get_attributes();
				    if(!empty($product_price)) array_push($product_prices, $product_price);
				    if(!empty($product_attrib)){
                        foreach($product_attrib as $key => $attrib){
	                        if(is_object($attrib) && get_class($attrib) == 'WC_Product_Attribute'){
		                        if($attrib['is_taxonomy']){
			                        $values = wc_get_product_terms( $product->get_id(), $attrib['name'], array( 'fields' => 'names' ) );
			                        if(isset($values[0])) {
                                        $attr = $attrib['name'] . ': ' .$values[0];
				                        array_push($product_attrs, $attr);
			                        }
		                        }else{
			                        $attrib_data = $attrib->get_data();
			                        if(!empty($attrib_data) && !empty($attrib_data['value'])){
				                        $attr = $attrib['name'] . ': ' .$attrib_data['value'];
				                        array_push($product_attrs, $attr);
			                        } elseif(!empty($attrib_data) && !empty($attrib_data['options']) && !empty($attrib_data['options'][0])){
				                        $attr = $attrib['name'] . ': ' .$attrib_data['options'][0];
				                        array_push($product_attrs, $attr);
			                        }
		                        }


	                        }
                        }

				    }
				    break;
		    }
            if(!empty($product_attrs) && is_array($product_attrs)){
	            $product_name = $product_name . ' (' . implode('; ', $product_attrs) . ')';
            }

            $hub_item_id = $hub->create_line_item($product_name, $product_id, $product_quantity, floatval($product_price));
            if(!empty($hub_item_id)){
                array_push($order_items, $hub_item_id);
            }

	    }
        $dealName = build_deal_name($order_id, $address_1, $address_2, $city, $state, $postcode, $country);
        if(!empty($dealName)){
	        $contact_id = $hub->search_or_create_contact($email, $first_name, $last_name, $phone, $company);
	        $deal_id =  $hub->create_deal($dealName, $order_total);
	        if(!empty($deal_id) && !empty($contact_id)){
		        $hub->associate_deal($deal_id, 3, 'contact', $contact_id);
		        if(!empty($order_items) && is_array($order_items)){
			        foreach ($order_items as $order_item){
				        $hub->associate_deal($deal_id, 19, 'line_item', $order_item);
			        }
		        }
	        }
        }
    }
    $order->update_meta_data( '_sync_to_hubspot_done', true );
    $order->save();

}
require_once(plugin_dir_path( __FILE__ ) . DIRECTORY_SEPARATOR . "HubspotOperations.class.php");



