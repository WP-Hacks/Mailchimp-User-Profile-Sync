<?php
/*
Plugin Name: Mailchimp User Profile Sync
Plugin URI: http://wphacks.org/plugin/mailchimp-user-profile
Description: 
Version: 0.0.1
Author: Michael Fitzpatrick-Ruth
Author URI: http://wphacks.org

*/
//include_once('includes/wp-hacks-standard-includes.php');
/*
TODO: Admin/other user changes should default to no notification of change - allow override
TODO: Shortcode to use outside of My Profile Page
TODO: Use Transients to save subscription for a little bit.
TODO: Hide API Key from Anyone but the user who submitted it.
*/

add_action('admin_init','mups_settings_api_init');
function mups_settings_api_init(){
	add_settings_section('mups_mailchimp_user_profile_sync_api_key_section','Mailchimp User Profile Sync Options', 'mups_mailchimp_user_profile_sync_settings_section_callback' /* callback */, 'mailchimp_user_profile_sync');
	add_settings_field('mailchimp_user_profile_sync_api_key','Mailchimp API Key','mups_mailchimp_user_profile_sync_api_key_settings_callback' /* callback */,'mailchimp_user_profile_sync','mups_mailchimp_user_profile_sync_api_key_section');
	register_setting('mailchimp_user_profile_sync','mailchimp_user_profile_sync_api_key','mups_mailchimp_user_profile_sync_sanitize_api_key');
}

function mups_mailchimp_user_profile_sync_settings_section_callback(){
}

function mups_mailchimp_user_profile_sync_api_key_settings_callback(){
	if(!defined('MAILCHIMP_USER_PROFILE_SYNC_API_KEY')){ //TODO: remove !
		echo '<input type="text" disabled class="regular-text" placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-usX">';
		?><p class="description"><code>MAILCHIMP_USER_PROFILE_SYNC_API_KEY</code> has been defined and cannot be changed.</p> <?php
	} else {
		echo '<input type="text" required="" maxlength="37" name="mailchimp_user_profile_sync_api_key" placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-usX" class="regular-text" value="'. esc_attr(get_option('mailchimp_user_profile_sync_api_key')) .'">';
		?><p class="description"><a target="_blank" href="https://admin.mailchimp.com/account/api-key-popup">Get Your Mailchimp API Key</a></p><?php
	}
}
function mups_mailchimp_user_profile_sync_sanitize_api_key($value){
	return substr(strip_tags(trim($value)),0,37);
}


add_action('admin_menu','mups_registar_settings_page_mailchimp_user_profile_sync');

function mups_registar_settings_page_mailchimp_user_profile_sync(){
	//add_menu_page('Dashboard - WP CRM', 'WP CRM','manage_options','wpcrm','wpcrm_display_page_wpcrm','','100');
	add_options_page('Mailchimp User Profile Sync', 'Mailchimp Sync','manage_options', 'mailchimp_user_profile_sync', 'mups_display_page_mailchimp_user_profile_sync');
}
function mups_display_page_mailchimp_user_profile_sync(){
?>
<form method="POST" action="options.php">
<?php settings_fields( 'mailchimp_user_profile_sync' );
do_settings_sections( 'mailchimp_user_profile_sync' );
submit_button();
?>
</form>
<?php
}

add_shortcode('mailchimp_user_profile_sync','mups_shortcode_mailchimp_user_profile_sync');
function mups_shortcode_mailchimp_user_profile_sync( $atts, $content, $tag ){
	$defaults = array(
	'display' => 'div', //valid, 'div' or 'table'
	);
	$merged_atts = shortcode_atts($defaults, $atts);
	/*
	validate atts
	*/
	extract($merged_atts);
	
	return;
}



function get_mailchimp_user_profile_sync_api_key(){
	if(defined('MAILCHIMP_USER_PROFILE_SYNC_API_KEY')){
		return apply_filters('mailchimp_user_profile_sync_api_key',MAILCHIMP_USER_PROFILE_SYNC_API_KEY);
	} else {
		return apply_filters('mailchimp_user_profile_sync_api_key',get_option('mailchimp_user_profile_sync_api_key'));
	}
}













include_once('mailchimp-mailchimp-api-php-db2a5b7264e8/src/Mailchimp.php');

add_action( 'show_user_profile', 'mups_view_mailchimp_user_profile_form' );
add_action( 'edit_user_profile', 'mups_view_mailchimp_user_profile_form' );

function mups_view_mailchimp_user_profile_form($user){
$section_name = "Manage Subscriptions";
$mc_show_private_lists = true;
$mc = new Mailchimp(get_mailchimp_user_profile_sync_api_key());
$mc_lists = new Mailchimp_Lists($mc);
$mc_all_lists = $mc_lists->getList();
?>
        <h3><?php echo $section_name; ?></h3>

        <table class="form-table">
			<tr>
			<th>
			</th>
			<td>
            <?php
				foreach($mc_all_lists['data'] as $intival=>$list_data){
						/*
						echo "<tr>\n";
							echo "<th>";
								
							echo "</th>";
						echo "<td>\n";
						*/
							$emails = array(
							);
							$emails[] = array('email'=>$user->user_email);
							$member_info = $mc_lists->memberInfo($list_data['id'],$emails);
							
							
							echo '<input type="checkbox" name="mailchimp_lists['. $list_data['id'] .']" '.  checked($member_info['success_count'],1,false) .' >'."\n";
							echo '<label for="">'. $list_data['name'] ."</label>\n";
							echo '<br>';
							//array('email'=>get_user_meta('email')));
							/*
							echo '<pre>';
								print_r($member_info);
							echo '</pre>';
							*/
						//echo "</td>\n";
					//echo "</tr>\n";
				}
            ?>
            </td>
            </tr>
        </table>
    <?php
}




function mups_save_mailchimp_user_profile_form($user_id){
	if ( !current_user_can( 'edit_user', $user_id ) ){
			return FALSE;
	}

	$user = get_user_by( 'id', $user_id );
	
	$mc = new Mailchimp(get_mailchimp_user_profile_sync_api_key());
	$mc_lists = new Mailchimp_Lists($mc);
			
			
	//update_usermeta( $user_id, 'mailchimp_lists', $_POST['mailchimp_lists'] );
	foreach($_POST['mailchimp_lists'] as $list_id=>$data){
		if(is_array($data)){
		
		} else {
			//http://apidocs.mailchimp.com/api/2.0/lists/subscribe.php
			/*
			echo $list_id;
			echo '<pre>';
			*/
			$raw_merge_field = $mc_lists->mergeVars(array($list_id));
			$merge_fields = array();
			/*
			print_r($raw_merge_field);
			echo '</pre>';
			*/
			if(!empty($raw_merge_field)){
			foreach($raw_merge_field as $array=>$data){
				print_r($data[0]['name']);
				if(is_array($data[0]['marge_vars'])){
					foreach($data[0]['merge_vars'] as $id=>$merge_var){
						if(!empty($merge_var['req'])){
							switch($merge_var['tag']){
								default:
									//apply_filters('wp_hacks_mailchimp_user_profile_'. $list_id .'_'. $merge_vars['tag'], );
									//apply_filters('wp_hacks_mailchimp_user_profile_'. $merge_vars['tag'], );
									$merge_fields[$merge_var['tag']] = NULL;
								break;
							}
						}
					}
				}
			}
			//$subscribe_results = $mc_lists->subscribe($list_id, array('email'=>$user->user_email), $merge_fields,  'html', false, false, false, true);
			//print_r($subscribe_results);
			}
			//echo "<hr><br>";
		}
	}
	//die();
}

add_action( 'personal_options_update', 'mups_save_mailchimp_user_profile_form',100);
add_action( 'edit_user_profile_update', 'mups_save_mailchimp_user_profile_form',100);
?>