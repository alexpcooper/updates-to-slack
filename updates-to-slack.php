<?php
/**
 * Plugin Name:         Updates to Slack
 * Plugin URI:
 * Description:         Sends Slack alerts about WordPress core, plugin and theme updates
 * Version:             2.1.0
 * Author:              Alex Cooper
 * Author URI:          https://alexpcooper.co.uk/
 * License:             GPL v2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Updates_To_Slack_Plugin' ) ) {

    class Updates_To_Slack_Plugin {
        private $plugin_name;
        private $connection_name;
        private $plugin_reference;
        private $cron_reference;
        private $support_url;

        public function __construct() {

            $this->plugin_name      = 'Updates to Slack';
            $this->connection_name  = 'Slack';
            $this->plugin_reference = 'updates2slack';
            $this->cron_reference   = 'cron_slackalerts_updates';
            $this->support_url      = 'https://alexpcooper.co.uk/wordpress-plugin/updates-to-slack/';

            
            add_action( 'init',                             array( $this, 'schedule_cron_check' ) );
            add_action( 'admin_init',                       array( $this, 'register_settings' ) );
            add_action( 'admin_menu',                       array( $this, 'register_options_page') );
            add_action( $this->cron_reference,              array( $this, 'check_for_updates') );

            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'register_plugin_link') );

            add_action('admin_footer', array( $this, 'register_admin_footer' ) );
        }

        public function register_settings() {
            add_option( $this->plugin_reference.'_slackurls', '' );
            add_option( $this->plugin_reference.'_settings', '' );
            add_option( $this->plugin_reference.'_enabled', 'no' );
            add_option( $this->plugin_reference.'_sitename', '' );
        
            add_option( $this->plugin_reference.'_nextruntime_date', '' );
            add_option( $this->plugin_reference.'_nextruntime_time', '' );
            add_option( $this->plugin_reference.'_frequency', 'daily' );
        
            add_option( $this->plugin_reference.'_ignore_plugins', '' );
            add_option( $this->plugin_reference.'_ignore_themes', '' );
        
            add_option( $this->plugin_reference.'_lastrundt', '', null, false );
            add_option( $this->plugin_reference.'_lastrundata', '', null, false );

            register_setting( $this->plugin_reference.'_options_group', $this->plugin_reference.'_option_name', array($this, 'options_page_callback') );

            register_deactivation_hook (__FILE__, array($this, 'unschedule_cron_check'));
        }


        public function register_plugin_link($links) {
            $links[] = '<a href="'.admin_url('options-general.php?page='.$this->plugin_reference).'">'. esc_html('Settings', $this->plugin_reference).'</a>';
            $links[] = '<a href="'.$this->support_url.'">'. esc_html('Support', $this->plugin_reference).'</a>';

            return $links;
        }

        public function register_admin_footer() {
            if (strpos($_SERVER['REQUEST_URI'], $this->plugin_reference) !== false) {
                echo sprintf('<p class="alignright" style="padding: 0 1em 2em 0;">%s | <a href="%s" target="_blank">Feedback, Help &amp; Support</a></p>', $this->plugin_name, $this->support_url);
            }
        }

        public function register_options_page() {
            add_options_page( $this->plugin_name, $this->plugin_name, 'manage_options', $this->plugin_reference, array($this, 'render_options_page') );
        }

        public function render_options_page() {
            require_once(__DIR__.'/view/options.php');
        }

        public function options_page_callback()
        {   
            update_option( $this->plugin_reference.'_slackurls',     sanitize_textarea_field($_POST[$this->plugin_reference.'_slackurls']) );
            update_option( $this->plugin_reference.'_enabled',       $this->sanitize_select_option( sanitize_text_field($_POST[$this->plugin_reference.'_enabled']), array("no", "yes") ) );
            update_option( $this->plugin_reference.'_sitename',      sanitize_text_field($_POST[$this->plugin_reference.'_sitename']) );
    
            update_option( $this->plugin_reference.'_nextruntime_date', sanitize_text_field($_POST[$this->plugin_reference.'_nextruntime_date']) );
            update_option( $this->plugin_reference.'_nextruntime_time', sanitize_text_field($_POST[$this->plugin_reference.'_nextruntime_time']) );
    
            $cron_frequency = $this->sanitize_select_option( sanitize_text_field($_POST[$this->plugin_reference.'_frequency']), array("daily", "weekly", "monthly") );
            update_option( $this->plugin_reference.'_frequency', $cron_frequency );
    
            $ignore_plugins = array();
            if (isset($_POST[$this->plugin_reference.'_ignore_plugins']) && is_array($_POST[$this->plugin_reference.'_ignore_plugins'])) {
                foreach ($_POST[$this->plugin_reference.'_ignore_plugins'] as $plugin) {
                    $ignore_plugins[] = sanitize_text_field($plugin);
                }
            }
            update_option( $this->plugin_reference.'_ignore_plugins', $ignore_plugins);

            
            $ignore_themes = array();
            if (isset($_POST[$this->plugin_reference.'_ignore_themes']) && is_array($_POST[$this->plugin_reference.'_ignore_themes'])) {
                foreach ($_POST[$this->plugin_reference.'_ignore_themes'] as $theme) {
                    $ignore_themes[] = sanitize_text_field($theme);
                }
            }
            update_option( $this->plugin_reference.'_ignore_themes', $ignore_themes);
    
    
            if (sanitize_text_field($_POST[$this->plugin_reference.'_nextruntime_time']) != '') {
                $new_dt = new DateTime(sanitize_text_field($_POST[$this->plugin_reference.'_nextruntime_date']).' '.sanitize_text_field($_POST[$this->plugin_reference.'_nextruntime_time']));
    
                update_option( $this->plugin_reference.'_nextruntime_date', $new_dt->format('Y-m-d') );
        		update_option( $this->plugin_reference.'_nextruntime_time', $new_dt->format('H:i').':00' );

                $this->unschedule_cron_check();
                $this->schedule_cron_check($cron_frequency, $new_dt->getTimestamp());
            }
            else {
                update_option( $this->plugin_reference.'_nextruntime_date', '' );
        		update_option( $this->plugin_reference.'_nextruntime_time', '' );
                $this->unschedule_cron_check();
            }
    
        }

        /**
         * Checks that POSTed values are within an expected list of possible values (case insensitive)
         * 
         * @param string $posted_value
         * @param array $possible_values
         * 
         * @return string
         */
        function sanitize_select_option($posted_value, $possible_values = array())
        {
            // if there are valid values to check against...
            if (count($possible_values) > 0) {
                if (in_array(strtolower(trim($posted_value)), array_map('strtolower', $possible_values) )) {
                    // value verified; return that value
                    return trim($posted_value);
                }
                else {
                    // if it fails, return the first allowed value
                    return $possible_values[0];
                }
            }
            else {
                // return blank
                return '';
            }
        }



        public function schedule_cron_check($frequency = 'daily', $time = '00:00') 
        {
            if ( wp_next_scheduled( $this->cron_reference ) ) {
                wp_reschedule_event( $time, $frequency, $this->cron_reference );
            }
            else {
                wp_schedule_event( $time, $frequency, $this->cron_reference );
            }
        }
        public function unschedule_cron_check() 
        {
            foreach (get_option('cron') as $cron_timestamp => $cron) {
                foreach ($cron as $cron_title => $value) {
                    if (trim($cron_title) == $this->cron_reference) {
                        wp_unschedule_event ($cron_timestamp, $this->cron_reference);
                    }
                }
            }
        }


        /**
         * Get site name, either configured or from site
         * 
         * @return string
         */
        public function get_site_name()
        {
            $site_name = get_option($this->plugin_reference.'_sitename');
            if (strlen(trim($site_name)) == 0) {
                $site_name = get_bloginfo('name');
            }

            return $site_name;
        }


        /**
         * Tests the functionality of the plugin
         * 
         * @param string $timestamp
         * 
         * @return integer|false
         */
        public function send_test_message($timestamp)
        {
            $now = new \DateTime();

            if ($timestamp && $timestamp == $now->format('H:i:s')) {
                $data = json_encode(
                    [
                        'text' => sprintf("This is a Test Message sent from the %s Plugin", $this->plugin_name), 
                        'blocks' => 
                        [
                            [
                                "type" => "section", 
                                "text" => [
                                    "type" => "mrkdwn", 
                                    "text" => sprintf("This is a test message sent from the %s Plugin on the *%s* site", $this->plugin_name, $this->get_site_name())
                                ] 
                            ]
                        ] 
                    ]);
                return $this->send_alert_to_slack($data);
            }

            return false;
        }


        /**
         * Checks for WordPress updates (core, plugins, themes), prepares Slack payload
         * 
         * @return integer|false
         */
        public function check_for_updates()
        {
            $updates = array('core' => array(), 'plugins' => array(), 'themes' => array());

            // core updates
            $updates_for_core = get_option('_site_transient_update_core');
            if ($updates_for_core->updates[0]->current != $updates_for_core->version_checked) {
                $updates['core'][] = sprintf(
                    'WordPress %s is available (currently on version %s)',
                    $updates_for_core->updates[0]->version,
                    $updates_for_core->version_checked
                );
            }


            // check on plugins
            foreach (get_option('_site_transient_update_plugins')->response as $plugin) {
                if (!is_array(get_option($this->plugin_reference.'_ignore_plugins')) 
			        || is_array(get_option($this->plugin_reference.'_ignore_plugins')) && !in_array(strtolower($plugin->slug), get_option($this->plugin_reference.'_ignore_plugins'))) {
                      $updates['plugins'][] = sprintf(
                        "*%s* is ready for version %s",
                        $plugin->slug,
                        $plugin->new_version
                    );
                }
            }


            // check on themes
            foreach (get_option('_site_transient_update_themes')->response as $theme) {
                if (!is_array(get_option($this->plugin_reference.'_ignore_themes')) 
			        || is_array(get_option($this->plugin_reference.'_ignore_themes')) && !in_array(strtolower($theme["theme"]), get_option($this->plugin_reference.'_ignore_themes'))) {
                    $updates['themes'][] = sprintf(
                        "*%s* theme is ready for version %s",
                        $theme["theme"],
                        $theme["new_version"]
                    );
                }
            }

            
            if (count($updates['core']) > 0 || count($updates['plugins']) > 0 || count($updates['themes']) > 0) {

                $site_name  =$this->get_site_name();

                $blocks = array();
                $blocks[] = [
                    'type' => 'section',
                    "text" => [
                        "type" => "mrkdwn", 
                        "text" => sprintf("Updates are availabe on the *%s* website", $this->get_site_name())
                    ] 
                ];

                foreach ($updates as $type => $update) {
                    $updates_list = '';

                    if (count($update) > 0) {

                        foreach ($update as $entity) {
                            $updates_list .= $entity.PHP_EOL;
                        }

                        $blocks[] = ['type' => 'divider'];
                        $blocks[] = [
                            'type' => 'section',
                            "text" => [
                                "type" => "mrkdwn", 
                                "text" => ":arrow_right: ".sprintf('*%s*', ucwords($type))
                            ] 
                        ];

                        $blocks[] = [
                            "type" => "section", 
                            "text" => [
                                "type" => "mrkdwn", 
                                "text" => $updates_list
                            ] 
                        ];
                    }
                }

                $blocks[] = ['type' => 'divider'];
                $blocks[] = [
                    'type' => 'actions',
                    'elements' => [
                        [
                            "type" => "button",
                            "text" => [ 
                                "type" => "plain_text",
                                "text" => sprintf("Open %s", $site_name),
                                "emoji" => true
                            ],
                            "value" => "click_for_website",
                            "url" => get_admin_url()
                        ]
                    ]
                ];

                $data = 
                    [
                        'text' => sprintf("Updates are availabe on the *%s* website", $site_name), 
                        'blocks' => $blocks
                    ];
                    
                return $this->send_alert_to_slack(json_encode($data));
            }

            return false;
        }

        /**
         * Send message to Slack
         * 
         * @param string
         * 
         * @return int
         */
        public function send_alert_to_slack( $data_payload )
        {
            $now = new DateTime();
            
            if (strtolower(get_option($this->plugin_reference.'_enabled')) != 'yes')
            {
                update_option($this->plugin_reference.'_lastrundt', $now->format('Y-m-d H:i:s'), false);
                update_option($this->plugin_reference.'_lastrundata', 'ERROR! Plugin configuration "Slack Alerts Enabled" hasn\'t been set to "Yes".', false);
                
                return false;
            }

            $now = new \DateTime();
            $response_code = 0;
            $plugin_output = '';
            
            $slack_urls = explode(PHP_EOL, get_option($this->plugin_reference.'_slackurls'));

            foreach ($slack_urls as $url)
            {
                if ( ! $url ) {
                    update_option('updates2slack_lastrundt', $now->format('Y-m-d H:i:s'), false);
                    update_option('updates2slack_lastrundata', 'ERROR! No Slack Webhook URLs have been configured.', false);
    
                    continue;
                }

                $plugin_output .= esc_url($url).PHP_EOL;

                $args = array(
                    'method'      => 'POST',
                    'body'        => $data_payload,
                    'timeout'     => '30',
                    'redirection' => '5',
                    'httpversion' => '1.0',
                    'blocking'    => true,
                    'headers'     => array(),
                    'cookies'     => array(),
                );
    
                $output = wp_remote_post( esc_url(trim($url)), $args );

                $plugin_output .=  'Outcome: ';

                if (array_key_exists('response', $output)) {
                    if (array_key_exists('message', $output["response"])) {
                        $plugin_output .= $output["response"]["message"];
                    }
    
                    $plugin_output .= ' (HTTP '.$output["response"]["code"].')';

                    $response_code = $output["response"]["code"];
                }
                elseif (array_key_exists('body', $output)) {
                    $plugin_output .=  'Outcome: '.$output["body"];
                }
    
                if ( is_wp_error( $output ) ) {
                    $plugin_output .=  'Outcome: Error - '.$output->get_error_message()."\n";
                }
    
                $plugin_output .= "\n";
            }

            update_option('updates2slack_lastrundt', $now->format('Y-m-d H:i:s'), false);
            update_option('updates2slack_lastrundata', esc_attr($plugin_output), false);

            return $response_code;
        }
    }

    new Updates_To_Slack_Plugin();
}
