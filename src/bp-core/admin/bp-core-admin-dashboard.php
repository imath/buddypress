<?php
/**
 * BuddyPress Dashboard.
 *
 * @package buddypress\bp-core\admin
 * @since 14.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bp_core_admin_dashboard() {
	?>
	<div class="wrap">

		<h1 class="wp-heading-inline"><?php esc_html_e( 'BuddyPress', 'buddypress' ); ?> </h1>
		<hr class="wp-header-end">
	</div>
	<?php
}
