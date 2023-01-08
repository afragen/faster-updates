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

require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

/**
 * Plugins Module: Upgrader.
 *
 * @since 1.0.0
 */
class Upgrader extends \Plugin_Upgrader {

	/**
	 * Install a package.
	 *
	 * Moves the contents of a package from a source directory, and installs them in
	 * a destination directory. Optionally removes the source. It can also optionally
	 * clear out the destination folder if it already exists.
	 *
	 * @since 1.0.0
	 *
	 * @global WP_Filesystem_Base $wp_filesystem        WordPress filesystem subclass.
	 * @global array              $wp_theme_directories
	 *
	 * @param array|string $args {
	 *     Optional. Array or string of arguments for installing a package. Default empty array.
	 *
	 *     @type string $source                      Required path to the package source. Default empty.
	 *     @type string $destination                 Required path to a folder to install the package in.
	 *                                               Default empty.
	 *     @type bool   $clear_destination           Whether to delete any files already in the destination
	 *                                               folder. Default false.
	 *     @type bool   $clear_working               Whether to delete the files from the working directory
	 *                                               after copying them to the destination. Default false.
	 *     @type bool   $abort_if_destination_exists Whether to abort the installation if
	 *                                               the destination folder already exists. Default true.
	 *     @type array  $hook_extra                  Extra arguments to pass to the filter hooks called by
	 *                                               WP_Upgrader::install_package(). Default empty array.
	 * }
	 *
	 * @return array|WP_Error The result (also stored in `WP_Upgrader::$result`), or a WP_Error on failure.
	 */
	public function install_package( $args = array() ) {
		global $wp_filesystem, $wp_theme_directories;

		$defaults = array(
			'source'                      => '', // Please always pass this.
			'destination'                 => '', // ...and this.
			'clear_destination'           => false,
			'clear_working'               => false,
			'abort_if_destination_exists' => true,
			'hook_extra'                  => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		// These were previously extract()'d.
		$source            = $args['source'];
		$destination       = $args['destination'];
		$clear_destination = $args['clear_destination'];

		set_time_limit( 300 );

		if ( empty( $source ) || empty( $destination ) ) {
			return new \WP_Error( 'bad_request', $this->strings['bad_request'] );
		}
		$this->skin->feedback( 'installing_package' );

		/**
		 * Filters the installation response before the installation has started.
		 *
		 * Returning a value that could be evaluated as a `WP_Error` will effectively
		 * short-circuit the installation, returning that value instead.
		 *
		 * @since 2.8.0
		 *
		 * @param bool|WP_Error $response   Installation response.
		 * @param array         $hook_extra Extra arguments passed to hooked filters.
		 */
		$res = apply_filters( 'upgrader_pre_install', true, $args['hook_extra'] );

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		// Retain the original source and destinations.
		$remote_source     = $args['source'];
		$local_destination = $destination;

		$source_files       = array_keys( $wp_filesystem->dirlist( $remote_source ) );
		$remote_destination = $wp_filesystem->find_folder( $local_destination );

		// Locate which directory to copy to the new folder. This is based on the actual folder holding the files.
		if ( 1 === count( $source_files ) && $wp_filesystem->is_dir( trailingslashit( $args['source'] ) . $source_files[0] . '/' ) ) {
			// Only one folder? Then we want its contents.
			$source = trailingslashit( $args['source'] ) . trailingslashit( $source_files[0] );
		} elseif ( 0 === count( $source_files ) ) {
			// There are no files?
			return new \WP_Error( 'incompatible_archive_empty', $this->strings['incompatible_archive'], $this->strings['no_files'] );
		} else {
			// It's only a single file, the upgrader will use the folder name of this file as the destination folder.
			// Folder name is based on zip filename.
			$source = trailingslashit( $args['source'] );
		}

		/**
		 * Filters the source file location for the upgrade package.
		 *
		 * @since 2.8.0
		 * @since 4.4.0 The $hook_extra parameter became available.
		 *
		 * @param string      $source        File source location.
		 * @param string      $remote_source Remote file source location.
		 * @param WP_Upgrader $upgrader      WP_Upgrader instance.
		 * @param array       $hook_extra    Extra arguments passed to hooked filters.
		 */
		$source = apply_filters( 'upgrader_source_selection', $source, $remote_source, $this, $args['hook_extra'] );

		if ( is_wp_error( $source ) ) {
			return $source;
		}

		// Has the source location changed? If so, we need a new source_files list.
		if ( $source !== $remote_source ) {
			$source_files = array_keys( $wp_filesystem->dirlist( $source ) );
		}

		/*
		 * Protection against deleting files in any important base directories.
		 * Theme_Upgrader & Plugin_Upgrader also trigger this, as they pass the
		 * destination directory (WP_PLUGIN_DIR / wp-content/themes) intending
		 * to copy the directory into the directory, whilst they pass the source
		 * as the actual files to copy.
		 */
		$protected_directories = array( ABSPATH, WP_CONTENT_DIR, WP_PLUGIN_DIR, WP_CONTENT_DIR . '/themes' );

		if ( is_array( $wp_theme_directories ) ) {
			$protected_directories = array_merge( $protected_directories, $wp_theme_directories );
		}

		if ( in_array( $destination, $protected_directories, true ) ) {
			$remote_destination = trailingslashit( $remote_destination ) . trailingslashit( basename( $source ) );
			$destination        = trailingslashit( $destination ) . trailingslashit( basename( $source ) );
		}

		if ( $clear_destination ) {
			// We're going to clear the destination if there's something there.
			$this->skin->feedback( 'remove_old' );

			$removed = $this->clear_destination( $remote_destination );

			/**
			 * Filters whether the upgrader cleared the destination.
			 *
			 * @since 2.8.0
			 *
			 * @param true|WP_Error $removed            Whether the destination was cleared.
			 *                                          True upon success, WP_Error on failure.
			 * @param string        $local_destination  The local package destination.
			 * @param string        $remote_destination The remote package destination.
			 * @param array         $hook_extra         Extra arguments passed to hooked filters.
			 */
			$removed = apply_filters( 'upgrader_clear_destination', $removed, $local_destination, $remote_destination, $args['hook_extra'] );

			if ( is_wp_error( $removed ) ) {
				return $removed;
			}
		} elseif ( $args['abort_if_destination_exists'] && $wp_filesystem->exists( $remote_destination ) ) {
			// If we're not clearing the destination folder and something exists there already, bail.
			// But first check to see if there are actually any files in the folder.
			$_files = $wp_filesystem->dirlist( $remote_destination );
			if ( ! empty( $_files ) ) {
				$wp_filesystem->delete( $remote_source, true ); // Clear out the source files.
				return new \WP_Error( 'folder_exists', $this->strings['folder_exists'], $remote_destination );
			}
		}

		// Create destination if needed.
		if ( ! $wp_filesystem->exists( $remote_destination ) ) {
			if ( ! $wp_filesystem->mkdir( $remote_destination, FS_CHMOD_DIR ) ) {
				return new \WP_Error( 'mkdir_failed_destination', $this->strings['mkdir_failed'], $remote_destination );
			}
		}

		$result = \Faster_Updates\Functions\move_dir( $source, $remote_destination );

		// Clear the working folder?
		if ( $args['clear_working'] ) {
			$wp_filesystem->delete( $remote_source, true );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$destination_name = basename( str_replace( $local_destination, '', $destination ) );
		if ( '.' === $destination_name ) {
			$destination_name = '';
		}

		$this->result = compact( 'source', 'source_files', 'destination', 'destination_name', 'local_destination', 'remote_destination', 'clear_destination' );

		/**
		 * Filters the installation response after the installation has finished.
		 *
		 * @since 2.8.0
		 *
		 * @param bool  $response   Installation response.
		 * @param array $hook_extra Extra arguments passed to hooked filters.
		 * @param array $result     Installation result data.
		 */
		$res = apply_filters( 'upgrader_post_install', true, $args['hook_extra'], $this->result );

		if ( is_wp_error( $res ) ) {
			$this->result = $res;
			return $res;
		}

		// Bombard the calling function will all the info which we've just used.
		return $this->result;
	}

}
