<?php

/**
 * WebHook processor for WooCommerce orders to HubSpot
 */

require_once("vendor/autoload.php");

//Start
include_once $_SERVER['DOCUMENT_ROOT'] . '/wp-config.php';

$email = '';
$first_name = '';
$phone = '';
$zip = '';
$screensfor = '';

$options = get_option( 'wcoth_options' );
if(!empty($options['wcoth_use_webhook'])){
	if(!empty($_POST['fields']) && is_array($_POST['fields'])){
		foreach($_POST['fields'] as $key => $field){
			switch ($field['id']){
				case 'firstname':
					if(!empty($field['value'])) $first_name = $field['value'];
					break;
				case 'phone':
					if(!empty($field['value'])) $phone = $field['value'];
					break;
				case 'email':
					if(!empty($field['value'])) $email = $field['value'];
					break;
				case 'zip':
					if(!empty($field['value'])) $zip = $field['value'];
					break;
				case 'screensfor':
					if(!empty($field['value'])) $screensfor = $field['value'];
					break;
			}
		}
		if(!empty($email)){
			require_once(plugin_dir_path( __FILE__ ) . DIRECTORY_SEPARATOR . "HubspotOperations.class.php");
			$hub = new HubspotOperations();
			$dealName = '';
			if(!empty($zip)) $dealName .= 'Zip: ' . $zip . '. ';
			if(!empty($dealName)) $dealName .= 'Screens for: ' . $screensfor . ' ';
			$contact_id = $hub->search_or_create_contact($email, $first_name, '', $phone, '');
			$deal_id =  $hub->create_deal($dealName, 0);
			if(!empty($deal_id) && !empty($contact_id)){
				$hub->associate_deal($deal_id, 3, 'contact', $contact_id);
			}
		}
	}
}
