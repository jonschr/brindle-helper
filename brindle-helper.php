<?php
/**
 * Plugin Name: Brindle Helper
 * Description: Dashboard customization and client roles for Brindle sites.
 * Version: 0.3.1
 * Author: Brindle Digital
 * Text Domain: brindle-helper
 */

defined( 'ABSPATH' ) || exit;

function brindle_helper_update_checker() {
	static $checker;
	if ( ! $checker ) {
		require_once __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';
		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://raw.githubusercontent.com/jonschr/brindle-helper/master/update.json',
			__FILE__,
			'brindle-helper'
		);
	}
	return $checker;
}
add_action( 'plugins_loaded', 'brindle_helper_update_checker' );

// Access policy, plugin-specific integrations and upstream source references: README.md.

/**
 * The source roles are copied when a custom role is first created.
 */
function brindle_helper_roles() {
	return array(
		'brindle_client_administrator' => array(
			'name' => __( 'Client Administrator', 'brindle-helper' ),
			'base' => 'administrator',
		),
		'brindle_client_editor' => array(
			'name' => __( 'Client Editor', 'brindle-helper' ),
			'base' => 'editor',
		),
		'brindle_employee' => array(
			'name' => __( 'Brindle Employee', 'brindle-helper' ),
			'base' => 'administrator',
		),
	);
}

function brindle_helper_tool_capabilities() {
	return array(
		'brindle_manage_wppusher'       => 'manage_options',
		'brindle_manage_wp_migrate'     => is_multisite() ? 'manage_network_options' : 'export',
		'brindle_manage_acf'            => 'manage_options',
		'brindle_manage_generateblocks' => 'manage_options',
		'brindle_manage_hfcm'           => 'manage_options',
		'brindle_manage_wp_umbrella'    => 'manage_options',
		'brindle_manage_font_awesome'   => 'manage_options',
		'brindle_manage_duplicate_page' => 'manage_options',
		'brindle_manage_appearance'     => 'edit_theme_options',
		'brindle_manage_core_settings'  => 'manage_options',
	);
}

function brindle_helper_restricted_capabilities() {
	// Core capabilities cover plugin management and theme file editing without screen-specific hooks.
	return array_merge(
		array( 'activate_plugins', 'install_plugins', 'update_plugins', 'delete_plugins', 'edit_plugins', 'edit_themes', 'switch_themes', 'install_themes', 'update_themes', 'delete_themes', 'edit_theme_options' ),
		array( 'update_core', 'install_languages', 'update_languages', 'import', 'export', 'view_site_health_checks', 'export_others_personal_data', 'erase_others_personal_data' ),
		array_keys( brindle_helper_tool_capabilities() )
	);
}

function brindle_helper_register_roles() {
	foreach ( brindle_helper_roles() as $slug => $definition ) {
		$source = get_role( $definition['base'] );

		// Preserve any later customizations to an existing role.
		if ( ! get_role( $slug ) && $source ) {
			add_role( $slug, $definition['name'], $source->capabilities );
		}

		$role = get_role( $slug );
		if ( $role ) {
			$permissions = array_replace( array_fill_keys( brindle_helper_restricted_capabilities(), false ), brindle_helper_appearance_capabilities( $slug ) );
			foreach ( $permissions as $capability => $allowed ) {
				// Store explicit denials so disabled permissions remain visible in the table.
				if ( ! array_key_exists( $capability, $role->capabilities ) || $allowed !== $role->capabilities[ $capability ] ) {
					$role->add_cap( $capability, $allowed );
				}
			}
		}
	}
}
register_activation_hook( __FILE__, 'brindle_helper_register_roles' );
add_action( 'init', 'brindle_helper_register_roles', 20 );

function brindle_helper_is_custom_role_user( $user_id = null ) {
	$user = null === $user_id ? wp_get_current_user() : get_userdata( $user_id );
	return $user && (bool) array_intersect( array_keys( brindle_helper_roles() ), $user->roles );
}

function brindle_helper_map_tool_capabilities( $caps, $capability, $user_id ) {
	$tools = brindle_helper_tool_capabilities();
	if ( ! isset( $tools[ $capability ] ) ) {
		return $caps;
	}
	if ( 'brindle_manage_appearance' === $capability && brindle_helper_is_employee( $user_id ) ) {
		return array( 'edit_theme_options' );
	}
	if ( brindle_helper_is_custom_role_user( $user_id ) ) {
		return array( 'do_not_allow' );
	}
	// Keep each tool's existing access requirement for other users.
	return array( $tools[ $capability ] );
}
add_filter( 'map_meta_cap', 'brindle_helper_map_tool_capabilities', 10, 3 );

require_once __DIR__ . '/appearance.php';
require_once __DIR__ . '/core-access.php';
require_once __DIR__ . '/dashboard.php';
require_once __DIR__ . '/login.php';

// ACF's supported capability setting covers its configuration screens and internal post types.
// Keep separate content options forms on their existing capabilities (see README.md).
add_filter( 'acf/settings/capability', static function () {
	return 'brindle_manage_acf';
} );
// WP Migrate shares export with WordPress content export; isolate only its AJAX/REST checks.
add_filter( 'wpmdb_ajax_cap', static function () {
	return 'brindle_manage_wp_migrate';
} );

function brindle_helper_generateblocks_manage_capability( $capability, $context = 'manage' ) {
	// Use GenerateBlocks' supported manage/use distinction so page-editor usage stays available.
	return 'manage' === $context && brindle_helper_is_custom_role_user() ? 'brindle_manage_generateblocks' : $capability;
}
foreach ( array( 'generateblocks_conditions_capability', 'generateblocks_overlays_capability', 'generateblocks_editor_access_capability', 'generateblocks_form_capability', 'generateblocks_manage_classes_capability' ) as $filter ) {
	add_filter( $filter, 'brindle_helper_generateblocks_manage_capability', 10, 2 );
}

function brindle_helper_generateblocks_post_type_capabilities( $args, $post_type ) {
	// Legacy and standalone resource editors also check post-type capabilities directly.
	if ( ( 0 === strpos( $post_type, 'gblocks_' ) || in_array( $post_type, array( 'gb_access_profile', 'gb_access_set' ), true ) ) && brindle_helper_is_custom_role_user() ) {
		foreach ( (array) ( $args['capabilities'] ?? array() ) as $action => $capability ) {
			// Reading existing styles, patterns and overlays remains available in the editor.
			if ( 0 === strpos( $action, 'edit_' ) || 0 === strpos( $action, 'delete_' ) || in_array( $action, array( 'create_posts', 'publish_posts' ), true ) ) {
				if ( 'do_not_allow' !== $capability && false !== $capability ) {
					$args['capabilities'][ $action ] = 'brindle_manage_generateblocks';
				}
			}
		}
	}
	return $args;
}
add_filter( 'register_post_type_args', 'brindle_helper_generateblocks_post_type_capabilities', 100, 2 );

function brindle_helper_restrict_generateblocks_rest( $response, $handler, $request ) {
	// These installed REST callbacks hard-code manage_options; the manage/use filters do not cover them.
	// Callback names and namespaces must be reviewed after GenerateBlocks updates (see README.md).
	$permission = $handler['permission_callback'] ?? null;
	if ( preg_match( '#^/generateblocks(-pro)?/v\d+/#', $request->get_route() )
		&& is_array( $permission )
		&& in_array( $permission[1], array( 'update_settings_permission', 'manage_options_permission' ), true )
		&& ! current_user_can( 'brindle_manage_generateblocks' ) ) {
		return new WP_Error( 'brindle_helper_forbidden', __( 'You do not have permission to manage GenerateBlocks.', 'brindle-helper' ), array( 'status' => 403 ) );
	}
	return $response;
}
add_filter( 'rest_request_before_callbacks', 'brindle_helper_restrict_generateblocks_rest', 10, 3 );

function brindle_helper_restrict_font_awesome_rest( $response, $handler, $request ) {
	// The installed settings controllers hard-code manage_options. Keep /api and
	// /api/token available for the content editor's icon chooser (see README.md).
	if ( preg_match( '#^/font-awesome/v\d+/(config|conflict-detection|preference-check)(/|$)#', $request->get_route() )
		&& ! current_user_can( 'brindle_manage_font_awesome' ) ) {
		return new WP_Error( 'brindle_helper_forbidden', __( 'You do not have permission to manage Font Awesome settings.', 'brindle-helper' ), array( 'status' => 403 ) );
	}
	return $response;
}
add_filter( 'rest_request_before_callbacks', 'brindle_helper_restrict_font_awesome_rest', 10, 3 );

function brindle_helper_wp_umbrella_settings_actions() {
	// These installed settings handlers share manage_options and expose no capability filter.
	// Scope to local settings actions; remote monitoring and personal 2FA use separate paths.
	return array(
		'admin-ajax.php' => array(
			'wp_health_proxy', 'wp_health_login', 'wp_health_allow_tracking', 'wp_health_disallow_tracking',
			'wp_umbrella_register', 'wp_umbrella_valid_api_key', 'wp_umbrella_check_api_key',
			'wp_umbrella_allow_one_click_access', 'wp_umbrella_disallow_one_click_access', 'wp_umbrella_repair_ajax',
		),
		'admin-post.php' => array(
			'wp_umbrella_support_option', 'wp_umbrella_regenerate_secret_token', 'wp_umbrella_hardening_options',
			'wp_umbrella_clean_transients', 'wp_umbrella_clean_activity_log_buffer', 'wp_umbrella_clean_redirect_table',
			'wp_umbrella_clean_htaccess', 'wp_umbrella_test_ping',
		),
	);
}

// WP Pusher exposes settings through options.php as well as its own screens.
foreach ( array( 'pusher-token-settings', 'pusher-license-settings', 'pusher-gh-settings', 'pusher-bb-settings', 'pusher-gl-settings', 'pusher-enable-logging' ) as $settings_group ) {
	add_filter( 'option_page_capability_' . $settings_group, static function () {
		return 'brindle_manage_wppusher';
	} );
}

function brindle_helper_admin_tool_capability( $page ) {
	if ( 'wppusher' === $page || 0 === strpos( $page, 'wppusher-' ) ) {
		return 'brindle_manage_wppusher';
	}
	if ( in_array( $page, array( 'wp-migrate-db', 'wp-migrate-db-pro' ), true ) ) {
		return 'brindle_manage_wp_migrate';
	}
	if ( 'generateblocks' === $page || 0 === strpos( $page, 'generateblocks-' ) ) {
		return 'brindle_manage_generateblocks';
	}
	if ( 0 === strpos( $page, 'hfcm-' ) ) {
		return 'brindle_manage_hfcm';
	}
	if ( 'wp-umbrella-settings' === $page ) {
		return 'brindle_manage_wp_umbrella';
	}
	if ( 'font-awesome' === $page ) {
		return 'brindle_manage_font_awesome';
	}
	// Duplicate Page saves through this screen; its separate duplication action keeps native permissions.
	if ( 'duplicate_page_settings' === $page ) {
		return 'brindle_manage_duplicate_page';
	}
	return '';
}

function brindle_helper_restrict_tool_menus() {
	// Installed tool menus hard-code shared capabilities (source references in README.md).
	// Apply tool-specific checks after registration, including hidden submenus.
	global $menu, $submenu, $_wp_menu_nopriv, $_wp_submenu_nopriv;
	foreach ( (array) $menu as $index => $item ) {
		$capability = brindle_helper_admin_tool_capability( $item[2] );
		if ( $capability ) {
			$menu[ $index ][1] = $capability;
			if ( ! current_user_can( $capability ) ) {
				$_wp_menu_nopriv[ $item[2] ] = true;
				unset( $menu[ $index ] );
			}
		}
	}
	foreach ( (array) $submenu as $parent => $items ) {
		foreach ( $items as $index => $item ) {
			$capability = brindle_helper_admin_tool_capability( $parent ) ?: brindle_helper_admin_tool_capability( $item[2] );
			if ( $capability ) {
				$submenu[ $parent ][ $index ][1] = $capability;
				if ( ! current_user_can( $capability ) ) {
					$_wp_submenu_nopriv[ $parent ][ $item[2] ] = true;
					unset( $submenu[ $parent ][ $index ] );
				}
			}
		}
		if ( empty( $submenu[ $parent ] ) ) {
			unset( $submenu[ $parent ] );
		}
	}
}
add_action( 'admin_menu', 'brindle_helper_restrict_tool_menus', 999 );
add_action( 'network_admin_menu', 'brindle_helper_restrict_tool_menus', 999 );

function brindle_helper_clean_admin_navigation() {
	if ( ! brindle_helper_is_custom_role_user() ) {
		return;
	}
	remove_menu_page( 'edit-comments.php' );
	brindle_helper_clean_appearance_menu();
}
add_action( 'admin_menu', 'brindle_helper_clean_admin_navigation', 1000 );

function brindle_helper_disable_dashboard_commands() {
	if ( brindle_helper_is_custom_role_user() ) {
		// Stop WordPress's dashboard palette initializer, including its keyboard shortcut.
		// Keep script registrations intact because the block editor also depends on these packages.
		remove_action( 'admin_enqueue_scripts', 'wp_enqueue_command_palette_assets' );
	}
}
add_action( 'admin_init', 'brindle_helper_disable_dashboard_commands' );

function brindle_helper_clean_admin_toolbar() {
	global $wp_admin_bar;
	if ( ! $wp_admin_bar ) {
		return;
	}
	// Site-wide branding applies to every role, in wp-admin and on the frontend.
	$wp_admin_bar->remove_node( 'wp-logo' );
	if ( ! brindle_helper_is_custom_role_user() ) {
		return;
	}
	// Plugin node IDs are documented in README.md. Removing a parent also hides its children.
	foreach ( array( 'comments', 'command-palette', 'gb_overlays-menu' ) as $id ) {
		$wp_admin_bar->remove_node( $id );
	}
	if ( ! brindle_helper_is_employee() ) {
		foreach ( array( 'gp_elements-menu', 'seopress', 'new-gp_elements' ) as $id ) {
			$wp_admin_bar->remove_node( $id );
		}
	}
	brindle_helper_clean_appearance_toolbar( $wp_admin_bar );
}
add_action( 'wp_before_admin_bar_render', 'brindle_helper_clean_admin_toolbar', 999 );

function brindle_helper_clean_dashboard_widgets() {
	if ( ! brindle_helper_is_custom_role_user() ) {
		return;
	}
	// Popup Maker and Visual Portfolio widget IDs are documented in README.md.
	// Cover all dashboard columns, including widgets moved by a user's saved layout.
	foreach ( array( 'dashboard_right_now', 'dashboard_quick_press', 'dashboard_primary', 'pum_analytics_basic', 'vpf_recent_portfolio_activity', 'dashboard_activity' ) as $id ) {
		foreach ( array( 'normal', 'side', 'column3', 'column4' ) as $context ) {
			remove_meta_box( $id, 'dashboard', $context );
		}
	}
}
add_action( 'wp_dashboard_setup', 'brindle_helper_clean_dashboard_widgets', 999 );

function brindle_helper_restrict_tool_pages() {
	// Reject direct requests before plugin admin_init handlers run; menu removal alone is insufficient.
	global $pagenow;
	$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$capability = brindle_helper_admin_tool_capability( $page );
	$action = $_POST['action'] ?? $_GET['action'] ?? '';
	if ( is_string( $action ) && in_array( wp_unslash( $action ), brindle_helper_wp_umbrella_settings_actions()[ $pagenow ] ?? array(), true ) ) {
		$capability = 'brindle_manage_wp_umbrella';
	}
	if ( ( is_string( $action ) && 0 === strpos( wp_unslash( $action ), 'hfcm-' ) ) || isset( $_POST['hfcm_save_security_settings'] ) ) {
		$capability = 'brindle_manage_hfcm';
	}
	if ( in_array( $pagenow, array( 'edit.php', 'post.php', 'post-new.php', 'edit-tags.php', 'site-editor.php' ), true ) ) {
		$post_id = $_GET['post'] ?? $_POST['post_ID'] ?? 0;
		$post_id = is_scalar( $post_id ) ? absint( $post_id ) : 0;
		$post_type = $post_id ? get_post_type( $post_id ) : ( $_GET['post_type'] ?? $_GET['postType'] ?? '' );
		$taxonomy = $_GET['taxonomy'] ?? '';
		if ( ( is_string( $post_type ) && ( 'wp_block' === $post_type || 0 === strpos( $post_type, 'gblocks_' ) || in_array( $post_type, array( 'gb_access_profile', 'gb_access_set' ), true ) ) )
			|| 'gblocks_pattern_collections' === $taxonomy ) {
			$capability = 'brindle_manage_generateblocks';
		}
	}
	if ( $capability && ! current_user_can( $capability ) ) {
		wp_die( esc_html__( 'You do not have permission to view this page.', 'brindle-helper' ), '', array( 'response' => 403 ) );
	}
}
add_action( 'admin_init', 'brindle_helper_restrict_tool_pages', 1 );

function brindle_helper_can_view_settings() {
	$user = wp_get_current_user();
	// On a single site, is_super_admin() also matches custom roles with delete_users.
	return in_array( 'administrator', $user->roles, true )
		|| ( is_multisite() && is_super_admin( $user->ID ) );
}

function brindle_helper_admin_menu() {
	if ( ! brindle_helper_can_view_settings() ) {
		return;
	}

	add_options_page(
		__( 'Brindle Helper', 'brindle-helper' ),
		__( 'Brindle Helper', 'brindle-helper' ),
		'manage_options',
		'brindle-helper',
		'brindle_helper_render_settings'
	);
}
add_action( 'admin_menu', 'brindle_helper_admin_menu' );

function brindle_helper_sidebar_brand() {
	global $wp_admin_bar;
	$site = $wp_admin_bar->get_node( 'site-name' );
	if ( ! $site || empty( $site->href ) ) {
		return;
	}
	// Copy the adjacent site's destination after toolbar customization, before core binds its nodes.
	$logo = is_admin() ? 'brindle-logo.svg' : 'brindle-wordmark.svg';
	if ( is_admin() && get_current_screen()->is_block_editor() ) {
		$logo = 'brindle-wordmark.svg';
	}
	echo '<a id="brindle-helper-brand" class="brindle-helper-brand" href="' . esc_url( $site->href ) . '" aria-label="' . esc_attr( wp_strip_all_tags( $site->title ) ) . '">'
		. '<img class="brindle-helper-logo" src="' . esc_url( plugins_url( 'assets/' . $logo, __FILE__ ) ) . '" alt="">'
		. '<img class="brindle-helper-mark" src="' . esc_url( plugins_url( 'assets/brindle-mark.svg', __FILE__ ) ) . '" alt="">'
		. '</a>';
}
add_action( 'wp_before_admin_bar_render', 'brindle_helper_sidebar_brand', 1000 );

function brindle_helper_admin_styles() {
	wp_enqueue_style( 'brindle-helper-admin', plugins_url( 'admin.css', __FILE__ ), array(), '0.3.1' );
}
add_action( 'admin_enqueue_scripts', 'brindle_helper_admin_styles' );

function brindle_helper_front_toolbar_classes( $classes ) {
	if ( ! is_admin() && is_admin_bar_showing() ) {
		$classes[] = 'brindle-helper-toolbar';
		// Match core's sidebar preference, including the current browser's settings cookie.
		if ( 'f' === get_user_setting( 'mfold' ) ) {
			$classes[] = 'brindle-helper-folded';
		}
		if ( ! get_user_setting( 'unfold' ) ) {
			$classes[] = 'brindle-helper-auto-fold';
		}
	}
	return $classes;
}
add_filter( 'body_class', 'brindle_helper_front_toolbar_classes' );

function brindle_helper_front_toolbar_styles() {
	if ( is_admin_bar_showing() ) {
		wp_enqueue_style( 'brindle-helper-front-toolbar', plugins_url( 'front-toolbar.css', __FILE__ ), array( 'admin-bar' ), '0.3.1' );
	}
}
add_action( 'wp_enqueue_scripts', 'brindle_helper_front_toolbar_styles' );

function brindle_helper_permission_sections( $capabilities ) {
	$patterns = array(
		__( 'Posts', 'brindle-helper' )                   => '/_posts?$/',
		__( 'Pages', 'brindle-helper' )                   => '/_pages?$/',
		__( 'Media', 'brindle-helper' )                   => '/^(upload_files|unfiltered_upload|edac_upload_pdf)$/',
		__( 'Comments, categories & links', 'brindle-helper' ) => '/^(moderate_comments|manage_categories|manage_links)$/',
		__( 'Portfolio', 'brindle-helper' )               => '/_(portfolio|vp_list)/',
		__( 'Users', 'brindle-helper' )                   => '/_users$/',
		__( 'Themes & appearance', 'brindle-helper' )     => '/_themes$|^edit_theme_options$|^brindle_(edit_menus|edit_widgets|manage_appearance)$/',
		__( 'Plugin management', 'brindle-helper' )      => '/_plugins$/',
		__( 'Developer tools', 'brindle-helper' )        => '/^brindle_manage_(?!core_settings)/',
		__( 'SEO', 'brindle-helper' )                     => '/^seopress_|_(redirections?|schemas?|broken_links?)$/',
		__( 'Site administration', 'brindle-helper' )     => '/^(read|manage_options|edit_dashboard|edit_files|update_core|install_languages|update_languages|import|export|unfiltered_html|view_site_health_checks|export_others_personal_data|erase_others_personal_data|brindle_manage_core_settings)$/',
		__( 'Legacy access levels', 'brindle-helper' )    => '/^level_/',
		__( 'Other permissions', 'brindle-helper' )       => '/.*/',
	);
	$sections = array_fill_keys( array_keys( $patterns ), array() );
	foreach ( $capabilities as $capability ) {
		foreach ( $patterns as $label => $pattern ) {
			if ( preg_match( $pattern, $capability ) ) {
				$sections[ $label ][] = $capability;
				break;
			}
		}
	}
	return array_filter( $sections );
}

function brindle_helper_render_settings() {
	if ( ! brindle_helper_can_view_settings() ) {
		wp_die( esc_html__( 'You do not have permission to view this page.', 'brindle-helper' ), '', array( 'response' => 403 ) );
	}

	$definitions  = brindle_helper_roles();
	$roles        = array();
	$capabilities = array();
	$users        = array_fill_keys( array_keys( $definitions ), array() );

	foreach ( $definitions as $slug => $definition ) {
		$roles[ $slug ] = get_role( $slug );
		if ( $roles[ $slug ] ) {
			$capabilities = array_merge( $capabilities, array_keys( $roles[ $slug ]->capabilities ) );
		}
	}

	foreach ( get_users( array( 'role__in' => array_keys( $definitions ), 'orderby' => 'display_name', 'order' => 'ASC' ) ) as $user ) {
		foreach ( $user->roles as $slug ) {
			if ( isset( $users[ $slug ] ) ) {
				$users[ $slug ][] = $user;
			}
		}
	}

	$capabilities = array_unique( $capabilities );
	sort( $capabilities );
	$sections = brindle_helper_permission_sections( $capabilities );
	?>
	<div class="wrap brindle-helper-settings">
		<h1><?php esc_html_e( 'Brindle Helper', 'brindle-helper' ); ?></h1>
		<p><?php esc_html_e( 'No settings are available yet. Compare the roles and their assigned users below.', 'brindle-helper' ); ?></p>
		<div class="brindle-helper-table-scroll">
			<table class="widefat striped brindle-helper-permissions">
				<caption class="screen-reader-text"><?php esc_html_e( 'Brindle role users and permissions comparison', 'brindle-helper' ); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Role details / permission', 'brindle-helper' ); ?></th>
						<?php foreach ( $definitions as $definition ) : ?>
							<th scope="col"><?php echo esc_html( $definition['name'] ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Based on', 'brindle-helper' ); ?></th>
						<?php foreach ( $definitions as $definition ) : ?>
							<td><?php echo esc_html( translate_user_role( wp_roles()->roles[ $definition['base'] ]['name'] ?? $definition['base'] ) ); ?></td>
						<?php endforeach; ?>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Assigned users', 'brindle-helper' ); ?></th>
						<?php foreach ( $definitions as $slug => $definition ) : ?>
							<td>
								<?php if ( empty( $users[ $slug ] ) ) : ?>
									<?php esc_html_e( 'No users assigned.', 'brindle-helper' ); ?>
								<?php else : ?>
									<ul>
										<?php foreach ( $users[ $slug ] as $user ) : ?>
											<li><?php echo esc_html( $user->display_name ); ?> <span class="description">(<?php echo esc_html( $user->user_login ); ?>)</span></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</td>
						<?php endforeach; ?>
					</tr>
				</tbody>
				<?php foreach ( $sections as $label => $section_capabilities ) : ?>
				<tbody>
					<tr class="brindle-helper-section">
						<th scope="rowgroup" colspan="4"><?php echo esc_html( $label ); ?></th>
					</tr>
					<?php foreach ( $section_capabilities as $capability ) : ?>
						<?php
						$granted = array();
						foreach ( $roles as $slug => $role ) {
							$granted[ $slug ] = $role && ! empty( $role->capabilities[ $capability ] );
						}
						$differs = count( array_unique( $granted ) ) > 1;
						?>
						<tr>
							<th scope="row">
								<code><?php echo esc_html( $capability ); ?></code>
								<?php if ( $differs ) : ?>
									<span class="description"> — <?php esc_html_e( 'Differs', 'brindle-helper' ); ?></span>
								<?php endif; ?>
							</th>
							<?php foreach ( $granted as $allowed ) : ?>
								<td>
									<span class="brindle-helper-permission <?php echo $allowed ? 'is-allowed' : 'is-denied'; ?>">
										<span aria-hidden="true"><?php echo $allowed ? '✓' : '−'; ?></span>
										<?php echo $allowed ? esc_html__( 'Yes', 'brindle-helper' ) : esc_html__( 'No', 'brindle-helper' ); ?>
									</span>
								</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<?php endforeach; ?>
			</table>
		</div>
		<p class="description"><?php esc_html_e( 'This table shows capabilities stored on each role, including capabilities added by other plugins. Individual user overrides and contextual WordPress permission checks may differ.', 'brindle-helper' ); ?></p>
	</div>
	<?php
}
