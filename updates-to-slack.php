<?php
/**
 * Plugin Name: Updates to Slack
 * Plugin URI: https://alexpcooper.co.uk
 * Description: Sends Slack alerts about WordPress core, plugin and theme updates
 * Version: 1.0.0
 * Author: Alex Cooper
 * Author URI: https://alexpcooper.co.uk
 */

define( 'CRON_JOB_REF', 'cron_slackalerts_updates' );


 // create a scheduled event (if it does not exist already)
function cronstarter_activation($frequency = 'daily', $time = '00:00')
{
	if( !wp_next_scheduled( CRON_JOB_REF ) )
    {
	   wp_schedule_event( $time, $frequency, CRON_JOB_REF );
	}
}
// and make sure it's called whenever WordPress loads
add_action('wp', 'cronstarter_activation');


// unschedule event upon plugin deactivation
function cronstarter_deactivate()
{
	// find out when the last event was scheduled
	$timestamp = wp_next_scheduled (CRON_JOB_REF);
	// unschedule previous event if any
	wp_unschedule_event ($timestamp, CRON_JOB_REF);
}
register_deactivation_hook (__FILE__, 'cronstarter_deactivate');



function sendUpdateInfoToSlack()
{
	$now = new DateTime();

	// make sure that we can run this in the first place...
	if (strtolower(get_option('updates2slack_enabled')) != 'yes')
	{
		update_option('updates2slack_lastrundt', $now->format('Y-m-d H:i:s'), true);
		update_option('updates2slack_lastrundata', 'ERROR! Plugin configuration "Slack Alerts Enabled" hasn\'t been set to "Yes".', true);
		return false;
	}
	if (strlen(trim(get_option('updates2slack_slackurls'))) == 0)
	{
		update_option('updates2slack_lastrundt', $now->format('Y-m-d H:i:s'), true);
		update_option('updates2slack_lastrundata', 'ERROR! No Slack Webhook URLs have been confiured.', true);
		return false;
	}


    $output_string = '';


    global $wpdb;
    $table_name = $wpdb->prefix.'options';

    $sql = 'SELECT `option_name`, `option_value` FROM `'.$table_name.'` WHERE `option_name` IN ("_site_transient_update_core", "_site_transient_update_plugins", "_site_transient_update_themes") ORDER BY `option_name` ASC;';
    $updates = $wpdb->get_results( $wpdb->prepare ( $sql ) );

    foreach ($updates as $update)
    {
        $title = str_replace('_site_transient_update_', '', strtolower($update->option_name));
        $detail = unserialize($update->option_value);

        $updates = '';
        if ($title == 'core')
        {
            if (! $detail->updates[0]->current == $detail->updates[0]->version)
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
        				"text": "WordPress Core version '.$detail->updates[0]->version.' is ready to be installed"
        			}
        		},';
            }
        }
        elseif ($title == 'plugins')
        {
            if ($detail->response)
            {
				$updates = '';
				$found_updates = 0;

				foreach ($detail->response as $plugin)
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

           }
        }
        elseif ($title == 'themes')
        {
            if ($detail->response)
            {
                $updates = '';
				$found_updates = 0;
            	foreach ($detail->response as $theme)
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

			}
        }

    }


    if (strlen($output_string) > 0)
    {
        $site_name = get_option('updates2slack_sitename');
        if (strlen(trim($site_name)) == 0)
        {
            $site_name = get_bloginfo('name');
        }

        $data = '{
	           "blocks": [
                   {
                       "type": "section",
                       "text": {
                           "type": "mrkdwn",
                           "text": "There are a number of updates available on the *'.$site_name.'* WordPress site",
                       }
                   },';

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
                                "url": "'.get_bloginfo('url').'/wp-admin/"
            				}
            			]
            		}
            	]
            }';

		$plugin_output = '';
		foreach (explode("\n", get_option('updates2slack_slackurls')) as $slack_url)
		{
			$plugin_output .=  'Slack: '.$slack_url."\n";

	        $ch = curl_init($slack_url);
	        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
	        curl_setopt($ch,CURLOPT_HEADER, false);
	        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,TRUE);
	        curl_setopt($ch,CURLOPT_POST, 1);
	        curl_setopt($ch,CURLOPT_POSTFIELDS, $data);

	        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

	        $output = curl_exec($ch);

			$plugin_output .=  'Outcome: '.$output."\n";

	        if (curl_errno($ch))
			{
	            echo curl_error($ch);
				$plugin_output .=  'Outcome: Error - '.curl_error($ch)."\n";
	        }
	        curl_close($ch);

			$plugin_output .=  "\n";
		}

		update_option('updates2slack_lastrundt', $now->format('Y-m-d H:i:s'));
		update_option('updates2slack_lastrundata', $plugin_output);

        return true;
    }
    else
    {
        update_option('updates2slack_lastrundt', $now->format('Y-m-d H:i:s'));
		update_option('updates2slack_lastrundata', 'No updates available');
    }



}

// hook that function onto our scheduled event:
add_action (CRON_JOB_REF, 'sendUpdateInfoToSlack');











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

	add_option('updates2slack_lastrundt', '');
	add_option('updates2slack_lastrundata', '');

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
	  <?php /* <input type="text" id="updates2slack_option_name" name="updates2slack_option_name" value="<?php echo get_option('updates2slack_option_name'); ?>" /> */ ?>
	  <textarea style="width: 100%;" rows="5" name="updates2slack_slackurls"><?php echo get_option('updates2slack_slackurls'); ?></textarea>
	  <br /><em>You can send to several <a href="https://slack.com/intl/en-gb/help/articles/115005265063-Incoming-webhooks-for-Slack" target="_blank">Slack Webhook URLs</a>, one per line.</em>
  </td>
  </tr>

  <tr valign="top">
  <th scope="row" align="left"><label for="updates2slack_enabled">Slack Alerts Enabled?</label></th>
  <td>
	  <select name="updates2slack_enabled">
		  <option value="no"<?php if (get_option('updates2slack_enabled') == 'no') { echo ' selected'; } ?>>No</option>
		  <option value="yes"<?php if (get_option('updates2slack_enabled') == 'yes') { echo ' selected'; } ?>>Yes</option>
	  </select>
  </td>
  </tr>

  <tr valign="top">
  <th scope="row" align="left"><label for="updates2slack_enabled">Site Name</label></th>
  <td>
	  <input type="text" name="updates2slack_sitename" value="<?php echo get_option('updates2slack_sitename'); ?>" style="width: 250px;" />
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
	  if (key($cron_name) == 'cron_slackalerts_updates')
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
		  <option value="daily"<?php if (get_option('updates2slack_frequency') == 'daily') { echo ' selected'; } ?>>Daily</option>
		  <option value="weekly"<?php if (get_option('updates2slack_frequency') == 'weekly') { echo ' selected'; } ?>>Weekly</option>
		  <option value="monthly"<?php if (get_option('updates2slack_frequency') == 'monthly') { echo ' selected'; } ?>>Monthly</option>
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
					  <pre><?php echo get_option('updates2slack_lastrundt'); ?></pre>
				  </td>
			  </tr>
			  <tr valign="top">
				  <th scope="row" align="left">Last Run Slack Response:</th>
				  <td>
					  <pre><?php echo get_option('updates2slack_lastrundata'); ?></pre>
				  </td>
			  </tr>

			  <tr valign="top">
				  <th scope="row" align="left">Test</th>
				  <td>
					  <?php
					  	$pageWasRefreshed = isset($_SERVER['HTTP_CACHE_CONTROL']) && $_SERVER['HTTP_CACHE_CONTROL'] === 'max-age=0';
					  	if (isset($_GET) && isset($_GET['testbutton']) && $_GET['testbutton'] == 1 && !$pageWasRefreshed)
						{
						?>
						  <p style="background-color: green; color: white; padding: 5px; text-align: center;">Slack Alert Triggered</p>
						  <?php sendUpdateInfoToSlack(); ?>
				      <?php } ?>
					  <?php $page_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?>
					  <a class="button" href="<?php echo $page_link; ?>&testbutton=1">Trigger Slack Alert Now</a>
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
		  if (in_array($string[0], get_option('updates2slack_ignore_plugins')))
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
	  foreach($site_themes as $key => $value)
	  {
		$checked = '';
		$value = strtolower($value);
  		if (in_array($value, get_option('updates2slack_ignore_themes')))
  		{
  			$checked = ' checked="checked"';
  		}

		echo '<li>';
		echo '<input type="checkbox" name="updates2slack_ignore_themes[]" value="'.$value.'" value=""'.$checked.' /> ';
		echo  $value;
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
		update_option( 'updates2slack_slackurls', 	$_POST['updates2slack_slackurls'] );
		update_option( 'updates2slack_enabled', 	$_POST['updates2slack_enabled'] );
        update_option( 'updates2slack_sitename', 	$_POST['updates2slack_sitename'] );

		update_option( 'updates2slack_nextruntime_date', $_POST['updates2slack_nextruntime_date']);
		update_option( 'updates2slack_nextruntime_time', $_POST['updates2slack_nextruntime_time']);
		update_option( 'updates2slack_frequency', 		 $_POST['updates2slack_frequency']);

		$ignore_plugins = array();
		foreach ($_POST['updates2slack_ignore_plugins'] as $plugin)
		{
			$ignore_plugins[] = $plugin;
		}
		update_option( 'updates2slack_ignore_plugins', $ignore_plugins);

		$ignore_themes = array();
		foreach ($_POST['updates2slack_ignore_themes'] as $theme)
		{
			$ignore_themes[] = $theme;
		}
		update_option( 'updates2slack_ignore_themes', $ignore_themes);


		if ($_POST['updates2slack_nextruntime_time'] != '')
		{
			$new_dt = new DateTime($_POST['updates2slack_nextruntime_date'].' '.$_POST['updates2slack_nextruntime_time']);

			cronstarter_deactivate();
			cronstarter_activation($_POST['updates2slack_frequency'], $new_dt->getTimestamp());
		}

	}
}
