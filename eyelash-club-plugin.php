<?php
/*
@package EyelashClubPlugin
Plugin Name: Eyelash Club Custom Plugin
Plugin URI:  https://bleazyusa.com/eyelash-club-plugin
Description: Custom features exclusively for Eyelash Club Holdings LLC
Version:     1.0
Author:      Bleazy
Author URI:  https://bleazyusa.com/
License:     GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: wporg
Domain Path: /languages
*/

// 5d4fdjd45dfkdl

if(!defined( 'ABSPATH' )){
	die;
}

$timezone = get_option('timezone_string');
date_default_timezone_set($timezone);



// register hooks
register_activation_hook(__FILE__, 'eyelash_club_install');
register_deactivation_hook(__FILE__, 'eyelash_club_uninstall');
function eyelash_club_install(){
	global $wpdb;
	global $eyelash_club_db_version;
	
	// create the table
	$eyelash_club_table = $wpdb->prefix.'eyelash_club';
	
	$sql_a = "CREATE TABLE $eyelash_club_table (
			id INT(3) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			name VARCHAR(255) NOT NULL,
			gateway_address VARCHAR(255) NOT NULL,
			datetime DATETIME NOT NULL)";
	
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta($sql_a);
}

function eyelash_club_uninstall(){
	global $wpdb;
	global $eyelash_club_db_version;
	
	// drop the table
	$eyelash_club_table = $wpdb->prefix.'eyelash_club';
	
	$wpdb->query("DROP TABLE IF EXISTS $eyelash_club_table");
}


add_action('admin_menu', 'eyelash_club_admin_action');
function eyelash_club_admin_action(){
	add_menu_page('Eyelash Club Admin', 'Eyelash Club', 'manage_options', 'eyelash-club-admin', 'eyelash_club_admin', '', 2);
	add_submenu_page('eyelash-club-admin', 'Eyelash Club Reports', 'Reports', 'manage_options', 'eyelash-club-admin-reports', 'eyelash_club_admin_reports');
	$intakeFormPage = add_submenu_page('eyelash-club-admin', 'Eyelash Club Client Form', 'Client Form', 'manage_options', 'eyelash-club-admin-client-form', 'eyelash_club_admin_client_form');
	$smsSettingsPage = add_submenu_page('eyelash-club-admin', 'Eyelash Club SMS Settings', 'SMS Settings', 'manage_options', 'eyelash-club-admin-sms-settings', 'eyelash_club_admin_sms_settings');
	//add_submenu_page('eyelash-club-admin', 'Eyelash Club Settings', 'Settings', 'manage_options', 'eyelash-club-admin-settings', 'eyelash_club_admin_settings');

    add_action( 'load-'.$intakeFormPage, 'load_ec_datatable_js' );
    add_action( 'load-'.$smsSettingsPage, 'load_ec_datatable_js' );
}

function load_ec_datatable_js() {
	add_action( 'admin_enqueue_scripts', 'plugin_datatables_external_files' );
}

function plugin_datatables_external_files() {
	wp_register_style('plugin_datatables_external_files_css', 'https://cdn.datatables.net/v/bs4-4.1.1/dt-1.10.18/r-2.2.2/datatables.min.css');
    wp_enqueue_style('plugin_datatables_external_files_css');
    wp_register_style('plugin_tiptip_external_files_css', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.tiptip/1.3/tipTip.min.css');
    wp_enqueue_style('plugin_tiptip_external_files_css');

	wp_enqueue_script( 'plugin_datatables_external_files_js', 'https://cdn.datatables.net/v/bs4-4.1.1/dt-1.10.18/r-2.2.2/datatables.min.js', array('jquery'), '3.3.5', true );
	wp_enqueue_script( 'plugin_tiptip_external_files_js', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.tiptip/1.3/jquery.tipTip.minified.js', array('jquery'), '3.3.5', true );
}

function eyelash_club_admin(){
	global $wpdb;
	
	//require_once('config.php');
	require_once('stripe/init.php');
	
	if(isset($_GET['ready_tip'])){
		$_POST['ready_tip'] = $_GET['ready_tip'];
	}
	
	$stripe = array(
		"secret_key" => "sk_live_rs6kmN2CRcqoyEGKfbLHGOAj",
		"publishable_key" => "pk_live_MpCRUhNRJs1NzVg8JL74AjmV"
	);
	/*$stripe = array(
		"secret_key" => "sk_test_zDNy7FTlMHxYbgURqUOFJlck",
		"publishable_key" => "pk_test_N2VwBzTgnwdiveG1TQsnYeYA"
	);*/
	//ch_1CyoQcD5wAjbBOzFFQh1kz9M

	\Stripe\Stripe::setApiKey($stripe['secret_key']);
	
	function format_tip($tip_input){
		$tip_input = preg_replace("/[^0-9]/", "", $tip_input);
		$tip_amount_decimal = substr($tip_input,-2,2);
		$tip_amount_whole = substr($tip_input,0,-2);
		
		$output['thousands'] = $tip_input;
		$output['decimal'] = $tip_amount_whole.'.'.$tip_amount_decimal;
		return $output;
	}
	
	function display_msg($message){
		echo '
			<div id="message" class="updated notice notice-success is-dismissible">
				<p>'.$message.'</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
			</div>';
	}
		
		
	$eyelash_club_table = $wpdb->prefix.'eyelash_club';
	$_posts_ = $wpdb->prefix.'posts';
	$_postmeta_ = $wpdb->prefix.'postmeta';
	$_users_ = $wpdb->prefix.'users';
	$_usermeta_ = $wpdb->prefix.'usermeta';
	$_ec_appt_tips_ = $wpdb->prefix.'ec_appt_tips';
	$_ec_appt_status_ = $wpdb->prefix.'ec_appt_status';
	$_woocommerce_payment_tokens_ = $wpdb->prefix.'woocommerce_payment_tokens';
	
	//echo "<div>NEW TIP POST ($_POST[tip_amount])</div>";
	
	
	/************* TIP SUBMITTED *************/
	if(isset($_POST['submit_tip'])){
		//$_POST['tip_amount'] = preg_replace("/[^0-9]/", "", $_POST['tip_amount']);
		$format_tip = format_tip($_POST['tip_amount']);
		
		
		//$tip_amount = $_POST['tip_amount'];
		$tip_amount = $format_tip['thousands'];
		//echo "<div>FORMATTED STRIPE TIP AMOUNT ($tip_amount)</div>";
		$statement_descriptor = 'EyelashClubTip('.$_POST['booking_id'].')';
		
		//$tip_amount_decimal = substr($tip_amount,-2,2);
		//$tip_amount_whole = substr($tip_amount,0,-2);
		//$_ec_appt_tip = $tip_amount_whole.'.'.$tip_amount_decimal;
		$_ec_appt_tip = $format_tip['decimal'];
		//echo "<div>FORMATTED _ec_appt_tip: $_ec_appt_tip</div>";
		
		if($_POST['stripe_token']){
			$stripe_source_id = $_POST['stripe_source_id'];
			//echo "<div>STRIPE TOKEN</div>";
			
			$charge = \Stripe\Charge::create([
				'amount' => $tip_amount,
				'currency' => 'usd',
				//'customer' => $stripe_customer_id,
				'source' => $stripe_source_id,
				'description' => $statement_descriptor,
				'statement_descriptor' => $statement_descriptor,
			]);
		}
		else{
			//echo "<script> setTimeout(function(){ window.location.replace('http://www.google.com/'); }, 5000); </script>";
			//echo "<div>TIP SUBMITTED ($_POST[tip_amount])</div>";
			
			$stripe_customer_id = $_POST['stripe_customer_id'];
			$stripe_source_id = $_POST['stripe_source_id'];
			//echo "<div>stripe_customer_id: ($_POST[stripe_customer_id])</div>";
			//echo "<div>stripe_source_id: ($_POST[stripe_source_id])</div>";
			
			$charge = \Stripe\Charge::create([
				'amount' => $tip_amount,
				'currency' => 'usd',
				'customer' => $stripe_customer_id,
				'source' => $stripe_source_id,
				'description' => $statement_descriptor,
				'statement_descriptor' => $statement_descriptor,
			]);
		}
		if($charge->status === 'succeeded'){
			display_msg('You have entered your tip successfully!');
		}
		else{
			display_msg('Uh oh! It seems there was a problem!');
		}
		
		/*echo "<div>";
		print_r($charge);
		echo "</div>";*/
		
		$post_id = $_POST['booking_id'];
		
		$datetime = date('Y-m-d H:i:s');
		
		update_post_meta($post_id,'_ec_appt_tip',$_ec_appt_tip);
		
		$postmeta = get_post_meta($post_id,'_ec_tech_id');
		$_ec_tech_id = $postmeta[0];
		
		if(empty($_ec_tech_id)){
			$_ec_tech_id = 0000;
		}
		
		$store_arr['booking_id'] = $_POST['booking_id'];
		$store_arr['tip_amount'] = $_ec_appt_tip;
		$store_arr['technician_id'] = $_ec_tech_id;
		$store_arr['datetime'] = $datetime;
		
		
		
		$wpdb->insert($_ec_appt_tips_,$store_arr);
		unset($store_arr);
		
		$current_user = wp_get_current_user();
		$store_arr['booking_id'] = $post_id;
		$store_arr['user_id'] = $current_user->ID;
		$store_arr['datetime'] = $datetime;
		
		$check_out = date("Y-m-d g:i a");
		update_post_meta($post_id,'_ec_appt_check_out',$check_out);
		update_post_meta($post_id,'_ec_appt_status','CHECKED OUT');
		$store_arr['status'] = 'CHECKED OUT';
		
		//print_r($store_arr);
		
		$wpdb->insert($_ec_appt_status_,$store_arr);
	}
	
	/************* TIP SUBMITTED *************/
	
	if($_GET['type'] === 'check_out'){
		$output = '
		<div class="wrap" align="center">
			<h1></h1>
		</div>
		<div align="center">
			<p><img src="https://eyelashclub.com/wp-content/uploads/2018/05/horiz_logo_text.png" alt="Eyelash Club Logo"></p>
		</div>';
		
		$sql_a = "SELECT * FROM $_posts_ 
				  WHERE ID='$_GET[post]'";
		$posts = $wpdb->get_results($sql_a);
		//print_r($posts);
		
		$sql_m = "SELECT * FROM $_postmeta_ 
				  WHERE post_id='$_GET[post]'";
		$postmeta = $wpdb->get_results($sql_m);
		//print_r($postmeta);
		$_booking_rating_val = '';
		foreach($postmeta as $data){
			if($data->meta_key === '_booking_product_id'){
				$_booking_product_id = $data->meta_value;
			}
			else if($data->meta_key === '_booking_resource_id'){
				$_booking_resource_id = $data->meta_value;
			}
			else if($data->meta_key === '_booking_start'){
				//$_booking_start = date("F j, Y, g:i a", $data->meta_value);
				$_booking_start = $data->meta_value;
				$bk_year = substr($_booking_start,0,4);
				$bk_month = substr($_booking_start,4,2);
				$bk_day = substr($_booking_start,6,2);
				$bk_hour = substr($_booking_start,8,2);
				$bk_min = substr($_booking_start,10,2);
				
				$datetime_convert = $bk_year.'-'.$bk_month.'-'.$bk_day.' '.$bk_hour.':'.$bk_min;
				
				$_booking_start = date("F j, Y, g:i a", strtotime($datetime_convert));
			}
			else if($data->meta_key === '_booking_end'){
				$_booking_end = $data->meta_value;
			}
			else if($data->meta_key === '_booking_customer_id'){
				$_booking_customer_id = $data->meta_value;
			}
            else if($data->meta_key === '_ec_appt_rating'){
				$_booking_rating_val = $data->meta_value;
			}
			else if($data->meta_key === '_ec_appt_tip'){
				$_booking_tip_val = $data->meta_value;
			}
		}
		if(!empty($_booking_tip_val)){
			//echo "<div>TIP AMOUNT: $_booking_tip_val</div>";
			$tip_posted = true;
		}
		else{
			//echo "<div>NO TIP YET</div>";
			$tip_posted = false;
		}
		
		$sql_b = "SELECT * FROM $_posts_ 
				  WHERE ID='$_booking_product_id'";
		$product = $wpdb->get_results($sql_b);
		
		$booking_product = $product[0]->post_title;
		
		$sql_c = "SELECT * FROM $_posts_ 
				  WHERE ID='$_booking_resource_id'";
		$resource = $wpdb->get_results($sql_c);
		
		$booking_resource = $resource[0]->post_title;
		$booked_product = $booking_product.' ('.$booking_resource.')';
		
		$sql_d = "SELECT * FROM $_users_ 
				  WHERE ID='$_booking_customer_id'";
		$user = $wpdb->get_results($sql_d);
		
		$customer_email = $user[0]->user_email;
		
		$sql_e = "SELECT * FROM $_usermeta_ 
				  WHERE user_id='$_booking_customer_id'";
		$cust_meta = $wpdb->get_results($sql_e);
		
		foreach($cust_meta as $cust){
			if($cust->meta_key === 'first_name'){
				$cust_first_name = $cust->meta_value;
			}
			else if($cust->meta_key === 'last_name'){
				$cust_last_name = $cust->meta_value;
			}
			
			else if($cust->meta_key === 'billing_email'){
				$cust_billing_email = $cust->meta_value;
			}
		}
		
		// get the payment source
		$sql_t = "SELECT * FROM $_woocommerce_payment_tokens_ 
				  WHERE user_id='$_booking_customer_id'";
		$payment_token = $wpdb->get_results($sql_t);
		//echo "<div>sql_t: $sql_t</div>";
		//print_r($payment_token);
		$stripe_source_id = $payment_token[0]->token;
		//echo "<div>stripe_source_id A: $stripe_source_id</div>";
		
		
		$stripe_customer = \Stripe\Customer::all(array("email" => $customer_email));
		//print_r($stripe_customer);
		$num_cus_all = count($stripe_customer->data);
		
		if($num_cus_all > 1 || empty($stripe_source_id)){
			// multiple customers - offer onetime_tip
			if(isset($_POST['onetime_tip'])){
				$stripe_pk = $stripe['publishable_key'];
				$format_tip = format_tip($_POST['tip_amount']);
				$tip_amount = $format_tip['thousands'];
				//echo "<div>STRIPE TIP ($tip_amount)</div>";
				
				$table_tip_tr = '
				<tr>
					<td><h3>Would you like to leave a tip?</h3></td>
					<td>
					<div style="padding:10px">
						<form action="" method="POST">
							<input type="hidden" name="ready_tip" value="true">
							<input type="hidden" name="tip_amount" value="'.$tip_amount.'">
							<script
								src="https://checkout.stripe.com/checkout.js" class="stripe-button"
								data-key="'.$stripe_pk.'"
								data-amount="'.$tip_amount.'"
								data-name="Eyelash Club"
								data-description="One Time Charge"
								data-image="https://stripe.com/img/documentation/checkout/marketplace.png"
								data-locale="auto">
							</script>
						</form>
					</div>
					</td>
				</tr>';
			}
			else if(isset($_POST['ready_tip'])){
				$submit_btn_name = 'submit_tip';
				$stripe_token = $_POST['stripeToken'];
				$stripe_source_id = $stripe_token;
				//echo "<div>stripe_token: $stripe_token</div>";
				$format_tip = format_tip($_POST['tip_amount']);
				$tip_amount = $format_tip['thousands'];
				$tip_amount_decimal = $format_tip['decimal'];
				
				$table_tip_tr = '
				<tr>
					<td><h3>Would you like to leave a tip?</h3>
					<p>You do not have a credit card on file. Your tip will be a one time charge.</p></td>
					<td>
					<div style="padding:10px">
						<form action="" method="post">
							<input type="hidden" name="stripe_customer_id" value="'.$stripe_customer_id.'">
							<input type="hidden" name="stripe_source_id" value="'.$stripe_source_id.'">
							<input type="hidden" name="stripe_token" value="true">
							<input type="hidden" name="booking_id" value="'.$_GET[post].'">
							<input type="hidden" name="tip_amount" value="'.$tip_amount.'">
							<input type="submit" class="button button-primary button-large eyelashClubPluginCheckoutTipBtnCls" name="'.$submit_btn_name.'" value="Tip $'.$tip_amount_decimal.'">
						</form>
					</div>
					</td>
				</tr>';
			}
			else{
				$submit_btn_name = 'onetime_tip';
				
				if($tip_posted){
					$table_tip_tr = '
					<tr>
						<td><h3>Tip Amount</h3>
						<p>No credit card on file. Tip posted is one time charge.</p></td>
						<td><h3>$'.$_booking_tip_val.'</h3></td>
					</tr>';
				}
				else{
					$table_tip_tr = '
					<tr>
						<td><h3>Would you like to leave a tip?</h3>
						<p>You do not have a credit card on file. Your tip will be a one time charge.</p></td>
						<td>
						<div style="padding:10px">
							<form action="" method="post">
								<input type="hidden" name="stripe_customer_id" value="'.$stripe_customer_id.'">
								<input type="hidden" name="stripe_source_id" value="'.$stripe_source_id.'">
								<input type="hidden" name="booking_id" value="'.$_GET[post].'">
								<input type="hidden" name="tip_amount" value="500">
								<input type="submit" class="button button-primary button-large eyelashClubPluginCheckoutTipBtnCls" name="'.$submit_btn_name.'" value="Tip $5.00">
							</form>
						</div>
						<div style="padding:10px">
							<form action="" method="post">
								<input type="hidden" name="stripe_customer_id" value="'.$stripe_customer_id.'">
								<input type="hidden" name="stripe_source_id" value="'.$stripe_source_id.'">
								<input type="hidden" name="booking_id" value="'.$_GET[post].'">
								<input type="hidden" name="tip_amount" value="1000">
								<input type="submit" class="button button-primary button-large eyelashClubPluginCheckoutTipBtnCls" name="'.$submit_btn_name.'" value="Tip $10.00">
							</form>
						</div>
						<div style="padding:10px">
							<form action="" method="post">
								<input type="hidden" name="stripe_customer_id" value="'.$stripe_customer_id.'">
								<input type="hidden" name="stripe_source_id" value="'.$stripe_source_id.'">
								<input type="hidden" name="booking_id" value="'.$_GET[post].'">
								<input type="hidden" name="tip_amount" value="1500">
								<input type="submit" class="button button-primary button-large eyelashClubPluginCheckoutTipBtnCls" name="'.$submit_btn_name.'" value="Tip $15.00">
							</form>
						</div>
						<div style="padding:10px">
							<form action="" method="post">
								<input type="hidden" name="stripe_customer_id" value="'.$stripe_customer_id.'">
								<input type="hidden" name="stripe_source_id" value="'.$stripe_source_id.'">
								<input type="hidden" name="booking_id" value="'.$_GET[post].'">
								<!--<input type="number" min="0" class="css-input eyelashClubPluginCheckoutTipInputCls" name="tip_amount" value="" placeholder="$25">-->
								<div class="input-group eyelashClubPluginCheckoutTipInputWrapperCls">
									<div class="input-group-prepend">
										<span class="input-group-text">$</span>
									</div>
									<input type="number" value="0.00" step="0.01" id="tip_amount" name="tip_amount" class="form-control eyelashClubPluginCheckoutTipInputCls" aria-label="Amount (to the nearest dollar)">
								</div>
						</div>
						<div style="padding:10px">
								<input type="submit" class="button button-primary button-large eyelashClubPluginCheckoutTipBtnCls" name="'.$submit_btn_name.'" value="Tip Custom Amount">
							</form>
						</div>
						</td>
					</tr>';
				}
			}
		}
		else{
			$stripe_customer_id = $stripe_customer->data[0]->id;
			//echo "<div>stripe_customer_id: $stripe_customer_id</div>";
			
			$stripe_hidden_inputs = '
			<input type="hidden" name="stripe_customer_id" value="'.$stripe_customer_id.'">
			<input type="hidden" name="stripe_source_id" value="'.$stripe_source_id.'">';
			
			$submit_btn_name = 'submit_tip';
			
			if($tip_posted){
				$table_tip_tr = '
				<tr>
					<td><h3>Tip Amount</h3>
					<p>Tip posted to credit card on file.</p></td>
					<td><h3>$'.$_booking_tip_val.'</h3></td>
				</tr>';
			}
			else{
				$table_tip_tr = '
				<tr>
					<td><h3>Would you like to leave a tip?</h3>
					<p>Your credit card on file will be charged.</p></td>
					<td>
					<div style="padding:10px">
						<form action="" method="post">
							<input type="hidden" name="stripe_customer_id" value="'.$stripe_customer_id.'">
							<input type="hidden" name="stripe_source_id" value="'.$stripe_source_id.'">
							<input type="hidden" name="booking_id" value="'.$_GET[post].'">
							<input type="hidden" name="tip_amount" value="500">
							<input type="submit" class="button button-primary button-large eyelashClubPluginCheckoutTipBtnCls" name="'.$submit_btn_name.'" value="Tip $5.00">
						</form>
					</div>
					<div style="padding:10px">
						<form action="" method="post">
							<input type="hidden" name="stripe_customer_id" value="'.$stripe_customer_id.'">
							<input type="hidden" name="stripe_source_id" value="'.$stripe_source_id.'">
							<input type="hidden" name="booking_id" value="'.$_GET[post].'">
							<input type="hidden" name="tip_amount" value="1000">
							<input type="submit" class="button button-primary button-large eyelashClubPluginCheckoutTipBtnCls" name="'.$submit_btn_name.'" value="Tip $10.00">
						</form>
					</div>
					<div style="padding:10px">
						<form action="" method="post">
							<input type="hidden" name="stripe_customer_id" value="'.$stripe_customer_id.'">
							<input type="hidden" name="stripe_source_id" value="'.$stripe_source_id.'">
							<input type="hidden" name="booking_id" value="'.$_GET[post].'">
							<input type="hidden" name="tip_amount" value="1500">
							<input type="submit" class="button button-primary button-large eyelashClubPluginCheckoutTipBtnCls" name="'.$submit_btn_name.'" value="Tip $15.00">
						</form>
					</div>
					<div style="padding:10px">
						<form action="" method="post">
							<input type="hidden" name="stripe_customer_id" value="'.$stripe_customer_id.'">
							<input type="hidden" name="stripe_source_id" value="'.$stripe_source_id.'">
							<input type="hidden" name="booking_id" value="'.$_GET[post].'">
							<!--<input type="number" min="0" class="css-input eyelashClubPluginCheckoutTipInputCls" name="tip_amount" value="" placeholder="$25">-->
							<div class="input-group eyelashClubPluginCheckoutTipInputWrapperCls">
								<div class="input-group-prepend">
									<span class="input-group-text">$</span>
								</div>
								<input type="number" value="0.00" step="0.01" id="tip_amount" name="tip_amount" class="form-control eyelashClubPluginCheckoutTipInputCls" aria-label="Amount (to the nearest dollar)">
							</div>
					</div>
					<div style="padding:10px">
							<input type="submit" class="button button-primary button-large eyelashClubPluginCheckoutTipBtnCls" name="'.$submit_btn_name.'" value="Tip Custom Amount">
						</form>
					</div>
					</td>
				</tr>';
			}
		}
			
		$output .= '
		<div>
			<table class="widefat">
				<thead>
					<tr>
						<th colspan="2"><h3>Booking #'.$posts[0]->ID.' Details</h3>
						<h3>'.$cust_first_name.' '.$cust_last_name.'</h3>
						<h3>'.$cust_billing_email.'</h3></th>
					</tr>
					<tr>
						<th><h4>Booking specification</h4></th>
						<th><h4>Booking date & time</h4></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><h3>'.$booked_product.'</h3></td>
						<td><h3>'.$_booking_start.'</h3></td>
					</tr>
					<tr>
						<td><h3>Please rate your appointment:</h3></td>
						<td>
						<form class="star-cb-form">
						  <fieldset id="booking_'.$posts[0]->ID.'">
                            <div class="ratingBlockMouseCls"'.($_booking_rating_val != '' ? ' style="display:block;"' : '').'></div>
							<span class="star-cb-group">
							  <input type="radio" id="rating-5" name="rating" value="5"'.($_booking_rating_val == '5' ? ' checked="checked"' : '').' /><label class="ratingLabelCls" for="rating-5">5</label>
							  <input type="radio" id="rating-4" name="rating" value="4"'.($_booking_rating_val == '4' ? ' checked="checked"' : '').' /><label class="ratingLabelCls" for="rating-4">4</label>
							  <input type="radio" id="rating-3" name="rating" value="3"'.($_booking_rating_val == '3' ? ' checked="checked"' : '').' /><label class="ratingLabelCls" for="rating-3">3</label>
							  <input type="radio" id="rating-2" name="rating" value="2"'.($_booking_rating_val == '2' ? ' checked="checked"' : '').' /><label class="ratingLabelCls" for="rating-2">2</label>
							  <input type="radio" id="rating-1" name="rating" value="1"'.($_booking_rating_val == '1' ? ' checked="checked"' : '').' /><label class="ratingLabelCls" for="rating-1">1</label>
							  <input type="radio" id="rating-0" name="rating" value="0" class="star-cb-clear"'.($_booking_rating_val == '' ? ' checked="checked"' : '').' /><label class="ratingLabelCls" for="rating-0" style="display:none;">0</label>
							</span>
						  </fieldset>
						</form></td>
					</tr>
					'.$table_tip_tr.'
					<tr>
						<td></td>
						<td></td>
					</tr>
				</tbody>
			</table>
		</div>';
	}
	else{
		//$sel_today = date("Ymd");
		$sel_date = new DateTime('yesterday');
		$sel_yesterday = $sel_date->format('Ymd');
		$date_picker_yesterday = $sel_date->format('Y-m-d');
		
		$sel_date = new DateTime('today');
		$sel_today = $sel_date->format('Ymd');
		$date_picker_today = $sel_date->format('Y-m-d');
		
		$sel_date = new DateTime('tomorrow');
		$sel_tomorrow = $sel_date->format('Ymd');
		$date_picker_tomorrow = $sel_date->format('Y-m-d');
		
		$sel_date = new DateTime('tomorrow + 1day');
		$sel_day_after = $sel_date->format('Ymd');
		
		function checkmydate($date){
			$tempDate = explode('-', $date);
			// checkdate(month, day, year)
			return checkdate($tempDate[1], $tempDate[2], $tempDate[0]);
		}
		
		// get the appointments
		/*$sql_a = "SELECT * FROM $_posts_ 
				  WHERE post_type='wc_booking'
				  ORDER BY post_date DESC";*/
		if($_POST['sel_day'] === 'Yesterday'){
			$sql_AND = "AND wpvq_postmeta.meta_value>'$sel_yesterday' 
						AND wpvq_postmeta.meta_value<'$sel_today' ";
			$yesterday_sel = 'selected';
			$date_picker = $date_picker_yesterday;
		}
		else if($_POST['sel_day'] === 'Today'){
			$sql_AND = "AND wpvq_postmeta.meta_value>'$sel_today' 
						AND wpvq_postmeta.meta_value<'$sel_tomorrow' ";
			$today_sel = 'selected';
			$date_picker = $date_picker_today;
		}
		else if($_POST['sel_day'] === 'Tomorrow'){
			$sql_AND = "AND wpvq_postmeta.meta_value>'$sel_tomorrow' 
						AND wpvq_postmeta.meta_value<'$sel_day_after' ";
			$tomorrow_sel = 'selected';
			$date_picker = $date_picker_tomorrow;
		}
		else{
			if($_POST['date_select']){
				$sel_day = str_replace('-','',$_POST['sel_day']);
				//$sel_date = new DateTime($_POST['sel_day']);
				$sel_date = new DateTime($_POST['sel_day'] .'+1 day');
				$sel_date_after = $sel_date->format('Ymd');
				echo "<div>sel_date_after:  $sel_date_after</div>";
				//$sel_date->add(new DateInterval('P1D'));
				//$sel_date_after = $sel_date->format('Ymd');
		
				$sql_AND = "AND wpvq_postmeta.meta_value>'$sel_day' 
							AND wpvq_postmeta.meta_value<'$sel_date_after' ";
				$date_picker = $_POST['sel_day'];
			}
			else{
				//$sql_AND = "";
				//$date_picker = $date_picker_today;
				$sql_AND = "AND wpvq_postmeta.meta_value>'$sel_today' 
							AND wpvq_postmeta.meta_value<'$sel_tomorrow' ";
				$today_sel = 'selected';
				$date_picker = $date_picker_today;
			}
		}
		$sql_a = "SELECT wpvq_posts.*, 
				  wpvq_postmeta.meta_value 
				  FROM wpvq_posts 
				  LEFT JOIN wpvq_postmeta ON wpvq_posts.ID=wpvq_postmeta.post_id 
				  WHERE wpvq_posts.post_type='wc_booking' 
				  AND (wpvq_posts.post_status='complete' OR wpvq_posts.post_status='confirmed' OR wpvq_posts.post_status='paid') 
				  AND wpvq_postmeta.meta_key='_booking_start' 
				  ".$sql_AND."
				  ORDER BY wpvq_postmeta.meta_value ASC";
		//$gateways = $wpdb->get_results("SELECT * FROM $browsing_sms_table");
		//$sql_a = "SELECT * FROM $eyelash_club_table";
		echo "<div>sql_a:  $sql_a</div>";
		$posts = $wpdb->get_results($sql_a);
		
		$output = '
		<div class="wrap">
			<h2>Eyelash Club Appointments</h2>
		</div>
		
		<div>
			<form action="" method="post">
				<select name="sel_day" onchange="this.form.submit()">
					<option value="null">Select a Day</option>
					<option value="Yesterday" '.$yesterday_sel.'>Yesterday</option>
					<option value="Today" '.$today_sel.'>Today</option>
					<option value="Tomorrow" '.$tomorrow_sel.'>Tomorrow</option>
				</select>
			</form>
			<form action="" method="post">
				<input type="hidden" name="date_select" value="true">
				<input size="25" type="date" name="sel_day" value="'.$date_picker.'">
				<input type="submit" class="button button-primary tips" value="Select Day/Date">
			</form>
		</div>
		
		<div>
			<table class="widefat">
				<thead>
					<tr>
						<th></th>
						<th>ID</th>
						<th>Booked Product</th>
						<th>Client</th>
						<th>Start Date</th>
					</tr>
				</thead>
				<tbody>';
		
		foreach($posts as $post){
			$sql_b = "SELECT * FROM $_postmeta_ WHERE post_id=$post->ID";
			//echo "<div>sql_b:  $sql_b</div>";
			$postmeta = $wpdb->get_results($sql_b);
			
			foreach($postmeta as $data){
				if($data->meta_key === '_booking_product_id'){
					$_booking_product_id = $data->meta_value;
				}
				else if($data->meta_key === '_booking_resource_id'){
					$_booking_resource_id = $data->meta_value;
				}
					else if($data->meta_key === '_booking_customer_id'){
					$_booking_customer_id = $data->meta_value;
				}
				else if($data->meta_key === '_booking_start'){
					//$_booking_start = date("F j, Y, g:i a", $data->meta_value);
					$_booking_start = $data->meta_value;
					$bk_year = substr($_booking_start,0,4);
					$bk_month = substr($_booking_start,4,2);
					$bk_day = substr($_booking_start,6,2);
					$bk_hour = substr($_booking_start,8,2);
					$bk_min = substr($_booking_start,10,2);
					
					$datetime_convert = $bk_year.'-'.$bk_month.'-'.$bk_day.' '.$bk_hour.':'.$bk_min;
				
					$_booking_start = date("F j, Y, g:i a", strtotime($datetime_convert));
				}
				else if($data->meta_key === '_booking_end'){
					$_booking_end = $data->meta_value;
				}
			}
			
			// get the booking product name
			$sql_c = "SELECT * FROM $_posts_ WHERE ID=$_booking_product_id";
			//echo "<div>sql_c:  $sql_c</div>";
			$post_booking_product = $wpdb->get_results($sql_c);
			foreach($post_booking_product as $post_data){
				$booking_product = $post_data->post_title;
			}
			//$booking_product = $post_booking_product->post_title;
			//echo "<div>booking_product:  $booking_product</div>";
					
			// get the booking resource name
			$sql_d = "SELECT * FROM $_posts_ WHERE ID=$_booking_resource_id";
			//echo "<div>sql_d:  $sql_d</div>";
			$post_booking_resource = $wpdb->get_results($sql_d);
			foreach($post_booking_resource as $post_data){
				$booking_resource = $post_data->post_title;
			}
			//$booking_resource = $post_booking_resource->post_title;
			
			$booked_product = $booking_product.' ('.$booking_resource.')';
			
			$sql_e = "SELECT * FROM $_usermeta_ 
					  WHERE user_id='$_booking_customer_id'";
			$cust_meta = $wpdb->get_results($sql_e);
			
			foreach($cust_meta as $cust){
				if($cust->meta_key === 'first_name'){
					$cust_first_name = $cust->meta_value;
				}
				else if($cust->meta_key === 'last_name'){
					$cust_last_name = $cust->meta_value;
				}
				else if($cust->meta_key === 'billing_email'){
					$cust_billing_email = $cust->meta_value;
				}
			}
			if(!empty($cust_first_name) && !empty($cust_last_name)){
				$cust_name = $cust_first_name.' '.$cust_last_name;
			}
			else{
				$cust_name = $cust_billing_email;
			}
			
			$output .= '
			<tr>
				<td></td>
				<td><a href="post.php?post='.$post->ID.'&action=edit">Booking #'.$post->ID.'</a></td>
				<td>'.$booked_product.'</td>
				<td>'.$cust_name.'</td>
				<td>'.$_booking_start.'</td>
			</tr>
			';
			
			unset($_ec_appt_tip);
			unset($cust_first_name);
			unset($cust_last_name);
			unset($cust_name);
			unset($cust_billing_email);
		}
		$output .= '
				</tbody>
			</table>
		</div>';
	}
	echo $output;
}


function eyelash_club_admin_reports(){
	global $wpdb;
	
	$eyelash_club_table = $wpdb->prefix.'eyelash_club';
	$_posts_ = $wpdb->prefix.'posts';
	$_postmeta_ = $wpdb->prefix.'postmeta';
	$_users_ = $wpdb->prefix.'users';
	$_usermeta_ = $wpdb->prefix.'usermeta';
	$_ec_appt_tips_ = $wpdb->prefix.'ec_appt_tips';
	$_ec_appt_status_ = $wpdb->prefix.'ec_appt_status';
	$_woocommerce_payment_tokens_ = $wpdb->prefix.'woocommerce_payment_tokens';
	
	$sel_date = new DateTime('-7days');
	$sel_7days = $sel_date->format('Ymd');
	$date_picker_7days = $sel_date->format('Y-m-d');
	//echo "<div>7days:  $date_picker_7days</div>";
	
	$sel_date = new DateTime('yesterday');
	$sel_yesterday = $sel_date->format('Ymd');
	$date_picker_yesterday = $sel_date->format('Y-m-d');
	
	$sel_date = new DateTime('today');
	$sel_today = $sel_date->format('Ymd');
	$date_picker_today = $sel_date->format('Y-m-d');
	
	$sel_date = new DateTime('tomorrow');
	$sel_tomorrow = $sel_date->format('Ymd');
	$date_picker_tomorrow = $sel_date->format('Y-m-d');
	
	$sel_date = new DateTime('tomorrow + 1day');
	$sel_day_after = $sel_date->format('Ymd');
	
	if($_POST['sel_day'] === '-7days'){
		$sql_AND = "AND wpvq_postmeta.meta_value>'$sel_7days' 
					AND wpvq_postmeta.meta_value<'$sel_today' ";
		$_7days_sel = 'selected';
		$date_picker = $date_picker_yesterday;
	}
	else if($_POST['sel_day'] === 'Yesterday'){
		$sql_AND = "AND wpvq_postmeta.meta_value>'$sel_yesterday' 
					AND wpvq_postmeta.meta_value<'$sel_today' ";
		$yesterday_sel = 'selected';
		$date_picker = $date_picker_yesterday;
	}
	else if($_POST['sel_day'] === 'Today'){
		$sql_AND = "AND wpvq_postmeta.meta_value>'$sel_today' 
					AND wpvq_postmeta.meta_value<'$sel_tomorrow' ";
		$today_sel = 'selected';
		$date_picker = $date_picker_today;
	}
	else if($_POST['sel_day'] === 'Tomorrow'){
		$sql_AND = "AND wpvq_postmeta.meta_value>'$sel_tomorrow' 
					AND wpvq_postmeta.meta_value<'$sel_day_after' ";
		$tomorrow_sel = 'selected';
		$date_picker = $date_picker_tomorrow;
	}
	else{
		if($_POST['date_select']){
			$sel_day = str_replace('-','',$_POST['sel_day']);
			//$sel_date = new DateTime($_POST['sel_day']);
			$sel_date = new DateTime($_POST['sel_day'] .'+1 day');
			$sel_date_after = $sel_date->format('Ymd');
			echo "<div>sel_date_after:  $sel_date_after</div>";
			//$sel_date->add(new DateInterval('P1D'));
			//$sel_date_after = $sel_date->format('Ymd');
	
			$sql_AND = "AND wpvq_postmeta.meta_value>'$sel_day' 
						AND wpvq_postmeta.meta_value<'$sel_date_after' ";
			$date_picker = $_POST['sel_day'];
		}
		else{
			$sql_AND = "";
			$date_picker = $date_picker_today;
		}
	}
	
	$sql_a = "SELECT wpvq_posts.*, 
			  wpvq_postmeta.meta_value 
			  FROM wpvq_posts 
			  LEFT JOIN wpvq_postmeta ON wpvq_posts.ID=wpvq_postmeta.post_id 
			  WHERE wpvq_posts.post_type='wc_booking' 
			  AND (wpvq_posts.post_status='complete' OR wpvq_posts.post_status='confirmed') 
			  AND wpvq_postmeta.meta_key='_booking_start' 
			  ".$sql_AND."
			  ORDER BY wpvq_postmeta.meta_value ASC";
	//echo "<div>sql_a:  $sql_a</div>";
	$posts = $wpdb->get_results($sql_a);
	
	$output = '
	<div class="wrap">
		<h2>Eyelash Club Tipping Report</h2>
	</div>
	
	<div>
		<form action="" method="post">
			<select name="sel_day" onchange="this.form.submit()">
				<option value="null">Select a Day</option>
				<option value="-7days" '.$_7days_sel.'>Prev 7 Days</option>
				<option value="Yesterday" '.$yesterday_sel.'>Yesterday</option>
				<option value="Today" '.$today_sel.'>Today</option>
				<option value="Tomorrow" '.$tomorrow_sel.'>Tomorrow</option>
			</select>
		</form>
		<form action="" method="post">
			<input type="hidden" name="date_select" value="true">
			<input size="25" type="date" name="sel_day" value="'.$date_picker.'">
			<input type="submit" class="button button-primary tips" value="Select Day/Date">
		</form>
	</div>
	
	<div>
		<table class="widefat">
			<thead>
				<tr>
					<th></th>
					<th>ID</th>
					<th>Booked Product</th>
					<th>Client</th>
					<th>Tip Amount</th>
					<th>Start Date</th>
				</tr>
			</thead>
			<tbody>';
	
	foreach($posts as $post){
		$sql_b = "SELECT * FROM $_postmeta_ WHERE post_id=$post->ID";
		//echo "<div>sql_b:  $sql_b</div>";
		$postmeta = $wpdb->get_results($sql_b);
		
		foreach($postmeta as $data){
			if($data->meta_key === '_booking_product_id'){
				$_booking_product_id = $data->meta_value;
			}
			else if($data->meta_key === '_booking_resource_id'){
				$_booking_resource_id = $data->meta_value;
			}
			else if($data->meta_key === '_booking_customer_id'){
				$_booking_customer_id = $data->meta_value;
			}
			else if($data->meta_key === '_booking_start'){
				//$_booking_start = date("F j, Y, g:i a", $data->meta_value);
				$_booking_start = $data->meta_value;
				$bk_year = substr($_booking_start,0,4);
				$bk_month = substr($_booking_start,4,2);
				$bk_day = substr($_booking_start,6,2);
				$bk_hour = substr($_booking_start,8,2);
				$bk_min = substr($_booking_start,10,2);
				
				$datetime_convert = $bk_year.'-'.$bk_month.'-'.$bk_day.' '.$bk_hour.':'.$bk_min;
			
				$_booking_start = date("F j, Y, g:i a", strtotime($datetime_convert));
			}
			else if($data->meta_key === '_booking_end'){
				$_booking_end = $data->meta_value;
			}
			else if($data->meta_key === '_ec_appt_tip'){
				$_ec_appt_tip = $data->meta_value;
			}
			
			if(!empty($_ec_appt_tip)){
				$_tip_amount = $_ec_appt_tip;
			}
			else{
				$_tip_amount = '';
			}
		}
		
		// get the booking product name
		$sql_c = "SELECT * FROM $_posts_ WHERE ID=$_booking_product_id";
		//echo "<div>sql_c:  $sql_c</div>";
		$post_booking_product = $wpdb->get_results($sql_c);
		foreach($post_booking_product as $post_data){
			$booking_product = $post_data->post_title;
		}
		//$booking_product = $post_booking_product->post_title;
		//echo "<div>booking_product:  $booking_product</div>";
				
		// get the booking resource name
		$sql_d = "SELECT * FROM $_posts_ WHERE ID=$_booking_resource_id";
		//echo "<div>sql_d:  $sql_d</div>";
		$post_booking_resource = $wpdb->get_results($sql_d);
		foreach($post_booking_resource as $post_data){
			$booking_resource = $post_data->post_title;
		}
		//$booking_resource = $post_booking_resource->post_title;
		
		$booked_product = $booking_product.' ('.$booking_resource.')';
		
		$sql_e = "SELECT * FROM $_usermeta_ 
				  WHERE user_id='$_booking_customer_id'";
		$cust_meta = $wpdb->get_results($sql_e);
		
		foreach($cust_meta as $cust){
			if($cust->meta_key === 'first_name'){
				$cust_first_name = $cust->meta_value;
			}
			else if($cust->meta_key === 'last_name'){
				$cust_last_name = $cust->meta_value;
			}
			else if($cust->meta_key === 'billing_email'){
				$cust_billing_email = $cust->meta_value;
			}
		}
		if(!empty($cust_first_name) && !empty($cust_last_name)){
			$cust_name = $cust_first_name.' '.$cust_last_name;
		}
		else{
			$cust_name = $cust_billing_email;
		}
		
		
		
		$output .= '
		<tr>
			<td></td>
			<td><a href="post.php?post='.$post->ID.'&action=edit">Booking #'.$post->ID.'</a></td>
			<td>'.$booked_product.'</td>
			<td>'.$cust_name.'</td>
			<td>'.$_tip_amount.'</td>
			<td>'.$_booking_start.'</td>
		</tr>
		';
		
		unset($_ec_appt_tip);
		unset($cust_first_name);
		unset($cust_last_name);
		unset($cust_name);
		unset($cust_billing_email);
	}
	$output .= '
			</tbody>
		</table>
	</div>';
	
	echo $output;
}


add_action('add_meta_boxes','meta_box_wp_booking');

function meta_box_wp_booking(){
	add_meta_box('ec_assign_tech','Assign Technician','assign_tech_callback','wc_booking','normal');
}

function assign_tech_callback($post){
	global $wpdb;
	wp_verify_nonce( plugin_basename(__FILE__), 'assign_tech_nonce');
	
	$post_id = $post->ID;
	
	$postmeta = get_post_meta($post_id,'_ec_tech_id');
	$_ec_tech_id = $postmeta[0];
	
	$booking_meta = get_post_meta($post_id,'_booking_resource_id');
	$resource_id = $booking_meta[0];
	//echo "<div>resource_id: $resource_id</div>";
	
	if(empty($_ec_tech_id)){
		update_post_meta($post_id,'_ec_tech_id',$resource_id);
		
		$postmeta = get_post_meta($post_id,'_ec_tech_id');
		$_ec_tech_id = $postmeta[0];
	}
	
	$_posts_ = $wpdb->prefix.'posts';
	$_postmeta_ = $wpdb->prefix.'postmeta';
	$sql_a = "SELECT * FROM $_posts_ 
			  WHERE post_type='bookable_resource'
			  AND post_status='publish' 
			  ORDER BY post_date DESC";
	$posts = $wpdb->get_results($sql_a);
		
	$output = '
	<label for="tech_id">Technician:  </label>
		<select name="tech_id">
			<option value="">Select a Technician</option>';
	foreach($posts as $post){
		if($post->ID === $_ec_tech_id){
			$selected = 'selected';
		}
		else{
			$selected = '';
		}
		$output .= '
			<option value="'.$post->ID.'" '.$selected.'>'.$post->post_title.'</option>';
	}
	$output .= '
		</select>';
	echo $output;
}

add_action('add_meta_boxes','meta_box_ec_appt_details');
add_action('add_meta_boxes','meta_box_ec_cust_intake_form');
add_action('add_meta_boxes','meta_box_ec_cust_send_sms');
add_action('add_meta_boxes','meta_box_ec_cust_user_notes');

function meta_box_ec_appt_details(){
	add_meta_box('ec_appt_details','Appointment Details','ec_appt_details_callback','wc_booking','normal');
}

function meta_box_ec_cust_send_sms() {
	add_meta_box('ec_send_sms','Send SMS','ec_send_sms_callback','wc_booking','normal');
}

function meta_box_ec_cust_user_notes() {
	add_meta_box('ec_booking_notes','Booking Notes','ec_custom_user_notes_callback','wc_booking','normal');
}

function meta_box_ec_cust_intake_form() {
	add_meta_box('ec_booking_intake_form','Client Form','ec_custom_intake_form_callback','wc_booking','normal');
}

function ec_appt_details_callback($post){
	global $wpdb;
	
	$eyelash_club_table = $wpdb->prefix.'eyelash_club';
	$_posts_ = $wpdb->prefix.'posts';
	$_postmeta_ = $wpdb->prefix.'postmeta';
	$_users_ = $wpdb->prefix.'users';
	$_usermeta_ = $wpdb->prefix.'usermeta';
	$_ec_appt_tips_ = $wpdb->prefix.'ec_appt_tips';
	
	$sql_a = "SELECT * FROM $_posts_ 
				  WHERE ID='$_GET[post]'";
		$posts = $wpdb->get_results($sql_a);
		//print_r($posts);
		
		$sql_m = "SELECT * FROM $_postmeta_ 
				  WHERE post_id='$_GET[post]'";
		$postmeta = $wpdb->get_results($sql_m);
		//print_r($postmeta);
		$_booking_rating_val = '';
		foreach($postmeta as $data){
			if($data->meta_key === '_booking_product_id'){
				$_booking_product_id = $data->meta_value;
			}
			else if($data->meta_key === '_booking_resource_id'){
				$_booking_resource_id = $data->meta_value;
			}
			else if($data->meta_key === '_booking_start'){
				//$_booking_start = date("F j, Y, g:i a", $data->meta_value);
				$_booking_start = $data->meta_value;
				$bk_year = substr($_booking_start,0,4);
				$bk_month = substr($_booking_start,4,2);
				$bk_day = substr($_booking_start,6,2);
				$bk_hour = substr($_booking_start,8,2);
				$bk_min = substr($_booking_start,10,2);
				
				$datetime_convert = $bk_year.'-'.$bk_month.'-'.$bk_day.' '.$bk_hour.':'.$bk_min;
				
				$_booking_start = date("F j, Y, g:i a", strtotime($datetime_convert));
			}
			else if($data->meta_key === '_booking_end'){
				$_booking_end = $data->meta_value;
			}
			else if($data->meta_key === '_booking_customer_id'){
				$_booking_customer_id = $data->meta_value;
			}
            else if($data->meta_key === '_ec_appt_rating'){
				$_booking_rating_val = $data->meta_value;
			}
			else if($data->meta_key === '_ec_appt_tip'){
				$_booking_tip_val = $data->meta_value;
			}
		}
		if(!empty($_booking_tip_val)){
			//echo "<div>TIP AMOUNT: $_booking_tip_val</div>";
			$tip_posted = true;
		}
		else{
			//echo "<div>NO TIP YET</div>";
			$tip_posted = false;
		}
	
    // this flag enables/disables the rating selection
    $isRatingEnabled = false;
    
	$output = '
	<div class="row">
        <div class="col-auto">
            <h2>Tip Amount: </h2>    
        </div>
        <div class="col">
            <div class="input-group eyelashClubPluginCheckoutTipInputWrapperCls">
                <div class="input-group-prepend">
                    <span class="input-group-text">$</span>
                </div>
                <input id="tip_amount" name="tip_amount" type="number" value="'.$_booking_tip_val.'" step="0.01" class="form-control eyelashClubPluginCheckoutTipInputCls" aria-label="Amount (to the nearest dollar)" readonly>
            </div>
        </div>
	</div>
	<div>
		<h2>Appointment/Technician Rating</h2>
		<table class="widefat">
			<tr>
				<td style="line-height:4rem !important;">
					<form class="star-cb-form">
					  <fieldset id="booking_'.$posts[0]->ID.'">
						<div class="ratingBlockMouseCls"'.($isRatingEnabled != true ? ' style="display:block;"' : '').'></div>
						<span class="star-cb-group">
						  <input type="radio" id="rating-5" name="rating" value="5"'.($_booking_rating_val == '5' ? ' checked="checked"' : '').' /><label class="ratingLabelCls" for="rating-5">5</label>
						  <input type="radio" id="rating-4" name="rating" value="4"'.($_booking_rating_val == '4' ? ' checked="checked"' : '').' /><label class="ratingLabelCls" for="rating-4">4</label>
						  <input type="radio" id="rating-3" name="rating" value="3"'.($_booking_rating_val == '3' ? ' checked="checked"' : '').' /><label class="ratingLabelCls" for="rating-3">3</label>
						  <input type="radio" id="rating-2" name="rating" value="2"'.($_booking_rating_val == '2' ? ' checked="checked"' : '').' /><label class="ratingLabelCls" for="rating-2">2</label>
						  <input type="radio" id="rating-1" name="rating" value="1"'.($_booking_rating_val == '1' ? ' checked="checked"' : '').' /><label class="ratingLabelCls" for="rating-1">1</label>
						  <input type="radio" id="rating-0" name="rating" value="0" class="star-cb-clear"'.($_booking_rating_val == '' ? ' checked="checked"' : '').' /><label class="ratingLabelCls" for="rating-0" style="display:none;">0</label>
						</span>
					  </fieldset>
					</form>
				</td>
			</tr>
		</table>
	</div>';
	
	echo $output;
}

add_action('save_post','assign_tech_save');

function assign_tech_save( $post_id ){
	/*if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
		return;
	if(!wp_verify_nonce($_POST['tech_id'], plugin_basename(__FILE__)))
		return;
	if('wc_booking' === $_POST['post_type']){
		if(!current_user_can('edit_post',$post_id))
			return;
	}
	else{
		if(!current_user_can('edit_post',$post_id))
			return;
	}*/
	
	$tech_id = $_POST['tech_id'];
	update_post_meta($post_id,'_ec_tech_id',$tech_id);
}

add_action('add_meta_boxes','meta_box_ec_booking_status');

function meta_box_ec_booking_status(){
	add_meta_box('ec_check_in_out','Check In/Check Out','ec_check_in_out_callback','wc_booking','side');
}

function ec_check_in_out_callback($post){
	global $wpdb;
	$_ec_appt_status_ = $wpdb->prefix.'ec_appt_status';
	$current_user = wp_get_current_user();
	//print_r($current_user);
	$datetime = date('Y-m-d H:i:s');
	
	$_posts_ = $wpdb->prefix.'posts';
	$_postmeta_ = $wpdb->prefix.'postmeta';
	$sql_a = "SELECT * FROM $_posts_ 
			  WHERE post_type='bookable_resource'
			  AND post_status='publish' 
			  ORDER BY post_date DESC";
	$posts = $wpdb->get_results($sql_a);
	
	$post_id = $post->ID;
	
	// get the _ec_appt_check_in
	$sql_b = "SELECT * FROM $_postmeta_ 
			  WHERE post_id='$post_id'
			  AND meta_key='_ec_appt_status'";
	//echo "<div>sql_b:  $sql_b</div>";
	$postmeta = $wpdb->get_results($sql_b);
	//print_r($postmeta);
	
	
	$_ec_appt_status = $postmeta[0]->meta_value;
	
	if($_ec_appt_status === 'CHECKED OUT'){
		// get the _ec_appt_check_in
		$sql_i = "SELECT * FROM $_postmeta_ 
				  WHERE post_id='$post_id'
				  AND meta_key='_ec_appt_check_in'";
		$postmeta = $wpdb->get_results($sql_i);
		$_ec_appt_check_in = $postmeta[0]->meta_value;
		
		// get the _ec_appt_check_out
		$sql_o = "SELECT * FROM $_postmeta_ 
				  WHERE post_id='$post_id'
				  AND meta_key='_ec_appt_check_out'";
		$postmeta = $wpdb->get_results($sql_o);
		$_ec_appt_check_out = $postmeta[0]->meta_value;
		
		$output = '
		<div>
			<h2>Checked In at: <strong>'.$_ec_appt_check_in.'</strong></h2>
			<h2>Checked Out at: <strong>'.$_ec_appt_check_out.'</strong></h2>
			<input type="hidden" name="check_in" value="1">
			<input type="submit" class="button button-primary button-large" name="save" value="Check In">
		</div>';
	}
	else if($_ec_appt_status === 'CHECKED IN'){
		// get the _ec_appt_check_in
		$sql = "SELECT * FROM $_postmeta_ 
				  WHERE post_id='$post_id'
				  AND meta_key='_ec_appt_check_in'";
		$postmeta = $wpdb->get_results($sql);
		$_ec_appt_check_in = $postmeta[0]->meta_value;
		
		$post_id = $post->ID;
		
		//<input type="submit" class="button button-primary button-large" name="save" value="Check Out">
		
		$output = '
		<div>
			<h2>Checked In at: <strong>'.$_ec_appt_check_in.'</strong></h2>
			<input type="hidden" name="check_out" value="1">
			<input type="button" class="button button-primary button-large" onclick="location.href=\'https://eyelashclub.com/wp-admin/admin.php?page=eyelash-club-admin&type=check_out&post='.$post_id.'\';" value="Check Out" />
		</div>';
	}
	else{
		$output = '
		<div>
			<input type="hidden" name="ec_status_save" value="true">
			<input type="hidden" name="check_in" value="1">
			<input type="submit" name="save" class="button button-primary button-large" value="Check In">
		</div>';
	}
	echo $output;
}

add_action('post_updated','ec_check_in_out_save');

function check_range($current_date, $end_date, $sec_within){
	// Convert to timestamp
	$start_ts = strtotime($current_date);
	$end_ts = strtotime($end_date);
	$sec_pass = $start_ts - $end_ts;
	
	if($sec_pass > $sec_within){
		return true;
	}
	else{
		return false;
	}
}

function ec_check_in_out_save( $post_id ){
	global $wpdb;
	$_ec_appt_status_ = $wpdb->prefix.'ec_appt_status';
	$current_user = wp_get_current_user();
	$datetime = date('Y-m-d H:i:s');
	
	
	
	// check if this has been inserted
	$sql = "SELECT * FROM $_ec_appt_status_ 
			  WHERE booking_id='$post_id' 
			  ORDER BY id DESC LIMIT 1";
	$results = $wpdb->get_results($sql);
	$insert_datetime = $results[0]->datetime;
	
	$store_arr['booking_id'] = $post_id;
	$store_arr['user_id'] = $current_user->ID;
	$store_arr['datetime'] = $datetime;
	
	
	if($_POST['save'] === 'Check In'){
		if(check_range($datetime,$insert_datetime,2)){
			$check_in = date("Y-m-d g:i a");
			update_post_meta($post_id,'_ec_appt_check_in',$check_in);
			update_post_meta($post_id,'_ec_appt_status','CHECKED IN');
			$store_arr['status'] = 'CHECKED IN';
			$wpdb->insert($_ec_appt_status_,$store_arr);
		}
	}
	/*else if($_POST['save'] === 'Check Out'){
		$check_out = date("Y-m-d g:i a");
		update_post_meta($post_id,'_ec_appt_check_out',$check_out);
		update_post_meta($post_id,'_ec_appt_status','CHECKED OUT');
		$store_arr['status'] = 'CHECKED OUT';
		$wpdb->insert($_ec_appt_status_,$store_arr);
	}*/
}

add_action('wp_head','ec_tipping_callback');

function ec_tipping_callback(){
	if(is_page( 'appointment' )){
		echo "<div><br><br><br>this is where we tip!</div>";
	}
}

// plugin store post rating
function plugin_save_post_rating_to_post_meta() {
    $ajaxResponse = 'error saving rating';
    if($_POST['id'] != '' && $_POST['rating']) {
        if(add_post_meta($_POST['id'], '_ec_appt_rating', $_POST['rating'], true))
            $ajaxResponse = 'rating saved';
        else
            $ajaxResponse = 'error saving rating';
    }
    echo $ajaxResponse;
    wp_die();
}
add_action('wp_ajax_plugin_add_rating_to_post_meta', 'plugin_save_post_rating_to_post_meta');

function plugin_styles(){
	wp_register_style('EyelashClubPluginStyles', plugins_url('/style.css',__FILE__));
    wp_register_style('EyelashClubPluginStylesBootstrap', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css');
	wp_enqueue_style('EyelashClubPluginStyles');
    wp_enqueue_style('EyelashClubPluginStylesBootstrap');
}
add_action('admin_enqueue_scripts', 'plugin_styles');


function plugin_scripts(){
    wp_enqueue_script('EyelashClubPluginScriptsJqueryCaret', plugins_url('/jquery.caret.js', __FILE__), array('jquery'), false, true);
	wp_enqueue_script('EyelashClubPluginScripts', plugins_url('/scripts.js', __FILE__), array('jquery'), false, true);
	wp_enqueue_script('EyelashClubStripeScript', 'https://checkout.stripe.com/checkout.js', array('jquery'), false, true);
}
add_action('admin_enqueue_scripts', 'plugin_scripts');

/* REFERRED BY USER: START */

function ec_users_referred_by($profileuser) {
	// appears on profile page
	$refByIsSet = false;
	$output = generateReferredByCode($profileuser, $refByIsSet);

	$user_meta = get_userdata($profileuser->ID);
	$user_roles = $user_meta->roles;

    if($refByIsSet) {
    	// if set, only admins/managers/shop managers/technicians can edit their own profile
    	$userCanEdit = false;
		for($i = 0; $i < count($user_roles); $i++) {
			if($user_roles[$i] == 'administrator' || $user_roles[$i] == 'manager' || $user_roles[$i] == 'shop_manager' || $user_roles[$i] == 'technician') {
				$userCanEdit = true;
				break;
			}
		}
		if($userCanEdit) {
			echo $output;
		}
		else {
			// can only view it
			$output = '<h2>Referred by</h2>';
			$output .= '<table class="form-table">';
			$output .= '<tbody><tr>';
		    $output .= '<th scope="row">User</th>';
		    $output .= '<td>';
		    $ref_by_saved = get_user_meta($profileuser->ID, '_ec_referred_by_user_id', true);
		    $user_info = get_userdata($ref_by_saved);
			$ref_by_saved_name = get_user_meta($profileuser->ID, '_ec_referred_by_user_full_name', true);
			$output .= '<input type="text" class="regular-text" value="'.$ref_by_saved_name.' (#'.$ref_by_saved.' &ndash; '.$user_info->user_email.')" readonly>';
		    $output .= '</td></tr>';
			$output .= '</tbody></table>';
			echo $output;
		}
    }
    else {
    	// if not set, show (for every user's profile page)
		echo $output;
    }

    // only show revisions to
    $showRevisions = false;
    for($i = 0; $i < count($user_roles); $i++) {
		if($user_roles[$i] == 'administrator' || $user_roles[$i] == 'manager' || $user_roles[$i] == 'shop_manager' || $user_roles[$i] == 'technician') {
			$showRevisions = true;
			break;
		}
	}
	if($showRevisions)
    	echo generateReferredByRevisions($profileuser->ID);
}

function ec_users_referred_by_other_users($profileuser) {
	// apears on other users profile
	$user_meta = get_userdata(get_current_user_id());
	$user_roles = $user_meta->roles;
	$isAdminUsr = false;
	for($i = 0; $i < count($user_roles); $i++) {
		if($user_roles[$i] == 'administrator' || $user_roles[$i] == 'manager' || $user_roles[$i] == 'shop_manager') {
			$isAdminUsr = true;
			break;
		}
	}
	// only show for admin and managers
	if($isAdminUsr) {
		$refByIsSet = false;
		$output = generateReferredByCode($profileuser, $refByIsSet);
		echo $output;
	}

	// only show revisions to
    $showRevisions = false;
    for($i = 0; $i < count($user_roles); $i++) {
		if($user_roles[$i] == 'administrator' || $user_roles[$i] == 'manager' || $user_roles[$i] == 'shop_manager' || $user_roles[$i] == 'technician') {
			$showRevisions = true;
			break;
		}
	}
	if($showRevisions)
    	echo generateReferredByRevisions($profileuser->ID);
}

function ec_users_referred_by_update_fields($user_id) {
	global $wpdb;
	if (!current_user_can('edit_user', $user_id)) {
        return false;
    }
    if(isset($_POST['_referred_by_user_id']) && isset($_POST['_referred_by_user_haschanged'])) {
    	if($_POST['_referred_by_user_id'] != '' && $_POST['_referred_by_user_haschanged'] == "true") {
    		update_user_meta($user_id, '_ec_referred_by_user_id', $_POST['_referred_by_user_id']);
    		update_user_meta($user_id, '_ec_referred_by_user_full_name', $_POST['_referred_by_user_name']);

    		// get customer/employee val
    		$refer_type = 'employee';
    		$user_meta = get_userdata($_POST['_referred_by_user_id']);
			$user_roles = $user_meta->roles;
			$userCanEdit = false;
			for($i = 0; $i < count($user_roles); $i++) {
				if($user_roles[$i] == 'customer') {
					$refer_type = 'customer';
					break;
				}
			}

    		// save to revisions
			$table_name = $wpdb->prefix . "eyelash_club_ref_by_revisions";
			$wpdb->insert( 
				$table_name, 
				array( 
					'timestamp' => current_time( 'mysql' ), 
					'user_id' => $user_id, 
					'ref_by_user_id' => $_POST['_referred_by_user_id'],
					'ref_by_user_name' => $_POST['_referred_by_user_name'],
					'refer_type' => $refer_type,
					'action_by_user_id' => get_current_user_id()
				) 
			);
    	}
    }
}

function ec_users_referred_by_other_users_update_fields($user_id) {
	global $wpdb;
	if (!current_user_can('edit_user', $user_id)) {
        return false;
    }
    if(isset($_POST['_referred_by_user_id']) && isset($_POST['_referred_by_user_haschanged'])) {
    	if($_POST['_referred_by_user_id'] != '' && $_POST['_referred_by_user_haschanged'] == "true") {
    		update_user_meta($user_id, '_ec_referred_by_user_id', $_POST['_referred_by_user_id']);
    		update_user_meta($user_id, '_ec_referred_by_user_full_name', $_POST['_referred_by_user_name']);

    		// get customer/employee val
    		$refer_type = 'employee';
    		$user_meta = get_userdata($_POST['_referred_by_user_id']);
			$user_roles = $user_meta->roles;
			$userCanEdit = false;
			for($i = 0; $i < count($user_roles); $i++) {
				if($user_roles[$i] == 'customer') {
					$refer_type = 'customer';
					break;
				}
			}

    		// save to revisions
			$table_name = $wpdb->prefix . "eyelash_club_ref_by_revisions";
			$wpdb->insert( 
				$table_name, 
				array( 
					'timestamp' => current_time( 'mysql' ), 
					'user_id' => $user_id, 
					'ref_by_user_id' => $_POST['_referred_by_user_id'],
					'ref_by_user_name' => $_POST['_referred_by_user_name'],
					'refer_type' => $refer_type,
					'action_by_user_id' => get_current_user_id()
				) 
			);
    	}
    }
}

function generateReferredByCode($profileuser, &$refByIsSet) {
	$refByIsSet = false;
	$default_value = '';
	$ref_by_saved = get_user_meta($profileuser->ID, '_ec_referred_by_user_id', true);
	$refUsrDetailsVis = 'display:none;';
	$refUsrDetailsName = '';
	$refUsrDetailsEmail = '';
	$refUsrDetailsId = '';
	if($ref_by_saved != '') {
		$refByIsSet = true;
		$user_info = get_userdata($ref_by_saved);
		$ref_by_saved_name = get_user_meta($profileuser->ID, '_ec_referred_by_user_full_name', true);
		$default_value = '<option selected="selected" value="'.$ref_by_saved.'">'.$ref_by_saved_name.' (#'.$ref_by_saved.' &ndash; '.$user_info->user_email.')</option>';
		$refUsrDetailsVis = '';
		$refUsrDetailsName = $ref_by_saved_name;
		$refUsrDetailsEmail = $user_info->user_email;
		$refUsrDetailsId = $ref_by_saved;
	}

	$output = '';
	$output .= '<h2>Referred by</h2>';
	$output .= '<table class="form-table">';
	$output .= '<tbody><tr>';
    $output .= '<th scope="row">Search user</th>';
    $output .= '<td>';
    $output .= '<select name="_referred_by_user_id" id="_referred_by_user_id" class="wc-customer-search" data-placeholder="User" data-allow_clear="true" style="width:100%;">'.$default_value.'</select><input type="hidden" id="_referred_by_user_haschanged" name="_referred_by_user_haschanged" value="false">';
    $output .= '</td></tr>';
    // existing data
    $output .= '<tr><th></th><td>';
    $output .= '<div id="referredByUserDetails" style="'.$refUsrDetailsVis.'">';
    $output .= '<label for="_referred_by_user_name">Full name:</label><br><input type="text" class="regular-text" name="_referred_by_user_name" id="_referred_by_user_name" value="'.$refUsrDetailsName.'"><br><br>';
    $output .= '</div></td></tr>';
	$output .= '</tbody></table>';
    // javascript
    $output .= '<script type="text/javascript">';
    $output .= 'jQuery(document).ready(function() {';
    $output .= 'jQuery("#_referred_by_user_id").on("select2:unselect", function (e) {';
    $output .= 'jQuery("#_referred_by_user_name").val("");';
    $output .= '});';
    $output .= 'jQuery("#_referred_by_user_id").on("select2:select", function (e) {';
 	$output .= 'jQuery("#_referred_by_user_haschanged").val("true");';
 	$output .= 'jQuery("#referredByUserDetails").show();';
 	$output .= 'var selData = e.params.data.text.trim();';
 	$output .= 'var name = "";';
 	$output .= 'var email = "";';
 	$output .= 'if(selData.charAt(0) != "(")';
 	$output .= 'name = selData.substring(0,selData.indexOf("(")-1);';
 	$output .= 'email = selData.substring(selData.lastIndexOf(" ")+1,selData.length-1);';
 	$output .= 'jQuery("#_referred_by_user_name").val(name);';
	$output .= '});';
    $output .= '});';
    $output .= '</script>';

    return $output;
}

function generateReferredByRevisions($user_id) {
	global $wpdb;

	$output = '<table class="form-table">';
	$output .= '<tbody><tr>';
    $output .= '<th scope="row">Revisions</th>';
    $output .= '<td><div style="width:100%;max-height:300px;overflow-y: auto;">';

    $output .= '<table style="width:100%;"><tr><td><strong>Datetime</strong></td><td style="text-align: center;"><strong>User</strong></td><td style="text-align: center;"><strong>Referred by</strong></td><td style="text-align: center;"><strong>Set by</strong></td></tr>';

    $revs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}eyelash_club_ref_by_revisions WHERE user_id = $user_id");
    $c = 0;
	foreach ( $revs as $rev ) {
		// target user
		$user_info = get_userdata($rev->user_id);
		$userStr = '#'.$rev->user_id.'<br>';
		if($user_info->first_name != '') {
			$userStr .= $user_info->first_name.' '.$user_info->last_name.'<br>';
		}
		$userStr .= $user_info->user_email;

		// ref by user
		$user_ref_by_info = get_userdata($rev->ref_by_user_id);
		$refByUserName = $rev->ref_by_user_name;
		$refByUserStr = '#'.$rev->ref_by_user_id.'<br>';
		if($refByUserName != '') {
			$refByUserStr .= $refByUserName.'<br>';
		}
		$refByUserStr .= $user_ref_by_info->user_email.'<br>';
		$refByUserStr .= $rev->refer_type.'<br>';

		// action by user
		$user_info_set_by = get_userdata($rev->action_by_user_id);
		$actionByUserStr = '#'.$rev->action_by_user_id.'<br>';
		if($user_info_set_by->first_name != '') {
			$actionByUserStr .= $user_info_set_by->first_name.' '.$user_info_set_by->last_name.'<br>';
		}
		$actionByUserStr .= $user_info_set_by->user_email;

		$output .= '<tr>';
		$output .= '<td>'.$rev->timestamp.'</td>';
		$output .= '<td style="text-align: center;">'.$userStr.'</td>';
		$output .= '<td style="text-align: center;">'.$refByUserStr.'</td>';
		$output .= '<td style="text-align: center;">'.$actionByUserStr.'</td>';
		$output .= '</tr>';
		$c++;
	}

    $output .= '</table>';

    $output .= '</div></td></tr>';
	$output .= '</tbody></table>';
	if($c == 0)
		$output = '';

	return $output;
}

function install_ref_by_revisions() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . "eyelash_club_ref_by_revisions"; 
	$sql = "CREATE TABLE $table_name (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
	  user_id mediumint(9) NOT NULL,
	  ref_by_user_id mediumint(9) NOT NULL,
	  ref_by_user_name varchar(30) DEFAULT '' NOT NULL,
	  refer_type varchar(20) DEFAULT '' NOT NULL,
	  action_by_user_id mediumint(9) NOT NULL,
	  PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}

function uninstall_ref_by_revisions(){
	global $wpdb;
	
	// drop the table
	$table_name = $wpdb->prefix . "eyelash_club_ref_by_revisions"; 
	
	$wpdb->query("DROP TABLE IF EXISTS $table_name");
}

register_activation_hook( __FILE__, 'install_ref_by_revisions');
register_deactivation_hook(__FILE__, 'uninstall_ref_by_revisions');
// appears on user-profile page
add_action('personal_options_update', 'ec_users_referred_by_update_fields');
add_action('show_user_profile', 'ec_users_referred_by', 10, 1);
// appears on other users' profile
add_action('edit_user_profile_update', 'ec_users_referred_by_other_users_update_fields');
add_action('edit_user_profile', 'ec_users_referred_by_other_users', 10, 1);

/* REFERRED BY USER: END */

/* USER NOTES: START */

function ec_user_notes($profileuser) {
	$nType = 'user';
	$nBookingId = '';

	$output = generateCustomUserNote($profileuser->ID, $nType, $nBookingId, false);

	$user_meta = get_userdata(get_current_user_id());
	$user_roles = $user_meta->roles;
	$showNotesFeature = false;

	for($i = 0; $i < count($user_roles); $i++) {
		if($user_roles[$i] == 'administrator' || $user_roles[$i] == 'manager' || $user_roles[$i] == 'shop_manager' || $user_roles[$i] == 'technician') {
			$showNotesFeature = true;
			break;
		}
	}

	if($showNotesFeature)
		echo $output;
}

function generateCustomUserNote($user_id, $nType, $nBookingId, $hideTitle) {
	global $wpdb;

	$output = '';
	if(!$hideTitle)
		$output .= '<h2>User notes</h2>';
	$output .= '<table class="form-table">';
	$output .= '<tbody><tr>';
    $output .= '<th scope="row">Add new note</th>';
    $output .= '<td style="text-align: right;">';
    $output .= '<textarea id="customNotesTA" name="customNotesTA" rows="10" style="width:100%;"></textarea>';
    $output .= '<br><button id="customNotesAddBtn" type="button" class="button">Save Note</button>';

    $output .= '<style>#tiptip_content, .chart-tooltip, .wc_error_tip { max-width: 300px !important; text-align: left !important; }</style>';

    // javascript
    $output .= '<script type="text/javascript">';
    $output .= 'jQuery(document).ready(function() {';
    
    $output .= 'jQuery("#customNotesAddBtn").on("click", function (e) {';
    
	$output .= "if(jQuery('#customNotesTA').val() == '') return;";

    $output .= 'jQuery("#customNotesAddBtn").prop("disabled",true);';
    $output .= 'var data = {';
	$output .= "'action': 'ec_add_user_custom_note',";
	$output .= "'ec_user_notes_user_id': '".$user_id."',";
	$output .= "'ec_user_notes_booking_id': '".$nBookingId."',";
	$output .= "'ec_user_notes_type': '".$nType."',";
	$output .= "'ec_user_notes_text': jQuery('#customNotesTA').val()";
	$output .= '};';

	$output .= 'jQuery.post(ajaxurl, data, function(response) {';
	$output .= 'console.log(response);';
	$output .= 'jQuery("#customNotesAddBtn").prop("disabled",false);';
	$output .= 'jQuery("#customNotesTA").val("");';
	$output .= 'jQuery("#ecCustomUserNotesTable tr:last").after(response);';
	$output .= '});';

	$output .= '});';

    $output .= '});';
    $output .= '</script>';

    $output .= '</td></tr>';
    $output .= '<tr>';
    $output .= '<th scope="row">All notes</th>';
    $output .= '<td><div style="width:100%;max-height:300px;overflow-y: auto;">';

    $output .= '<table id="ecCustomUserNotesTable" style="width:100%;"><tr><td><strong>Author ID</strong></td><td><strong>User ID</strong></td><td><strong>Note type</strong></td><td><strong>Booking ID</strong></td><td><strong>Created at</strong></td><td><strong>Note</strong></td></tr>';

    $u = $user_id;

    $notes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}eyelash_club_user_custom_notes WHERE user_id = $u");
    
    foreach ( $notes as $note ) {
    	$bIdStr = '-';
    	$uid = ec_getUniqueId(8);
    	if($note->booking_id != '0')
    		$bIdStr = '#'.$note->booking_id;
		 $output .= '<tr><td>#'.$note->author_id.'</td><td>#'.$note->user_id.'</td><td>'.$note->type.'</td><td>'.$bIdStr.'</td><td>'.$note->timestamp.'</td><td><a id="'.$uid.'" href="#">View</a><script>jQuery(document).ready(function() { jQuery("#'.$uid.'").tipTip({maxWidth: "auto", content: "'.trim(preg_replace('/\s+/', ' ', nl2br($note->note))).'"}); });</script></td></tr>';
	}
    
    $output .= '</table></div></td></tr>';
    $output .= '</tbody></table>';

    return $output;
}

function ec_user_notes_update($user_id) {

}

function install_user_custom_notes() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . "eyelash_club_user_custom_notes"; 
	$sql = "CREATE TABLE $table_name (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
	  user_id mediumint(9) NOT NULL,
	  booking_id mediumint(9) NOT NULL,
	  author_id mediumint(9) NOT NULL,
	  type varchar(10) DEFAULT '' NOT NULL,
	  note TEXT NOT NULL,
	  PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}

function uninstall_user_custom_notes(){
	global $wpdb;
	
	// drop the table
	$table_name = $wpdb->prefix . "eyelash_club_user_custom_notes"; 
	
	$wpdb->query("DROP TABLE IF EXISTS $table_name");
}

function ec_add_user_custom_note() {
	global $wpdb; // this is how you get access to the database

	$table_name = $wpdb->prefix . "eyelash_club_user_custom_notes";
	$timeNow = current_time( 'mysql' );
	$wpdb->insert( 
		$table_name, 
		array( 
			'timestamp' => $timeNow, 
			'user_id' => $_POST['ec_user_notes_user_id'], 
			'booking_id' => $_POST['ec_user_notes_booking_id'],
			'author_id' => get_current_user_id(),
			'type' => $_POST['ec_user_notes_type'],
			'note' => $_POST['ec_user_notes_text']
		) 
	);

	$bIdStr = '-';
	if($_POST['ec_user_notes_booking_id'] != '')
		$bIdStr = '#'.$_POST['ec_user_notes_booking_id'];
	$uid = ec_getUniqueId(8);
	echo '<tr><td>#'.get_current_user_id().'</td><td>#'.$_POST['ec_user_notes_user_id'].'</td><td>'.$_POST['ec_user_notes_type'].'</td><td>'.$bIdStr.'</td><td>'.$timeNow.'</td><td><a id="'.$uid.'" href="#">View</a><script>jQuery(document).ready(function() { jQuery("#'.$uid.'").tipTip({maxWidth: "auto", content: "'.trim(preg_replace('/\s+/', ' ', nl2br($_POST['ec_user_notes_text']))).'"}); });</script></td></tr>';

	wp_die(); // this is required to terminate immediately and return a proper response
}

function ec_custom_user_notes_callback($post) {
	$nType = 'booking';
	$nBookingId = $post->ID;

	$output = generateCustomUserNote($post->post_author, $nType, $nBookingId, true);

	$user_meta = get_userdata(get_current_user_id());
	$user_roles = $user_meta->roles;
	$showNotesFeature = false;

	for($i = 0; $i < count($user_roles); $i++) {
		if($user_roles[$i] == 'administrator' || $user_roles[$i] == 'manager' || $user_roles[$i] == 'shop_manager' || $user_roles[$i] == 'technician') {
			$showNotesFeature = true;
			break;
		}
	}

	if($showNotesFeature)
		echo $output;
}

register_activation_hook( __FILE__, 'install_user_custom_notes');
register_deactivation_hook(__FILE__, 'uninstall_user_custom_notes');
add_action( 'wp_ajax_ec_add_user_custom_note', 'ec_add_user_custom_note' );
//add_action('edit_user_profile_update', 'ec_user_notes_update');
add_action('edit_user_profile', 'ec_user_notes', 10, 1);

function ec_getUniqueId($length = 8 ) {
	$pool = '123456789';
	
	$crypto_rand_secure = function ( $min, $max ) {
		$range = $max - $min;
		if ( $range < 0 ) return $min; // not so random...
		$log    = log( $range, 2 );
		$bytes  = (int) ( $log / 8 ) + 1; // length in bytes
		$bits   = (int) $log + 1; // length in bits
		$filter = (int) ( 1 << $bits ) - 1; // set all lower bits to 1
		do {
			$rnd = hexdec( bin2hex( openssl_random_pseudo_bytes( $bytes ) ) );
			$rnd = $rnd & $filter; // discard irrelevant bits
		} while ( $rnd >= $range );
		return $min + $rnd;
	};

	$token = "";
	$max   = strlen( $pool );
	for ( $i = 0; $i < $length; $i++ ) {
		$token .= $pool[$crypto_rand_secure( 0, $max )];
	}
	return $token;
}

/* USER NOTES: END */

/* CLIENT FORM (WP-ADMIN): START */

function ec_custom_intake_form_callback($post) {
	global $wpdb;

	$output = '';
	
	$_intakeform_table = $wpdb->prefix."ec_forms";

	$u = $post->post_author;
	$sel_sql = "SELECT * FROM $_intakeform_table WHERE client_id=$u";
	$res = $wpdb->get_results($sel_sql);
	$rowcount = $wpdb->num_rows;
	if($wpdb->num_rows > 0) {
		$output .= '<input type="button" value="View" style="cursor:pointer;" onclick="window.open(\''.get_permalink(get_page_by_path('view-intake-form')).'?u='.$u.'\',\'_blank\');">&nbsp;<input type="button" value="Edit" style="cursor:pointer;" onclick="window.open(\''.get_permalink(get_page_by_path('edit-intake-form')).'?u='.$u.'\',\'_blank\');">';
	}
	else {
		$output .= '<input type="button" value="Fill Out Intake Form" style="cursor:pointer;" onclick="window.open(\''.get_permalink(get_page_by_path('fill-out-intake-form')).'?u='.$u.'\',\'_blank\');">';
	}

	echo $output;
}

function eyelash_club_admin_client_form() {
	global $wpdb;
	
	$_intakeform_table = $wpdb->prefix."ec_forms";

	$output = '<div class="wrap">
		<h2>Eyelash Club Client Form Data</h2>
	</div><br>';
	// minor style fix
	$output .= '<style>.custom-select { padding: .375rem 1.75rem .375rem .75rem !important; line-height: 1.5 !important; padding-top: .375rem !important; padding-bottom: .375rem !important; font-size: 75% !important; }</style>';
	
	$output .= '<div style="width:100%;background-color:#ffffff;padding:0.5rem;">';
	$output .= '<table id="clientFormDataTable" class="display compact" style="width:100%;">';
	// header
	$output .= '<thead><tr>';
    $output .= '<th>Client ID</th>';
    $output .= '<th>Email</th>';
    $output .= '<th>Name</th>';
    $output .= '<th>Phone</th>';
    $output .= '<th>Created At</th>';
    $output .= '<th>Last Modified At</th>';
    $output .= '<th></th>';
    $output .= '</tr></thead><tbody>';

    $sel_sql = "SELECT * FROM $_intakeform_table";
	$res = $wpdb->get_results($sel_sql);

	foreach ( $res as $row ) {
		$output .= '<tr>';
		$output .= '<td>'.$row->client_id.'</td>';
		$output .= '<td>'.$row->email.'</td>';
		$output .= '<td>'.$row->name.'</td>';
		$output .= '<td>'.$row->phone.'</td>';
		$output .= '<td>'.$row->created_at.'</td>';
		$output .= '<td>'.$row->last_modified_at.'</td>';
		
		$output .= '<td><input type="button" value="View" style="cursor:pointer;" onclick="window.open(\''.get_permalink(get_page_by_path('view-intake-form')).'?u='.$row->client_id.'\',\'_blank\');">&nbsp;<input type="button" value="Edit" style="cursor:pointer;" onclick="window.open(\''.get_permalink(get_page_by_path('edit-intake-form')).'?u='.$row->client_id.'\',\'_blank\');"></td>';
		$output .= '</tr>';
	}

    $output .= '</tbody></table></div>';

    // javascript
    $output .= '<script>jQuery(document).ready(function($) {$("#clientFormDataTable").DataTable( { responsive: true, "order": [[ 4, "desc" ]] } );});</script>';

	echo $output;
}

function install_ec_intake_form() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . "ec_forms"; 

	$sql = "CREATE TABLE $table_name (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  client_id mediumint(9) NOT NULL,
	  last_modified_by mediumint(9) NOT NULL,
	  created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
	  last_modified_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
	  name varchar(200) DEFAULT '' NOT NULL,
	  address varchar(255) DEFAULT '' NOT NULL,
	  city varchar(75) DEFAULT '' NOT NULL,
	  state varchar(3) DEFAULT '' NOT NULL,
	  zip varchar(20) DEFAULT '' NOT NULL,
	  phone varchar(30) DEFAULT '' NOT NULL,
	  email varchar(200) DEFAULT '' NOT NULL,
	  contact_how varchar(30) DEFAULT '' NOT NULL,
	  how_did_you_hear varchar(200) DEFAULT '' NOT NULL,
	  health_history TEXT NOT NULL,
	  allergic_ac varchar(10) DEFAULT '' NOT NULL,
	  allergic_topical varchar(3) DEFAULT '' NOT NULL,
	  eye_disease varchar(3) DEFAULT '' NOT NULL,
	  current_medication TEXT NOT NULL,
	  prev_conditions TEXT NOT NULL,
	  other_health_conditions TEXT NOT NULL,
	  hair_question_answers TEXT NOT NULL,
	  sleep_side varchar(10) DEFAULT '' NOT NULL,
	  hair_growth varchar(10) DEFAULT '' NOT NULL,
	  other_info TEXT NOT NULL,
	  PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}

function uninstall_ec_intake_form(){
	global $wpdb;
	
	// drop the table
	$table_name = $wpdb->prefix . "ec_forms"; 
	
	$wpdb->query("DROP TABLE IF EXISTS $table_name");
}

register_activation_hook( __FILE__, 'install_ec_intake_form');
register_deactivation_hook(__FILE__, 'uninstall_ec_intake_form');

/* CLIENT FORM (WP-ADMIN): END */

/* CLIENT FORM (FRONT-END): START */

function ec_intake_form_control_shortcode() {
	if(is_user_logged_in()) {
		global $wpdb;
		$_intakeform_table = $wpdb->prefix."ec_forms";

		$output = '';

		$formDataExists = false;
		$formWasJustSubmitted = false;
		$queryType = '';
		$isAdminUsr = false;
		$isReadOnly = false;

		// check if user is admin
		$user_meta = get_userdata(get_current_user_id());
		$user_roles = $user_meta->roles;
		for($i = 0; $i < count($user_roles); $i++) {
			if($user_roles[$i] == 'administrator') {
				$isAdminUsr = true;
				break;
			}
		}

		// check if data already exists in db
		$u = get_current_user_id();
		$sel_sql = "SELECT * FROM $_intakeform_table WHERE client_id=$u";
		$res = $wpdb->get_results($sel_sql);
		$rowcount = $wpdb->num_rows;
		if($wpdb->num_rows > 0)
			$formDataExists = true;

		// form was just submitted
		if(isset($_POST['ec_intake_form_submitted'])) {
			if($_POST['ec_intake_form_submitted'] == 'true') {
				// insert/update and display 'thank you message'
				$formWasJustSubmitted = true;
				$contactHowStr = '';
				$prevCondStr = '';
				if($_POST['ec_intake_form_howtocontact'] != '')
					$contactHowStr = implode("|",$_POST['ec_intake_form_howtocontact']);
				if($_POST['ec_intake_form_prev_conditions'] != '')
					$prevCondStr = implode("|",$_POST['ec_intake_form_prev_conditions']);
				if(!$formDataExists) {
					$queryType = 'insert';
					$output .= '<p>Thanks for filling out our form!</p>';
					$wpdb->insert( 
						$_intakeform_table, 
						array(
							'client_id' => get_current_user_id(),
							'last_modified_by' => get_current_user_id(),
							'created_at' => current_time( 'mysql' ),
							'last_modified_at' => current_time( 'mysql' ),
							'name' => $_POST['ec_intake_form_name'],
							'address' => $_POST['ec_intake_form_address'],
							'city' => $_POST['ec_intake_form_city'],
							'state' => $_POST['ec_intake_form_state'],
							'zip' => $_POST['ec_intake_form_zip'],
							'phone' => $_POST['ec_intake_form_phone'],
							'email' => $_POST['ec_intake_form_email'],
							'contact_how' => $contactHowStr,
							'how_did_you_hear' => $_POST['ec_intake_form_howdidyouhear'],
							'health_history' => $_POST['ec_intake_form_health_history'],
							'allergic_ac' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_allergic_ac_rad'),
							'allergic_topical' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_reactiontt_rad'),
							'eye_disease' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_eyed_rad'),
							'current_medication' => $_POST['ec_intake_form_current_meds'],
							'prev_conditions' => $prevCondStr,
							'other_health_conditions' => $_POST['ec_intake_form_other_hc'],
							'hair_question_answers' => ec_intakeForm_generateHairGrowthJsonForDb(),
							'sleep_side' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_sleepside_rad'),
							'hair_growth' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_hairg_rad'),
							'other_info' => $_POST['ec_intake_form_anything_else']
						) 
					);
				}
				else {
					$queryType = 'update';
					$output .= '<p>Thanks for updating our form!</p>';
					$wpdb->update( 
						$_intakeform_table,
						array( 
							'last_modified_by' => get_current_user_id(),
							'last_modified_at' => current_time( 'mysql' ),
							'name' => $_POST['ec_intake_form_name'],
							'address' => $_POST['ec_intake_form_address'],
							'city' => $_POST['ec_intake_form_city'],
							'state' => $_POST['ec_intake_form_state'],
							'zip' => $_POST['ec_intake_form_zip'],
							'phone' => $_POST['ec_intake_form_phone'],
							'email' => $_POST['ec_intake_form_email'],
							'contact_how' => $contactHowStr,
							'how_did_you_hear' => $_POST['ec_intake_form_howdidyouhear'],
							'health_history' => $_POST['ec_intake_form_health_history'],
							'allergic_ac' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_allergic_ac_rad'),
							'allergic_topical' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_reactiontt_rad'),
							'eye_disease' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_eyed_rad'),
							'current_medication' => $_POST['ec_intake_form_current_meds'],
							'prev_conditions' => $prevCondStr,
							'other_health_conditions' => $_POST['ec_intake_form_other_hc'],
							'hair_question_answers' => ec_intakeForm_generateHairGrowthJsonForDb(),
							'sleep_side' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_sleepside_rad'),
							'hair_growth' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_hairg_rad'),
							'other_info' => $_POST['ec_intake_form_anything_else']
						), 
						array( 'client_id' => get_current_user_id() )
					);
				}
			}
		}

		// form data
		$stateArr = array(
			'AL' => 'Alabama',
			'AK' => 'Alaska',
			'AZ' => 'Arizona',
			'AR' => 'Arkansas',
			'CA' => 'California',
			'CO' => 'Colorado',
			'CT' => 'Connecticut',
			'DE' => 'Delaware',
			'DC' => 'District Of Columbia',
			'FL' => 'Florida',
			'GA' => 'Georgia',
			'HI' => 'Hawaii',
			'ID' => 'Idaho',
			'IL' => 'Illinois',
			'IN' => 'Indiana',
			'IA' => 'Iowa',
			'KS' => 'Kansas',
			'KY' => 'Kentucky',
			'LA' => 'Louisiana',
			'ME' => 'Maine',
			'MD' => 'Maryland',
			'MA' => 'Massachusetts',
			'MI' => 'Michigan',
			'MN' => 'Minnesota',
			'MS' => 'Mississippi',
			'MO' => 'Missouri',
			'MT' => 'Montana',
			'NE' => 'Nebraska',
			'NV' => 'Nevada',
			'NH' => 'New Hampshire',
			'NJ' => 'New Jersey',
			'NM' => 'New Mexico',
			'NY' => 'New York',
			'NC' => 'North Carolina',
			'ND' => 'North Dakota',
			'OH' => 'Ohio',
			'OK' => 'Oklahoma',
			'OR' => 'Oregon',
			'PA' => 'Pennsylvania',
			'RI' => 'Rhode Island',
			'SC' => 'South Carolina',
			'SD' => 'South Dakota',
			'TN' => 'Tennessee',
			'TX' => 'Texas',
			'UT' => 'Utah',
			'VT' => 'Vermont',
			'VA' => 'Virginia',
			'WA' => 'Washington',
			'WV' => 'West Virginia',
			'WI' => 'Wisconsin',
			'WY' => 'Wyoming',
		);

		$current_user = wp_get_current_user();

		$name = '';
		if($current_user->user_firstname != '' || $current_user->user_lastname != '')
			$name = $current_user->user_firstname.' '.$current_user->user_lastname;

		$meta_value = get_user_meta(get_current_user_id(), 'billing_address_1', true);
		$address = (($meta_value != '') ? $meta_value : '');
		$meta_value = get_user_meta(get_current_user_id(), 'billing_city', true);
		$city = (($meta_value != '') ? $meta_value : '');
		$meta_value = get_user_meta(get_current_user_id(), 'billing_state', true);
		$state =  (($meta_value != '') ? $meta_value : '');
		$meta_value = get_user_meta(get_current_user_id(), 'billing_postcode', true);
		$zip =  (($meta_value != '') ? $meta_value : '');
		$meta_value = get_user_meta(get_current_user_id(), 'billing_phone', true);
		$phone =  (($meta_value != '') ? $meta_value : '');

 		// this always exists
 		$email = $current_user->user_email;

		$howtocontact = '';
		$howdiduhear = '';
		$health_history = '';
		$allergic_ac = '';
		$reaction_tt = '';
		$eye_disease = '';
		$current_meds = '';
		$prev_conditions = '';
		$other_hc = '';
		$q_pregnurse = $q_pregnurse_details = $q_pregnurse_adverse = '';
		$q_contacts = $q_contacts_details = $q_contacts_adverse = '';
		$q_glasses = $q_glasses_details = $q_glasses_adverse = '';
		$q_retinacc = $q_retinacc_details = $q_retinacc_adverse = '';
		$q_tan = $q_tan_details = $q_tan_adverse = '';
		$q_facialt = $q_facialt_details = $q_facialt_adverse = '';
		$q_botox = $q_botox_details = $q_botox_adverse = '';
		$q_latisse = $q_latisse_details = $q_latisse_adverse = '';
		$side_sleepon = '';
		$hair_growth = '';
		$anything_else = '';
		$cname = '';
		$cemail = '';
		$today_date = date('m/d/Y');

		// if form was just submitted do another select
		if($formWasJustSubmitted) {
			$sel_sql = "SELECT * FROM $_intakeform_table WHERE client_id=$u";
			$res = $wpdb->get_results($sel_sql);
		}
		
		// set form values (if already existed or after submit)
		if($formWasJustSubmitted || $formDataExists) {
			foreach ( $res as $row ) {
				$name = $row->name;
				$address = $row->address;
				$city = $row->city;
				$state = $row->state;
				$zip = $row->zip;
				$phone = $row->phone;
				$email = $row->email;

				$howtocontact =  $row->contact_how;
				$howdiduhear = $row->how_did_you_hear;
				$health_history = $row->health_history;
				$allergic_ac = $row->allergic_ac;
				$reaction_tt = $row->allergic_topical;
				$eye_disease = $row->eye_disease;
				$current_meds = $row->current_medication;
				$prev_conditions = $row->prev_conditions;
				$other_hc = $row->other_health_conditions;

				$q_pregnurse = $q_pregnurse_details = $q_pregnurse_adverse = '';
				$q_contacts = $q_contacts_details = $q_contacts_adverse = '';
				$q_glasses = $q_glasses_details = $q_glasses_adverse = '';
				$q_retinacc = $q_retinacc_details = $q_retinacc_adverse = '';
				$q_tan = $q_tan_details = $q_tan_adverse = '';
				$q_facialt = $q_facialt_details = $q_facialt_adverse = '';
				$q_botox = $q_botox_details = $q_botox_adverse = '';
				$q_latisse = $q_latisse_details = $q_latisse_adverse = '';
				$q_obj = json_decode($row->hair_question_answers, true);
				foreach ($q_obj as $key => $jsons) { 
					foreach($jsons as $key => $value) {
				    	ec_intakeForm_setHairQuestionVarValues($q_pregnurse, $q_pregnurse_details, $q_pregnurse_adverse, 'pregnurse', $key, $value);
				    	ec_intakeForm_setHairQuestionVarValues($q_contacts, $q_contacts_details, $q_contacts_adverse, 'contacts', $key, $value);
				    	ec_intakeForm_setHairQuestionVarValues($q_glasses, $q_glasses_details, $q_glasses_adverse, 'glasses', $key, $value);
				    	ec_intakeForm_setHairQuestionVarValues($q_retinacc, $q_retinacc_details, $q_retinacc_adverse, 'retinacc', $key, $value);
				    	ec_intakeForm_setHairQuestionVarValues($q_tan, $q_tan_details, $q_tan_adverse, 'tan', $key, $value);
				    	ec_intakeForm_setHairQuestionVarValues($q_facialt, $q_facialt_details, $q_facialt_adverse, 'facialt', $key, $value);
				    	ec_intakeForm_setHairQuestionVarValues($q_botox, $q_botox_details, $q_botox_adverse, 'botox', $key, $value);
				    	ec_intakeForm_setHairQuestionVarValues($q_latisse, $q_latisse_details, $q_latisse_adverse, 'latisse', $key, $value);
					}
				}

				$side_sleepon = $row->sleep_side;
				$hair_growth = $row->hair_growth;
				$anything_else = $row->other_info;

				// show date when was modified
				$dt = new DateTime($row->last_modified_at);
				$date_only = $dt->format('m/d/Y');
				$today_date = $date_only;
			}
		}

		// small tweak for date: if admin, date is always today
		if($isAdminUsr)
			$today_date = date('m/d/Y');

		// readonly if: non-admin and was just submitted or form data existed
		if($formWasJustSubmitted || $formDataExists) {
			// read-only if not-admin and form data already exists
			if(!$isAdminUsr)
				$isReadOnly = true;
		}
		$output .= '<h3 class="widget-title">Intake Form</h3>';
		include( plugin_dir_path( __FILE__ ) . 'inc/intake-form.php');

	}
	else
		$output = 'Please log-in to your account.';	
	return $output;
}

function ec_intake_form_control_admin_create_shortcode() {
	if(is_user_logged_in()) {
		global $wpdb;
		$_intakeform_table = $wpdb->prefix."ec_forms";

		$output = '';

		$formDataExists = false;
		$formWasJustSubmitted = false;
		$queryType = '';
		$isAdminUsr = false;
		$isReadOnly = false;

		// check if user is admin
		$user_meta = get_userdata(get_current_user_id());
		$user_roles = $user_meta->roles;
		for($i = 0; $i < count($user_roles); $i++) {
			if($user_roles[$i] == 'administrator' || $user_roles[$i] == 'manager' || $user_roles[$i] == 'shop_manager' || $user_roles[$i] == 'technician') {
				$isAdminUsr = true;
				break;
			}
		}

		// if not admin, exit
		if(!$isAdminUsr) {
			$output = 'This user doesn\'t have permission to view this page.';
			return $output;
		}

		$userId = '';
		if(isset($_GET['u']))
			if($_GET['u'] != '')
				$userId = $_GET['u'];

		if($userId == '') {
			$output = 'Unable to view client form data (missing user id).';
			return $output;
		}

		// check if data already exists in db
		$sel_sql = "SELECT * FROM $_intakeform_table WHERE client_id=$userId";
		$res = $wpdb->get_results($sel_sql);
		$rowcount = $wpdb->num_rows;
		if($wpdb->num_rows > 0)
			$formDataExists = true;

		// form was just submitted
		if(isset($_POST['ec_intake_form_submitted'])) {
			if($_POST['ec_intake_form_submitted'] == 'true') {
				// insert/update and display 'thank you message'
				$formWasJustSubmitted = true;
				$contactHowStr = '';
				$prevCondStr = '';
				if($_POST['ec_intake_form_howtocontact'] != '')
					$contactHowStr = implode("|",$_POST['ec_intake_form_howtocontact']);
				if($_POST['ec_intake_form_prev_conditions'] != '')
					$prevCondStr = implode("|",$_POST['ec_intake_form_prev_conditions']);
				if(!$formDataExists) {
					$queryType = 'insert';
					$output .= '<p>Form submitted successfully!</p>';
					$wpdb->insert( 
						$_intakeform_table, 
						array(
							'client_id' => $userId,
							'last_modified_by' => get_current_user_id(),
							'created_at' => current_time( 'mysql' ),
							'last_modified_at' => current_time( 'mysql' ),
							'name' => $_POST['ec_intake_form_name'],
							'address' => $_POST['ec_intake_form_address'],
							'city' => $_POST['ec_intake_form_city'],
							'state' => $_POST['ec_intake_form_state'],
							'zip' => $_POST['ec_intake_form_zip'],
							'phone' => $_POST['ec_intake_form_phone'],
							'email' => $_POST['ec_intake_form_email'],
							'contact_how' => $contactHowStr,
							'how_did_you_hear' => $_POST['ec_intake_form_howdidyouhear'],
							'health_history' => $_POST['ec_intake_form_health_history'],
							'allergic_ac' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_allergic_ac_rad'),
							'allergic_topical' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_reactiontt_rad'),
							'eye_disease' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_eyed_rad'),
							'current_medication' => $_POST['ec_intake_form_current_meds'],
							'prev_conditions' => $prevCondStr,
							'other_health_conditions' => $_POST['ec_intake_form_other_hc'],
							'hair_question_answers' => ec_intakeForm_generateHairGrowthJsonForDb(),
							'sleep_side' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_sleepside_rad'),
							'hair_growth' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_hairg_rad'),
							'other_info' => $_POST['ec_intake_form_anything_else']
						) 
					);
				}
				else {
					$queryType = 'update';
					$output .= '<p>Form updated successfully!</p>';
					$wpdb->update( 
						$_intakeform_table,
						array( 
							'last_modified_by' => get_current_user_id(),
							'last_modified_at' => current_time( 'mysql' ),
							'name' => $_POST['ec_intake_form_name'],
							'address' => $_POST['ec_intake_form_address'],
							'city' => $_POST['ec_intake_form_city'],
							'state' => $_POST['ec_intake_form_state'],
							'zip' => $_POST['ec_intake_form_zip'],
							'phone' => $_POST['ec_intake_form_phone'],
							'email' => $_POST['ec_intake_form_email'],
							'contact_how' => $contactHowStr,
							'how_did_you_hear' => $_POST['ec_intake_form_howdidyouhear'],
							'health_history' => $_POST['ec_intake_form_health_history'],
							'allergic_ac' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_allergic_ac_rad'),
							'allergic_topical' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_reactiontt_rad'),
							'eye_disease' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_eyed_rad'),
							'current_medication' => $_POST['ec_intake_form_current_meds'],
							'prev_conditions' => $prevCondStr,
							'other_health_conditions' => $_POST['ec_intake_form_other_hc'],
							'hair_question_answers' => ec_intakeForm_generateHairGrowthJsonForDb(),
							'sleep_side' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_sleepside_rad'),
							'hair_growth' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_hairg_rad'),
							'other_info' => $_POST['ec_intake_form_anything_else']
						), 
						array( 'client_id' => $userId )
					);
				}
			}
		}

		// form data
		$stateArr = array(
			'AL' => 'Alabama',
			'AK' => 'Alaska',
			'AZ' => 'Arizona',
			'AR' => 'Arkansas',
			'CA' => 'California',
			'CO' => 'Colorado',
			'CT' => 'Connecticut',
			'DE' => 'Delaware',
			'DC' => 'District Of Columbia',
			'FL' => 'Florida',
			'GA' => 'Georgia',
			'HI' => 'Hawaii',
			'ID' => 'Idaho',
			'IL' => 'Illinois',
			'IN' => 'Indiana',
			'IA' => 'Iowa',
			'KS' => 'Kansas',
			'KY' => 'Kentucky',
			'LA' => 'Louisiana',
			'ME' => 'Maine',
			'MD' => 'Maryland',
			'MA' => 'Massachusetts',
			'MI' => 'Michigan',
			'MN' => 'Minnesota',
			'MS' => 'Mississippi',
			'MO' => 'Missouri',
			'MT' => 'Montana',
			'NE' => 'Nebraska',
			'NV' => 'Nevada',
			'NH' => 'New Hampshire',
			'NJ' => 'New Jersey',
			'NM' => 'New Mexico',
			'NY' => 'New York',
			'NC' => 'North Carolina',
			'ND' => 'North Dakota',
			'OH' => 'Ohio',
			'OK' => 'Oklahoma',
			'OR' => 'Oregon',
			'PA' => 'Pennsylvania',
			'RI' => 'Rhode Island',
			'SC' => 'South Carolina',
			'SD' => 'South Dakota',
			'TN' => 'Tennessee',
			'TX' => 'Texas',
			'UT' => 'Utah',
			'VT' => 'Vermont',
			'VA' => 'Virginia',
			'WA' => 'Washington',
			'WV' => 'West Virginia',
			'WI' => 'Wisconsin',
			'WY' => 'Wyoming',
		);

		$current_user = get_userdata($userId);

		$name = '';
		if($current_user->user_firstname != '' || $current_user->user_lastname != '')
			$name = $current_user->user_firstname.' '.$current_user->user_lastname;

		$meta_value = get_user_meta($userId, 'billing_address_1', true);
		$address = (($meta_value != '') ? $meta_value : '');
		$meta_value = get_user_meta($userId, 'billing_city', true);
		$city = (($meta_value != '') ? $meta_value : '');
		$meta_value = get_user_meta($userId, 'billing_state', true);
		$state =  (($meta_value != '') ? $meta_value : '');
		$meta_value = get_user_meta($userId, 'billing_postcode', true);
		$zip =  (($meta_value != '') ? $meta_value : '');
		$meta_value = get_user_meta($userId, 'billing_phone', true);
		$phone =  (($meta_value != '') ? $meta_value : '');

 		// this always exists
 		$email = $current_user->user_email;

		$howtocontact = '';
		$howdiduhear = '';
		$health_history = '';
		$allergic_ac = '';
		$reaction_tt = '';
		$eye_disease = '';
		$current_meds = '';
		$prev_conditions = '';
		$other_hc = '';
		$q_pregnurse = $q_pregnurse_details = $q_pregnurse_adverse = '';
		$q_contacts = $q_contacts_details = $q_contacts_adverse = '';
		$q_glasses = $q_glasses_details = $q_glasses_adverse = '';
		$q_retinacc = $q_retinacc_details = $q_retinacc_adverse = '';
		$q_tan = $q_tan_details = $q_tan_adverse = '';
		$q_facialt = $q_facialt_details = $q_facialt_adverse = '';
		$q_botox = $q_botox_details = $q_botox_adverse = '';
		$q_latisse = $q_latisse_details = $q_latisse_adverse = '';
		$side_sleepon = '';
		$hair_growth = '';
		$anything_else = '';
		$cname = '';
		$cemail = '';
		$today_date = date('m/d/Y');

		// if form was just submitted do another select
		if($formWasJustSubmitted) {
			$sel_sql = "SELECT * FROM $_intakeform_table WHERE client_id=$userId";
			$res = $wpdb->get_results($sel_sql);
		}
		
		// set form values (if already existed or after submit)
		if($formWasJustSubmitted || $formDataExists) {
			foreach ( $res as $row ) {
				$name = $row->name;
				$address = $row->address;
				$city = $row->city;
				$state = $row->state;
				$zip = $row->zip;
				$phone = $row->phone;
				$email = $row->email;

				$howtocontact =  $row->contact_how;
				$howdiduhear = $row->how_did_you_hear;
				$health_history = $row->health_history;
				$allergic_ac = $row->allergic_ac;
				$reaction_tt = $row->allergic_topical;
				$eye_disease = $row->eye_disease;
				$current_meds = $row->current_medication;
				$prev_conditions = $row->prev_conditions;
				$other_hc = $row->other_health_conditions;

				$q_pregnurse = $q_pregnurse_details = $q_pregnurse_adverse = '';
				$q_contacts = $q_contacts_details = $q_contacts_adverse = '';
				$q_glasses = $q_glasses_details = $q_glasses_adverse = '';
				$q_retinacc = $q_retinacc_details = $q_retinacc_adverse = '';
				$q_tan = $q_tan_details = $q_tan_adverse = '';
				$q_facialt = $q_facialt_details = $q_facialt_adverse = '';
				$q_botox = $q_botox_details = $q_botox_adverse = '';
				$q_latisse = $q_latisse_details = $q_latisse_adverse = '';
				$q_obj = json_decode($row->hair_question_answers, true);
				foreach ($q_obj as $key => $jsons) { 
					foreach($jsons as $key => $value) {
				    	ec_intakeForm_setHairQuestionVarValues($q_pregnurse, $q_pregnurse_details, $q_pregnurse_adverse, 'pregnurse', $key, $value);
				    	ec_intakeForm_setHairQuestionVarValues($q_contacts, $q_contacts_details, $q_contacts_adverse, 'contacts', $key, $value);
				    	ec_intakeForm_setHairQuestionVarValues($q_glasses, $q_glasses_details, $q_glasses_adverse, 'glasses', $key, $value);
				    	ec_intakeForm_setHairQuestionVarValues($q_retinacc, $q_retinacc_details, $q_retinacc_adverse, 'retinacc', $key, $value);
				    	ec_intakeForm_setHairQuestionVarValues($q_tan, $q_tan_details, $q_tan_adverse, 'tan', $key, $value);
				    	ec_intakeForm_setHairQuestionVarValues($q_facialt, $q_facialt_details, $q_facialt_adverse, 'facialt', $key, $value);
				    	ec_intakeForm_setHairQuestionVarValues($q_botox, $q_botox_details, $q_botox_adverse, 'botox', $key, $value);
				    	ec_intakeForm_setHairQuestionVarValues($q_latisse, $q_latisse_details, $q_latisse_adverse, 'latisse', $key, $value);
					}
				}

				$side_sleepon = $row->sleep_side;
				$hair_growth = $row->hair_growth;
				$anything_else = $row->other_info;

				// show date when was modified
				$dt = new DateTime($row->last_modified_at);
				$date_only = $dt->format('m/d/Y');
				$today_date = $date_only;
			}
		}

		// small tweak for date: if admin, date is always today
		if($isAdminUsr)
			$today_date = date('m/d/Y');

		$output .= '<h3 class="widget-title">Intake Form</h3>';
		include( plugin_dir_path( __FILE__ ) . 'inc/intake-form.php');

	}
	else
		$output = 'Please log-in to your account.';	
	return $output;
}

function ec_intake_form_control_admin_view_shortcode() {
	if(is_user_logged_in()) {
		global $wpdb;
		$_intakeform_table = $wpdb->prefix."ec_forms";

		$output = '';

		$formDataExists = false;
		$formWasJustSubmitted = false;
		$queryType = '';
		$isAdminUsr = false;
		$isReadOnly = false;

		// check if user is admin
		$user_meta = get_userdata(get_current_user_id());
		$user_roles = $user_meta->roles;
		for($i = 0; $i < count($user_roles); $i++) {
			if($user_roles[$i] == 'administrator' || $user_roles[$i] == 'manager' || $user_roles[$i] == 'shop_manager' || $user_roles[$i] == 'technician') {
				$isAdminUsr = true;
				break;
			}
		}

		// if not admin, exit
		if(!$isAdminUsr) {
			$output = 'This user doesn\'t have permission to view this page.';
			return $output;
		}

		$userId = '';
		if(isset($_GET['u']))
			if($_GET['u'] != '')
				$userId = $_GET['u'];

		if($userId == '') {
			$output = 'Unable to view client form data (missing user id).';
			return $output;
		}

		// check if data already exists in db
		$sel_sql = "SELECT * FROM $_intakeform_table WHERE client_id=$userId";
		$res = $wpdb->get_results($sel_sql);
		$rowcount = $wpdb->num_rows;
		if($wpdb->num_rows > 0)
			$formDataExists = true;

		if(!$formDataExists) {
			$output = 'No form data available for requested user.';
			return $output;
		}

		// form data
		$stateArr = array(
			'AL' => 'Alabama',
			'AK' => 'Alaska',
			'AZ' => 'Arizona',
			'AR' => 'Arkansas',
			'CA' => 'California',
			'CO' => 'Colorado',
			'CT' => 'Connecticut',
			'DE' => 'Delaware',
			'DC' => 'District Of Columbia',
			'FL' => 'Florida',
			'GA' => 'Georgia',
			'HI' => 'Hawaii',
			'ID' => 'Idaho',
			'IL' => 'Illinois',
			'IN' => 'Indiana',
			'IA' => 'Iowa',
			'KS' => 'Kansas',
			'KY' => 'Kentucky',
			'LA' => 'Louisiana',
			'ME' => 'Maine',
			'MD' => 'Maryland',
			'MA' => 'Massachusetts',
			'MI' => 'Michigan',
			'MN' => 'Minnesota',
			'MS' => 'Mississippi',
			'MO' => 'Missouri',
			'MT' => 'Montana',
			'NE' => 'Nebraska',
			'NV' => 'Nevada',
			'NH' => 'New Hampshire',
			'NJ' => 'New Jersey',
			'NM' => 'New Mexico',
			'NY' => 'New York',
			'NC' => 'North Carolina',
			'ND' => 'North Dakota',
			'OH' => 'Ohio',
			'OK' => 'Oklahoma',
			'OR' => 'Oregon',
			'PA' => 'Pennsylvania',
			'RI' => 'Rhode Island',
			'SC' => 'South Carolina',
			'SD' => 'South Dakota',
			'TN' => 'Tennessee',
			'TX' => 'Texas',
			'UT' => 'Utah',
			'VT' => 'Vermont',
			'VA' => 'Virginia',
			'WA' => 'Washington',
			'WV' => 'West Virginia',
			'WI' => 'Wisconsin',
			'WY' => 'Wyoming',
		);

		// get user data
		foreach ( $res as $row ) {
			$name = $row->name;
			$address = $row->address;
			$city = $row->city;
			$state = $row->state;
			$zip = $row->zip;
			$phone = $row->phone;
			$email = $row->email;

			$howtocontact =  $row->contact_how;
			$howdiduhear = $row->how_did_you_hear;
			$health_history = $row->health_history;
			$allergic_ac = $row->allergic_ac;
			$reaction_tt = $row->allergic_topical;
			$eye_disease = $row->eye_disease;
			$current_meds = $row->current_medication;
			$prev_conditions = $row->prev_conditions;
			$other_hc = $row->other_health_conditions;

			$q_pregnurse = $q_pregnurse_details = $q_pregnurse_adverse = '';
			$q_contacts = $q_contacts_details = $q_contacts_adverse = '';
			$q_glasses = $q_glasses_details = $q_glasses_adverse = '';
			$q_retinacc = $q_retinacc_details = $q_retinacc_adverse = '';
			$q_tan = $q_tan_details = $q_tan_adverse = '';
			$q_facialt = $q_facialt_details = $q_facialt_adverse = '';
			$q_botox = $q_botox_details = $q_botox_adverse = '';
			$q_latisse = $q_latisse_details = $q_latisse_adverse = '';
			$q_obj = json_decode($row->hair_question_answers, true);
			foreach ($q_obj as $key => $jsons) { 
				foreach($jsons as $key => $value) {
			    	ec_intakeForm_setHairQuestionVarValues($q_pregnurse, $q_pregnurse_details, $q_pregnurse_adverse, 'pregnurse', $key, $value);
			    	ec_intakeForm_setHairQuestionVarValues($q_contacts, $q_contacts_details, $q_contacts_adverse, 'contacts', $key, $value);
			    	ec_intakeForm_setHairQuestionVarValues($q_glasses, $q_glasses_details, $q_glasses_adverse, 'glasses', $key, $value);
			    	ec_intakeForm_setHairQuestionVarValues($q_retinacc, $q_retinacc_details, $q_retinacc_adverse, 'retinacc', $key, $value);
			    	ec_intakeForm_setHairQuestionVarValues($q_tan, $q_tan_details, $q_tan_adverse, 'tan', $key, $value);
			    	ec_intakeForm_setHairQuestionVarValues($q_facialt, $q_facialt_details, $q_facialt_adverse, 'facialt', $key, $value);
			    	ec_intakeForm_setHairQuestionVarValues($q_botox, $q_botox_details, $q_botox_adverse, 'botox', $key, $value);
			    	ec_intakeForm_setHairQuestionVarValues($q_latisse, $q_latisse_details, $q_latisse_adverse, 'latisse', $key, $value);
				}
			}

			$side_sleepon = $row->sleep_side;
			$hair_growth = $row->hair_growth;
			$anything_else = $row->other_info;

			// show date when was modified
			$dt = new DateTime($row->last_modified_at);
			$date_only = $dt->format('m/d/Y');
			$today_date = $date_only;
		}

		$isReadOnly = true;

		$output .= '<h3 class="widget-title">Intake Form for Client ID #'.$userId.'</h3>';
		include( plugin_dir_path( __FILE__ ) . 'inc/intake-form.php');
	}
	else
		$output = 'Please log-in to your account.';	
	return $output;
}

function ec_intake_form_control_admin_edit_shortcode() {
	if(is_user_logged_in()) {
		global $wpdb;
		$_intakeform_table = $wpdb->prefix."ec_forms";

		$output = '';

		$formDataExists = false;
		$formWasJustSubmitted = false;
		$queryType = '';
		$isAdminUsr = false;
		$isReadOnly = false;

		// check if user is admin
		$user_meta = get_userdata(get_current_user_id());
		$user_roles = $user_meta->roles;
		for($i = 0; $i < count($user_roles); $i++) {
			if($user_roles[$i] == 'administrator') {
				$isAdminUsr = true;
				break;
			}
		}

		// if not admin, exit
		if(!$isAdminUsr) {
			$output = 'This user doesn\'t have permission to view this page.';
			return $output;
		}

		$userId = '';
		if(isset($_GET['u']))
			if($_GET['u'] != '')
				$userId = $_GET['u'];

		if($userId == '') {
			$output = 'Unable to edit client form data (missing user id).';
			return $output;
		}

		// check if data already exists in db
		$sel_sql = "SELECT * FROM $_intakeform_table WHERE client_id=$userId";
		$res = $wpdb->get_results($sel_sql);
		$rowcount = $wpdb->num_rows;
		if($wpdb->num_rows > 0)
			$formDataExists = true;

		if(!$formDataExists) {
			$output = 'No form data available for requested user.';
			return $output;
		}

		// form was just submitted
		if(isset($_POST['ec_intake_form_submitted'])) {
			if($_POST['ec_intake_form_submitted'] == 'true') {
				// insert/update and display 'thank you message'
				$formWasJustSubmitted = true;
				$contactHowStr = '';
				$prevCondStr = '';
				if($_POST['ec_intake_form_howtocontact'] != '')
					$contactHowStr = implode("|",$_POST['ec_intake_form_howtocontact']);
				if($_POST['ec_intake_form_prev_conditions'] != '')
					$prevCondStr = implode("|",$_POST['ec_intake_form_prev_conditions']);

				$output .= '<p>Form updated successfully!</p>';
				$wpdb->update( 
					$_intakeform_table,
					array( 
						'last_modified_by' => get_current_user_id(),
						'last_modified_at' => current_time( 'mysql' ),
						'name' => $_POST['ec_intake_form_name'],
						'address' => $_POST['ec_intake_form_address'],
						'city' => $_POST['ec_intake_form_city'],
						'state' => $_POST['ec_intake_form_state'],
						'zip' => $_POST['ec_intake_form_zip'],
						'phone' => $_POST['ec_intake_form_phone'],
						'email' => $_POST['ec_intake_form_email'],
						'contact_how' => $contactHowStr,
						'how_did_you_hear' => $_POST['ec_intake_form_howdidyouhear'],
						'health_history' => $_POST['ec_intake_form_health_history'],
						'allergic_ac' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_allergic_ac_rad'),
						'allergic_topical' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_reactiontt_rad'),
						'eye_disease' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_eyed_rad'),
						'current_medication' => $_POST['ec_intake_form_current_meds'],
						'prev_conditions' => $prevCondStr,
						'other_health_conditions' => $_POST['ec_intake_form_other_hc'],
						'hair_question_answers' => ec_intakeForm_generateHairGrowthJsonForDb(),
						'sleep_side' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_sleepside_rad'),
						'hair_growth' => ec_intakeForm_getPostDefaultStrValue('ec_intake_form_hairg_rad'),
						'other_info' => $_POST['ec_intake_form_anything_else']
					), 
					array( 'client_id' => $userId )
				);
			}
		}

		// form data
		$stateArr = array(
			'AL' => 'Alabama',
			'AK' => 'Alaska',
			'AZ' => 'Arizona',
			'AR' => 'Arkansas',
			'CA' => 'California',
			'CO' => 'Colorado',
			'CT' => 'Connecticut',
			'DE' => 'Delaware',
			'DC' => 'District Of Columbia',
			'FL' => 'Florida',
			'GA' => 'Georgia',
			'HI' => 'Hawaii',
			'ID' => 'Idaho',
			'IL' => 'Illinois',
			'IN' => 'Indiana',
			'IA' => 'Iowa',
			'KS' => 'Kansas',
			'KY' => 'Kentucky',
			'LA' => 'Louisiana',
			'ME' => 'Maine',
			'MD' => 'Maryland',
			'MA' => 'Massachusetts',
			'MI' => 'Michigan',
			'MN' => 'Minnesota',
			'MS' => 'Mississippi',
			'MO' => 'Missouri',
			'MT' => 'Montana',
			'NE' => 'Nebraska',
			'NV' => 'Nevada',
			'NH' => 'New Hampshire',
			'NJ' => 'New Jersey',
			'NM' => 'New Mexico',
			'NY' => 'New York',
			'NC' => 'North Carolina',
			'ND' => 'North Dakota',
			'OH' => 'Ohio',
			'OK' => 'Oklahoma',
			'OR' => 'Oregon',
			'PA' => 'Pennsylvania',
			'RI' => 'Rhode Island',
			'SC' => 'South Carolina',
			'SD' => 'South Dakota',
			'TN' => 'Tennessee',
			'TX' => 'Texas',
			'UT' => 'Utah',
			'VT' => 'Vermont',
			'VA' => 'Virginia',
			'WA' => 'Washington',
			'WV' => 'West Virginia',
			'WI' => 'Wisconsin',
			'WY' => 'Wyoming',
		);

		// if form was just submitted do another select
		if($formWasJustSubmitted) {
			$sel_sql = "SELECT * FROM $_intakeform_table WHERE client_id=$userId";
			$res = $wpdb->get_results($sel_sql);
		}
		
		// set form values (if already existed or after submit)
		if($formWasJustSubmitted || $formDataExists) {
			foreach ( $res as $row ) {
				$name = $row->name;
				$address = $row->address;
				$city = $row->city;
				$state = $row->state;
				$zip = $row->zip;
				$phone = $row->phone;
				$email = $row->email;

				$howtocontact =  $row->contact_how;
				$howdiduhear = $row->how_did_you_hear;
				$health_history = $row->health_history;
				$allergic_ac = $row->allergic_ac;
				$reaction_tt = $row->allergic_topical;
				$eye_disease = $row->eye_disease;
				$current_meds = $row->current_medication;
				$prev_conditions = $row->prev_conditions;
				$other_hc = $row->other_health_conditions;

				$q_pregnurse = $q_pregnurse_details = $q_pregnurse_adverse = '';
				$q_contacts = $q_contacts_details = $q_contacts_adverse = '';
				$q_glasses = $q_glasses_details = $q_glasses_adverse = '';
				$q_retinacc = $q_retinacc_details = $q_retinacc_adverse = '';
				$q_tan = $q_tan_details = $q_tan_adverse = '';
				$q_facialt = $q_facialt_details = $q_facialt_adverse = '';
				$q_botox = $q_botox_details = $q_botox_adverse = '';
				$q_latisse = $q_latisse_details = $q_latisse_adverse = '';
				$q_obj = json_decode($row->hair_question_answers, true);
				foreach ($q_obj as $key => $jsons) { 
					foreach($jsons as $key => $value) {
				    	ec_intakeForm_setHairQuestionVarValues($q_pregnurse, $q_pregnurse_details, $q_pregnurse_adverse, 'pregnurse', $key, $value);
				    	ec_intakeForm_setHairQuestionVarValues($q_contacts, $q_contacts_details, $q_contacts_adverse, 'contacts', $key, $value);
				    	ec_intakeForm_setHairQuestionVarValues($q_glasses, $q_glasses_details, $q_glasses_adverse, 'glasses', $key, $value);
				    	ec_intakeForm_setHairQuestionVarValues($q_retinacc, $q_retinacc_details, $q_retinacc_adverse, 'retinacc', $key, $value);
				    	ec_intakeForm_setHairQuestionVarValues($q_tan, $q_tan_details, $q_tan_adverse, 'tan', $key, $value);
				    	ec_intakeForm_setHairQuestionVarValues($q_facialt, $q_facialt_details, $q_facialt_adverse, 'facialt', $key, $value);
				    	ec_intakeForm_setHairQuestionVarValues($q_botox, $q_botox_details, $q_botox_adverse, 'botox', $key, $value);
				    	ec_intakeForm_setHairQuestionVarValues($q_latisse, $q_latisse_details, $q_latisse_adverse, 'latisse', $key, $value);
					}
				}

				$side_sleepon = $row->sleep_side;
				$hair_growth = $row->hair_growth;
				$anything_else = $row->other_info;

				// show date when was modified
				$dt = new DateTime($row->last_modified_at);
				$date_only = $dt->format('m/d/Y');
				$today_date = $date_only;
			}
		}

		$today_date = date('m/d/Y');

		$output .= '<h3 class="widget-title">Intake Form for Client ID #'.$userId.'</h3>';
		include( plugin_dir_path( __FILE__ ) . 'inc/intake-form.php');
	}
	else
		$output = 'Please log-in to your account.';	
	return $output;
}

function ec_intakeForm_getPostDefaultStrValue($postValStr) {
	if($_POST[$postValStr] === NULL)
		return '';
	return $_POST[$postValStr];
}

function ec_intakeForm_setHairQuestionVarValues(&$radVarRef, &$detailsVarRef, &$adverseVarRef, $varToken, $key, $value) {
	switch($key) {
		case 'ec_intake_form_q_'.$varToken.'_rad':
			$radVarRef = $value;
		break;
		case 'ec_intake_form_q_'.$varToken.'_details':
			$detailsVarRef = $value;
		break;
		case 'ec_intake_form_q_'.$varToken.'_adverse':
			$adverseVarRef = $value;
		break;
	}
}

function ec_intakeForm_generateHairGrowthBoxHtml($questionLabel, $radSuffix, $detailsSuffix, $adverseSuffix, $radPrevVal, $detailsPrevVal, $adversePrevVal, $isReadOnly) {
	$output = '';
	$output .= '<p><label>'.$questionLabel.'<br>';
	$output .= '<span class="wpcf7-form-control-wrap">';
	$output .= '<span class="wpcf7-form-control wpcf7-radio wpcf7-validates-as-required form-control">';

	$output .= '<span class="wpcf7-list-item first"><label><input type="radio" name="ec_intake_form_q'.$radSuffix.'" value="yes"'.(($radPrevVal == 'yes') ? ' checked="checked"' : '').(($isReadOnly) ? ' readonly disabled' : '').'><span class="wpcf7-list-item-label">Yes</span></label></span>';
	$output .= '<span class="wpcf7-list-item"><label><input type="radio" name="ec_intake_form_q'.$radSuffix.'" value="no"'.(($radPrevVal == 'no') ? ' checked="checked"' : '').(($isReadOnly) ? ' readonly disabled' : '').'><span class="wpcf7-list-item-label">No</span></label></span><br>';

	// details
	$output .= '<label>Details<br>';
	$output .= '<span class="wpcf7-form-control-wrap">';
	$output .= '<input type="text" name="ec_intake_form_q'.$detailsSuffix.'" value="'.$detailsPrevVal.'" size="40" class="wpcf7-form-control wpcf7-text wpcf7-validates-as-required form-control" aria-required="true" aria-invalid="false"'.(($isReadOnly) ? ' readonly' : '').'>';
	$output .= '</span></label><br>';

	// adverse r
	$output .= '<label>Adverse Reactions<br>';
	$output .= '<span class="wpcf7-form-control-wrap">';
	$output .= '<input type="text" name="ec_intake_form_q'.$adverseSuffix.'" value="'.$adversePrevVal.'" size="40" class="wpcf7-form-control wpcf7-text wpcf7-validates-as-required form-control" aria-required="true" aria-invalid="false"'.(($isReadOnly) ? ' readonly' : '').'>';
	$output .= '</span></label><br>';

	$output .= '</span></span>';
	$output .= '</label></p>';
	return $output;
}

function ec_intakeForm_generateHairGrowthJsonForDb() {
	$jsonStr = '[';

	$varPref = 'ec_intake_form_q';
	$jsonStr .= json_encode(array($varPref.'_pregnurse_rad' => ec_intakeForm_getPostDefaultStrValue($varPref.'_pregnurse_rad'), $varPref.'_pregnurse_details' => ec_intakeForm_getPostDefaultStrValue($varPref.'_pregnurse_details'), $varPref.'_pregnurse_adverse' => ec_intakeForm_getPostDefaultStrValue($varPref.'_pregnurse_adverse'))).',';
	$jsonStr .= json_encode(array($varPref.'_contacts_rad' => ec_intakeForm_getPostDefaultStrValue($varPref.'_contacts_rad'), $varPref.'_contacts_details' => ec_intakeForm_getPostDefaultStrValue($varPref.'_contacts_details'), $varPref.'_contacts_adverse' => ec_intakeForm_getPostDefaultStrValue($varPref.'_contacts_adverse'))).',';
	$jsonStr .= json_encode(array($varPref.'_glasses_rad' => ec_intakeForm_getPostDefaultStrValue($varPref.'_glasses_rad'), $varPref.'_glasses_details' => ec_intakeForm_getPostDefaultStrValue($varPref.'_glasses_details'), $varPref.'_glasses_adverse' => ec_intakeForm_getPostDefaultStrValue($varPref.'_glasses_adverse'))).',';

	$jsonStr .= json_encode(array($varPref.'_retinacc_rad' => ec_intakeForm_getPostDefaultStrValue($varPref.'_retinacc_rad'), $varPref.'_retinacc_details' => ec_intakeForm_getPostDefaultStrValue($varPref.'_retinacc_details'), $varPref.'_retinacc_adverse' => ec_intakeForm_getPostDefaultStrValue($varPref.'_retinacc_adverse'))).',';
	$jsonStr .= json_encode(array($varPref.'_tan_rad' => ec_intakeForm_getPostDefaultStrValue($varPref.'_tan_rad'), $varPref.'_tan_details' => ec_intakeForm_getPostDefaultStrValue($varPref.'_tan_details'), $varPref.'_tan_adverse' => ec_intakeForm_getPostDefaultStrValue($varPref.'_tan_adverse'))).',';
	$jsonStr .= json_encode(array($varPref.'_facialt_rad' => ec_intakeForm_getPostDefaultStrValue($varPref.'_facialt_rad'), $varPref.'_facialt_details' => ec_intakeForm_getPostDefaultStrValue($varPref.'_facialt_details'), $varPref.'_facialt_adverse' => ec_intakeForm_getPostDefaultStrValue($varPref.'_facialt_adverse'))).',';

	$jsonStr .= json_encode(array($varPref.'_botox_rad' => ec_intakeForm_getPostDefaultStrValue($varPref.'_botox_rad'), $varPref.'_botox_details' => ec_intakeForm_getPostDefaultStrValue($varPref.'_botox_details'), $varPref.'_botox_adverse' => ec_intakeForm_getPostDefaultStrValue($varPref.'_botox_adverse'))).',';
	$jsonStr .= json_encode(array($varPref.'_latisse_rad' => ec_intakeForm_getPostDefaultStrValue($varPref.'_latisse_rad'), $varPref.'_latisse_details' => ec_intakeForm_getPostDefaultStrValue($varPref.'_latisse_details'), $varPref.'_latisse_adverse' => ec_intakeForm_getPostDefaultStrValue($varPref.'_latisse_adverse')));
	$jsonStr .= ']';
	return $jsonStr;
}

function ec_intakeForm_generateHairGrowthJsonObjForDb($radVarName, $detVarName, $advVarName) {
	$jsonStr = '{"ec_intake_form_q'.$radVarName.'":"'.$_POST["ec_intake_form_q".$radVarName].'", "ec_intake_form_q'.$detVarName.'":"'.$_POST["ec_intake_form_q".$detVarName].'", "ec_intake_form_q'.$advVarName.'":"'.$_POST["ec_intake_form_q".$advVarName].'"}';
	return $jsonStr;
}

function ec_intakeForm_generatePrevCondCheckBoxHtml($label, $value, $prev_conditions, $isReadOnly) {
	return '<span class="wpcf7-list-item"><label><input type="checkbox" name="ec_intake_form_prev_conditions[]" value="'.$value.'"'.(in_array($value, $prev_conditions) ? ' checked' : '').(($isReadOnly) ? ' readonly disabled' : '').'><span class="wpcf7-list-item-label">'.$label.'</span></label></span>';
}

add_shortcode('ec_intake_form_control', 'ec_intake_form_control_shortcode');
add_shortcode('ec_intake_form_control_admin_view', 'ec_intake_form_control_admin_view_shortcode');
add_shortcode('ec_intake_form_control_admin_edit', 'ec_intake_form_control_admin_edit_shortcode');
add_shortcode('ec_intake_form_control_admin_create', 'ec_intake_form_control_admin_create_shortcode');

/* CLIENT FORM (FRONT-END): END */

/* SEND SMS FEATURE: START */

function ec_send_sms_callback($post) {
	global $wpdb;

	$output = '';

	$_ec_sms_templates_table = $wpdb->prefix."eyelash_club_sms_templates";

	$_posts_ = $wpdb->prefix.'posts';
	$_postmeta_ = $wpdb->prefix.'postmeta';
	$_users_ = $wpdb->prefix.'users';
	$_usermeta_ = $wpdb->prefix.'usermeta';
	
	$pId = $post->ID;
	$sql_a = "SELECT * FROM $_posts_ 
				  WHERE ID='$pId'";
	$posts = $wpdb->get_results($sql_a);
	//print_r($posts);
		
	$sql_m = "SELECT * FROM $_postmeta_ 
				  WHERE post_id='$pId'";
	$postmeta = $wpdb->get_results($sql_m);

	$_booking_customer_id = '';
	foreach($postmeta as $data) {
		if($data->meta_key === '_booking_customer_id')
			$_booking_customer_id = $data->meta_value;
	}

	// preloader for textarea
	$output .= '<style>';
	$output .= '.loadingSmsTa {';
	$output .= 'background-image: url("'.plugins_url('/loading_anim.gif', __FILE__).'") !important;background-size: 25px 25px !important;background-position:center center !important;background-repeat: no-repeat !important;';
	$output .= '}';
	$output .= '</style>';

	$output .= '<table class="form-table">';
	$output .= '<tbody><tr>';
    $output .= '<th scope="row">Message</th>';
    $output .= '<td style="text-align: right;">';
    $output .= '<textarea id="sendSmsMsgTA" name="sendSmsMsgTA" rows="10" style="width:100%;"></textarea>';
    $output .= '</td></tr>';

    $output .= '<tr><th scope="row">Template</th>';
	$output .= '<td><select id="sendSmsTemplatesSel" style="width:100%;"><option value="none">- select a template -</option>';

	$sel_sql = "SELECT * FROM $_ec_sms_templates_table";
	$res = $wpdb->get_results($sel_sql);

	foreach ( $res as $row ) {
		$output .= '<option value="'.$row->id.'">'.$row->name.'</option>';
	}

	$output .= '</select></td></tr>';

	$meta_value = get_user_meta($_booking_customer_id, 'billing_phone', true);
	$phone =  (($meta_value != '') ? $meta_value : '');

    $output .= '<tr><th scope="row">Phone No.</th>';
	$output .= '<td><input id="sendSmsPhoneNoInp" name="sendSmsPhoneNoInp" type="text" style="width:100%" value="'.$phone.'"></td></tr>';

	$output .= '<tr><th scope="row"></th>';
	$output .= '<td align="right"><button id="sendSmsBtn" type="button" class="button">Send SMS</button></td></tr>';

	$output .= '</tr></tbody></table>';

	// get twilio settings
	$smsSid = '';
	$smsAuthToken = '';
	$smsTPhone = '';
	$_ec_sms_settings_table = $wpdb->prefix . "eyelash_club_sms_settings";

	$sel_sql = "SELECT * FROM $_ec_sms_settings_table";
	$res = $wpdb->get_results($sel_sql);

	foreach ( $res as $row ) {
		$smsSid = $row->sid;
		$smsAuthToken = $row->auth_token;
		$smsTPhone = $row->phone;
	}

     // javascript
    $output .= '<script type="text/javascript">';
    $output .= 'jQuery(document).ready(function() {';

    $output .= 'jQuery("#sendSmsBtn").on("click", function() {';
    $output .= 'if(jQuery("#sendSmsPhoneNoInp").val() != "" && jQuery("#sendSmsMsgTA").val() != "") {';
    $output .= 'jQuery("#sendSmsBtn").prop("disabled", true);';
    $output .= 'jQuery("#sendSmsMsgTA").prop("disabled", true);';
	$output .= 'jQuery("#sendSmsMsgTA").addClass("loadingSmsTa");';
	$output .= 'var data = {';
	$output .= "'message': jQuery('#sendSmsMsgTA').val(),";
	$output .= "'sid': '".$smsSid."',";
	$output .= "'auth_token': '".$smsAuthToken."',";
	$output .= "'twilio_phone': '".$smsTPhone."',";
	$output .= "'customer_id': '".$_booking_customer_id."',";
	$output .= "'number': jQuery('#sendSmsPhoneNoInp').val()";
	$output .= '};';

	$output .= 'jQuery.ajax({';
    $output .= 'url: "https://eyelashclub.com/sendTwilioSms.php",';
    $output .= 'type: "post",';
    $output .= 'data: data,';
    $output .= 'success: function( data, textStatus, jQxhr ){';
    $output .= 'console.log("success");';
    $output .= 'jQuery("#sendSmsTemplatesSel").prop("selectedIndex", 0);';
    $output .= 'jQuery("#sendSmsMsgTA").removeClass("loadingSmsTa");';
	$output .= 'jQuery("#sendSmsMsgTA").prop("disabled", false);';
	$output .= 'jQuery("#sendSmsBtn").prop("disabled", false);';
	$output .= 'jQuery("#sendSmsMsgTA").val("");';
    $output .= '},';
    $output .= 'error: function( jqXhr, textStatus, errorThrown ){';
    $output .= 'console.log( errorThrown );';
    $output .= '}';
    $output .= '});';

    $output .= '}';
    $output .= '});';

    $output .= 'jQuery("#sendSmsTemplatesSel").on("change", function() {';
	$output .= 'if(this.value != "none") {';
	
	$output .= 'jQuery("#sendSmsMsgTA").prop("disabled", true);';
	$output .= 'jQuery("#sendSmsMsgTA").addClass("loadingSmsTa");';
	$output .= 'var data = {';
	$output .= "'action': 'ec_sms_get_template',";
	$output .= "'template_id': this.value,";
	$output .= "'booking_id': '".$pId."',";
	$output .= "'customer_id': '".$_booking_customer_id."'";
	$output .= '};';

	$output .= 'jQuery.post(ajaxurl, data, function(response) {';
	$output .= 'if(response != "") {';
	$output .= 'jQuery("#sendSmsMsgTA").removeClass("loadingSmsTa");';
	$output .= 'jQuery("#sendSmsMsgTA").prop("disabled", false);';
	$output .= 'jQuery("#sendSmsMsgTA").val(response);';
	$output .= '}';
	$output .= '});';

	$output .= '}';
	$output .= '});';
    $output .= '});';
    $output .= '</script>';

	echo $output;
}

add_action( 'wp_ajax_ec_sms_get_template', 'ec_sms_get_template' );

function ec_sms_get_template() {
	global $wpdb; // this is how you get access to the database

	$i = 0;

	$_posts_ = $wpdb->prefix.'posts';
	$_postmeta_ = $wpdb->prefix.'postmeta';
	$_users_ = $wpdb->prefix.'users';
	$_usermeta_ = $wpdb->prefix.'usermeta';

	$tId = $_POST['template_id'];
	$bId = $_POST['booking_id'];
	$custId = $_POST['customer_id'];

	$templateBody = '';

	$_ec_sms_templates_table = $wpdb->prefix."eyelash_club_sms_templates";
	$sql_templ = "SELECT * FROM $_ec_sms_templates_table 
				  WHERE id='$tId'";
	$temp_res = $wpdb->get_results($sql_templ);
	foreach ( $temp_res as $trow ) {
		$templateBody = stripslashes($trow->template);
	}

	// find tags in template
	preg_match_all("/\{(.*?)\}/", $templateBody, $matches);

	$foundTagArr = array();
	
	// match all found tags from template, inside tags table
	$_ec_sms_tags_table = $wpdb->prefix."eyelash_club_sms_tags";
	$sel_sql = "SELECT * FROM $_ec_sms_tags_table";
	$res = $wpdb->get_results($sel_sql);

	// user meta
	$sql_e = "SELECT * FROM $_usermeta_ 
			WHERE user_id='$custId'";
	$cust_meta = $wpdb->get_results($sql_e);

	// post meta
	$sql_m = "SELECT * FROM $_postmeta_ 
			WHERE post_id='$bId'";
	$postmeta = $wpdb->get_results($sql_m);

	// post data
	$sql_a = "SELECT * FROM $_posts_ 
			  WHERE post_type='bookable_resource'
			  AND ID='' 
			  ORDER BY post_date DESC";
	$posts = $wpdb->get_results($sql_a);

	foreach ( $res as $row ) {
		for($i = 0; $i < count($matches[1]); $i++) {
			if($matches[1][$i] == $row->identifier) {
				$tagObj = new stdClass();
				$tagObj->identifier = $row->identifier;
				$tagObj->table_name = $row->table_name;
				$tagObj->meta_key = $row->meta_key;
				$tagObj->meta_value = '';
				
				if($tagObj->table_name == "usermeta") {
					// get usermeta values
					foreach($cust_meta as $cust){
						if($cust->meta_key === $tagObj->meta_key) {
							$tagObj->meta_value = $cust->meta_value;
							break;
						}
					}
				}
				else if($tagObj->table_name == "postmeta") {
					// get postmeta values
					foreach($postmeta as $postm){
						if($postm->meta_key === $tagObj->meta_key) {
							$tagObj->meta_value = $postm->meta_value;
							break;
						}
					}
					if($tagObj->meta_value != "") {
						// special cases
						switch($postm->meta_key) {
							case "_ec_tech_id":
								// get tech name by id
								$techId = $tagObj->meta_value;
								$sql_pm_special = "SELECT * FROM $_posts_ 
										  WHERE post_type='bookable_resource'
										  AND ID='$techId'";
								$posts = $wpdb->get_results($sql_pm_special);
								foreach ( $posts as $post ) {
									$tagObj->meta_value = $post->post_title;
									break;
								}
							break;
							case "_booking_product_id":
								// get product name
								$productId = $tagObj->meta_value;
								$sql_pm_special = "SELECT * FROM $_posts_ 
									  WHERE post_type='product'
									  AND post_status='publish'
									  AND ID='$productId'";
								$posts = $wpdb->get_results($sql_pm_special);
								foreach ( $posts as $post ) {
									$tagObj->meta_value = $post->post_title;
									break;
								}
							break;
							case "_booking_start":
								// get date time
								$bk_year = substr($tagObj->meta_value,0,4);
								$bk_month = substr($tagObj->meta_value,4,2);
								$bk_day = substr($tagObj->meta_value,6,2);
								$bk_hour = substr($tagObj->meta_value,8,2);
								$bk_min = substr($tagObj->meta_value,10,2);
								
								$datetime_convert = $bk_year.'-'.$bk_month.'-'.$bk_day.' '.$bk_hour.':'.$bk_min;
								
								$_booking_start = date("F j, Y, g:i a", strtotime($datetime_convert));
								$tagObj->meta_value = $_booking_start;
							break;
						}
					}
				}

				$foundTagArr[] = $tagObj;
				break;
			}
		}
	}

	// replace tags in template with real values
	for($i = 0; $i < count($foundTagArr); $i++) {
		if($foundTagArr[$i]->meta_value != '') {
			$templateBody = str_replace('{'.$foundTagArr[$i]->identifier.'}', $foundTagArr[$i]->meta_value, $templateBody);
		}
	}

	echo $templateBody;
	wp_die(); // this is required to terminate immediately and return a proper response
}

function eyelash_club_admin_sms_settings() {
	global $wpdb;

	$active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'twilio';

	$output = '<div class="wrap">
		<h2>Eyelash Club SMS Settings</h2>
		<hr class="wp-header-end">';
	$output .= '<h2 class="nav-tab-wrapper">';
	$output .= '<a class="nav-tab'.(($active_tab == 'twilio') ? ' nav-tab-active' : '').'" href="'.admin_url('admin.php?page=eyelash-club-admin-sms-settings&tab=twilio').'">Twilio API</a>';
	$output .= '<a class="nav-tab'.(($active_tab == 'templates') ? ' nav-tab-active' : '').'" href="'.admin_url('admin.php?page=eyelash-club-admin-sms-settings&tab=templates').'">Templates</a>';
	$output .= '<a class="nav-tab'.(($active_tab == 'tags') ? ' nav-tab-active' : '').'" href="'.admin_url('admin.php?page=eyelash-club-admin-sms-settings&tab=tags').'">Tags</a>';
	$output .= '<a class="nav-tab'.(($active_tab == 'history') ? ' nav-tab-active' : '').'" href="'.admin_url('admin.php?page=eyelash-club-admin-sms-settings&tab=history').'">SMS History</a>';
	$output .= '</h2>';

	$output .= '<div>';
	switch($active_tab) {
		case 'twilio':
			$output .= ec_smsSettings_getTwilioSettingsContent();
		break;
		case 'templates':
			$output .= ec_smsSettings_getTemplatesContent();
		break;
		case 'tags':
			$output .= ec_smsSettings_getTagsContent();
		break;
		case 'history':
			$output .= ec_smsSettings_getHistoryContent();
		break;
	}
	$output .= '</div>';
	
	$output .= '</div>';

	echo $output;
}

function ec_smsSettings_getHistoryContent() {
	global $wpdb;

	$eyelash_club_sms_history = $wpdb->prefix."eyelash_club_sms_history";

	$output = '<h3>SMS History/status</h3>';
	
	// minor style fix
	$output .= '<style>.custom-select { padding: .375rem 1.75rem .375rem .75rem !important; line-height: 1.5 !important; padding-top: .375rem !important; padding-bottom: .375rem !important; font-size: 75% !important; }#tiptip_content, .chart-tooltip, .wc_error_tip { max-width: 300px !important; text-align: left !important; }</style>';

	$output .= '<div style="width:100%;background-color:#ffffff;padding:0.5rem;">';
	$output .= '<table id="smsHistDataTable" class="display compact" style="width:100%;">';
	// header
	$output .= '<thead><tr>';
    $output .= '<th>ID</th>';
    $output .= '<th>Timestamp</th>';
    $output .= '<th>Customer ID</th>';
    $output .= '<th>Number</th>';
    $output .= '<th>Message</th>';
    $output .= '<th>Status</th>';
    $output .= '</tr></thead><tbody>';

    $sel_sql = "SELECT * FROM $eyelash_club_sms_history";
	$res = $wpdb->get_results($sel_sql);

	foreach ( $res as $row ) {
		$output .= '<tr>';
		$output .= '<td>'.$row->id.'</td>';
		$output .= '<td>'.$row->timestamp.'</td>';
		$output .= '<td>'.$row->client_id.'</td>';
		$output .= '<td>'.$row->number.'</td>';
		$output .= '<td><a id="msg_'.$row->id.'" href="#">View</a><script>jQuery(document).ready(function() { jQuery("#msg_'.$row->id.'").tipTip({maxWidth: "auto", content: "'.trim(preg_replace('/\s+/', ' ', nl2br($row->message))).'"}); });</script></td>';
		$output .= '<td>'.$row->status.'</td>';
		$output .= '</tr>';
	}

    $output .= '</tbody></table></div>';

    // javascript
    $output .= '<script>jQuery(document).ready(function($) {$("#smsHistDataTable").DataTable( { responsive: true, "order": [[ 1, "desc" ]] } );});';
    $output .= '</script>';

	return $output;
}

function ec_smsSettings_getTwilioSettingsContent() {
	global $wpdb;

	$smsSid = '';
	$smsAuthToken = '';
	$smsTPhone = '';

	$_ec_sms_settings_table = $wpdb->prefix."eyelash_club_sms_settings";

	$output = '';

	// detect update
	if(isset($_POST['ec_twilio_sid']) && isset($_POST['ec_twilio_auth_token']) && isset($_POST['ec_twilio_phone'])) {
		if($_POST['ec_twilio_sid'] != '' && $_POST['ec_twilio_auth_token'] != '' && $_POST['ec_twilio_phone'] != '') {
			$wpdb->update( 
				$_ec_sms_settings_table,
				array( 
					'sid' => $_POST['ec_twilio_sid'], 
					'auth_token' => $_POST['ec_twilio_auth_token'],
					'phone' => $_POST['ec_twilio_phone']
				), 
				array( 'uid' => '1' )
			);

			$output .= '<div id="message" class="updated notice is-dismissible"><p><strong>Settings updated.</strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
		}
	}

	$sel_sql = "SELECT * FROM $_ec_sms_settings_table";
	$res = $wpdb->get_results($sel_sql);

	foreach ( $res as $row ) {
		$smsSid = $row->sid;
		$smsAuthToken = $row->auth_token;
		$smsTPhone = $row->phone;
	}

	$output .= '<form id="ec-twilio-settings" action="'.admin_url('admin.php?page=eyelash-club-admin-sms-settings&tab=twilio').'" method="post" novalidate="novalidate">';
	$output .= '<table class="form-table"><tbody>';
	$output .= '<tr><th scope="row"><label for="ec_twilio_sid">SID</label></th><td><input style="width:100%;" name="ec_twilio_sid" type="text" id="ec_twilio_sid" value="'.$smsSid.'"></td></tr>';

	$output .= '<tr><th scope="row"><label for="ec_twilio_auth_token">Auth Token</label></th><td><input style="width:100%;" name="ec_twilio_auth_token" type="text" id="ec_twilio_auth_token" value="'.$smsAuthToken.'"></td></tr>';

	$output .= '<tr><th scope="row"><label for="ec_twilio_phone">Phone Number</label></th><td><input style="width:100%;" name="ec_twilio_phone" type="text" id="ec_twilio_phone" value="'.$smsTPhone.'"></td></tr>';
	$output .= '</tbody></table>';
	$output .= '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Update Settings"></p>';
	$output .= '</form>';
	return $output;
}

function ec_smsSettings_getTemplatesContent() {
	global $wpdb;

	$_ec_sms_templates_table = $wpdb->prefix."eyelash_club_sms_templates";

	$output = '';
	$editTemplate = false;
	$editTemplateId = '';
	$editTemplateName = '';
	$editTemplateText = '';

	if(isset($_GET['edit'])) {
		if($_GET['edit'] != '') {
			$editTemplate = true;
			$editTemplateId = $_GET['edit'];
			$sql_templ = "SELECT * FROM $_ec_sms_templates_table 
				  WHERE id='$editTemplateId'";
			$templ_edit_res = $wpdb->get_results($sql_templ);
			foreach ( $templ_edit_res as $trow ) {
				$editTemplateName = $trow->name;
				$editTemplateText = stripslashes($trow->template);
			}
		}
	}

	// if add template save detected
	if(isset($_POST['ec_sms_template_name']) && isset($_POST['ec_sms_template'])) {
		if($_POST['ec_sms_template_name'] != '' && $_POST['ec_sms_template'] != '') {
			if(isset($_POST['ec_sms_template_action'])) {
				if($_POST['ec_sms_template_action'] == 'add') {
					$wpdb->insert( 
						$_ec_sms_templates_table, 
						array( 
							'name' => $_POST['ec_sms_template_name'], 
							'template' => $_POST['ec_sms_template']
						) 
					);
					$output .= '<div id="message" class="updated notice is-dismissible"><p><strong>New template added.</strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
				}
				else if($_POST['ec_sms_template_action'] == 'edit') {
					$wpdb->update( 
						$_ec_sms_templates_table,
						array( 
							'name' => $_POST['ec_sms_template_name'],
							'template' => $_POST['ec_sms_template']
						), 
						array( 'id' => $_POST['ec_sms_template_edit_id'] )
					);

					$output .= '<div id="message" class="updated notice is-dismissible"><p><strong>Template edited.</strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
				}
			}
		}
	}

	$output .= '<form id="ec-sms-tags" action="'.admin_url('admin.php?page=eyelash-club-admin-sms-settings&tab=templates').'" method="post" novalidate="novalidate">';
	$output .= '<h3 id="ec_sms_template_addeditTitle">';
	if($editTemplate)
		$output .= 'Edit template #'.$editTemplateId;
	else
		$output .= 'Add new template';
	$output .= '</h3>';
	$output .= '<table class="form-table"><tbody>';
	
	$output .= '<tr><th scope="row"><label for="ec_sms_template_name">Template name</label></th><td><input style="width:100%;" name="ec_sms_template_name" type="text" id="ec_sms_template_name" value="'.(($editTemplate) ? $editTemplateName : '').'"></td></tr>';

	$output .= '<tr><th scope="row"><label for="ec_sms_template">Text</label></th><td><textarea name="ec_sms_template" id="ec_sms_template" rows="8" style="width:100%;">'.(($editTemplate) ? $editTemplateText : '').'</textarea>';

	$output .= 'Tags: <select id="ec_sms_template_tagSelect">';

	$_ec_sms_tags_table = $wpdb->prefix."eyelash_club_sms_tags";
	$sel_sql = "SELECT * FROM $_ec_sms_tags_table";
	$res = $wpdb->get_results($sel_sql);

	foreach ( $res as $row ) {
		$output .= '<option value="'.$row->identifier.'">'.$row->identifier.'</option>';
	}

	$output .= '</select>&nbsp;<input type="button" value="Add" style="cursor:pointer;" onclick="ec_sms_template_addTagToTemplate();">';

	$output .= '</td></tr>';
	$output .= '</tbody></table>';

	$output .= '<div style="padding-bottom:1rem;"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Template">';
	if($editTemplate)
		$output .= '&nbsp;<input type="button" name="cancel_edit" id="cancel_edit" class="button button-secondary" value="Cancel Edit" onclick="ec_sms_template_cancelEditTemplate();">';
	$output .= '</div>';
	$output .= '<input type="hidden" name="ec_sms_template_action" id="ec_sms_template_action" value="'.(($editTemplate) ? 'edit' : 'add').'">';
	if($editTemplate)
		$output .= '<input type="hidden" name="ec_sms_template_edit_id" id="ec_sms_template_edit_id" value="'.$editTemplateId.'">';
	$output .= '</form>';

	// minor style fix
	$output .= '<style>.custom-select { padding: .375rem 1.75rem .375rem .75rem !important; line-height: 1.5 !important; padding-top: .375rem !important; padding-bottom: .375rem !important; font-size: 75% !important; }#tiptip_content, .chart-tooltip, .wc_error_tip { max-width: 300px !important; text-align: left !important; }</style>';

	$output .= '<div style="width:100%;background-color:#ffffff;padding:0.5rem;">';
	$output .= '<table id="smsTemplateDataTable" class="display compact" style="width:100%;">';
	// header
	$output .= '<thead><tr>';
    $output .= '<th>Template ID</th>';
    $output .= '<th>Name</th>';
    $output .= '<th>Text</th>';
    $output .= '<th></th>';
    $output .= '</tr></thead><tbody>';

    $sel_sql = "SELECT * FROM $_ec_sms_templates_table";
	$res = $wpdb->get_results($sel_sql);

	foreach ( $res as $row ) {
		$output .= '<tr>';
		$output .= '<td>'.$row->id.'</td>';
		$output .= '<td>'.$row->name.'</td>';
		$output .= '<td><a id="tv_'.$row->id.'" href="#">View</a><script>jQuery(document).ready(function() { jQuery("#tv_'.$row->id.'").tipTip({maxWidth: "auto", content: "'.trim(preg_replace('/\s+/', ' ', nl2br($row->template))).'"}); });</script></td>';
		$output .= '<td><input type="button" value="Edit" style="cursor:pointer;" onclick="window.open(\''.admin_url('admin.php?page=eyelash-club-admin-sms-settings&tab=templates&edit='.$row->id).'\',\'_self\')">&nbsp;<input type="button" value="Delete" style="cursor:pointer;" onclick="ec_sms_template_deleteTemplate(\''.$row->id.'\', this)"></td>';
		$output .= '</tr>';
	}

    $output .= '</tbody></table></div>';

    // javascript
    $output .= '<script>jQuery(document).ready(function($) {$("#smsTemplateDataTable").DataTable( { responsive: true, "order": [[ 0, "desc" ]] } );});function ec_sms_template_deleteTemplate(tId, btn) {';
    
    $output .= 'jQuery(btn).prop("disabled",true);';
    $output .= 'var data = {';
	$output .= "'action': 'ec_sms_delete_template',";
	$output .= "'template_id': tId";
	$output .= '};';

	$output .= 'jQuery.post(ajaxurl, data, function(response) {';
	$output .= 'if(response == "success") {';
	$output .= 'console.log("success");';
	$output .= 'jQuery("#smsTemplateDataTable").DataTable().row(jQuery(btn).parents("tr")).remove().draw();';
	$output .= '}';
	$output .= '});';

    $output .= '}';
    $output .='function ec_sms_template_addTagToTemplate() { var oldTxt = jQuery("#ec_sms_template").val(); jQuery("#ec_sms_template").val(oldTxt+"{"+jQuery("#ec_sms_template_tagSelect").val()+"}"); }';

    $output .= 'function ec_sms_template_cancelEditTemplate() { jQuery("#ec_sms_template_addeditTitle").text("Add new template"); jQuery("#ec_sms_template_name").val(""); jQuery("#ec_sms_template").val(""); jQuery("#cancel_edit").hide(); jQuery("#ec_sms_template_action").val("add") }';

    $output .='</script>';

	return $output;
}

function ec_smsSettings_getTagsContent() {
	global $wpdb;

	$_ec_sms_tags_table = $wpdb->prefix."eyelash_club_sms_tags";

	$output = '';

	// if add tag detected
	if(isset($_POST['ec_sms_tag_name']) && isset($_POST['ec_sms_tag_dbtable']) && isset($_POST['ec_sms_tag_metakey'])) {
		if($_POST['ec_sms_tag_name'] != '' && $_POST['ec_sms_tag_dbtable'] != '' && $_POST['ec_sms_tag_metakey'] != '') {
			$wpdb->insert( 
				$_ec_sms_tags_table, 
				array( 
					'identifier' => $_POST['ec_sms_tag_name'], 
					'table_name' => $_POST['ec_sms_tag_dbtable'],
					'meta_key' => $_POST['ec_sms_tag_metakey']
				) 
			);
			$output .= '<div id="message" class="updated notice is-dismissible"><p><strong>New tag added.</strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
		}
	}

	$output .= '<form id="ec-sms-tags" action="'.admin_url('admin.php?page=eyelash-club-admin-sms-settings&tab=tags').'" method="post" novalidate="novalidate">';
	$output .= '<h3>Add new tag</h3>';
	$output .= '<table class="form-table"><tbody>';
	$output .= '<tr><th scope="row"><label for="ec_sms_tag_dbtable">Meta Db Table</label></th><td>';
	
	$output .= '<select id="ec_sms_tag_dbtable" name="ec_sms_tag_dbtable">';
	$output .= '<option value="usermeta">'.$wpdb->prefix."usermeta".'</option>';
	$output .= '<option value="postmeta">'.$wpdb->prefix."postmeta".'</option>';
	$output .= '</select>';

	$output .= '</td></tr>';
	
	$output .= '<tr><th scope="row"><label for="ec_sms_tag_metakey">Meta Key</label></th><td><input style="width:100%;" name="ec_sms_tag_metakey" type="text" id="ec_sms_tag_metakey" value=""></td></tr>';

	$output .= '<tr><th scope="row"><label for="ec_sms_tag_name">Tag Name <span class="description">(without {})</span></label></th><td><input style="width:100%;" name="ec_sms_tag_name" type="text" id="ec_sms_tag_name" value=""></td></tr>';
	$output .= '</tbody></table>';

	$output .= '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Tag"></p>';
	$output .= '</form>';

	// minor style fix
	$output .= '<style>.custom-select { padding: .375rem 1.75rem .375rem .75rem !important; line-height: 1.5 !important; padding-top: .375rem !important; padding-bottom: .375rem !important; font-size: 75% !important; }</style>';

	$output .= '<div style="width:100%;background-color:#ffffff;padding:0.5rem;">';
	$output .= '<table id="smsTagsDataTable" class="display compact" style="width:100%;">';
	// header
	$output .= '<thead><tr>';
    $output .= '<th>Tag ID</th>';
    $output .= '<th>Name</th>';
    $output .= '<th>Meta Db Table</th>';
    $output .= '<th>Meta Key</th>';
    $output .= '<th></th>';
    $output .= '</tr></thead><tbody>';

    $sel_sql = "SELECT * FROM $_ec_sms_tags_table";
	$res = $wpdb->get_results($sel_sql);

	foreach ( $res as $row ) {
		$output .= '<tr>';
		$output .= '<td>'.$row->id.'</td>';
		$output .= '<td>{'.$row->identifier.'}</td>';
		$output .= '<td>'.$wpdb->prefix.$row->table_name.'</td>';
		$output .= '<td>'.$row->meta_key.'</td>';
		
		$output .= '<td><input type="button" value="Delete" style="cursor:pointer;" onclick="ec_sms_tags_deleteTag(\''.$row->id.'\', this)">&nbsp;</td>';
		$output .= '</tr>';
	}

    $output .= '</tbody></table></div>';

    // javascript
    $output .= '<script>jQuery(document).ready(function($) {$("#smsTagsDataTable").DataTable( { responsive: true, "order": [[ 0, "desc" ]] } );});function ec_sms_tags_deleteTag(tagId, btn) {';
    
    $output .= 'jQuery(btn).prop("disabled",true);';
    $output .= 'var data = {';
	$output .= "'action': 'ec_sms_delete_tag',";
	$output .= "'tag_id': tagId";
	$output .= '};';

	$output .= 'jQuery.post(ajaxurl, data, function(response) {';
	$output .= 'if(response == "success") {';
	$output .= 'console.log("success");';
	$output .= 'jQuery("#smsTagsDataTable").DataTable().row(jQuery(btn).parents("tr")).remove().draw();';
	$output .= '}';
	$output .= '});';

    $output .= '}</script>';

	return $output;
}

add_action( 'wp_ajax_ec_sms_delete_tag', 'ec_sms_delete_tag' );
add_action( 'wp_ajax_ec_sms_delete_template', 'ec_sms_delete_template' );

function ec_sms_delete_tag() {
	global $wpdb; // this is how you get access to the database

	$table_name = $wpdb->prefix . "eyelash_club_sms_tags";
	$wpdb->delete( $table_name, array( 'id' => $_POST['tag_id'] ) );
	echo 'success';
	wp_die(); // this is required to terminate immediately and return a proper response
}

function ec_sms_delete_template() {
	global $wpdb; // this is how you get access to the database

	$table_name = $wpdb->prefix . "eyelash_club_sms_templates";
	$wpdb->delete( $table_name, array( 'id' => $_POST['template_id'] ) );
	echo 'success';
	wp_die(); // this is required to terminate immediately and return a proper response
}

function install_sms_templates() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . "eyelash_club_sms_templates"; 
	$sql = "CREATE TABLE $table_name (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  template TEXT NOT NULL,
	  name varchar(30) DEFAULT '' NOT NULL,
	  PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}

function install_sms_tags() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . "eyelash_club_sms_tags"; 
	$sql = "CREATE TABLE $table_name (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  identifier varchar(100) DEFAULT '' NOT NULL,
	  table_name varchar(100) DEFAULT '' NOT NULL,
	  meta_key varchar(100) DEFAULT '' NOT NULL,
	  PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}

function install_sms_history() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . "eyelash_club_sms_history";
	$sql = "CREATE TABLE $table_name (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
	  client_id mediumint(9) NOT NULL,
	  number varchar(30) DEFAULT '' NOT NULL,
	  message TEXT NOT NULL,
	  message_sid varchar(100) DEFAULT '' NOT NULL,
	  status varchar(50) DEFAULT '' NOT NULL,
	  PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}

function install_sms_settings() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . "eyelash_club_sms_settings";
	$sql = "CREATE TABLE $table_name (
	  uid tinyint(1) DEFAULT '1' NOT NULL,
	  sid varchar(50) DEFAULT '' NOT NULL,
	  auth_token varchar(50) DEFAULT '' NOT NULL,
	  phone varchar(50) DEFAULT '' NOT NULL
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}

function uninstall_sms_templates(){
	global $wpdb;
	
	// drop the table
	$table_name = $wpdb->prefix . "eyelash_club_sms_templates"; 
	
	$wpdb->query("DROP TABLE IF EXISTS $table_name");
}

function uninstall_sms_tags(){
	global $wpdb;
	
	// drop the table
	$table_name = $wpdb->prefix . "eyelash_club_sms_tags"; 
	
	$wpdb->query("DROP TABLE IF EXISTS $table_name");
}

function uninstall_sms_history(){
	global $wpdb;
	
	// drop the table
	$table_name = $wpdb->prefix . "eyelash_club_sms_history"; 
	
	$wpdb->query("DROP TABLE IF EXISTS $table_name");
}

function uninstall_sms_settings(){
	global $wpdb;
	
	// drop the table
	$table_name = $wpdb->prefix . "eyelash_club_sms_settings"; 
	
	$wpdb->query("DROP TABLE IF EXISTS $table_name");
}

register_activation_hook( __FILE__, 'install_sms_templates');
register_activation_hook( __FILE__, 'install_sms_tags');
register_activation_hook( __FILE__, 'install_sms_history');
register_activation_hook( __FILE__, 'install_sms_settings');
register_deactivation_hook(__FILE__, 'uninstall_sms_templates');
register_deactivation_hook(__FILE__, 'uninstall_sms_tags');
register_deactivation_hook(__FILE__, 'uninstall_sms_history');
register_deactivation_hook(__FILE__, 'uninstall_sms_settings');

/* SEND SMS FEATURE: END */

?>