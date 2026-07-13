<?php
/**
 * Plugin Name:       DSE Heads-Up
 * Plugin URI:        https://github.com/DS-Emotion/dse-heads-up
 * Description:        A shared team status board inside the WordPress admin. Every user can set their status (Inactive / In Progress) and open a tray describing the page and message they are working on, so the team has visibility of current activity at a glance.
 * Version:           1.0.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            DS.Emotion
 * Author URI:        https://www.dsemotion.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dse-heads-up
 *
 * ---------------------------------------------------------------------------
 * HOW UPDATES WORK
 * ---------------------------------------------------------------------------
 * The `Version:` line above is what WordPress compares against GitHub. When a
 * GitHub Release exists whose tag is a HIGHER version than this number, an
 * "update available" notice appears in the WordPress admin.
 *
 * Every release, you MUST:
 *   1. Bump the `Version:` value above (e.g. 1.0.0 -> 1.0.1).
 *   2. Commit and push.
 *   3. Publish a GitHub Release with a matching tag (e.g. v1.0.1 or 1.0.1).
 *
 * See README.md for the full checklist.
 * ---------------------------------------------------------------------------
 *
 * @package DSE_Heads_Up
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UAO_VERSION', '1.0.1' );
define( 'UAO_FILE', __FILE__ );
define( 'UAO_URL', plugin_dir_url( __FILE__ ) );
define( 'UAO_PATH', plugin_dir_path( __FILE__ ) );

/**
 * ===========================================================================
 *  GitHub update checker
 * ===========================================================================
 * Uses the bundled Plugin Update Checker library (YahnisElsts, v5.7).
 * The repo is PUBLIC, so no access token / authentication is required.
 */
require plugin_dir_path( __FILE__ ) . 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$dse_heads_up_update_checker = PucFactory::buildUpdateChecker(
	// 1) The PUBLIC GitHub repo URL for THIS plugin. Change per plugin.
	'https://github.com/DS-Emotion/dse-heads-up/',
	// 2) Absolute path to this main plugin file. Leave as __FILE__.
	__FILE__,
	// 3) The plugin slug. Must match this plugin's folder name.
	'dse-heads-up'
);

/*
 * Look at GitHub *Releases* rather than the newest commit on a branch.
 * Publishing a Release is what triggers an update.
 */
$dse_heads_up_update_checker->getVcsApi()->enableReleaseAssets();

/**
 * ===========================================================================
 *  PLUGIN CODE
 * ===========================================================================
 */

/**
 * The available task statuses. Inactive is the default.
 *
 * @return array
 */
function uao_statuses() {
	return array(
		'inactive'    => array( 'label' => __( 'Inactive', 'dse-heads-up' ) ),
		'in_progress' => array( 'label' => __( 'In Progress', 'dse-heads-up' ) ),
	);
}

/**
 * Get a user's current status key, defaulting to "inactive".
 *
 * @param int $user_id User ID.
 * @return string
 */
function uao_get_status( $user_id ) {
	$status = get_user_meta( $user_id, 'uao_status', true );
	return array_key_exists( $status, uao_statuses() ) ? $status : 'inactive';
}

/**
 * A status other than inactive means the user's tray is "open" for all to see.
 *
 * @param string $status Status key.
 * @return bool
 */
function uao_is_open( $status ) {
	return ( 'in_progress' === $status );
}

/**
 * Format an "updated" timestamp using the site's date & time format.
 *
 * @param int $ts Unix timestamp.
 * @return string
 */
function uao_format_updated( $ts ) {
	$ts = (int) $ts;
	if ( ! $ts ) {
		return '';
	}
	return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
}

/**
 * Return the contents of the editable User Guide text file.
 *
 * Developers can edit /user-guide.txt in the plugin folder, or point to a
 * different file with the `uao_guide_file` filter.
 *
 * @return string
 */
function uao_get_user_guide() {
	$file = apply_filters( 'uao_guide_file', UAO_PATH . 'user-guide.txt' );
	if ( is_readable( $file ) ) {
		return (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
	return '';
}

/*
|--------------------------------------------------------------------------
| Live presence ("currently logged in")
|--------------------------------------------------------------------------
*/

/**
 * How long after a user's last activity we still treat them as logged in.
 *
 * @return int Seconds.
 */
function uao_presence_seconds() {
	return (int) apply_filters( 'uao_presence_seconds', 5 * MINUTE_IN_SECONDS );
}

/**
 * Is this user currently logged in / active right now?
 *
 * @param int $user_id User ID.
 * @return bool
 */
function uao_is_logged_in( $user_id ) {
	$seen = (int) get_user_meta( $user_id, 'uao_last_seen', true );
	return $seen && ( time() - $seen ) <= uao_presence_seconds();
}

/**
 * Record the current user's last-seen time (throttled to once a minute).
 */
function uao_track_seen() {
	if ( ! is_user_logged_in() ) {
		return;
	}
	$user_id = get_current_user_id();
	$now     = time();
	$last    = (int) get_user_meta( $user_id, 'uao_last_seen', true );
	if ( ( $now - $last ) < 60 ) {
		return;
	}
	update_user_meta( $user_id, 'uao_last_seen', $now );
}
add_action( 'admin_init', 'uao_track_seen' );
add_action( 'wp', 'uao_track_seen' );

/**
 * Keep presence fresh while a tab is open, via the WordPress Heartbeat API.
 *
 * @param array $response Heartbeat response.
 * @return array
 */
function uao_heartbeat_seen( $response ) {
	if ( is_user_logged_in() ) {
		update_user_meta( get_current_user_id(), 'uao_last_seen', time() );
	}
	return $response;
}
add_filter( 'heartbeat_received', 'uao_heartbeat_seen' );

/*
|--------------------------------------------------------------------------
| Last login capture
|--------------------------------------------------------------------------
*/

/**
 * Record login time.
 *
 * @param string  $user_login Username.
 * @param WP_User $user       User object.
 */
function uao_record_login( $user_login, $user ) {
	if ( $user instanceof WP_User ) {
		update_user_meta( $user->ID, 'uao_last_login', time() );
	}
}
add_action( 'wp_login', 'uao_record_login', 10, 2 );

/**
 * Human-readable "time ago", or a dash when empty.
 *
 * @param int $timestamp Unix timestamp.
 * @return string
 */
function uao_time_ago( $timestamp ) {
	$timestamp = (int) $timestamp;
	if ( ! $timestamp ) {
		return '—';
	}
	$diff = time() - $timestamp;
	if ( $diff < 45 ) {
		return __( 'Just now', 'dse-heads-up' );
	}
	if ( $diff < HOUR_IN_SECONDS ) {
		$mins = max( 1, (int) round( $diff / MINUTE_IN_SECONDS ) );
		/* translators: %d: minutes */
		return sprintf( _n( '%d min ago', '%d mins ago', $mins, 'dse-heads-up' ), $mins );
	}
	if ( $diff < DAY_IN_SECONDS ) {
		$hours = (int) round( $diff / HOUR_IN_SECONDS );
		/* translators: %d: hours */
		return sprintf( _n( '%d hour ago', '%d hours ago', $hours, 'dse-heads-up' ), $hours );
	}
	return date_i18n( 'M j, g:i A', $timestamp );
}

/*
|--------------------------------------------------------------------------
| Admin menu + assets
|--------------------------------------------------------------------------
*/

/**
 * Register the top-level admin menu page.
 */
function uao_register_menu() {
	$hook = add_menu_page(
		__( 'DSE Heads-Up', 'dse-heads-up' ),
		__( 'Heads-Up', 'dse-heads-up' ),
		'list_users',
		'dse-heads-up',
		'uao_render_page',
		'dashicons-groups',
		3
	);
	add_action( "load-$hook", 'uao_screen_options' );
}
add_action( 'admin_menu', 'uao_register_menu' );

/**
 * Enqueue assets on our page and on the Dashboard home (where it is embedded).
 *
 * @param string $hook Current admin page hook.
 */
function uao_enqueue_assets( $hook ) {
	// Heartbeat keeps the "currently logged in" indicator fresh.
	wp_enqueue_script( 'heartbeat' );

	if ( 'toplevel_page_dse-heads-up' !== $hook && 'index.php' !== $hook ) {
		return;
	}

	// Load CSS & JS inline (read from disk via PHP) so the plugin does not
	// depend on the web server serving files from the plugin directory —
	// some hosts/security rules return 403 for direct asset requests.
	$css = @file_get_contents( UAO_PATH . 'assets/admin.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$js  = @file_get_contents( UAO_PATH . 'assets/admin.js' );  // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

	// Style: register an empty handle and attach the CSS inline.
	wp_register_style( 'uao-admin', false, array(), UAO_VERSION );
	wp_enqueue_style( 'uao-admin' );
	if ( $css ) {
		wp_add_inline_style( 'uao-admin', $css );
	}

	// Script: register an empty handle (depends on jQuery), localize, then
	// attach the JS inline so it prints in the footer after the UAO data.
	wp_register_script( 'uao-admin', false, array( 'jquery' ), UAO_VERSION, true );
	wp_enqueue_script( 'uao-admin' );

	$labels = array();
	foreach ( uao_statuses() as $key => $data ) {
		$labels[ $key ] = $data['label'];
	}

	wp_localize_script(
		'uao-admin',
		'UAO',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'uao_save' ),
			'labels'  => $labels,
			'updated'   => __( 'Updated', 'dse-heads-up' ),
			'workingOn' => __( 'Working on', 'dse-heads-up' ),
			'noUpdate'  => __( 'No update yet — set your status and message.', 'dse-heads-up' ),
			'saved'   => __( 'Saved', 'dse-heads-up' ),
			'saving'  => __( 'Saving…', 'dse-heads-up' ),
			'error'   => __( 'Error saving', 'dse-heads-up' ),
		)
	);

	if ( $js ) {
		wp_add_inline_script( 'uao-admin', $js );
	}
}
add_action( 'admin_enqueue_scripts', 'uao_enqueue_assets' );

/**
 * Add a "per page" screen option.
 */
function uao_screen_options() {
	add_screen_option(
		'per_page',
		array(
			'label'   => __( 'Users per page', 'dse-heads-up' ),
			'default' => 25,
			'option'  => 'uao_per_page',
		)
	);
}

/**
 * Persist the per-page screen option.
 */
function uao_set_screen_option( $status, $option, $value ) {
	return ( 'uao_per_page' === $option ) ? (int) $value : $status;
}
add_filter( 'set-screen-option', 'uao_set_screen_option', 10, 3 );

/*
|--------------------------------------------------------------------------
| AJAX — a user updates their OWN status / working-on / message
|--------------------------------------------------------------------------
*/

/**
 * Save a single field for the current user only.
 */
function uao_ajax_save() {
	check_ajax_referer( 'uao_save', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'dse-heads-up' ) ), 403 );
	}

	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

	// Users may only edit their own row.
	if ( $user_id !== get_current_user_id() ) {
		wp_send_json_error( array( 'message' => __( 'You can only edit your own status.', 'dse-heads-up' ) ), 403 );
	}

	$field = isset( $_POST['field'] ) ? sanitize_key( $_POST['field'] ) : '';
	$value = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';

	switch ( $field ) {
		case 'status':
			$value = sanitize_key( $value );
			if ( ! array_key_exists( $value, uao_statuses() ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid status.', 'dse-heads-up' ) ), 400 );
			}
			update_user_meta( $user_id, 'uao_status', $value );
			break;

		case 'working_on':
			update_user_meta( $user_id, 'uao_working_on', sanitize_text_field( $value ) );
			break;

		case 'message':
			update_user_meta( $user_id, 'uao_message', sanitize_textarea_field( $value ) );
			break;

		default:
			wp_send_json_error( array( 'message' => __( 'Invalid field.', 'dse-heads-up' ) ), 400 );
	}

	$now = time();
	update_user_meta( $user_id, 'uao_updated', $now );
	wp_send_json_success( array( 'updated' => uao_format_updated( $now ) ) );
}
add_action( 'wp_ajax_uao_save', 'uao_ajax_save' );

/*
|--------------------------------------------------------------------------
| Includes
|--------------------------------------------------------------------------
*/
require_once UAO_PATH . 'includes/render-page.php';
require_once UAO_PATH . 'includes/dashboard-home.php';
