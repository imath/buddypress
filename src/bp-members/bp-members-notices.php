<?php
/**
 * BuddyPress Notices functions.
 *
 * @package buddypress\bp-members\bp-members-notices
 * @since 14.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load the Commmunity Notices Admin.
add_action( bp_core_admin_hook(), array( 'BP_Members_Notices_Admin', 'register_notices_admin' ), 9 );

/**
 * Send a notice.
 *
 * @since 14.0.0
 *
 * @param array $args {
 *     Array of parameters.
 *     @type string $title   The subject of the notice. Required. Defaults to ''.
 *     @type string $content The content to be noticed. Required. Defaults to ''.
 *     @type string $target  The targeted audience. Optional. Defaults to "community".
 *     @type string $link    The action link of the notice. Optional. Defaults to ''.
 * }
 * @return integer|WP_Error The notice ID on success, a WP Error on failure.
 */
function bp_members_send_notice( $args = array() ) {

	if ( ! bp_current_user_can( 'bp_moderate' ) ) {
		return new WP_Error( 'bp_notices_unallowed', __( 'You are not allowed to send community notices.', 'buddypress' ) );
	}

	$r     = bp_parse_args(
		$args,
		array(
			'title'   => '',
			'content' => '',
			'target'  => 'community',
			'link'    => '',
		)
	);
	$attrs = array();

	if ( ! $r['subject'] || ! $r['content'] ) {
		return new WP_Error( 'bp_notices_missing_data', __( 'The notice subject and content are required fields.', 'buddypress' ) );
	}

	// Sanitize data.
	$subject = sanitize_text_field( $r['subject'] );
	$content = sanitize_textarea_field( $r['content'] );

	$attrs['target'] = 'community';
	if ( in_array( $r['target'], array( 'community', 'admins', 'writers' ), true ) ) {
		$attrs['target'] = $r['target'];
	}

	if ( $r['link'] ) {
		$attrs['link'] = sanitize_url( $r['link'] );
	}


	// Use the block grammar to save content.
	$message = serialize_block(
		array(
			'blockName'    => 'bp/member-notice',
			'innerContent' => array( $content ),
			'attrs'        => $attrs,
		)
	);

	$notice            = new BP_Members_Notice();
	$notice->subject   = sanitize_text_field( $subject );
	$notice->message   = $message;
	$notice->date_sent = bp_core_current_time();
	$notice->is_active = 1;

	// Send it.
	$notice_id = $notice->save();

	/**
	 * Fires after a notice has been successfully sent.
	 *
	 * Please stop using this hook.
	 *
	 * @since 1.0.0
	 * @deprecated 14.0.0
	 *
	 * @param string            $subject Subject of the notice.
	 * @param string            $content Content of the notice.
	 * @param BP_Members_Notice $notice  Notice object sent.
	 */
	do_action_deprecated( 'messages_send_notice', array( $subject, $content, $notice ), '14.0.0', 'bp_members_notice_sent' );

	$saved_values = get_object_vars( $notice );

	if ( $notice_id ) {
		/**
		 * Fires after a notice has been successfully added to the sending queue.
		 *
		 * @since 14.0.0
		 *
		 * @param integer $notice_id    The notice ID.
		 * @param array   $saved_values The list of the saved values keyed by object properties.
		 */
		do_action( 'bp_members_notice_sent', $notice_id, $saved_values );
	}

	return $notice_id;
}

/**
 * Dismiss a sitewide notice for a user.
 *
 * @since 14.0.0
 *
 * @param int $user_id   ID of the user to dismiss the notice for.
 *                       Defaults to the logged-in user.
 * @param int $notice_id ID of the notice to be dismissed.
 *                       Defaults to the currently active notice.
 * @return bool False on failure, true if notice is dismissed
 *              (or was already dismissed).
 */
function bp_members_dismiss_notice( $user_id = 0, $notice_id = 0 ) {
	$retval = false;
	if ( ! $user_id ) {
		$user_id = bp_loggedin_user_id();
	}

	// Bail if no user is set.
	if ( ! $user_id ) {
		return $retval;
	}

	if ( $notice_id ) {
		$notice = new BP_Members_Notice( $notice_id );
	} else {
		$notice = BP_Members_Notice::get_active();
	}

	// Bail if no notice is set.
	if ( empty( $notice->id ) ) {
		return $retval;
	}

	// Fetch the user's closed notices and add the new item.
	$closed_notices = (array) bp_get_user_meta( $user_id, 'closed_notices', true );
	$closed_notices = array_filter( $closed_notices );

	if ( in_array( (int) $notice->id, $closed_notices, true ) ) {
		// The notice has already been dismissed, so there's nothing to do.
		$retval = true;
	} else {
		// Add the notice to the closed_notices meta.
		$closed_notices[] = (int) $notice->id;
		$closed_notices   = array_map( 'absint', array_unique( $closed_notices ) );
		$success          = bp_update_user_meta( $user_id, 'closed_notices', $closed_notices );

		// The return value from update_user_meta() could be an integer or a boolean.
		$retval = (bool) $success;
	}

	return $retval;
}

/**
 * Handle editing of sitewide notices.
 *
 * @since 2.4.0 This function was split from messages_screen_notices(). See #6505.
 *
 * @return bool
 */
function bp_members_edit_notice() {

	/**
	 *
	 *
	 *
	 *
	 * @todo check to see is we still need this code.
	 *
	 */
	return false;

	// Get the notice ID (1|2|3).
	$notice_id = bp_action_variable( 1 );

	// Bail if notice ID is not numeric.
	if ( empty( $notice_id ) || ! is_numeric( $notice_id ) ) {
		return false;
	}

	// Bail if the current user doesn't have administrator privileges.
	if ( ! bp_current_user_can( 'bp_moderate' ) ) {
		return false;
	}

	// Get the action (deactivate|activate|delete).
	$action = sanitize_key( bp_action_variable( 0 ) );

	// Check the nonce.
	check_admin_referer( "messages_{$action}_notice" );

	// Get the notice from database.
	$notice   = new BP_Members_Notice( $notice_id );
	$success  = false;
	$feedback = '';

	// Take action.
	switch ( $action ) {

		// Deactivate.
		case 'deactivate' :
			$success  = $notice->deactivate();
			$feedback = true === $success
				? __( 'Notice deactivated successfully.',              'buddypress' )
				: __( 'There was a problem deactivating that notice.', 'buddypress' );
			break;

		// Activate.
		case 'activate' :
			$success  = $notice->activate();
			$feedback = true === $success
				? __( 'Notice activated successfully.',              'buddypress' )
				: __( 'There was a problem activating that notice.', 'buddypress' );
			break;

		// Delete.
		case 'delete' :
			$success  = $notice->delete();
			$feedback = true === $success
				? __( 'Notice deleted successfully.',              'buddypress' )
				: __( 'There was a problem deleting that notice.', 'buddypress' );
			break;
	}

	// Feedback.
	if ( ! empty( $feedback ) ) {

		// Determine message type.
		$type = ( true === $success )
			? 'success'
			: 'error';

		// Add feedback message.
		bp_core_add_message( $feedback, $type );
	}

	// Redirect.
	$redirect_to = bp_loggedin_user_url( bp_members_get_path_chunks( array( bp_get_messages_slug(), 'notices' ) ) );

	bp_core_redirect( $redirect_to );
}


/**
 * Prepend a notification about the active Sitewide notice.
 *
 * @since 14.0.0
 *
 * @param false|array $notifications False if there are no items, an array of notification items otherwise.
 * @param int         $user_id       The user ID.
 * @return false|array               False if there are no items, an array of notification items otherwise.
 */
function bp_members_get_notice_for_user( $notifications, $user_id ) {
	if ( ! doing_action( 'admin_bar_menu' ) ) {
		return $notifications;
	}

	$notice = BP_Members_Notice::get_active();
	if ( empty( $notice->id ) ) {
		return $notifications;
	}

	$closed_notices = bp_get_user_meta( $user_id, 'closed_notices', true );
	if ( empty( $closed_notices ) ) {
		$closed_notices = array();
	}

	if ( in_array( $notice->id, $closed_notices, true ) ) {
		return $notifications;
	}

	$notice_notification = (object) array(
		'id'                => 0,
		'user_id'           => $user_id,
		'item_id'           => $notice->id,
		'secondary_item_id' => 0,
		'component_name'    => 'members',
		'component_action'  => 'new_notice',
		'date_notified'     => $notice->date_sent,
		'is_new'            => 1,
		'total_count'       => 1,
		'content'           => $notice->message,
		'href'              => bp_loggedin_user_url(),
	);

	if ( ! is_array( $notifications ) ) {
		$notifications = array( $notice_notification );
	} else {
		array_unshift( $notifications, $notice_notification );
	}

	return $notifications;
}
add_filter( 'bp_core_get_notifications_for_user', 'bp_members_get_notice_for_user', 10, 2 );

/**
 * Output the title of a notice.
 *
 * @since 14.0.0
 *
 * @param BP_Members_Notice|null $notice The notice object.
 */
function bp_notice_title( $notice = null ) {
	// Escaping is made in `bp-members/bp-members-filters.php`.
	// phpcs:ignore WordPress.Security.EscapeOutput
	echo bp_get_notice_title( $notice );
}

/**
 * Get the title of a notice.
 *
 * @since 14.0.0
 *
 * @param BP_Members_Notice|null $notice The notice object.
 * @return string The notice title.
 */
function bp_get_notice_title( $notice = null ) {
	$notice_title = '';

	if ( ! empty( $notice->subject ) ) {
		/**
		 * Stop using this filter, use `bp_get_notice_title` instead.
		 *
		 * @since 1.0.0
		 * @deprecated 14.0.0
		 *
		 * @param string $subject Subject of the current notice in the loop.
		 */
		$notice_title = apply_filters_deprecated( 'bp_get_message_notice_subject', array( $notice->subject ), '14.0.0', 'bp_get_notice_title' );
	}

	/**
	 * Filter the notice title.
	 *
	 * @since 14.0.0
	 *
	 * @param string                 $notice_title The notice title.
	 * @param BP_Members_Notice|null $notice       The notice object if it exists. Null otherwise.
	 */
	return apply_filters( 'bp_get_notice_title', $notice_title, $notice );
}

/**
 * Output the content of a notice.
 *
 * @since 14.0.0
 *
 * @param BP_Members_Notice|null $notice The notice object.
 */
function bp_notice_content( $notice = null ) {
	// Escaping is made in `bp-messages/bp-messages-filters.php`.
	// phpcs:ignore WordPress.Security.EscapeOutput
	echo bp_get_notice_content( $notice );
}

/**
 * Get the content of a notice.
 *
 * @since 14.0.0
 *
 * @param BP_Members_Notice|null $notice The notice object.
 * @return string The notice content.
 */
function bp_get_notice_content( $notice = null ) {
	$notice_content = '';

	if ( ! empty( $notice->message ) ) {
		$notice_data = parse_blocks( $notice->message );

		if ( isset( $notice_data[0]['innerHTML'] ) ) {
			$notice_content = $notice_data[0]['innerHTML'];
		} else {
			$notice_content = $notice->message;
		}

		$notice_content = apply_filters_deprecated( 'bp_get_message_notice_text', array( $notice_content ), '14.0.0', 'bp_get_notice_content' );
	}

	/**
	 * Filters the notice content.
	 *
	 * @since 14.0.0
	 *
	 * @param string                 $notice_content The content of the notice.
	 * @param BP_Members_Notice|null $notice         The notice object if it exists. Null otherwise.
	 */
	return apply_filters( 'bp_get_notice_content', $notice_content, $notice );
}

/**
 * Get the type of a notice.
 *
 * @since 14.0.0
 *
 * @param BP_Members_Notice|null $notice The notice object.
 * @return string The notice content.
 */
function bp_get_notice_type( $notice = null ) {
	$notice_type = 'dashicons-buddicons-community';

	if ( empty( $notice->message ) ) {
		return;
	}

	$notice_data = parse_blocks( $notice->message );

	if ( isset( $notice_data[0]['attrs']['target'] ) ) {
		$target = $notice_data[0]['attrs']['target'];

		if ( 'admins' === $target ) {
			$notice_type = 'dashicons-dashboard';
		} elseif ( 'writers' === $target ) {
			$notice_type = 'dashicons-edit';
		}
	}

	/**
	 * Filters the notice type.
	 *
	 * @since 14.0.0
	 *
	 * @param string                 $notice_type The type of the notice.
	 * @param BP_Members_Notice|null $notice      The notice object if it exists. Null otherwise.
	 */
	return apply_filters( 'bp_get_notice_type', $notice_type, $notice );
}

/**
 * Output the URL for dismissing a notice for the current user.
 *
 * @since 14.0.0
 */
function bp_notice_dismiss_url() {
	echo esc_url( bp_get_notice_dismiss_url() );
}

/**
 * Get the URL for dismissing the current notice for the current user.
 *
 * @since 14.0.0
 *
 * @return string URL for dismissing the current notice for the current user.
 */
function bp_get_notice_dismiss_url() {
	$link = wp_nonce_url(
		add_query_arg( array( 'page' => 'bp-sitewide-notices', 'action' => 'dismiss' ), bp_get_admin_url( 'users.php' ) ),
		'messages_dismiss_notice'
	);

	/**
	 * Filters the URL for dismissing the current notice for the current user.
	 *
	 * @since 9.0.0
	 * @deprecated 14.0.0
	 *
	 * @param string $link URL for dismissing the current notice.
	 */
	$link = apply_filters_deprecated( 'bp_get_message_notice_dismiss_link', array( $link ), '14.0.0', 'bp_get_notice_dismiss_url' );


	/**
	 * Filters the URL for dismissing the current notice for the current user.
	 *
	 * @since 14.0.0
	 *
	 * @param string $link URL for dismissing the current notice.
	 */
	return apply_filters( 'bp_get_notice_dismiss_url', $link );
}

/**
 * Used to render the active notice after the WP Admin Bar.
 *
 * @since 14.0.0
 */
function bp_render_active_notice() {
	$notice = BP_Members_Notice::get_active();

	if ( empty( $notice->id ) ) {
		return;
	}
	?>
	<aside popover="auto" id="bp-notices-container" role="complementary" tabindex="-1">
		<section>
			<header class="bp-notice-header">
				<h2><?php bp_notice_title( $notice ); ?></h2>
			</header>
			<div class="bp-notice-body">
				<div class="bp-notice-type dashicons <?php echo esc_attr( bp_get_notice_type( $notice ) ); ?>" ></div>
				<div class="bp-notice-content">
					<?php bp_notice_content( $notice ); ?>
				</div>
			</div>
			<footer class="bp-notice-footer">
			</footer>
		</section>
	</aside>
	<?php
}

/**
 * Callback function to render the BP Sitewide Notices Block.
 *
 * @since 14.0.0
 *
 * @param array $attributes The block attributes.
 * @return string HTML output.
 */
function bp_members_render_notices_block( $attributes = array() ) {
	$block_args = bp_parse_args(
		$attributes,
		array(
			'title' => '',
		),
		'widget_object_sitewide_notices'
	);

	if ( ! is_user_logged_in() ) {
		return;
	}

	$feedback_tpl  = '<div class="components-placeholder">' . "\n";
	$feedback_tpl .= '<div class="components-placeholder__label">%1$s</div>' . "\n";
	$feedback_tpl .= '<div class="components-placeholder__fieldset">%2$s</div>' . "\n";
	$feedback_tpl .= '</div>';

	// Don't display the block if there are no Notices to show.
	$notice = BP_Members_Notice::get_active();
	if ( empty( $notice->id ) ) {
		// Previewing the Block inside the editor.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return sprintf(
				$feedback_tpl,
				esc_html__( 'Preview unavailable', 'buddypress' ),
				esc_html__( 'No active sitewide notices.', 'buddypress' )
			);
		}

		return;
	}

	// Only enqueue common/specific scripts and data once per page load.
	if ( ! wp_script_is( 'bp-sitewide-notices-script', 'enqueued' ) ) {
		wp_enqueue_script( 'bp-sitewide-notices-script' );
	}

	$closed_notices = (array) bp_get_user_meta( bp_loggedin_user_id(), 'closed_notices', true );

	if ( in_array( $notice->id, $closed_notices, true ) ) {
		// Previewing the Block inside the editor.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return sprintf(
				$feedback_tpl,
				esc_html__( 'Preview unavailable', 'buddypress' ),
				esc_html__( 'You dismissed the sitewide notice.', 'buddypress' )
			);
		}

		return;
	}

	// There is an active, non-dismissed notice to show.
	$title = $block_args['title'];

	$classnames         = 'widget_bp_core_sitewide_messages buddypress widget';
	$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $classnames ) );

	$widget_content = '<div class="bp-sitewide-notice-block">';

	if ( $title ) {
		$widget_content .= sprintf(
			'<h2 class="widget-title">%s</h2>',
			esc_html( $title )
		);
	}

	$widget_content .= sprintf(
		'<div class="bp-sitewide-notice-message info bp-notice" rel="n-%1$d">
			<strong>%2$s</strong>
			<a href="%3$s" class="bp-tooltip button dismiss-notice" data-bp-tooltip="%4$s" data-bp-sitewide-notice-id="%5$d"><span class="bp-screen-reader-text">%6$s</span> <span aria-hidden="true">&#x2716;</span></a>
			%7$s
		</div>',
		esc_attr( $notice->id ),
		bp_get_notice_title( $notice ),
		esc_url( bp_get_notice_dismiss_url() ),
		esc_attr__( 'Dismiss this notice', 'buddypress' ),
		esc_attr( $notice->id ),
		esc_html__( 'Dismiss this notice', 'buddypress' ),
		bp_get_notice_content( $notice )
	);

	$widget_content .= '</div>';

	// Enqueue BP Tooltips.
	wp_enqueue_style( 'bp-tooltips' );

	if ( ! did_action( 'dynamic_sidebar_before' ) ) {
		return sprintf(
			'<div %1$s>%2$s</div>',
			$wrapper_attributes,
			$widget_content
		);
	}

	return $widget_content;
}