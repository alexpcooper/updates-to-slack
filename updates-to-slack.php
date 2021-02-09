<?php
/**
 * Plugin Name:         Updates to Slack
 * Plugin URI:
 * Description:         Sends Slack alerts about WordPress core, plugin and theme updates
 * Version:             1.4.1
 * Author:              Alex Cooper
 * Author URI:          https://alexpcooper.co.uk/
 * License:             GPL v2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 */

define( 'CRON_JOB_REF_UPDATES2SLACK', 'cron_slackalerts_updates' );


 // create a scheduled event (if it does not exist already)
function updates2slack_cronstarter_activation($frequency = 'daily', $time = '00:00')
{
	if( !wp_next_scheduled( CRON_JOB_REF_UPDATES2SLACK ) )
    {
	   wp_schedule_event( $time, $frequency, CRON_JOB_REF_UPDATES2SLACK );
	}
}
// and make sure it's called whenever WordPress loads
add_action('wp', 'updates2slack_cronstarter_activation');


// unschedule event upon plugin deactivation
function updates2slack_cronstarter_deactivate()
{
	// find out when the last event was scheduled
	$timestamp = wp_next_scheduled (CRON_JOB_REF_UPDATES2SLACK);
	// unschedule previous event if any
	wp_unschedule_event ($timestamp, CRON_JOB_REF_UPDATES2SLACK);
}
register_deactivation_hook (__FILE__, 'updates2slack_cronstarter_deactivate');



function updates2slack_sendUpdateInfoToSlack($test_mode = 0)
{
	$now = new DateTime();

	// make sure that we can run this in the first place...
	if (strtolower(get_option('updates2slack_enabled')) != 'yes')
	{
		update_option('updates2slack_lastrundt', $now->format('Y-m-d H:i:s'), false);
		update_option('updates2slack_lastrundata', 'ERROR! Plugin configuration "Slack Alerts Enabled" hasn\'t been set to "Yes".', false);
		return false;
	}
	if (strlen(trim(get_option('updates2slack_slackurls'))) == 0)
	{
		update_option('updates2slack_lastrundt', $now->format('Y-m-d H:i:s'), false);
		update_option('updates2slack_lastrundata', 'ERROR! No Slack Webhook URLs have been configured.', false);
		return false;
	}


    $output_string = '';


    $updates_for_core = get_option('_site_transient_update_core');

    $updates = '';
    if ($updates_for_core->updates[0]->current != $updates_for_core->version_checked)
    {
        $output_string .= '{
             "type": "divider"
         },{
             "type": "section",
             "text": {
                 "type": "mrkdwn",
                 "text": "*WordPress Core*"
             }
         },';

        $output_string .= '{
			"type": "section",
			"text": {
				"type": "mrkdwn",
				"text": "WordPress Core version '.$updates_for_core->updates[0]->version.' is ready to be installed (currently on version '.$updates_for_core->version_checked.')"
			}
		},';
    }


    // check on plugins
    $updates_for_plugins = get_option('_site_transient_update_plugins');

	$updates = '';
	$found_updates = 0;

	foreach ($updates_for_plugins->response as $plugin)
    {
		if (!in_array(strtolower($plugin->slug), get_option('updates2slack_ignore_plugins')))
		{
            $updates .= '{
    			"type": "section",
    			"text": {
    				"type": "mrkdwn",
    				"text": "*'.$plugin->slug.'* is ready for version '.$plugin->new_version.'"
    			}
    		},';

			++$found_updates;
        }
	}

	if ($found_updates > 0)
	{

        $output_string .= '{
             "type": "divider"
         },{
             "type": "section",
             "text": {
                 "type": "mrkdwn",
                 "text": "*Plugins*"
             }
         },';

		 $output_string .= $updates;
	}


   // check on themes
   $updates_for_themes = get_option('_site_transient_update_themes');

    $updates = '';
	$found_updates = 0;
	foreach ($updates_for_themes->response as $theme)
    {
		if (!in_array(strtolower($theme["theme"]), get_option('updates2slack_ignore_themes')))
		{
            $updates .= '{
    			"type": "section",
    			"text": {
    				"type": "mrkdwn",
    				"text": "*'.$theme["theme"].'* is ready for version '.$theme["new_version"].'"
    			}
    		},';

			++$found_updates;
		}
    }

	if ($found_updates > 0)
	{
		$output_string .= '{
             "type": "divider"
         },{
             "type": "section",
             "text": {
                 "type": "mrkdwn",
                 "text": "*Themes*"
             }
         },';

		 $output_string .= $updates;
	}





    if (strlen($output_string) > 0 || $test_mode == 1)
    {
        $site_name = get_option('updates2slack_sitename');
        if (strlen(trim($site_name)) == 0)
        {
            $site_name = get_bloginfo('name');
        }

        if ($test_mode == 1 && strlen($output_string) == 0)
        {
            $data = '{
	           "blocks": [
                   {
                       "type": "section",
                       "text": {
                           "type": "mrkdwn",
                           "text": "This is a test message sent from the *'.$site_name.'* WordPress site",
                       }
                   },';
        }
        else
        {
            $data = '{
	           "blocks": [
                   {
                       "type": "section",
                       "text": {
                           "type": "mrkdwn",
                           "text": "There are a number of updates available on the *'.$site_name.'* WordPress site",
                       }
                   },';
        }

        $data .= $output_string;

        $data .= '{
                		"type": "divider"
            		},
                    {
            			"type": "actions",
            			"elements": [
            				{
            					"type": "button",
            					"text": {
            						"type": "plain_text",
            						"text": "Go to '.$site_name.'",
            						"emoji": true
            					},
            					"value": "click_for_website",
                                "url": "'.get_admin_url().'"
            				}
            			]
            		}
            	]
            }';

		$plugin_output = '';
		foreach (explode("\n", get_option('updates2slack_slackurls')) as $slack_url)
		{
			$plugin_output .=  'Slack: '.esc_url($slack_url)."\n";

            $args = array(
                'method'      => 'POST',
                'body'        => $data,
                'timeout'     => '30',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => array(),
                'cookies'     => array(),
            );

            $output = wp_remote_post( esc_url($slack_url), $args );

            $plugin_output .=  'Outcome: ';

            if (array_key_exists('response', $output))
            {
                if (array_key_exists('message', $output["response"]))
                {
                    $plugin_output .= $output["response"]["message"];
                }

                $plugin_output .= ' (HTTP '.$output["response"]["code"].')';
            }
            elseif (array_key_exists('body', $output))
            {
                $plugin_output .=  'Outcome: '.$output["body"];
            }

            if ( is_wp_error( $output ) )
            {
                $plugin_output .=  'Outcome: Error - '.$output->get_error_message()."\n";
            }

            $plugin_output .= "\n";
		}

		update_option('updates2slack_lastrundt', $now->format('Y-m-d H:i:s'), false);
		update_option('updates2slack_lastrundata', esc_attr($plugin_output), false);

        return true;
    }
    else
    {
        update_option('updates2slack_lastrundt', $now->format('Y-m-d H:i:s'), false);
		update_option('updates2slack_lastrundata', 'No updates available', false);
    }

    $GLOBALS['wp_object_cache']->delete( 'updates2slack_lastrundt', 'options' );
    $GLOBALS['wp_object_cache']->delete( 'updates2slack_lastrundata', 'options' );

}

// hook that function onto our scheduled event:
add_action (CRON_JOB_REF_UPDATES2SLACK, 'updates2slack_sendUpdateInfoToSlack');











function updates2slack_register_settings()
{
	add_option( 'updates2slack_settings', '');
	add_option( 'updates2slack_enabled', 'no');
    add_option( 'updates2slack_sitename', '');

	add_option( 'updates2slack_nextruntime_date', '');
	add_option( 'updates2slack_nextruntime_time', '');
	add_option( 'updates2slack_frequency', 'daily');

	add_option( 'updates2slack_ignore_plugins', '');
	add_option( 'updates2slack_ignore_themes', '');

	add_option('updates2slack_lastrundt', '', null, false);
	add_option('updates2slack_lastrundata', '', null, false);

   register_setting( 'updates2slack_options_group', 'updates2slack_option_name', 'updates2slack_callback' );
}
add_action( 'admin_init', 'updates2slack_register_settings' );


function updates2slack_register_options_page()
{
  add_options_page('Updates to Slack', 'Updates to Slack', 'manage_options', 'updates2slack', 'updates2slack_options_page');
}
add_action('admin_menu', 'updates2slack_register_options_page');


function updates2slack_options_page()
{
?>
<?php
  $pageWasRefreshed = isset($_SERVER['HTTP_CACHE_CONTROL']) && sanitize_text_field($_SERVER['HTTP_CACHE_CONTROL']) === 'max-age=0';
  $page_link = (isset($_SERVER['HTTPS']) && sanitize_text_field($_SERVER['HTTPS']) === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
  if (isset($_GET) && isset($_GET['testbutton']) && sanitize_text_field($_GET['testbutton']) == 1 && !$pageWasRefreshed)
  {
      updates2slack_sendUpdateInfoToSlack(1);
      header('Location: '.$page_link);
  }
?>

 <div>
  <?php screen_icon(); ?>
  <h2>Updates to Slack Settings</h2>
  <form method="post" action="options.php">
  <?php settings_fields( 'updates2slack_options_group' ); ?>
  <h3>Slack Options</h3>
  <table cellpadding="5" width="100%">
  <tr valign="top">
  <th scope="row" align="left"><label for="updates2slack_slackurls">Slack URL(s)</label></th>
  <td>
	  <textarea style="width: 100%;" rows="5" name="updates2slack_slackurls"><?php echo esc_textarea(get_option('updates2slack_slackurls')); ?></textarea>
	  <br /><em>You can send to several <a href="https://slack.com/intl/en-gb/help/articles/115005265063-Incoming-webhooks-for-Slack" target="_blank">Slack Webhook URLs</a>, one per line.</em>
  </td>
  </tr>

  <tr valign="top">
  <th scope="row" align="left"><label for="updates2slack_enabled">Slack Alerts Enabled?</label></th>
  <td>
	  <select name="updates2slack_enabled">
		  <option value="no"<?php if (strtolower(esc_attr(get_option('updates2slack_enabled'))) == 'no') { echo ' selected'; } ?>>No</option>
		  <option value="yes"<?php if (strtolower(esc_attr(get_option('updates2slack_enabled'))) == 'yes') { echo ' selected'; } ?>>Yes</option>
	  </select>
  </td>
  </tr>

  <tr valign="top">
  <th scope="row" align="left"><label for="updates2slack_enabled">Site Name</label></th>
  <td>
	  <input type="text" name="updates2slack_sitename" value="<?php echo esc_attr(get_option('updates2slack_sitename')); ?>" style="width: 250px;" />
      <br /><em>Used to identify the site in the Slack alert. If left blank, the site name will be taken from the <a href="/wp-admin/options-general.php">site's General Settings</a>.</em>
  </td>
  </tr>


  <tr valign="top">
    <td colspan="2">
  	  <hr>
  	  <h3>Scheduling</h3>
  </td>
  </tr>
  <?php

  $current_cron_status = 'Not scheduled';
  $already_scheduled = false;
  foreach (_get_cron_array() as $cron_time => $cron_name)
  {
	  if (key($cron_name) == CRON_JOB_REF_UPDATES2SLACK)
	  {
          $already_scheduled = true;

		  $cron_dt = new DateTime("@$cron_time");
		  $current_cron_status = 'Next trigger due at '.$cron_dt->format('Y-m-d H:i:s');
		  break;
	  }
  }

  ?>

  <tr valign="top">
  <th scope="row" align="left"><label for="updates2slack_nextruntime">Next Scheduled Run Time</label></th>
  <td>
	  <input type="date" name="updates2slack_nextruntime_date" value="<?php if ($already_scheduled) { echo $cron_dt->format('Y-m-d'); } // get_option('updates2slack_nextruntime_date'); ?>" /> at <input type="time" name="updates2slack_nextruntime_time" value="<?php if ($already_scheduled) { echo $cron_dt->format('H:i'); } // get_option('updates2slack_nextruntime_time'); ?>" />
	  <br /><em>Please note that this is your server's time (currently set to <?php $thistime = new DateTime(); echo $thistime->format('H:i'); ?>) and may not reflect your locality</em>
  </td>
  </tr>
  <tr valign="top">
  <th scope="row" align="left"><label for="updates2slack_frequency">Frequency</label></th>
  <td>
	  <select name="updates2slack_frequency">
		  <option value="daily"<?php if (esc_attr(strtolower(get_option('updates2slack_frequency'))) == 'daily') { echo ' selected'; } ?>>Daily</option>
		  <option value="weekly"<?php if (esc_attr(strtolower(get_option('updates2slack_frequency'))) == 'weekly') { echo ' selected'; } ?>>Weekly</option>
		  <option value="monthly"<?php if (esc_attr(strtolower(get_option('updates2slack_frequency'))) == 'monthly') { echo ' selected'; } ?>>Monthly</option>
	   </select>
  </td>
  </tr>




    <tr valign="top">
  	  <td colspan="2">
  		  <hr>
  		  <h3>Reporting</h3>
  		  <table cellpadding="5" width="100%">
			  <tr valign="top">
				  <th scope="row" align="left" width="20%">Last Run:</th>
				  <td>
					  <pre><?php echo esc_attr(get_option('updates2slack_lastrundt')); ?></pre>
				  </td>
			  </tr>
			  <tr valign="top">
				  <th scope="row" align="left">Last Run Slack Response:</th>
				  <td>
					  <pre><?php echo esc_attr(get_option('updates2slack_lastrundata')); ?></pre>
				  </td>
			  </tr>

			  <tr valign="top">
				  <th scope="row" align="left">Test</th>
				  <td>
					  <?php
					  	$pageWasRefreshed = isset($_SERVER['HTTP_CACHE_CONTROL']) && sanitize_text_field($_SERVER['HTTP_CACHE_CONTROL']) === 'max-age=0';
					  	if (isset($_GET) && isset($_GET['testbutton']) && sanitize_text_field($_GET['testbutton']) == 1 && !$pageWasRefreshed)
						{
						?>
						  <p style="background-color: green; color: white; padding: 5px; text-align: center;">Slack Alert Triggered</p>
				      <?php } ?>
					  <a class="button" <?php if (strlen(trim(esc_textarea(get_option('updates2slack_slackurls')))) == 0) { echo 'href="javascript:void(0);" disabled'; } else { echo ' href="'.$page_link.'&testbutton=1"'; } ?>>Trigger Slack Alert Now</a>
                      <?php if (strlen(trim(esc_textarea(get_option('updates2slack_slackurls')))) == 0) { ?><p>Please save a Slack URL before testing</p><?php } else { ?><p><em>Please ensure your settings are saved before testing</em></p><?php } ?>
				  </td>
			  </tr>
		  </table>
  	  </td>
    </tr>



  <tr valign="top">
	  <td colspan="2">
		  <hr>
		  <h3>Ignore Plugins and Themes</h3>
		  <p>You can prevent alerts from including specific plugins and themes, by ticking the relevant boxes below.</p>
	  </td>
  </tr>

  <tr valign="top">
	  <th scope="row" align="left"><label for="updates2slack_ignore_plugins">Ignore Plugins</label></th>
	  <td><?php
	  $active_plugins = get_option('active_plugins');
	  echo '<ul>';
	  foreach($active_plugins as $key => $value)
	  {
		  $string = explode('/',$value); // Folder name will be displayed
		  $checked = '';
		  $string[0] = strtolower($string[0]);
		  if (is_array(get_option('updates2slack_ignore_plugins')) && in_array($string[0], get_option('updates2slack_ignore_plugins')))
		  {
			  $checked = ' checked="checked"';
		  }
		  echo '<li>';
		  echo '<input type="checkbox" name="updates2slack_ignore_plugins[]" value="'.$string[0].'" value=""'.$checked.' /> ';
		  echo  $string[0];
		  echo '</li>';
	  }
	  echo '</ul>';
	  ?></td>
  </tr>


  <tr valign="top">
	  <th scope="row" align="left"><label for="updates2slack_ignore_themes">Ignore Themes</label></th>
	  <td><?php
	  $site_themes = wp_get_themes();
	  echo '<ul>';
	  foreach($site_themes as $key => $site_theme)
	  {
		$checked = '';
		$site_theme = strtolower($site_theme);
  		if (is_array(get_option('updates2slack_ignore_themes')) && in_array($site_theme, get_option('updates2slack_ignore_themes')))
  		{
  			$checked = ' checked="checked"';
  		}

		echo '<li>';
		echo '<input type="checkbox" name="updates2slack_ignore_themes[]" value="'.$site_theme.'" value=""'.$checked.' /> ';
		echo  $site_theme;
		echo '</li>';
	  }
	  echo '</ul>';
	  ?></td>
  </tr>


  </table>
  <?php  submit_button(); ?>
  </form>
  </div>
<?php
}

if( !function_exists("updates2slack_callback") )
{
	function updates2slack_callback()
	{
		update_option( 'updates2slack_slackurls',     sanitize_textarea_field($_POST['updates2slack_slackurls']) );
		update_option( 'updates2slack_enabled',       updates2slack_sanitize_select_option( sanitize_text_field($_POST['updates2slack_enabled']), array("no", "yes") ) );
        update_option( 'updates2slack_sitename',      sanitize_text_field($_POST['updates2slack_sitename']) );

		update_option( 'updates2slack_nextruntime_date', sanitize_text_field($_POST['updates2slack_nextruntime_date']) );
		update_option( 'updates2slack_nextruntime_time', sanitize_text_field($_POST['updates2slack_nextruntime_time']) );

        $cron_frequency = updates2slack_sanitize_select_option( sanitize_text_field($_POST['updates2slack_frequency']), array("daily", "weekly", "monthly") );
		update_option( 'updates2slack_frequency', $cron_frequency );

		$ignore_plugins = array();
        if (isset($_POST['updates2slack_ignore_plugins']) && is_array($_POST['updates2slack_ignore_plugins']))
        {
    		foreach ($_POST['updates2slack_ignore_plugins'] as $plugin)
    		{
    			$ignore_plugins[] = sanitize_text_field($plugin);
    		}
    		update_option( 'updates2slack_ignore_plugins', $ignore_plugins);
        }

		$ignore_themes = array();
        if (isset($_POST['updates2slack_ignore_themes']) && is_array($_POST['updates2slack_ignore_themes']))
        {
    		foreach ($_POST['updates2slack_ignore_themes'] as $theme)
    		{
    			$ignore_themes[] = sanitize_text_field($theme);
    		}
    		update_option( 'updates2slack_ignore_themes', $ignore_themes);
        }


		if (sanitize_text_field($_POST['updates2slack_nextruntime_time']) != '')
		{
			$new_dt = new DateTime(sanitize_text_field($_POST['updates2slack_nextruntime_date']).' '.sanitize_text_field($_POST['updates2slack_nextruntime_time']));

			updates2slack_cronstarter_deactivate();
			updates2slack_cronstarter_activation($cron_frequency, $new_dt->getTimestamp());
		}

	}
}

// function used to check that POSTed values are within an expected list of possible values (case insensitive)
function updates2slack_sanitize_select_option($posted_value, $possible_values = array())
{
    // if there are valid values to check against...
    if (count($possible_values) > 0)
    {
        if (in_array(strtolower(trim($posted_value)), array_map('strtolower', $possible_values) ))
        {
            // value verified; return that value
            return trim($posted_value);
        }
        else
        {
            // if it fails, return the first allowed value
            return $possible_values[0];
        }
    }
    else
    {
        // return blank
        return '';
    }
}
