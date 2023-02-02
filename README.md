<img src="https://img.shields.io/wordpress/plugin/v/updates-to-slack?style=plastic"> <img src="https://img.shields.io/wordpress/plugin/last-updated/updates-to-slack?style=plastic">


# Updates to Slack

**Updates to Slack** is a *WordPress plugin* that informs of Core, Plugin and Theme updates, that are required on your WordPress installation, to one or more Slack channels. It does so on a daily, weekly or monthly schedule at a time of your choosing.

This is helpful if you have lots of WordPress sites that you need to maintain, or if you have multiple "owners" of, a WordPress site and require visibility on updates available to keep your installation secure and up-to-date. It acts as an alternative to the standard WordPress alerts from within the admin so that you don't need to be logged in to check; this solution provides a sort of "push notification" to your Slack channel(s).


## Plugin Features

* **Multiple Slack Channels:** Allows you to send notifications to one or more Slack channels, using [Slack's Block Kit](https://api.slack.com/block-kit)
* **Scheduled Updates:** Alerts are triggered at a time you set, on either daily, weekly or monthly intervals
* **Test Option:** Check that your alerts are being sent without waiting on the schedule
* **Ignore Plugins and Themes:** Choose which individual plugins and themes you don't want to know about, when an update is available
* **Content Permissions:** Gives you control over which users (by role) have access to post content.
* **Secure:** [Slack webhooks](https://slack.com/intl/en-gb/help/articles/115005265063-Incoming-webhooks-for-Slack) are sent from your WordPress site, on a secure URL to your Slack channel(s)
* **Reporting:**  See when a notification was last triggered and what the result was

## Support

If you require support from me, the plugin author, feel free to contact me directly and place a request.


## Copyright and License

2020&thinsp;&ndash;&thinsp;2023 &copy; [Alex Cooper MIT](https://github.com/alexpcooper/updates-to-slack/blob/main/LICENSE).


## Documentation

### Installation

Install the plugin at /wp-content/plugins/, as you would any other standard WordPress plugin. The directory name of the plugin needs to be "updates-to-slack".


### How to use the plugin

Once installed and activated go to *Settings* -> *Updates to Slack*

<img src="https://alexpcooper.co.uk/wp-content/uploads/2021/04/screenshot-3.png" width="325" alt="WordPress Settings -> Updates to Slack" />

The following options are available;
* **Slack URL(s):** [Create a Slack webhook](https://slack.com/intl/en-gb/help/articles/115005265063-Incoming-webhooks-for-Slack) for your channel and enter it in here (ie. https ://hooks.slack.com/services/ ... ). For multiple URLs, simply add additional Slack webhooks, one per line, in this field
* **Slack Alerts Enabled?:** *Yes* to enable, *No* to prevent alerts. useful if you're also running a Development installation
* **Site Name:** If left blank, your site name is taken from the one entered in the General Settings of your WordPress site
* **Next Scheduled Run Time:** Enter a date and time of the next trigger
* **Frequency:** How often the trigger will run following the Next Scheduled Run Time; Daily, Weekly or Monthly
* **Last Run:** Tells you the date and time of the last trigger
* **Last Run Slack Response:** The outcome of the last trigger (usually either "No updates available", meaning it had no reason to send a message to your Slack channel, or "OK" if Slack received the message without any issues)
* **Test:** A button that allows you to trigger a check immediately. Useful for testing purposes
* **Ignore Plugins and Themes:** Allows you to select which of your themes and plugins you don't wish to be notified about when there is an update

<img src="https://alexpcooper.co.uk/wp-content/uploads/2021/02/screenshot-1.png" width="80%" alt="Updates to Slack Config (1)" />
<img src="https://alexpcooper.co.uk/wp-content/uploads/2021/02/screenshot-2.png" width="80%" alt="Updates to Slack Config (2)" />

<img src="https://alexpcooper.co.uk/wp-content/uploads/2023/02/screenshot-5.png" width="80%" alt="Updates to Slack: Slack Card update" />
<img src="https://alexpcooper.co.uk/wp-content/uploads/2023/02/screenshot-6.png" width="80%" alt="Updates to Slack: Slack Card update" />

### Why Ignore Plugins and Themes?

The idea behind this was that you may have plugins that, should an update occur, would deprecate functionality on your site and updating it isn't an option then you may not want to hear via every scheduled alert that there's an update out the for it. Similarly, with themes, you may be keeping a native WordPress theme, such as twenty twenty-one for troubleshooting but you don't actually need to immediately address any available updates for it.


### Security Concerns

It isn't advised to keep a WordPress site on a live environment with updates required. The purpose of this plugin is quick notification of when updates are needed / available, especially with the Core of WordPress (which is why you're unable to "ignore" a Core update). The purpose behind ignoring plugins and themes is designed to reduce "notification overload" so that you're in control of the alerts you are sent and aren't bombarded with needless information for themes and plugins that you don't need to be informed about.


### Limitations
* This plugin has been tested on WordPress core versions 4.0 onwards.
* I currently have this Updates to Slack plugin running on several live WordPress sites; it has proved very useful.
* If there's a demand to continue to add further functionality to this plugin then I am open to doing so. In the meantime I intend to keep it working on new versions of WordPress.
