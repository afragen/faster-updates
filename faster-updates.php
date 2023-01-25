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
 * Description: Speeds up plugin/theme updates by moving directories rather than recursively copying files. Only for updating from 'update-core.php'.
 * Version: 0.5.3
 * Network: true
 * License: MIT
 * Text Domain: faster-updates
 * Requires PHP: 5.6
 * Requires at least: 6.0
 * GitHub Plugin URI: https://github.com/afragen/faster-updates
 * Primary Branch: main
 */

namespace Faster_Updates;

/*
 * Exit if called directly.
 * PHP version check and exit.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once __DIR__ . '/functions/move.php';
require_once __DIR__ . '/modules/plugins/class-main.php';
require_once __DIR__ . '/modules/themes/class-main.php';

new \Faster_Updates\Modules\Plugins\Main();
new \Faster_Updates\Modules\Themes\Main();
