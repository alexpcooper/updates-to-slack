<?php
  $test_outcome = null;
  $pageWasRefreshed = isset($_SERVER['HTTP_CACHE_CONTROL']) && sanitize_text_field($_SERVER['HTTP_CACHE_CONTROL']) === 'max-age=0';
  $page_link = (isset($_SERVER['HTTPS']) && sanitize_text_field($_SERVER['HTTPS']) === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
  if (isset($_GET) && isset($_GET['testbutton']) && sanitize_text_field($_GET['testbutton']) == 1 && !$pageWasRefreshed)
  {
	$now = new DateTime();
	$test_outcome = $this->send_test_message($now->format('H:i:s'));
  }
?>

 <div>
  <h2><?php echo sprintf('%s Settings', $this->plugin_name); ?></h2>
  <form method="post" action="options.php">
  <?php settings_fields( $this->plugin_reference.'_options_group' ); ?>
  <h3><?php echo sprintf('%s', $this->connection_name); ?> Options</h3>
  <table cellpadding="5" width="100%">
  <tr valign="top">
  <th scope="row" align="left"><label for="<?php echo $this->plugin_reference; ?>_slackurls"><?php echo $this->connection_name; ?> URL(s)</label></th>
  <td>
	  <textarea style="width: 100%;" rows="5" name="<?php echo $this->plugin_reference; ?>_slackurls"><?php echo esc_textarea(get_option($this->plugin_reference.'_slackurls')); ?></textarea>
	  <br /><em>You can send to several <a href="https://slack.com/intl/en-gb/help/articles/115005265063-Incoming-webhooks-for-Slack" target="_blank"><?php echo $this->connection_name; ?> Webhook URLs</a>, one per line.</em>
  </td>
  </tr>

  <tr valign="top">
  <th scope="row" align="left"><label for="<?php echo $this->plugin_reference; ?>_enabled"><?php echo $this->connection_name; ?> Alerts Enabled?</label></th>
  <td>
	  <select name="<?php echo $this->plugin_reference; ?>_enabled">
		  <option value="no"<?php if (strtolower(esc_attr(get_option($this->plugin_reference.'_enabled'))) == 'no') { echo ' selected'; } ?>>No</option>
		  <option value="yes"<?php if (strtolower(esc_attr(get_option($this->plugin_reference.'_enabled'))) == 'yes') { echo ' selected'; } ?>>Yes</option>
	  </select>
  </td>
  </tr>

  <tr valign="top">
  <th scope="row" align="left"><label for="<?php echo $this->plugin_reference; ?>_enabled">Site Name</label></th>
  <td>
	  <input type="text" name="<?php echo $this->plugin_reference; ?>_sitename" value="<?php echo esc_attr(get_option($this->plugin_reference.'_sitename')); ?>" style="width: 250px;" />
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
  foreach ( _get_cron_array() as $cron_time => $data )
  {
      if ( is_int( $cron_time ) ) {
          if ( 1 <= count( $data ) ){
              foreach ( $data as $cron_name => $cron_data ) {
            	  if ($cron_name == $this->cron_reference) {
                      $already_scheduled = true;

            		  $cron_dt = new DateTime("@$cron_time");
            		  $current_cron_status = 'Next trigger due at '.$cron_dt->format('Y-m-d H:i:s');
            		  break;
            	  }

              }
          }
      }
  }

  ?>

  <tr valign="top">
  <th scope="row" align="left"><label for="<?php echo $this->plugin_reference; ?>_nextruntime">Next Scheduled Run Time</label></th>
  <td>
	  <input type="date" name="<?php echo $this->plugin_reference; ?>_nextruntime_date" value="<?php if ($already_scheduled) { echo $cron_dt->format('Y-m-d'); } // get_option('updates2slack_nextruntime_date'); ?>" /> at <input type="time" name="<?php echo $this->plugin_reference; ?>_nextruntime_time" value="<?php if ($already_scheduled) { echo $cron_dt->format('H:i'); } // get_option('updates2slack_nextruntime_time'); ?>" />
	  <br /><em>Please note that this is your server's time (currently set to <?php $thistime = new DateTime(); echo $thistime->format('H:i'); ?>) and may not reflect your locality</em>
  </td>
  </tr>
  <tr valign="top">
  <th scope="row" align="left"><label for="<?php echo $this->plugin_reference; ?>_frequency">Frequency</label></th>
  <td>
	  <select name="<?php echo $this->plugin_reference; ?>_frequency">
		  <option value="daily"<?php if (esc_attr(strtolower(get_option($this->plugin_reference.'_frequency'))) == 'daily') { echo ' selected'; } ?>>Daily</option>
		  <option value="weekly"<?php if (esc_attr(strtolower(get_option($this->plugin_reference.'_frequency'))) == 'weekly') { echo ' selected'; } ?>>Weekly</option>
		  <option value="monthly"<?php if (esc_attr(strtolower(get_option($this->plugin_reference.'_frequency'))) == 'monthly') { echo ' selected'; } ?>>Monthly</option>
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
					  <pre><?php echo esc_attr(get_option($this->plugin_reference.'_lastrundt')); ?></pre>
				  </td>
			  </tr>
			  <tr valign="top">
				  <th scope="row" align="left">Last Run <?php echo $this->connection_name; ?> Response:</th>
				  <td>
					  <pre><?php echo esc_attr(get_option($this->plugin_reference.'_lastrundata')); ?></pre>
				  </td>
			  </tr>

			  <tr valign="top">
				  <th scope="row" align="left">Test</th>
				  <td>
					  <?php
					  	if (is_numeric($test_outcome)) {
						?>
						  <p style="background-color: <?php if ($test_outcome >= 200 && $test_outcome < 300) { echo ' green'; } else { echo 'red'; } ?>; color: white; padding: 5px; text-align: center;"><?php if ($test_outcome >= 200 && $test_outcome < 300) { echo $this->connection_name.' Alert Triggered'; } else { echo 'Error with Triggering Message to '.$this->connection_name; } ?></p>
				      <?php } ?>
					  <a class="button" <?php if (strlen(trim(esc_textarea(get_option($this->plugin_reference.'_slackurls')))) == 0) { echo 'href="javascript:void(0);" disabled'; } else { echo ' href="'.$page_link.'&testbutton=1"'; } ?>>Trigger <?php echo $this->connection_name; ?> Alert Now</a>
                      <?php if (strlen(trim(esc_textarea(get_option($this->plugin_reference.'_slackurls')))) == 0) { ?><p>Please save a <?php echo $this->connection_name; ?> URL before testing</p><?php } else { ?><p><em>Please ensure your settings are saved before testing</em></p><?php } ?>
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
	  <th scope="row" align="left"><label for="<?php echo $this->plugin_reference; ?>_ignore_plugins">Ignore Plugins</label></th>
	  <td><?php
	  $active_plugins = get_option('active_plugins');
	  echo '<ul>';
	  foreach($active_plugins as $key => $value) {
		  $string = explode('/',$value); // Folder name will be displayed
		  $checked = '';
		  $string[0] = strtolower($string[0]);
		  if (is_array(get_option($this->plugin_reference.'_ignore_plugins')) && in_array($string[0], get_option($this->plugin_reference.'_ignore_plugins')))
		  {
			  $checked = ' checked="checked"';
		  }
		  echo '<li>';
		  echo '<input type="checkbox" name="'.$this->plugin_reference.'_ignore_plugins[]" value="'.$string[0].'" value=""'.$checked.' /> ';
		  echo  $string[0];
		  echo '</li>';
	  }
	  echo '</ul>';
	  ?></td>
  </tr>


  <tr valign="top">
	  <th scope="row" align="left"><label for="<?php echo $this->plugin_reference; ?>_ignore_themes">Ignore Themes</label></th>
	  <td><?php
	  $site_themes = wp_get_themes();
	  echo '<ul>';
	  foreach($site_themes as $theme_key => $theme_name)
	  {
		$checked = '';
		$theme_key = strtolower($theme_key);
  		if (is_array(get_option($this->plugin_reference.'_ignore_themes')) && in_array($theme_key, get_option($this->plugin_reference.'_ignore_themes'))) {
  			$checked = ' checked="checked"';
  		}

		echo '<li>';
		echo '<input type="checkbox" name="'.$this->plugin_reference.'_ignore_themes[]" value="'.$theme_key.'" value=""'.$checked.' /> ';
		echo  $theme_name;
		echo '</li>';
	  }
	  echo '</ul>';
	  ?></td>
  </tr>


  </table>
  <?php submit_button(); ?>
  </form>
  </div>