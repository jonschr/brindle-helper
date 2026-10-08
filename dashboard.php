<?php
/**
 * Plain dashboard content copied from the local Ultimate Dashboard widgets.
 * Source records, links and permission requirements are documented in README.md.
 */
defined( 'ABSPATH' ) || exit;

function brindle_helper_dashboard_post_type_access( $post_type ) {
	$type = get_post_type_object( $post_type );
	return $type && current_user_can( $type->cap->edit_posts );
}

function brindle_helper_add_dashboard_widgets() {
	// Reuse the former Welcome ID to preserve the user's visibility and placement.
	wp_add_dashboard_widget( 'brindle_helper_welcome', __( 'Brindle Dashboard', 'brindle-helper' ), 'brindle_helper_render_dashboard_widget', null, null, 'normal', 'high' );
}
add_action( 'wp_dashboard_setup', 'brindle_helper_add_dashboard_widgets', 1000 );

function brindle_helper_dashboard_links() {
	$website = array();
	$management = array();
	if ( brindle_helper_dashboard_post_type_access( 'page' ) ) {
		$website[] = array( admin_url( 'edit.php?post_type=page' ), __( 'Edit Pages', 'brindle-helper' ) );
	}
	// A leftover/custom post type alone does not prove its plugin is loaded.
	if ( defined( 'RENTFETCH_VERSION' ) && brindle_helper_dashboard_post_type_access( 'properties' )
		&& get_posts( array( 'post_type' => 'properties', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) ) ) {
		$website[] = array( admin_url( 'edit.php?post_type=properties' ), __( 'Update Specials', 'brindle-helper' ) );
	}
	if ( function_exists( 'popup_maker_config' ) && brindle_helper_dashboard_post_type_access( 'popup' ) ) {
		$website[] = array( admin_url( 'edit.php?post_type=popup' ), __( 'Update Pop-Up Announcement', 'brindle-helper' ) );
	}
	$website[] = array( home_url( '/' ), __( 'Visit Website', 'brindle-helper' ), true );
	// Match Gravity Forms' entries authorization. Inactive forms can still have leads.
	if ( class_exists( 'GFForms' ) && class_exists( 'GFFormsModel' )
		&& ( current_user_can( 'gravityforms_view_entries' ) || current_user_can( 'gform_full_access' ) )
		&& GFFormsModel::get_form_ids( null, false ) ) {
		$management[] = array( admin_url( 'admin.php?page=gf_entries' ), __( 'View Leads', 'brindle-helper' ) );
	}
	if ( defined( 'RENTFETCH_VERSION' ) && function_exists( 'rentfetch_options_page_html' ) && current_user_can( 'manage_options' ) ) {
		$management[] = array( admin_url( 'admin.php?page=rentfetch-options' ), __( 'Rent Fetch Settings', 'brindle-helper' ) );
	}
	return array_filter( array(
		__( 'Your website', 'brindle-helper' ) => $website,
		__( 'Management', 'brindle-helper' )   => $management,
	) );
}

function brindle_helper_dashboard_link( $url, $label, $new_tab = false ) {
	printf( '<a href="%s"%s>%s%s</a>', esc_url( $url ), $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '', esc_html( $label ), $new_tab ? ' <span class="dashicons dashicons-external" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( ' (opens in a new tab)', 'brindle-helper' ) . '</span>' : '' );
}

function brindle_helper_render_dashboard_widget() {
	echo '<p>' . esc_html__( 'You can navigate through the tabs in the left sidebar to edit your website. If you get stuck, we have a support form you can fill in for our team to help out. Thank you for choosing Brindle, we appreciate your partnership.', 'brindle-helper' ) . '</p>';
	foreach ( brindle_helper_dashboard_links() as $heading => $links ) {
		echo '<h3>' . esc_html( $heading ) . '</h3><ul class="brindle-helper-dashboard-links">';
		foreach ( $links as $link ) {
			echo '<li>';
			brindle_helper_dashboard_link( ...$link );
			echo '</li>';
		}
		echo '</ul>';
	}
	echo '<h3>' . esc_html__( 'Help & feedback', 'brindle-helper' ) . '</h3>';
	echo '<ul class="brindle-helper-dashboard-links"><li><p>' . esc_html__( 'Get in touch with Brindle for help with website updates or to open a ticket request.', 'brindle-helper' ) . '</p>';
	brindle_helper_dashboard_link( 'https://brindledigitalmarketing.hipporello.net/desk/form/b93fef0e6ae444a5a98d75f9f086a00a', __( 'Open Support Ticket', 'brindle-helper' ), true );
	echo '</li><li><p>' . esc_html__( "Enjoy working with Brindle? We'd love for you to share your experience by leaving us a review!", 'brindle-helper' ) . '</p>';
	brindle_helper_dashboard_link( 'https://g.page/r/CaT9YdZ8xMi4EB0/review', __( 'Leave a Review', 'brindle-helper' ), true );
	echo '</li></ul>';
}
