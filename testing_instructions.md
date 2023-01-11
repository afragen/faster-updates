# Testing Instructions

1. In `wp-config.php`, set `WP_DEBUG` and `WP_DEBUG_LOG` to true, and `WP_DEBUG_DISPLAY` to false. You can also install the WP Debugging plugin from the plugin repository to set these more easily. 😉
1. Install and activate older versions of some simple and complex plugins such as akismet jetpack mailpoet woocommerce wordpress-seo wpforms-lite. You can download older versions from the plugins repository by navigating to  Development > Advanced, then scroll to the bottom and download an older version. WP-CLI users can pass a --version parameter with the version number to install and activate. You may also simply decrease the version number in the main plugin file locally.
1. Navigate to Dashboard > Updates.
1. Update one plugin.
1. Update two plugins.
1. Update the remaining plugins.
1. Check for errors in the admin screens and frontend.
1. Check for errors in `wp-content/debug.log`.
1. Check the plugin directories in `wp-content/plugins`. Ensure that the main directory, and subdirectories have files in them.
1. Report any errors you encounter, or let us know if you don't encounter any errors.
1. Feel free to do the same for a few themes.

If you use VirtualBox in your development environment, commonly used in Chassis and VVV, please help. In addition to the instructions above, if you encounter an error. Please uncomment the following line in `faster-updates.php` and retest.

`// shell_exec( 'sync; echo 2 > /proc/sys/vm/drop_caches' );`
