<?php
/**
 * Faster Updates
 *
 * @package faster-updates
 * @author Andy Fragen <andy@thefragens.com>
 * @license MIT
 */

/**
 * Plugin Name: Faster Updates
 * Author: WP Core Contributors
 * Description: Speeds up plugin/theme updates by moving directories rather than recursively copying files.
 * Version: 0.1.0.1
 * Network: true
 * License: MIT
 * Text Domain: faster-updates
 * Requires PHP: 5.6
 * Requires at least: 6.2
 * GitHub Plugin URI: https://github.com/afragen/faster-updates
 * Primary Branch: main
 */

/*
 * Exit if called directly.
 * PHP version check and exit.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

add_filter(
	'upgrader_copy_directory',
	function( $callback ) {
		require_once __DIR__ . '/move-dir.php';
		return 'move_dir';
	},
	100,
	1
);

add_filter( 'upgrader_use_move_dir', '__return_true' );

// Hopefully add some VirtualBox compatibility.
add_action(
	'post_move_dir',
	function() {
		/*
		 * VirtualBox has a bug when PHP's rename() is followed by an unlink().
		 *
		 * The bug is caused by delayed clearing of the filesystem cache, and
		 * the solution is to clear dentries and inodes at the system level.
		 *
		 * Most hosts add shell_exec() to the disable_function directive.
		 * function_exists() is usually sufficient to detect this.
		 */
		if ( function_exists( 'shell_exec' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
			shell_exec( 'sync; echo 2 > /proc/sys/vm/drop_caches' );
		}
	}
);
