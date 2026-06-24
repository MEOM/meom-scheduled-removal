<?php
/**
 * Plugin Name: Meom Scheduled Removal
 * Plugin URI:  https://meom.fi
 * Description: Schedule automatic unpublishing (set to draft) of posts at a chosen date and time, using a native post-meta field and a Gutenberg DateTimePicker.
 * Version:     1.0.0
 * Author:      Meom
 * Author URI:  https://meom.fi
 * License:     GPL-2.0+
 * Text Domain: meom-scheduled-removal
 * Domain Path: /languages
 *
 * @package Meom\Scheduled_Removal
 */

namespace Meom\Scheduled_Removal;

// Halt if this file is called directly.
if ( ! defined( 'ABSPATH' ) ) {
    die;
}

const VERSION    = '1.0.0';
const META_KEY   = 'meom_scheduled_removal';
const CRON_HOOK  = 'meom_scheduled_removal_event';
const SWEEP_HOOK = 'meom_scheduled_removal_sweep';
const DATE_FORMAT = 'Y-m-d\TH:i:s';

define( 'MEOM_SCHEDULED_REMOVAL_DIR', plugin_dir_path( __FILE__ ) );
define( 'MEOM_SCHEDULED_REMOVAL_URL', plugin_dir_url( __FILE__ ) );

/**
 * Return the post types that support scheduled removal.
 *
 * @return string[] List of post type slugs.
 */
function get_supported_post_types(): array {
    return array_values( (array) apply_filters( 'meom_scheduled_removal_post_types', array( 'post' ) ) );
}

/**
 * Validate and normalize a removal datetime string.
 *
 * Accepts the ISO string produced by the Gutenberg DateTimePicker
 * (e.g. "2026-06-24T14:30:00"). Returns '' for empty or invalid input.
 *
 * @param mixed $value Raw meta value.
 * @return string Normalized "Y-m-d\TH:i:s" string, or '' if invalid.
 */
function sanitize_removal_date( $value ): string {
    if ( ! is_string( $value ) || '' === $value ) {
        return '';
    }

    $date   = \DateTime::createFromFormat( DATE_FORMAT, $value, wp_timezone() );
    $errors = \DateTime::getLastErrors();

    if ( ! $date || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) ) {
        return '';
    }

    return $date->format( DATE_FORMAT );
}

/**
 * Enqueue the block editor script and pass the supported post types to JS.
 *
 * @return void
 */
function enqueue_editor_assets(): void {
    $asset_file = MEOM_SCHEDULED_REMOVAL_DIR . 'build/index.asset.php';

    if ( ! file_exists( $asset_file ) ) {
        return;
    }

    $asset = include $asset_file;

    wp_enqueue_script(
        'meom-scheduled-removal-editor',
        MEOM_SCHEDULED_REMOVAL_URL . 'build/index.js',
        $asset['dependencies'],
        $asset['version'],
        true
    );

    wp_set_script_translations(
        'meom-scheduled-removal-editor',
        'meom-scheduled-removal',
        MEOM_SCHEDULED_REMOVAL_DIR . 'languages'
    );

    wp_localize_script(
        'meom-scheduled-removal-editor',
        'meomScheduledRemoval',
        array( 'postTypes' => get_supported_post_types() )
    );
}
add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_editor_assets' );

/**
 * Load the plugin text domain.
 *
 * @return void
 */
function load_textdomain(): void {
    load_plugin_textdomain( 'meom-scheduled-removal', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', __NAMESPACE__ . '\\load_textdomain' );

/**
 * Plugin activation: schedule the hourly sweep.
 *
 * @return void
 */
function activate(): void {
    if ( ! wp_next_scheduled( SWEEP_HOOK ) ) {
        wp_schedule_event( time(), 'hourly', SWEEP_HOOK );
    }
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );

/**
 * Plugin deactivation: clear the hourly sweep.
 *
 * @return void
 */
function deactivate(): void {
    wp_clear_scheduled_hook( SWEEP_HOOK );
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );

require_once MEOM_SCHEDULED_REMOVAL_DIR . 'includes/class-register-meta.php';
require_once MEOM_SCHEDULED_REMOVAL_DIR . 'includes/class-scheduled-removal.php';

// Boot the components.
new Register_Meta();
new Scheduled_Removal();
