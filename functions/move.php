<?php
/**
 * Faster Updates
 *
 * @author   Andy Fragen, Colin Stewart
 * @license  MIT
 * @link     https://github.com/afragen/faster-updates
 * @package  faster-updates
 */

namespace Faster_Updates\Functions;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

global $wp_filesystem;

if ( ! $wp_filesystem ) {
	require_once ABSPATH . '/wp-admin/includes/file.php';
	WP_Filesystem();
}

/**
 * Moves a directory from one location to another via the rename() PHP function.
 * If the renaming failed, falls back to copy_dir().
 *
 * @since 6.2.0
 *
 * Assumes that WP_Filesystem() has already been called and setup.
 *
 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
 *
 * @param string $from        Source directory.
 * @param string $to          Destination directory.
 * @return true|WP_Error True on success, WP_Error on failure.
 */
function move_dir( $from, $to ) {
	global $wp_filesystem;

	$result = false;

	/**
	 * Fires before move_dir().
	 *
	 * @since 6.2.0
	 */
	do_action( 'pre_move_dir' );

	if ( 'direct' === $wp_filesystem->method ) {
		if ( $wp_filesystem->rmdir( $to ) ) {
			$result = @rename( $from, $to );
			wp_opcache_invalidate_directory( $to );
		}
	} else {
		// Non-direct filesystems use some version of rename without a fallback.
		$result = $wp_filesystem->move( $from, $to );
		wp_opcache_invalidate_directory( $to );
	}

	if ( ! $result ) {
		if ( ! $wp_filesystem->is_dir( $to ) ) {
			if ( ! $wp_filesystem->mkdir( $to, FS_CHMOD_DIR ) ) {
				return new \WP_Error( 'mkdir_failed_move_dir', __( 'Could not create directory.' ), $to );
			}
		}

		$result = copy_dir( $from, $to, array( basename( $to ) ) );

		// Clear the source directory.
		if ( ! is_wp_error( $result ) ) {
			$wp_filesystem->delete( $from, true );
		}
	}

	/**
	 * Fires after move_dir().
	 *
	 * @since 6.2.0
	 */
	do_action( 'post_move_dir' );

	return $result;
}

/**
 * Invalidate OPcache of directory of files.
 *
 * @since 6.2.0
 *
 * @param string $dir Path to directory.
 *
 * @return void
 */
function wp_opcache_invalidate_directory( $dir ) {
	global $wp_filesystem;

	$files   = array();
	$dirlist = $wp_filesystem->dirlist( $dir, false, true );

	// Local version of WP_Upgrader::flatten_dirlist().
	$flatten_dirlist = function( $nested_files, $path = '' ) use ( &$flatten_dirlist ) {
		$files = array();

		foreach ( $nested_files as $name => $details ) {
			$files[ $path . $name ] = $details;

			// Append children recursively.
			if ( ! empty( $details['files'] ) ) {
				$children = $flatten_dirlist( $details['files'], $path . $name . '/' );

				// Merge keeping possible numeric keys, which array_merge() will reindex from 0..n.
				$files = $files + $children;
			}
		}
		return $files;
	};
	$files           = $flatten_dirlist( $dirlist );

	foreach ( array_keys( $files ) as $file ) {
		wp_opcache_invalidate( trailingslashit( $dir ) . $file );
	}
}
