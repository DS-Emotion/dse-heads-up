<?php
/**
 * Renders the DSE User Progress Overview as a stacked list of status cards.
 *
 * @package UserActivityOverview
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Output the overview page.
 */
function uao_render_page() {
	if ( ! current_user_can( 'list_users' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this page.', 'dse-heads-up' ) );
	}

	$current_user_id = get_current_user_id();
	$statuses        = uao_statuses();
	$user_guide      = uao_get_user_guide();

	// ---- Fetch all users. ----
	$users = get_users( array( 'number' => -1, 'orderby' => 'display_name' ) );

	// ---- Stats. ----
	$total_users = count( $users );
	$count_prog  = 0;
	foreach ( $users as $u ) {
		if ( 'in_progress' === uao_get_status( $u->ID ) ) {
			$count_prog++;
		}
	}

	// ---- Sort: yourself first, then In Progress, then Inactive; then by name. ----
	$rank = array( 'in_progress' => 0, 'inactive' => 1 );
	$rows = $users;
	usort(
		$rows,
		function ( $a, $b ) use ( $rank, $current_user_id ) {
			// Always pin the logged-in user to the very top so they can set
			// their own status without scrolling to find their name.
			$sa = ( (int) $a->ID === $current_user_id );
			$sb = ( (int) $b->ID === $current_user_id );
			if ( $sa !== $sb ) {
				return $sa ? -1 : 1;
			}
			$ra = $rank[ uao_get_status( $a->ID ) ];
			$rb = $rank[ uao_get_status( $b->ID ) ];
			if ( $ra !== $rb ) {
				return $ra - $rb;
			}
			return strcasecmp( $a->display_name, $b->display_name );
		}
	);

	// ---- Pagination. ----
	$per_page = (int) get_user_meta( $current_user_id, 'uao_per_page', true );
	if ( $per_page < 1 ) {
		$per_page = 25;
	}
	$paged       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
	$total_rows  = count( $rows );
	$total_pages = max( 1, (int) ceil( $total_rows / $per_page ) );
	$paged       = min( $paged, $total_pages );
	$page_rows   = array_slice( $rows, ( $paged - 1 ) * $per_page, $per_page );
	?>
	<div class="wrap uao-wrap">

		<div class="uao-header">
			<div class="uao-header__title">
				<h1><?php esc_html_e( 'DSE Heads-Up', 'dse-heads-up' ); ?></h1>
				<p class="uao-subtitle"><?php esc_html_e( 'Set your status and let the team see what you are working on', 'dse-heads-up' ); ?></p>
			</div>
			<div class="uao-header__right">
				<?php if ( $user_guide ) : ?>
					<a href="#" class="uao-guide-link" id="uao-guide-open"><span class="dashicons dashicons-book-alt"></span><?php esc_html_e( 'User Guide', 'dse-heads-up' ); ?></a>
				<?php endif; ?>
				<div class="uao-stats">
				<div class="uao-stat">
					<span class="uao-stat__label"><?php esc_html_e( 'Total Users', 'dse-heads-up' ); ?></span>
					<span class="uao-stat__num"><?php echo esc_html( number_format_i18n( $total_users ) ); ?></span>
				</div>
				<div class="uao-stat">
					<span class="uao-stat__label"><?php esc_html_e( 'In Progress', 'dse-heads-up' ); ?></span>
					<span class="uao-stat__num uao-stat__num--amber"><?php echo esc_html( number_format_i18n( $count_prog ) ); ?></span>
				</div>
				</div>
			</div>
		</div>

		<div class="uao-cards">
		<?php if ( empty( $page_rows ) ) : ?>
			<p class="uao-empty"><?php esc_html_e( 'No users found.', 'dse-heads-up' ); ?></p>
		<?php else : ?>
			<?php
			foreach ( $page_rows as $u ) :
				$status     = uao_get_status( $u->ID );
				$is_open    = uao_is_open( $status );
				$is_self    = ( (int) $u->ID === $current_user_id );
				$last_login = (int) get_user_meta( $u->ID, 'uao_last_login', true );
				$working_on = get_user_meta( $u->ID, 'uao_working_on', true );
				$message    = get_user_meta( $u->ID, 'uao_message', true );
				$updated    = (int) get_user_meta( $u->ID, 'uao_updated', true );

				$classes = array( 'uao-card', 'uao-card--' . $status );
				if ( $is_open ) {
					$classes[] = 'is-open';
				}
				if ( $is_self ) {
					$classes[] = 'is-self';
				}
				?>
				<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-user="<?php echo esc_attr( $u->ID ); ?>">

					<div class="uao-card__head">
						<div class="uao-card__name">
							<span class="uao-card__namerow">
								<?php echo esc_html( $u->display_name ); ?>
								<?php if ( $is_self ) : ?><span class="uao-you"><?php esc_html_e( 'You', 'dse-heads-up' ); ?></span><?php endif; ?>
							</span>
							<?php if ( uao_is_logged_in( $u->ID ) ) : ?>
								<span class="uao-online"><span class="uao-online__dot"></span><?php esc_html_e( 'Currently logged in', 'dse-heads-up' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="uao-card__col uao-card__login">
							<span class="uao-card__lbl"><?php esc_html_e( 'Last Log In', 'dse-heads-up' ); ?></span>
							<span class="uao-card__val"><?php echo esc_html( uao_time_ago( $last_login ) ); ?></span>
						</div>
						<div class="uao-card__col uao-card__status">
							<span class="uao-card__lbl"><?php esc_html_e( 'Status', 'dse-heads-up' ); ?></span>
							<span class="uao-badge">
								<span class="uao-badge__dot"></span>
								<span class="uao-badge__text"><?php echo esc_html( $statuses[ $status ]['label'] ); ?></span>
							</span>
						</div>
					</div>

					<?php if ( $is_self ) : ?>

						<div class="uao-card__body uao-self-view">
							<h3 class="uao-card__title uao-view-title"<?php echo $working_on ? '' : ' style="display:none;"'; ?>><?php echo $working_on ? esc_html( sprintf( __( 'Working on %s', 'dse-heads-up' ), $working_on ) ) : ''; ?></h3>
							<p class="uao-card__msg uao-view-msg<?php echo $message ? '' : ' uao-card__msg--muted'; ?>"><?php echo $message ? esc_html( $message ) : esc_html__( 'No update yet — set your status and message.', 'dse-heads-up' ); ?></p>
							<p class="uao-card__time uao-view-time"<?php echo $updated ? '' : ' style="display:none;"'; ?>><?php echo $updated ? esc_html( sprintf( __( 'Updated %s', 'dse-heads-up' ), uao_format_updated( $updated ) ) ) : ''; ?></p>
							<p><button type="button" class="button uao-edit-btn"><?php esc_html_e( 'Edit', 'dse-heads-up' ); ?></button></p>
						</div>

						<div class="uao-card__edit" style="display:none;">
							<div class="uao-statuspick" role="group" aria-label="<?php esc_attr_e( 'Set your status', 'dse-heads-up' ); ?>">
								<?php foreach ( $statuses as $key => $data ) : ?>
									<button type="button" class="uao-statusbtn uao-statusbtn--<?php echo esc_attr( $key ); ?><?php echo ( $key === $status ) ? ' is-active' : ''; ?>" data-status="<?php echo esc_attr( $key ); ?>">
										<span class="uao-statusbtn__dot"></span><?php echo esc_html( $data['label'] ); ?>
									</button>
								<?php endforeach; ?>
							</div>
							<label class="uao-field">
								<span class="uao-field__lbl"><?php esc_html_e( 'Working on', 'dse-heads-up' ); ?></span>
								<input type="text" class="uao-input" data-field="working_on" value="<?php echo esc_attr( $working_on ); ?>" placeholder="<?php esc_attr_e( 'e.g. Home page', 'dse-heads-up' ); ?>" />
							</label>
							<label class="uao-field">
								<span class="uao-field__lbl"><?php esc_html_e( 'Message', 'dse-heads-up' ); ?></span>
								<textarea class="uao-input" data-field="message" rows="4" placeholder="<?php esc_attr_e( 'Describe what you are working on…', 'dse-heads-up' ); ?>"><?php echo esc_textarea( $message ); ?></textarea>
							</label>
							<p class="uao-editbar">
								<button type="button" class="button button-primary uao-done-btn"><?php esc_html_e( 'Done', 'dse-heads-up' ); ?></button>
								<span class="uao-savestate" aria-live="polite"></span>
								<span class="uao-card__time uao-view-time"<?php echo $updated ? '' : ' style="display:none;"'; ?>><?php echo $updated ? esc_html( sprintf( __( 'Updated %s', 'dse-heads-up' ), uao_format_updated( $updated ) ) ) : ''; ?></span>
							</p>
						</div>

					<?php elseif ( $is_open ) : ?>

						<div class="uao-card__body">
							<?php if ( $working_on ) : ?>
								<h3 class="uao-card__title"><?php printf( esc_html__( 'Working on %s', 'dse-heads-up' ), esc_html( $working_on ) ); ?></h3>
							<?php endif; ?>
							<?php if ( $message ) : ?>
								<p class="uao-card__msg"><?php echo nl2br( esc_html( $message ) ); ?></p>
							<?php endif; ?>
							<?php if ( ! $working_on && ! $message ) : ?>
								<p class="uao-card__msg uao-card__msg--muted"><?php esc_html_e( 'No message yet.', 'dse-heads-up' ); ?></p>
							<?php endif; ?>
							<?php if ( $updated ) : ?>
								<p class="uao-card__time"><?php printf( esc_html__( 'Updated %s', 'dse-heads-up' ), esc_html( uao_format_updated( $updated ) ) ); ?></p>
							<?php endif; ?>
						</div>

					<?php endif; ?>

				</div>
			<?php endforeach; ?>
		<?php endif; ?>
		</div>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="uao-pagination tablenav-pages">
				<?php
				$base = add_query_arg(
					array(
						'page'  => 'dse-heads-up',
						'paged' => '%#%',
					),
					admin_url( 'admin.php' )
				);
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => $base,
							'format'    => '',
							'current'   => $paged,
							'total'     => $total_pages,
							'prev_text' => '‹',
							'next_text' => '›',
						)
					)
				);
				?>
			</div>
		<?php endif; ?>


		<?php if ( $user_guide ) : ?>
		<div class="uao-guide-modal" id="uao-guide-modal" style="display:none;" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'User Guide', 'dse-heads-up' ); ?>">
			<div class="uao-guide-modal__backdrop" data-uao-guide-close></div>
			<div class="uao-guide-modal__box" role="document">
				<button type="button" class="uao-guide-modal__close" data-uao-guide-close aria-label="<?php esc_attr_e( 'Close', 'dse-heads-up' ); ?>">&times;</button>
				<h2 class="uao-guide-modal__title"><?php esc_html_e( 'User Guide', 'dse-heads-up' ); ?></h2>
				<div class="uao-guide-modal__content"><?php echo esc_html( $user_guide ); ?></div>
			</div>
		</div>
		<?php endif; ?>

	</div>
	<?php
}
