<?php
/**
 * Faster Updates
 *
 * @author   Andy Fragen, Colin Stewart
 * @license  MIT
 * @link     https://github.com/afragen/faster-updates
 * @package  faster-updates
 */

namespace Faster_Updates\Modules\Plugins;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once __DIR__ . '/class-upgrader.php';

/**
 * Plugins Module: Main.
 * 
 * @since 1.0.0
 */
class Main {

	/**
	 * Constructor. Registers action hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'load-update-core.php', [ $this, 'force_faster_updates_action' ] );
		add_action( 'update-core-custom_do-plugin-upgrade-faster', [ $this, 'upgrade' ] );
		add_action( 'update-custom_update-selected-plugins-faster', [ $this, 'bulk_update' ] );
	}

	/**
	 * Replaces Core's 'do-plugin-upgrade' action with a custom 'do-plugin-upgrade-faster' action.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function force_faster_updates_action() {
		if ( isset( $_GET['action'] ) && 'do-plugin-upgrade' === $_GET['action'] ) {
			$_GET['action'] = 'do-plugin-upgrade-faster';
		}
	}

	/**
	 * Single Plugin Upgrade: Replaces the 'action' GET parameter
	 * of 'update.php' with 'update-selected-plugins-faster'.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function upgrade() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( __( 'Sorry, you are not allowed to update this site.' ) );
		}

		check_admin_referer( 'upgrade-core' );

		if ( isset( $_GET['plugins'] ) ) {
			$plugins = explode( ',', $_GET['plugins'] );
		} elseif ( isset( $_POST['checked'] ) ) {
			$plugins = (array) $_POST['checked'];
		} else {
			wp_redirect( admin_url( 'update-core.php' ) );
			exit;
		}

		$url = 'update.php?action=update-selected-plugins-faster&plugins=' . urlencode( implode( ',', $plugins ) );
		$url = wp_nonce_url( $url, 'bulk-update-plugins' );

		// Used in the HTML title tag.
		$title = __( 'Update Plugins' );

		require_once ABSPATH . 'wp-admin/admin-header.php';
		?>
		<div class="wrap">
			<h1><?php _e( 'Update Plugins' ); ?></h1>
			<iframe src="<?php echo $url; ?>" style="width: 100%; height: 100%; min-height: 750px;" frameborder="0" title="<?php esc_attr_e( 'Update progress' ); ?>"></iframe>
		</div>
		<?php

		wp_localize_script(
			'updates',
			'_wpUpdatesItemCounts',
			array(
				'totals' => wp_get_update_data(),
			)
		);

		require_once ABSPATH . 'wp-admin/admin-footer.php';
	}

	/**
	 * Bulk Plugin Upgrade: Uses a custom plugin upgrader class.
	 *
	 * Also replaces the 'action' GET parameter of 'update.php'
	 * with 'update-selected-plugins-faster'.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function bulk_update() {
		if ( ! current_user_can( 'update_plugins' ) ){
			wp_die( __( 'Sorry, you are not allowed to update plugins for this site.' ) );
		}

		check_admin_referer( 'bulk-update-plugins' );

		if ( isset( $_GET['plugins'] ) ) {
			$plugins = explode( ',', stripslashes( $_GET['plugins'] ) );
		} elseif ( isset( $_POST['checked'] ) ) {
			$plugins = (array) $_POST['checked'];
		} else {
			$plugins = array();
		}

		$plugins = array_map( 'urldecode', $plugins );

		$url   = 'update.php?action=update-selected-plugins-faster&amp;plugins=' . urlencode( implode( ',', $plugins ) );
		$nonce = 'bulk-update-plugins';

		wp_enqueue_script( 'updates' );
		iframe_header();

		$upgrader = new Upgrader( new \Bulk_Plugin_Upgrader_Skin( compact( 'nonce', 'url' ) ) );
		$upgrader->bulk_upgrade( $plugins );

		iframe_footer();
	}

}
