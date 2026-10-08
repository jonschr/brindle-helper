<?php
/** Core administration and user-management policy; see README.md. */
defined( 'ABSPATH' ) || exit;

function brindle_helper_operational_roles( $actor_id = null ) {
	$actor = null === $actor_id ? wp_get_current_user() : get_userdata( $actor_id );
	if ( ! $actor ) {
		return array();
	}
	$roles = array( 'brindle_client_administrator', 'brindle_client_editor', 'subscriber' );
	if ( in_array( 'brindle_employee', $actor->roles, true ) ) {
		$roles[] = 'brindle_employee';
	} elseif ( ! in_array( 'brindle_client_administrator', $actor->roles, true ) ) {
		return array();
	}
	return $roles;
}

function brindle_helper_can_manage_user( $actor_id, $target_id ) {
	$target = get_userdata( $target_id );
	return $target && ! empty( $target->roles )
		&& ! ( is_multisite() && is_super_admin( $target_id ) )
		&& ! array_diff( $target->roles, brindle_helper_operational_roles( $actor_id ) );
}

function brindle_helper_map_core_capabilities( $caps, $capability, $user_id, $args ) {
	if ( ! brindle_helper_is_custom_role_user( $user_id ) ) {
		return $caps;
	}
	if ( in_array( $capability, array( 'edit_post', 'edit_page' ), true ) && isset( $args[0] ) && is_scalar( $args[0] ) ) {
		$post = get_post( absint( $args[0] ) );
		if ( $post && 'revision' === $post->post_type ) {
			$post = get_post( $post->post_parent );
		}
		if ( $post && 'page' === $post->post_type && $post->ID === (int) get_option( 'wp_page_for_privacy_policy' ) ) {
			// Core adds manage_privacy_options -> manage_options to editing this content page.
			// Keep all ordinary page-editing checks while configuration remains guarded separately.
			return array_values( array_diff( $caps, array( 'manage_options' ) ) );
		}
	}
	// These native meta capabilities otherwise map to manage_options, ignoring stored denials.
	if ( in_array( $capability, array( 'export_others_personal_data', 'erase_others_personal_data', 'view_site_health_checks' ), true ) ) {
		return array( 'do_not_allow' );
	}
	if ( in_array( $capability, array( 'list_users', 'create_users', 'edit_users', 'promote_users', 'delete_users', 'remove_users', 'add_users' ), true ) && ! brindle_helper_operational_roles( $user_id ) ) {
		return array( 'do_not_allow' );
	}
	if ( in_array( $capability, array( 'edit_user', 'promote_user', 'delete_user', 'remove_user', 'switch_to_user' ), true ) ) {
		$target_id = isset( $args[0] ) && is_scalar( $args[0] ) ? absint( $args[0] ) : 0;
		// Every role can maintain its own profile; self-deletion and protected-account changes are denied.
		if ( 'edit_user' === $capability && $target_id === (int) $user_id ) {
			return $caps;
		}
		if ( ! brindle_helper_can_manage_user( $user_id, $target_id ) || ( $target_id === (int) $user_id && in_array( $capability, array( 'delete_user', 'remove_user' ), true ) ) ) {
			return array( 'do_not_allow' );
		}
	}
	return $caps;
}
add_filter( 'map_meta_cap', 'brindle_helper_map_core_capabilities', 200, 4 );

add_filter( 'editable_roles', static function ( $roles ) {
	return brindle_helper_is_custom_role_user() ? array_intersect_key( $roles, array_flip( brindle_helper_operational_roles() ) ) : $roles;
} );

// Core form and REST handlers use editable_roles. Validate defaults and role-less submissions too.
add_action( 'user_profile_update_errors', static function ( $errors, $update, $user ) {
	if ( ! brindle_helper_is_custom_role_user() ) {
		return;
	}
	$role = $user->role ?? ( $update ? null : get_option( 'default_role', 'subscriber' ) );
	if ( null !== $role && ! in_array( $role, brindle_helper_operational_roles(), true ) ) {
		$errors->add( 'brindle_helper_forbidden_role', __( 'You cannot assign that user role.', 'brindle-helper' ) );
	}
}, 10, 3 );

function brindle_helper_restrict_core_rest( $response, $handler, $request ) {
	if ( ! brindle_helper_is_custom_role_user() ) {
		return $response;
	}
	$route = $request->get_route();
	$blocked = (bool) preg_match( '#^/wp/v2/(settings|connectors)(/|$)#', $route );
	if ( preg_match( '#^/wp/v2/users(?:/\d+)?/?$#', $route ) && in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
		$roles = $request->get_param( 'roles' );
		if ( null === $roles && '/wp/v2/users' === untrailingslashit( $route ) ) {
			$roles = array( get_option( 'default_role', 'subscriber' ) );
		}
		if ( null !== $roles && ( ! is_array( $roles ) || ! $roles || array_diff( $roles, brindle_helper_operational_roles() ) ) ) {
			$blocked = true;
		}
	}
	if ( $blocked ) {
		return new WP_Error( 'brindle_helper_forbidden', __( 'You do not have permission to perform this action.', 'brindle-helper' ), array( 'status' => 403 ) );
	}
	return $response;
}
add_filter( 'rest_request_before_callbacks', 'brindle_helper_restrict_core_rest', 5, 3 );

function brindle_helper_core_settings_screens() {
	return array( 'options-general.php', 'options-connectors.php', 'options-writing.php', 'options-reading.php', 'options-discussion.php', 'options-media.php', 'options-permalink.php', 'options-privacy.php' );
}

function brindle_helper_clean_core_menus() {
	if ( ! brindle_helper_is_custom_role_user() ) {
		return;
	}
	remove_submenu_page( 'index.php', 'update-core.php' );
	foreach ( array( 'tools.php', 'import.php', 'export.php', 'site-health.php', 'export-personal-data.php', 'erase-personal-data.php' ) as $page ) {
		remove_submenu_page( 'tools.php', $page );
	}
	foreach ( brindle_helper_core_settings_screens() as $page ) {
		remove_submenu_page( 'options-general.php', $page );
	}
	// Plugin submenus under Tools and Settings keep their own permission checks.
	global $submenu;
	foreach ( array( 'tools.php', 'options-general.php' ) as $parent ) {
		if ( empty( $submenu[ $parent ] ) ) {
			remove_menu_page( $parent );
		}
	}
}
add_action( 'admin_menu', 'brindle_helper_clean_core_menus', 1001 );

function brindle_helper_restrict_core_pages() {
	if ( ! brindle_helper_is_custom_role_user() ) {
		return;
	}
	global $pagenow;
	// Shared core parent filenames can also host plugin screens; guard the bare core screens.
	$page = $_GET['page'] ?? '';
	$blocked = ! $page && in_array( $pagenow, array_merge( brindle_helper_core_settings_screens(), array( 'tools.php', 'update-core.php', 'import.php', 'export.php', 'site-health.php', 'export-personal-data.php', 'erase-personal-data.php' ) ), true );
	if ( 'user-edit.php' === $pagenow ) {
		$target_id = $_REQUEST['user_id'] ?? 0;
		$blocked = ! is_scalar( $target_id ) || ! current_user_can( 'edit_user', absint( $target_id ) );
	}
	if ( 'options.php' === $pagenow ) {
		$group = $_POST['option_page'] ?? $_GET['option_page'] ?? '';
		$blocked = ! $group || in_array( $group, array( 'options', 'general', 'connectors', 'writing', 'reading', 'discussion', 'media', 'privacy', 'permalink' ), true );
	}
	// Core adds a special "none" role to bulk actions after editable_roles has run.
	if ( 'users.php' === $pagenow && isset( $_REQUEST['new_role'] ) && ! in_array( $_REQUEST['new_role'], brindle_helper_operational_roles(), true ) && ! empty( $_REQUEST['new_role'] ) ) {
		$blocked = true;
	}
	if ( $blocked ) {
		wp_die( esc_html__( 'You do not have permission to view this page.', 'brindle-helper' ), '', array( 'response' => 403 ) );
	}
}
add_action( 'admin_init', 'brindle_helper_restrict_core_pages', 1 );

foreach ( array( 'options', 'general', 'connectors', 'writing', 'reading', 'discussion', 'media', 'privacy', 'permalink' ) as $group ) {
	add_filter( 'option_page_capability_' . $group, static function ( $capability ) {
		return brindle_helper_is_custom_role_user() ? 'brindle_manage_core_settings' : $capability;
	} );
}
add_action( 'wp_before_admin_bar_render', static function () {
	global $wp_admin_bar;
	if ( brindle_helper_is_custom_role_user() && $wp_admin_bar ) {
		$wp_admin_bar->remove_node( 'updates' );
	}
}, 1000 );
