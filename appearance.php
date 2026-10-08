<?php
/** Appearance access policy and plugin integrations; see README.md. */
defined( 'ABSPATH' ) || exit;

function brindle_helper_is_employee( $user_id = null ) {
	$user = null === $user_id ? wp_get_current_user() : get_userdata( $user_id );
	return $user && in_array( 'brindle_employee', $user->roles, true );
}

function brindle_helper_appearance_capabilities( $role ) {
	$capabilities = array(
		'brindle_edit_menus'   => true,
		'brindle_edit_widgets' => in_array( $role, array( 'brindle_client_administrator', 'brindle_employee' ), true ),
	);
	if ( 'brindle_employee' === $role ) {
		$capabilities['edit_theme_options'] = true;
		$capabilities['brindle_manage_appearance'] = true;
	}
	return $capabilities;
}

function brindle_helper_map_appearance_capabilities( $caps, $capability, $user_id ) {
	if ( ! brindle_helper_is_custom_role_user( $user_id ) || brindle_helper_is_employee( $user_id ) ) {
		return $caps;
	}
	if ( in_array( $capability, array( 'customize', 'edit_css' ), true ) ) {
		return array( 'do_not_allow' );
	}
	if ( ! in_array( 'edit_theme_options', $caps, true ) ) {
		return $caps;
	}
	// WordPress shares this capability across menus, widgets and site design.
	// Allow it only inside the native menu/widget requests and their actual REST callbacks.
	$context = end( $GLOBALS['brindle_helper_appearance_rest_contexts'] );
	if ( false === $context ) {
		global $pagenow;
		$context = 'nav-menus.php' === $pagenow ? 'brindle_edit_menus' : ( 'widgets.php' === $pagenow ? 'brindle_edit_widgets' : '' );
		if ( wp_doing_ajax() ) {
			$action = $_REQUEST['action'] ?? '';
			if ( in_array( $action, array( 'add-menu-item', 'menu-get-metabox', 'menu-quick-search', 'menu-locations-save' ), true ) ) {
				$context = 'brindle_edit_menus';
			} elseif ( 'delete-post' === $action && isset( $_POST['id'] ) && is_scalar( $_POST['id'] ) && 'nav_menu_item' === get_post_type( absint( $_POST['id'] ) ) ) {
				$context = 'brindle_edit_menus';
			} elseif ( in_array( $action, array( 'widgets-order', 'save-widget', 'delete-inactive-widgets' ), true ) ) {
				$context = 'brindle_edit_widgets';
			}
		}
	}
	return $context ? array_values( array_unique( array_map( static fn( $cap ) => 'edit_theme_options' === $cap ? $context : $cap, $caps ) ) ) : array( 'do_not_allow' );
}
$GLOBALS['brindle_helper_appearance_rest_contexts'] = array();
add_filter( 'map_meta_cap', 'brindle_helper_map_appearance_capabilities', 100, 3 );

function brindle_helper_appearance_rest_context( $response, $handler, $request ) {
	$route = $request->get_route();
	$context = '';
	if ( preg_match( '#^/wp/v2/(menus|menu-items|menu-locations)(/|$)#', $route ) ) {
		$context = 'brindle_edit_menus';
	} elseif ( preg_match( '#^/wp/v2/(widgets|widget-types|sidebars)(/|$)#', $route ) ) {
		$context = 'brindle_edit_widgets';
	}
	// A stack keeps nested REST dispatches from borrowing another route's permission.
	$GLOBALS['brindle_helper_appearance_rest_contexts'][] = $context;
	// GeneratePress administration and its font API hard-code manage_options.
	if ( brindle_helper_is_custom_role_user() && ! brindle_helper_is_employee() && preg_match( '#^/generatepress-(pro|font-library)/v\d+/#', $route ) ) {
		return new WP_Error( 'brindle_helper_forbidden', __( 'You do not have permission to manage site appearance.', 'brindle-helper' ), array( 'status' => 403 ) );
	}
	return $response;
}
add_filter( 'rest_request_before_callbacks', 'brindle_helper_appearance_rest_context', 1, 3 );
add_filter( 'rest_request_after_callbacks', static function ( $response ) {
	array_pop( $GLOBALS['brindle_helper_appearance_rest_contexts'] );
	return $response;
}, 999 );

function brindle_helper_appearance_resource_capabilities( $args, $post_type ) {
	if ( brindle_helper_is_custom_role_user() && ! brindle_helper_is_employee() && in_array( $post_type, array( 'gp_elements', 'popup_theme', 'gp_font' ), true ) ) {
		// Restrict definitions while preserving reads of existing popup themes and frontend resources.
		foreach ( array( 'edit_post', 'delete_post', 'create_posts', 'publish_posts', 'edit_posts', 'edit_others_posts', 'edit_private_posts', 'edit_published_posts', 'delete_posts', 'delete_others_posts', 'delete_private_posts', 'delete_published_posts' ) as $action ) {
			$args['capabilities'][ $action ] = 'brindle_manage_appearance';
		}
	}
	return $args;
}
add_filter( 'register_post_type_args', 'brindle_helper_appearance_resource_capabilities', 110, 2 );

function brindle_helper_prepare_appearance_menu() {
	if ( ! brindle_helper_is_custom_role_user() ) {
		return;
	}
	global $menu, $submenu;
	foreach ( $menu as &$item ) {
		if ( 'themes.php' === $item[2] ) {
			$item[1] = 'brindle_edit_menus';
		}
	}
	foreach ( $submenu['themes.php'] ?? array() as $index => $item ) {
		if ( in_array( $item[2], array( 'themes.php', 'nav-menus.php', 'widgets.php' ), true ) ) {
			// Keep the parent in place during WordPress's early reparenting pass.
			// The Themes child is removed below and its direct request is always denied.
			$submenu['themes.php'][ $index ][1] = 'widgets.php' === $item[2] ? 'brindle_edit_widgets' : 'brindle_edit_menus';
		}
	}
}
// Rewrite core menu checks before WordPress removes inaccessible submenus.
add_action( '_admin_menu', 'brindle_helper_prepare_appearance_menu', 100 );

function brindle_helper_clean_appearance_menu() {
	if ( brindle_helper_is_employee() ) {
		remove_submenu_page( 'themes.php', 'themes.php' );
		remove_submenu_page( 'themes.php', 'theme-editor.php' );
		return;
	}
	global $submenu;
	foreach ( $submenu['themes.php'] ?? array() as $item ) {
		if ( 'nav-menus.php' !== $item[2] && ! ( 'widgets.php' === $item[2] && current_user_can( 'brindle_edit_widgets' ) ) ) {
			remove_submenu_page( 'themes.php', $item[2] );
		}
	}
}

function brindle_helper_clean_appearance_toolbar( $bar ) {
	if ( brindle_helper_is_employee() ) {
		$bar->remove_node( 'themes' );
		return;
	}
	foreach ( array( 'themes', 'customize', 'site-editor', 'background', 'header' ) as $id ) {
		$bar->remove_node( $id );
	}
	$bar->add_node( array( 'id' => 'menus', 'parent' => 'appearance', 'title' => __( 'Menus' ), 'href' => admin_url( 'nav-menus.php' ) ) );
	if ( current_user_can( 'brindle_edit_widgets' ) && current_theme_supports( 'widgets' ) ) {
		$bar->add_node( array( 'id' => 'widgets', 'parent' => 'appearance', 'title' => __( 'Widgets' ), 'href' => admin_url( 'widgets.php' ) ) );
	} else {
		$bar->remove_node( 'widgets' );
	}
}

function brindle_helper_restrict_appearance_pages() {
	if ( ! brindle_helper_is_custom_role_user() ) {
		return;
	}
	global $pagenow;
	$page = $_GET['page'] ?? '';
	if ( brindle_helper_is_employee() ) {
		if ( in_array( $pagenow, array( 'theme-install.php', 'theme-editor.php' ), true ) || ( 'themes.php' === $pagenow && empty( $page ) ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'brindle-helper' ), '', array( 'response' => 403 ) );
		}
		return;
	}
	$blocked = in_array( $pagenow, array( 'themes.php', 'theme-install.php', 'site-editor.php', 'font-library.php', 'customize.php' ), true );
	$blocked = $blocked || ( is_string( $page ) && ( 'generate-options' === $page || 0 === strpos( $page, 'generatepress-' ) ) );
	$blocked = $blocked || ( 'widgets.php' === $pagenow && ! current_user_can( 'brindle_edit_widgets' ) );
	$post_id = $_GET['post'] ?? $_POST['post_ID'] ?? 0;
	$post_type = is_scalar( $post_id ) && $post_id ? get_post_type( absint( $post_id ) ) : ( $_GET['post_type'] ?? '' );
	$blocked = $blocked || ( in_array( $pagenow, array( 'edit.php', 'post.php', 'post-new.php' ), true ) && in_array( $post_type, array( 'gp_elements', 'popup_theme', 'gp_font' ), true ) );
	// Legacy GeneratePress admin_init handlers accept submissions on any admin page.
	foreach ( array_keys( $_POST ) as $key ) {
		if ( in_array( $key, array( 'generate_multi_activate', 'gp_premium_license_key', 'generate_action', 'generate_reset_action', 'generate_reset_customizer' ), true ) || preg_match( '/^generate_package_.*_(de)?activate_package$/', $key ) ) {
			$blocked = true;
		}
	}
	if ( $blocked ) {
		wp_die( esc_html__( 'You do not have permission to view this page.', 'brindle-helper' ), '', array( 'response' => 403 ) );
	}
}
add_action( 'admin_init', 'brindle_helper_restrict_appearance_pages', 1 );

function brindle_helper_theme_options_capability( $page ) {
	// Lola's theme settings and its child forms share edit_posts by default.
	if ( 'brindle-lola-2' === get_stylesheet()
		&& in_array( 'brindle_client_editor', wp_get_current_user()->roles, true )
		&& ( in_array( $page['menu_slug'], array( 'theme-general-settings', 'acf-options-site-setting', 'acf-options-amenities-manager', 'acf-options-amenities-page-manager' ), true ) || 'theme-general-settings' === $page['parent_slug'] ) ) {
		$page['capability'] = 'do_not_allow';
	}
	return $page;
}
add_filter( 'acf/get_options_page', 'brindle_helper_theme_options_capability' );
