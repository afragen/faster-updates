<?php
/**
 * Faster Updates
 *
 * @author   Andy Fragen, Colin Stewart
 * @license  MIT
 * @link     https://github.com/afragen/faster-updates
 * @package  faster-updates
 */

namespace Faster_Updates\Modules\Themes;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once __DIR__ . '/class-upgrader.php';

/**
 * Themes Module: Main.
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
		add_action( 'load-update-core.php', array( $this, 'force_faster_updates_action' ) );
		add_action( 'update-core-custom_do-theme-upgrade-faster', array( $this, 'upgrade' ) );
		add_action( 'update-custom_update-selected-themes-faster', array( $this, 'bulk_update' ) );
	}

	/**
	 * Replaces Core's 'do-theme-upgrade' action with a custom 'do-theme-upgrade-faster' action.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function force_faster_updates_action() {
		if ( isset( $_GET['action'] ) && 'do-theme-upgrade' === $_GET['action'] ) {
			$_GET['action'] = 'do-theme-upgrade-faster';
		}
	}

	/**
	 * Single Theme Upgrade: Replaces the 'action' GET parameter
	 * of 'update.php' with 'update-selected-themes-faster'.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function upgrade() {
		if ( ! current_user_can( 'update_themes' ) ) {
			wp_die( __( 'Sorry, you are not allowed to update this site.' ) );
		}

		check_admin_referer( 'upgrade-core' );

		if ( isset( $_GET['themes'] ) ) {
			$themes = explode( ',', $_GET['themes'] );
		} elseif ( isset( $_POST['checked'] ) ) {
			$themes = (array) $_POST['checked'];
		} else {
			wp_redirect( admin_url( 'update-core.php' ) );
			exit;
		}

		$url = 'update.php?action=update-selected-themes-faster&themes=' . urlencode( implode( ',', $themes ) );
		$url = wp_nonce_url( $url, 'bulk-update-themes' );

		// Used in the HTML title tag.
		$title = __( 'Update Themes' );

		require_once ABSPATH . 'wp-admin/admin-header.php';
		?>
		<div class="wrap">
			<h1><?php _e( 'Update Themes' ); ?></h1>
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
	 * Bulk Theme Upgrade: Uses a custom theme upgrader class.
	 *
	 * Also replaces the 'action' GET parameter of 'update.php'
	 * with 'update-selected-themes-faster'.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function bulk_update() {
		if ( ! current_user_can( 'update_themes' ) ) {
			wp_die( __( 'Sorry, you are not allowed to update themes for this site.' ) );
		}

		check_admin_referer( 'bulk-update-themes' );

		if ( isset( $_GET['themes'] ) ) {
			$themes = explode( ',', stripslashes( $_GET['themes'] ) );
		} elseif ( isset( $_POST['checked'] ) ) {
			$themes = (array) $_POST['checked'];
		} else {
			$themes = array();
		}

		$themes = array_map( 'urldecode', $themes );

		$url   = 'update.php?action=update-selected-themes-faster&amp;themes=' . urlencode( implode( ',', $themes ) );
		$nonce = 'bulk-update-themes';

		wp_enqueue_script( 'updates' );
		iframe_header();

		$upgrader = new Upgrader( new \Bulk_Theme_Upgrader_Skin( compact( 'nonce', 'url' ) ) );
		$upgrader->bulk_upgrade( $themes );

		iframe_footer();
	}

}
