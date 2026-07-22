<?php
/**
 * Plugin Name:       DSE Heads-Up
 * Plugin URI:        https://github.com/DS-Emotion/dse-heads-up
 * Description:        A shared team status board inside the WordPress admin. Every user can set their status (Inactive / In Progress) and open a tray describing the page and message they are working on, so the team has visibility of current activity at a glance.
 * Version:           1.2.1
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

define( 'UAO_VERSION', '1.2.1' );
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

	// Since 1.1.0 the assets load on EVERY admin page (not just the board and
	// Dashboard home) because the announcement overlay popup can appear
	// anywhere in wp-admin. The hook is kept for future page-specific logic.
	unset( $hook );

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
			'ajaxUrl' => admin_url( 'admin-ajax.php', 'relative' ),
			'nonce'   => wp_create_nonce( 'uao_save' ),
			'labels'  => $labels,
			'updated'   => __( 'Updated', 'dse-heads-up' ),
			'workingOn' => __( 'Working on', 'dse-heads-up' ),
			'noUpdate'  => __( 'No update yet — set your status and message.', 'dse-heads-up' ),
			'saved'   => __( 'Saved', 'dse-heads-up' ),
			'confirmBtn' => __( 'I’ve seen this', 'dse-heads-up' ),
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

		case 'announce':
			if ( '1' === $value ) {
				update_user_meta( $user_id, 'uao_announce', '1' );
			} else {
				delete_user_meta( $user_id, 'uao_announce' );
			}
			break;

		case 'freeze':
			if ( ! current_user_can( 'uao_super_admin' ) ) {
				wp_send_json_error( array( 'message' => __( 'Only Super Admins can use Content Freeze.', 'dse-heads-up' ) ), 403 );
			}
			if ( '1' === $value ) {
				update_option(
					'uao_freeze',
					array(
						'by'   => $user_id,
						'time' => time(),
					)
				);
				// Auto-announce: everyone gets the popup and must confirm it.
				if ( ! uao_is_announcing( $user_id ) ) {
					update_user_meta( $user_id, 'uao_announce', '1' );
					update_user_meta( $user_id, 'uao_announce_by_freeze', '1' );
				}
			} else {
				// Clear the auto-announcement of whoever activated the freeze
				// (any Super Admin may lift a freeze, not just its creator).
				$freezer = uao_freeze_by();
				if ( $freezer && get_user_meta( $freezer, 'uao_announce_by_freeze', true ) ) {
					delete_user_meta( $freezer, 'uao_announce' );
					delete_user_meta( $freezer, 'uao_announce_by_freeze' );
				}
				delete_option( 'uao_freeze' );
			}
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
| Announcements — overlay popup shown to every user
|--------------------------------------------------------------------------
| A user can tick "Announce" on their own card. Their current message is then
| shown as a blocking overlay popup to every OTHER logged-in user, on any
| wp-admin page, until that person clicks the confirm button. Confirmations
| are stored per viewer, keyed to the announcement's "updated" timestamp, so
| editing the message while the box is ticked re-announces it to everyone.
*/

/**
 * Is this user currently announcing their update?
 *
 * @param int $user_id User ID.
 * @return bool
 */
function uao_is_announcing( $user_id ) {
	return '1' === get_user_meta( $user_id, 'uao_announce', true );
}

/**
 * All announcements the viewer has not yet confirmed.
 *
 * @param int $viewer_id The user who will see the popup.
 * @return array[] Each: id, name, working_on, message, updated.
 */
function uao_get_pending_announcements( $viewer_id ) {
	$announcers = get_users(
		array(
			'meta_key'   => 'uao_announce', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => '1',            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'orderby'    => 'display_name',
		)
	);

	$acks = get_user_meta( $viewer_id, 'uao_ack', true );
	$acks = is_array( $acks ) ? $acks : array();

	$pending = array();
	foreach ( $announcers as $u ) {
		// Never show your own announcement back to yourself.
		if ( (int) $u->ID === (int) $viewer_id ) {
			continue;
		}
		$updated = (int) get_user_meta( $u->ID, 'uao_updated', true );
		if ( ! $updated ) {
			continue;
		}
		// The active Content Freeze announcement is "locked" for everyone
		// without the Super Admin capability: it has no confirm button and
		// keeps showing until a Super Admin lifts the freeze.
		$locked = ( uao_is_frozen() && uao_freeze_by() === (int) $u->ID && ! user_can( $viewer_id, 'uao_super_admin' ) );

		// Already confirmed this (or a newer) version of the announcement.
		$acked = isset( $acks[ $u->ID ] ) ? (int) $acks[ $u->ID ] : 0;
		if ( $acked >= $updated && ! $locked ) {
			continue;
		}
		$pending[] = array(
			'id'         => (int) $u->ID,
			'name'       => $u->display_name,
			'working_on' => (string) get_user_meta( $u->ID, 'uao_working_on', true ),
			'message'    => (string) get_user_meta( $u->ID, 'uao_message', true ),
			'updated'    => $updated,
			'locked'     => $locked,
		);
	}

	return $pending;
}

/**
 * Print the announcement overlay popup in the footer of every admin page.
 * Nothing is printed when the viewer has no unconfirmed announcements.
 */
function uao_render_announcement_popup() {
	if ( ! is_user_logged_in() ) {
		return;
	}

	$pending = uao_get_pending_announcements( get_current_user_id() );
	if ( empty( $pending ) ) {
		return;
	}
	?>
	<div class="uao-announce-modal" id="uao-announce-modal" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Team announcement', 'dse-heads-up' ); ?>">
		<div class="uao-announce-modal__backdrop"></div>
		<div class="uao-announce-modal__box" role="document">
			<h2 class="uao-announce-modal__title"><span class="dashicons dashicons-megaphone"></span><?php esc_html_e( 'Heads-Up!', 'dse-heads-up' ); ?></h2>
			<p class="uao-announce-modal__intro"><?php esc_html_e( 'A team mate wants to make sure you have seen their update. Click the button to confirm you have read it.', 'dse-heads-up' ); ?></p>
			<?php foreach ( $pending as $a ) : ?>
				<div class="uao-announce-item<?php echo ( uao_freeze_by() === (int) $a['id'] ) ? ' uao-announce-item--freeze' : ''; ?>" data-announcer="<?php echo esc_attr( $a['id'] ); ?>" data-updated="<?php echo esc_attr( $a['updated'] ); ?>">
					<div class="uao-announce-item__head">
						<span class="uao-announce-item__name"><?php echo esc_html( $a['name'] ); ?><?php if ( uao_freeze_by() === (int) $a['id'] ) : ?> <span class="uao-freeze-chip"><?php esc_html_e( 'Content Freeze', 'dse-heads-up' ); ?></span><?php endif; ?></span>
						<span class="uao-announce-item__time"><?php printf( esc_html__( 'Updated %s', 'dse-heads-up' ), esc_html( uao_format_updated( $a['updated'] ) ) ); ?></span>
					</div>
					<?php if ( $a['working_on'] ) : ?>
						<h3 class="uao-announce-item__title"><?php printf( esc_html__( 'Working on %s', 'dse-heads-up' ), esc_html( $a['working_on'] ) ); ?></h3>
					<?php endif; ?>
					<?php if ( $a['message'] ) : ?>
						<p class="uao-announce-item__msg"><?php echo nl2br( esc_html( $a['message'] ) ); ?></p>
					<?php else : ?>
						<p class="uao-announce-item__msg uao-announce-item__msg--muted"><?php esc_html_e( 'No message provided.', 'dse-heads-up' ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $a['locked'] ) ) : ?>
						<p class="uao-announce-item__locked"><span class="dashicons dashicons-lock"></span><?php esc_html_e( 'The site is under a Content Freeze. This notice will stay until a Super Admin lifts it.', 'dse-heads-up' ); ?></p>
					<?php else : ?>
						<button type="button" class="button button-primary uao-announce-confirm"><?php esc_html_e( 'I’ve seen this', 'dse-heads-up' ); ?></button>
						<span class="uao-announce-item__err" aria-live="polite"></span>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}
add_action( 'admin_footer', 'uao_render_announcement_popup' );

/**
 * AJAX: the viewer confirms they have seen one announcement.
 */
function uao_ajax_ack() {
	check_ajax_referer( 'uao_save', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'dse-heads-up' ) ), 403 );
	}

	$viewer    = get_current_user_id();
	$announcer = isset( $_POST['announcer'] ) ? absint( $_POST['announcer'] ) : 0;
	$updated   = isset( $_POST['updated'] ) ? absint( $_POST['updated'] ) : 0;

	if ( ! $announcer || ! $updated ) {
		wp_send_json_error( array( 'message' => __( 'Invalid announcement.', 'dse-heads-up' ) ), 400 );
	}

	$acks = get_user_meta( $viewer, 'uao_ack', true );
	$acks = is_array( $acks ) ? $acks : array();

	$prev                = isset( $acks[ $announcer ] ) ? (int) $acks[ $announcer ] : 0;
	$acks[ $announcer ]  = max( $prev, $updated );
	update_user_meta( $viewer, 'uao_ack', $acks );

	wp_send_json_success();
}
add_action( 'wp_ajax_uao_ack', 'uao_ajax_ack' );

/*
|--------------------------------------------------------------------------
| Super Admin role + Content Freeze
|--------------------------------------------------------------------------
| A custom "Super Admin" role (a clone of Administrator plus the
| `uao_super_admin` capability) gets an extra CONTENT FREEZE toggle on their
| own Heads-Up card. While a freeze is active, every user WITHOUT the
| `uao_super_admin` capability loses editing capabilities across the whole
| CMS (content, media, themes, plugins, settings, users). They can still log
| in, browse wp-admin and use the Heads-Up board. Any Super Admin can lift
| the freeze. Assign the role in Users -> (edit user) -> Role.
*/

/**
 * Register the Super Admin role (idempotent, runs on init so it also
 * appears after plugin updates without needing re-activation).
 */
function uao_register_super_admin_role() {
	$role = get_role( 'dse_super_admin' );
	if ( ! $role ) {
		$admin = get_role( 'administrator' );
		$caps  = $admin ? $admin->capabilities : array( 'read' => true );

		$caps['uao_super_admin'] = true;
		add_role( 'dse_super_admin', __( 'Super Admin', 'dse-heads-up' ), $caps );
	} elseif ( ! $role->has_cap( 'uao_super_admin' ) ) {
		$role->add_cap( 'uao_super_admin' );
	}
}
add_action( 'init', 'uao_register_super_admin_role' );

/**
 * Is a Content Freeze currently active?
 *
 * @return bool
 */
function uao_is_frozen() {
	$f = get_option( 'uao_freeze' );
	return is_array( $f ) && ! empty( $f['by'] );
}

/**
 * Freeze details: array( 'by' => user_id, 'time' => timestamp ), or empty.
 *
 * @return array
 */
function uao_freeze_info() {
	$f = get_option( 'uao_freeze' );
	return is_array( $f ) ? $f : array();
}

/**
 * User ID of whoever activated the current freeze (0 when not frozen).
 *
 * @return int
 */
function uao_freeze_by() {
	$f = uao_freeze_info();
	return isset( $f['by'] ) ? (int) $f['by'] : 0;
}

/**
 * Does this user hold the Super Admin capability?
 *
 * @param int $user_id User ID.
 * @return bool
 */
function uao_user_is_super( $user_id ) {
	return user_can( $user_id, 'uao_super_admin' );
}

/**
 * The capabilities stripped from non-Super-Admins during a freeze
 * (full lock-down: content, media, comments, themes, plugins, settings,
 * users, import/export and core updates).
 *
 * @return string[]
 */
function uao_frozen_caps() {
	$caps = array(
		// Content.
		'edit_posts', 'edit_others_posts', 'edit_published_posts', 'edit_private_posts',
		'publish_posts', 'delete_posts', 'delete_others_posts', 'delete_published_posts', 'delete_private_posts',
		'edit_pages', 'edit_others_pages', 'edit_published_pages', 'edit_private_pages',
		'publish_pages', 'delete_pages', 'delete_others_pages', 'delete_published_pages', 'delete_private_pages',
		// Media + files.
		'upload_files', 'edit_files', 'unfiltered_upload', 'unfiltered_html',
		// Comments + taxonomies.
		'edit_comments', 'moderate_comments', 'manage_categories', 'manage_links',
		// Appearance.
		'edit_theme_options', 'customize', 'switch_themes', 'install_themes', 'update_themes', 'delete_themes', 'edit_themes',
		// Plugins.
		'activate_plugins', 'install_plugins', 'update_plugins', 'delete_plugins', 'edit_plugins',
		// Site management.
		'manage_options', 'import', 'export', 'update_core',
		// Users.
		'create_users', 'edit_users', 'delete_users', 'promote_users', 'remove_users', 'add_users',
	);
	return apply_filters( 'uao_frozen_caps', $caps );
}

/**
 * While frozen, strip editing capabilities from everyone who is not a
 * Super Admin. Runs on every capability check (user_has_cap), which also
 * covers the REST API, XML-RPC, admin menus and the block editor.
 *
 * @param array $allcaps All capabilities of the user.
 * @return array
 */
function uao_freeze_block_caps( $allcaps ) {
	if ( ! uao_is_frozen() ) {
		return $allcaps;
	}
	// Super Admins keep everything. (Checked via $allcaps to avoid the
	// infinite recursion that current_user_can() would cause here.)
	if ( ! empty( $allcaps['uao_super_admin'] ) ) {
		return $allcaps;
	}
	foreach ( uao_frozen_caps() as $cap ) {
		unset( $allcaps[ $cap ] );
	}
	return $allcaps;
}
add_filter( 'user_has_cap', 'uao_freeze_block_caps', 100 );

/**
 * Red banner on every admin page while a freeze is active.
 */
function uao_render_freeze_banner() {
	if ( ! uao_is_frozen() ) {
		return;
	}
	$info = uao_freeze_info();
	$user = get_userdata( (int) $info['by'] );
	$name = $user ? $user->display_name : __( 'a Super Admin', 'dse-heads-up' );
	$time = isset( $info['time'] ) ? uao_format_updated( (int) $info['time'] ) : '';
	?>
	<div class="uao-freeze-banner">
		<span class="dashicons dashicons-lock"></span>
		<span>
			<strong><?php esc_html_e( 'CONTENT FREEZE', 'dse-heads-up' ); ?></strong>
			<?php printf( esc_html__( '%1$s has locked the site since %2$s. Only Super Admins can make changes.', 'dse-heads-up' ), esc_html( $name ), esc_html( $time ) ); ?>
			<?php if ( current_user_can( 'uao_super_admin' ) ) : ?>
				<?php esc_html_e( 'You are a Super Admin, so you can still edit. Untick Content Freeze on your Heads-Up card to lift it.', 'dse-heads-up' ); ?>
			<?php endif; ?>
		</span>
	</div>
	<?php
}
add_action( 'admin_notices', 'uao_render_freeze_banner', 1 );

/*
|--------------------------------------------------------------------------
| Includes
|--------------------------------------------------------------------------
*/
require_once UAO_PATH . 'includes/render-page.php';
require_once UAO_PATH . 'includes/dashboard-home.php';
