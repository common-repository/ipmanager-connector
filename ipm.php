<?php
/*
Plugin Name: IPManager Connector
Plugin URI: http://wordpress.org/extend/plugins/ipmanager-connector/
Description: Connects a WordPress install to an IPManager install via SOAP
Version: 0.1
Author: Michael Dale
Author URI: http://www.dalegroup.net/
*/

/*
Change this value to TRUE to stop admins from disabling/uninstalling/changing the IPM plugin. 
See FAQ for more info http://wordpress.org/extend/plugins/ipmanager-connector/faq/
*/
define('IPM_LOCKDOWN', FALSE);

/*
Stop people from accessing the file directly and causing errors.
*/
if (!function_exists('add_action')) {
	die('You cannot run this file directly.');
}

/*
Don't break stuff if user tries to use this plugin on anything less than WordPress 2.0.0
*/
if (!function_exists('get_role')) {
	return;
}

//includes
if (!class_exists('nusoap_client')) {
	include('ipm-nusoap.php');
}
include('ipm-soap.class.php');
include('ipm-soap-client.class.php');

/*
Required for WordPress 2.5+
*/
global $ipm_version;
global $wpdb;

//ipm version number
$ipm_version = '0.1';

//setup the database, check/upgrade database
ipm_is_installed();
ipm_site();
ipm_upgrade();

//menu actions
add_action('admin_menu', 'ipm_admin_menu_settings');

//events
add_action('activate_' . plugin_basename(__FILE__), 'ipm_trigger_activate_ipm');
add_action('deactivate_' . plugin_basename(__FILE__), 'ipm_trigger_deactivate_ipm');

add_action('init', 'ipm_add_admin_cap');
add_action('init', 'ipm_uninstall');
add_action('init', 'ipm_get_anon_ticket_submit');

add_filter( 'the_content', 'ipm_text_filter' );

function ipm_text_filter( $content ) {
	global $ipm_client;
	
	if ($ipm_client->is_connected()) {
		$pos = strpos($content, '<!--ipm_anon_support_form-->');
		if ($pos == true | $pos === 0) {
			$form		= ipm_anon_ticket_form();
			$content 	= str_replace('<!--ipm_anon_support_form-->', $form, $content);
		}
	}
	return $content;

}




//checks to see if ipm is installed
function ipm_is_installed() {
	global $wpdb;

	$installed = get_option('ipm_installed');
	
	if(!$installed) {
		ipm_install_system();
		return false;
	}
	else {
		return true;
	}

}

function ipm_get_anon_ticket_submit() {
	global $ipm_client;
	
	if (isset($_POST['ipm_submit_anon_ticket'])) {
												
		$result = $ipm_client->add_anonymous_ticket(array('name' => $_POST['ipm_name'], 'subject' => $_POST['ipm_subject'], 'email' => $_POST['ipm_email'], 'description' => $_POST['ipm_description']));
	}
}

function ipm_anon_ticket_form() {
	global $ipm_client;
	
	$return = '<form action="' . wp_specialchars($_SERVER['REQUEST_URI']) . '" method="post">';
	
	if (false !== $result = $ipm_client->get_last_result()) {
		$return .= '<strong>' . wp_specialchars($result['message']) . '</strong>';
	}
	
	$return .= '<p>Name <br /><input name="ipm_name" size="35"  type="text" value="';
	
	if (isset($_POST['ipm_name'])) { 
		$return .= wp_specialchars($_POST['ipm_name']);
	}
	
	$return .= '" /></p>';
	
	$return .= '<p>Email Address <br /><input name="ipm_email" size="35"  type="text" value="';
	
	if (isset($_POST['ipm_email'])) { 
		$return .= wp_specialchars($_POST['ipm_email']);
	}
	
	$return .= '" /></p>';
	
	
	$return .= '<p>Subject <br /><input name="ipm_subject" size="35"  type="text" value="';
	
	if (isset($_POST['ipm_subject'])) { 
		$return .= wp_specialchars($_POST['ipm_subject']);
	}
	
	$return .= '" /></p>';
	
	
	$return .= '<p>Description <br /><textarea name="ipm_description" cols="50" rows="10">';
	
	if (isset($_POST['ipm_description'])) {
	
		$return .= wp_specialchars($_POST['ipm_description']);
	}
	
	$return .= '</textarea></p>';

	$return .= '<p class="submit"><input type="submit" name="ipm_submit_anon_ticket" value="Submit"/></p></form>';
		
	return $return;
}

//this function checks that the user is really trying to uninstall and if they have permission to (if so uninstall)
function ipm_uninstall() {
	
	if (isset($_POST['ipm_submit_uninstall'])) {
		if (current_user_can('ipm') && !IPM_LOCKDOWN) {
			if (function_exists('check_admin_referer')) {
				check_admin_referer('ipm-uninstall');
			}
			ipm_uninstall_system();
		}
		else {
			if (function_exists('btev_trigger_error')) {
				btev_trigger_error("Unauthorised Uninstall Attempt of IPManager Connector.", E_USER_WARNING);
			}
		}
	}
}

//install
function ipm_install_system() {
	global $wpdb, $ipm_version;

	//create config will do nothing if option already exists
	ipm_create_config();	
	ipm_site();
	//ipm_schedule_tasks();
	
	if (function_exists('btev_trigger_error')) {
		btev_trigger_error('IPManager Connector ' . $ipm_version . ' Has Been Successfully Installed.', E_USER_NOTICE);
	}
	add_option('ipm_installed', '1');
}

//this function does the uninstalling
function ipm_uninstall_system() {
	global $wpdb;

	/*
	Deactivate Plugin
	*/
	$current = get_option('active_plugins');
	array_splice($current, array_search(plugin_basename(__FILE__), $current), 1 ); // Array-fu!
	update_option('active_plugins', $current);

	/*
	Delete Options from WordPress Table
	*/
	delete_option('ipm_config');
	delete_option('ipm_installed');
	
	/*
	Unschedule Cron Tasks
	*/
	//if (function_exists('wp_clear_scheduled_hook')) {
	//	wp_clear_scheduled_hook('ipm_cron_hourly_tasks_hook');
	//	wp_clear_scheduled_hook('ipm_cron_daily_tasks_hook');
	//}
	
	/*
	Redirect To Plugin Page
	*/
	wp_redirect('plugins.php?deactivate=true');
}

//upgrade database if needed
function ipm_upgrade() {
	global $wpdb, $ipm_version;

	if (ipm_get_config('version') != $ipm_version) {
		ipm_set_config('version', '0.1', TRUE);
		if (function_exists('btev_trigger_error')) {
			btev_trigger_error('IPManager Connector upgraded to version ' . $ipm_version, E_USER_NOTICE);
		}
	}

} 

//lets get the details about ipm out of the database
function ipm_site() {
	global $ipm_site, $ipm_client;
	
	$ipm_site 	= get_option('ipm_config');

	$ipm_client	= new ipm_soap_client();

}

//returns a config value from the site array
function ipm_get_config($config_name) {
	global $ipm_site;
	if (!empty($ipm_site)) {
		if (array_key_exists($config_name, $ipm_site)) {
			return $ipm_site[$config_name];
		}
		else {
			return false;
		}
	}
	else {
		return false;
	}
}

//sets a config value into the array
function ipm_set_config($config_name, $config_value, $update_now = FALSE) {
	global $ipm_site;
	
	$ipm_site[$config_name] = $config_value;
	
	if ($update_now) {
		ipm_save_config();
	}
}

//saves the site array back to the database
function ipm_save_config() {
	global $ipm_site;
	
	update_option('ipm_config', $ipm_site);	
}

//populates the site table with info
function ipm_create_config() {
	global $ipm_version;
	
	$site = array();
	//version 0.1/0.2/0.3
	$site['installed'] = current_time('mysql');
	$site['version'] = $ipm_version;
	$site['active'] = 0;
	$site['remote_site_soap_url'] = '';

	add_option('ipm_config', $site, 'IPManager Connector Config');
}


//add a role so that we can check if the user can "do stuff" (TM)
function ipm_add_admin_cap() {

	$role = get_role('administrator');
	$role->add_cap('ipm');

}

//link to ipmanager connector settings
function ipm_subpanel_settings_link() {
	return 'options-general.php?page=ipm.php_settings';
}

function ipm_soap_register_client_site($connect_array) {
	if (empty($connect_array['url'])) {
		$array['success']		= 0;
	}
	else {
		$register_client			= new ipm_soap($connect_array['url']);		
		$send_array['message']		= 'Wordpress Test';
		$array 						= $register_client->call('ipm_soap_echo', array($send_array));
	}
	
	return $array;
}
function ipm_soap_unregister_client_site() {
	return true;
}

//html for event viewer settings
function ipm_subpanel_settings() {
?>

<?php
	if (isset($_POST['submit']) || isset($_POST['ipm_soap_remove'])) {
		if (!IPM_LOCKDOWN) {
			if (isset($_POST['submit'])) {
				$connect_array['url'] 		= $_POST['ipm_soap_server_url'];
				
				$result 					= ipm_soap_register_client_site($connect_array);
						
				if ($result['success'] == 1) {
					$ipm_message = 'Successfully connected to IPManager server';
					
					ipm_set_config('remote_site_soap_url', $connect_array['url']);
					ipm_set_config('active', '1');
				}
				else {
					$ipm_message = 'Failed to connect to IPManager server';
					ipm_set_config('active', '0');
				}
				ipm_save_config();
				if (function_exists('btev_trigger_error')) {
					btev_trigger_error($ipm_message, E_USER_NOTICE);
				}
			}
			else if (isset($_POST['ipm_soap_remove'])) {
				if (ipm_soap_unregister_client_site()) {
					$ipm_message = 'Successfully removed IPManager connection';
					ipm_set_config('remote_site_soap_url', '');
					ipm_set_config('active', '0');
					ipm_save_config();
				}
				else {
					$ipm_message = 'Failed to remove IPManager connection';
				}
				if (function_exists('btev_trigger_error')) {
					btev_trigger_error($ipm_message, E_USER_NOTICE);
				}
			}
			
			if (function_exists('btev_trigger_error')) {
				btev_trigger_error("IPManager Connector Settings Updated.", E_USER_NOTICE);
			}
			?>
			<?php if (isset($ipm_message)) { ?>
				<div id="message" class="updated fade"><p><strong><?php echo wp_specialchars($ipm_message); ?></strong></p></div>
			<?php } ?>
		<?php
		}
		else {
			if (function_exists('btev_trigger_error')) {
				btev_trigger_error("Unauthorised Update Attempt of IPManager Connector Settings.", E_USER_WARNING);
			}
		}
	}
	?>
<div class="wrap">
	<h2>IPManager Connector Settings</h2>
	<form action="<?php echo ipm_subpanel_settings_link(); ?>" method="post">
		<table class="form-table">

			<tr valign="top">
				<?php if (ipm_get_config('active') == 0) { ?>
				<th scope="row">Connect to Server</th>
				<td>
					<fieldset>
						<p>Server SOAP URL (e.g https://ipmanager.example.net/ipm_soap.php)<br /><input name="ipm_soap_server_url" size="35"  type="text" value="<?php
							if (isset($_POST['ipm_soap_server_url'])) { 
								echo wp_specialchars($_POST['ipm_soap_server_url']);
							} ?>" /></p>
						<p class="submit"><input type="submit" name="submit" value="Submit"/></p>
					</fieldset>
				</td>
				<?php } else { ?>
				<th scope="row">Connected</th>
				<td>
					<fieldset>
						<p>Connected To Server: <?php echo wp_specialchars(ipm_get_config('remote_site_soap_url')); ?></p>
						<p class="submit"><input type="submit" name="ipm_soap_remove" value="Remove Connection"/></p>
					</fieldset>
				</td>
				<?php } ?>
			</tr>
		
		</table>
			
	</form>
	
	<div id="ipm_uninstall">
		<script type="text/javascript">
		<!--
		function ipm_uninstall() {
			if (confirm("Are you sure you wish to uninstall IPManager Connector?")){
				return true;
			}
			else{
				return false;
			}
		}
		//-->
		</script>
		<form action="<?php echo ipm_subpanel_settings_link(); ?>" method="post" onsubmit="return ipm_uninstall(this);">
		<?php
			if (function_exists('wp_nonce_field')) {
				wp_nonce_field('ipm-uninstall');
			}
			?>
			<p class="submit"><input type="submit" name="ipm_submit_uninstall" value="Uninstall" /> (This removes all IPManager settings)</p>
		</form>
	</div>
</div>
<?php
}

//basic date function. Should be able to use a wordpress one though.
function ipm_now($format = 'Y-m-d H:i:s', $add_seconds = 0) {

	$base_time = time() + $add_seconds + 3600 * get_option('gmt_offset');
	
	switch($format) {
	
		case 'Y-m-d H:i:s':
			return gmdate('Y-m-d H:i:s', $base_time);
		break;
		
		case 'H:i:s':
			return gmdate('H:i:s', $base_time);
		break;
		
		case 'Y-m-d':
			return gmdate('Y-m-d', $base_time);
		break;
		
		case 'Y':
			return gmdate('Y', $base_time);
		break;
		
		case 'm':
			return gmdate('m', $base_time);
		break;
		
		case 'd':
			return gmdate('d', $base_time);
		break;
	
	}
}

//adds the event viewer settings to the options submenu
function ipm_admin_menu_settings() {
	if (function_exists('add_options_page')) {
		add_options_page('IPM Settings', 'IPManager Connector Settings', 8, basename(__FILE__) . '_settings', 'ipm_subpanel_settings');
	}
}

/*
===========================================================================================================================================
*/
function ipm_trigger_activate_ipm() {
	if (function_exists('btev_trigger_error')) {
		btev_trigger_error('IPManager Connector activated.', E_USER_NOTICE);
	}
	return;
}
function ipm_trigger_deactivate_ipm() {
	if (!IPM_LOCKDOWN) {
		if (function_exists('ipm_trigger_error')) {
			btev_trigger_error('IPManager Connector deactivated.', E_USER_NOTICE);
		}
	}
	else {
		add_action('shutdown', 'ipm_lockdown_reactivate');
	}
	return;
}
function ipm_lockdown_reactivate() {
	//nope IPM isn't going away
	if (function_exists('btev_trigger_error')) {
		btev_trigger_error("Unauthorised Deactivation Attempt of IPManager Connector.", E_USER_WARNING);
	}
	$current = get_option('active_plugins');
	if (!isset($current[plugin_basename(__FILE__)])) {
		$current[] = plugin_basename(__FILE__);
		sort($current);
		update_option('active_plugins', $current);
	}
	return;
}
?>