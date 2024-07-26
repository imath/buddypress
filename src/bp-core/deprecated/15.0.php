<?php
/**
 * Deprecated functions.
 *
 * @package BuddyPress
 * @deprecated 15.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Update the spam status of the member on multisite configs.
 *
 * @since 5.0.0
 * @deprecated 15.0.0
 *
 * @param int    $user_id The user ID to spam or ham.
 * @param string $value   '0' to mark the user as `ham`, '1' to mark as `spam`.
 * @return bool          True if the spam status of the member changed.
 *                       False otherwise.
 */
function bp_core_update_member_status( $user_id = 0, $value = 0 ) {
	_deprecated_function( __FUNCTION__, '15.0.0' );

	if ( ! is_multisite() || ! $user_id ) {
		return false;
	}

	/**
	 * The `update_user_status()` function is deprecated since WordPress 5.3.0.
	 * Continue to use it if WordPress current major version is lower than 5.3.
	 */
	if ( bp_get_major_wp_version() < 5.3 ) {
		return update_user_status( $user_id, 'spam', $value );
	}

	if ( $value ) {
		$value = '1';
	}

	// Otherwise use the replacement function.
	$user = wp_update_user(
		array(
			'ID'   => $user_id,
			'spam' => $value,
		)
	);

	if ( is_wp_error( $user ) ) {
		return false;
	}

	return true;
}
