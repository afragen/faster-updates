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
 * @param string $from      Source directory.
 * @param string $to        Destination directory.
 * @param bool   $overwrite Overwrite destination.
 *                          Default is false.
 * @return true|WP_Error True on success, WP_Error on failure.
 */
function move_dir( $from, $to, $overwrite = false ) {
	global $wp_filesystem;

	if ( ! $overwrite && $wp_filesystem->exists( $to ) ) {
		return new WP_Error(
			'to_directory_already_exists_move_dir',
			sprintf(
				/* translators: %s: The '$to' argument name. */
				__( '%s already exists.' ),
				'<code>$to</code>'
			)
		);
	}

	$result = false;

	if ( 'direct' === $wp_filesystem->method ) {
		if ( $wp_filesystem->delete( $to, true ) ) {
			$result = @rename( $from, $to );
		}
	} else {
		// Non-direct filesystems use some version of rename without a fallback.
		$result = $wp_filesystem->move( $from, $to, $overwrite );
	}

	if ( $result ) {
		/*
		 * When using an environment with shared folders,
		 * there is a delay in updating the filesystem's cache.
		 *
		 * This is a known issue in environments with a VirtualBox provider.
		 *
		 * A 200ms delay gives time for the filesystem to update its cache,
		 * prevents "Operation not permitted", and "No such file or directory" warnings.
		 *
		 * This delay is used in other projects, including Composer.
		 * @link https://github.com/composer/composer/blob/main/src/Composer/Util/Platform.php#L228-L233
		 */
		usleep( 200000 );
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

	return $result;
}

/**
 * Invalidate OPcache of directory of files.
 *
 * @since 6.2.0
 *
 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
 *
 * @param string $dir The path to invalidate.
 * @return void
 */
function wp_opcache_invalidate_directory( $dir ) {
	global $wp_filesystem;

	if ( ! is_string( $dir ) || '' === trim( $dir ) ) {
		if ( WP_DEBUG ) {
			$error_message = sprintf(
				/* translators: %s: The function name. */
				__( '%s expects a non-empty string.' ),
				'<code>wp_opcache_invalidate_directory()</code>'
			);
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error( $error_message );
		}
		return;
	}

	$dirlist = $wp_filesystem->dirlist( $dir, false, true );

	if ( empty( $dirlist ) ) {
		return;
	}

	/*
	 * Recursively invalidate opcache of files in a directory.
	 *
	 * WP_Filesystem_*::dirlist() returns an array of file and directory information.
	 *
	 * This does not include a path to the file or directory.
	 * To invalidate files within sub-directories, recursion is needed
	 * to prepend an absolute path containing the sub-directory's name.
	 *
	 * @param array  $dirlist Array of file/directory information from WP_Filesystem_Base::dirlist(),
	 *                        with sub-directories represented as nested arrays.
	 * @param string $path    Absolute path to the directory.
	 */
	$invalidate_directory = function( $dirlist, $path ) use ( &$invalidate_directory ) {
		$path = trailingslashit( $path );

		foreach ( $dirlist as $name => $details ) {
			if ( 'f' === $details['type'] ) {
				wp_opcache_invalidate( $path . $name, true );
				continue;
			}

			if ( is_array( $details['files'] ) && ! empty( $details['files'] ) ) {
				$invalidate_directory( $details['files'], $path . $name );
			}
		}
	};

	$invalidate_directory( $dirlist, $dir );
}
