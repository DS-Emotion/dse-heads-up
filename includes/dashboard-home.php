<?php
/**
 * Render the full User Activity Overview on the WordPress Dashboard home
 * screen, positioned above the dashboard widget area.
 *
 * @package UserActivityOverview
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Output the full overview at the top of the Dashboard home (index.php),
 * before the widget columns.
 *
 * Hooked to admin_notices, which WordPress prints at the top of the page
 * body — above the "Dashboard" heading and the widget area.
 */
function uao_render_on_dashboard_home() {
	$screen = get_current_screen();
	if ( ! $screen || 'dashboard' !== $screen->id ) {
		return;
	}
	if ( ! current_user_can( 'list_users' ) ) {
		return;
	}

	echo '<div class="uao-dashboard-embed">';
	uao_render_page();
	echo '</div>';
}
add_action( 'admin_notices', 'uao_render_on_dashboard_home' );
