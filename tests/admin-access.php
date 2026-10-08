<?php
/**
 * Local integration check: wp eval-file wp-content/plugins/brindle-helper/tests/admin-access.php
 * Append "http" to also check authenticated admin URLs on a .localhost development site.
 */
defined( 'ABSPATH' ) || exit;

$check = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};
$original_user = get_current_user_id();
$tool_caps = array( 'brindle_manage_wppusher', 'brindle_manage_wp_migrate', 'brindle_manage_acf', 'brindle_manage_generateblocks', 'brindle_manage_hfcm', 'brindle_manage_appearance', 'brindle_manage_core_settings', 'brindle_manage_wp_umbrella', 'brindle_manage_font_awesome', 'brindle_manage_duplicate_page' );
$http = in_array( 'http', $args, true );
$check( ! $http || str_ends_with( wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ), 'HTTP checks require a local .localhost site.' );
$blocked_urls = array(
	'admin.php?page=wppusher', 'admin.php?page=wppusher-plugins-create', 'admin.php?page=wppusher-plugins',
	'admin.php?page=wppusher-themes-create', 'admin.php?page=wppusher-themes',
	'tools.php?page=wp-migrate-db-pro', 'theme-editor.php',
	'edit.php?post_type=acf-field-group', 'post-new.php?post_type=acf-field-group',
	'edit.php?post_type=acf-post-type', 'edit.php?post_type=acf-taxonomy', 'edit.php?post_type=acf-ui-options-page',
	'edit.php?post_type=acf-field-group&page=acf-tools', 'edit.php?post_type=acf-field-group&page=acf-settings-updates',
	'admin.php?page=generateblocks', 'admin.php?page=generateblocks-settings', 'admin.php?page=generateblocks-styles',
	'admin.php?page=generateblocks-asset-library', 'admin.php?page=generateblocks-conditions',
	'admin.php?page=generateblocks-overlay-panels', 'admin.php?page=generateblocks-editor-access', 'admin.php?page=generateblocks-forms',
	'edit.php?post_type=wp_block', 'edit.php?post_type=gblocks_styles',
	'admin.php?page=hfcm-list', 'admin.php?page=hfcm-create', 'admin.php?page=hfcm-tools',
	'admin.php?page=hfcm-update', 'admin.php?page=hfcm-request-handler', 'admin-ajax.php?action=hfcm-request',
	'themes.php', 'theme-install.php', 'site-editor.php', 'customize.php', 'font-library.php',
	'themes.php?page=generate-options', 'themes.php?page=generatepress-font-library',
	'edit.php?post_type=gp_elements', 'post-new.php?post_type=gp_elements',
	'edit.php?post_type=popup_theme', 'post-new.php?post_type=popup_theme',
	'update-core.php', 'tools.php', 'import.php', 'export.php', 'site-health.php',
	'export-personal-data.php', 'erase-personal-data.php', 'options.php',
	'options.php?option_page=general', 'options.php?option_page=connectors',
	'options-general.php?page=wp-umbrella-settings', 'options-general.php?page=font-awesome',
	'options-general.php?page=duplicate_page_settings',
);
$blocked_urls = array_merge( $blocked_urls, brindle_helper_core_settings_screens() );
$employee_appearance_urls = array(
	'site-editor.php', 'customize.php', 'font-library.php',
	'themes.php?page=generate-options', 'themes.php?page=generatepress-font-library',
	'edit.php?post_type=gp_elements', 'post-new.php?post_type=gp_elements',
	'edit.php?post_type=popup_theme', 'post-new.php?post_type=popup_theme',
);
$umbrella_actions = array(
	'admin-ajax.php' => array( 'wp_health_proxy', 'wp_health_login', 'wp_health_allow_tracking', 'wp_health_disallow_tracking', 'wp_umbrella_register', 'wp_umbrella_valid_api_key', 'wp_umbrella_check_api_key', 'wp_umbrella_allow_one_click_access', 'wp_umbrella_disallow_one_click_access', 'wp_umbrella_repair_ajax' ),
	'admin-post.php' => array( 'wp_umbrella_support_option', 'wp_umbrella_regenerate_secret_token', 'wp_umbrella_hardening_options', 'wp_umbrella_clean_transients', 'wp_umbrella_clean_activity_log_buffer', 'wp_umbrella_clean_redirect_table', 'wp_umbrella_clean_htaccess', 'wp_umbrella_test_ping' ),
);
foreach ( $umbrella_actions as $script => $actions ) {
	foreach ( $actions as $action ) {
		$blocked_urls[] = $script . '?action=' . $action;
	}
}
$font_awesome_settings_routes = array(
	array( 'POST', '/font-awesome/v1/config' ),
	array( 'POST', '/font-awesome/v1/preference-check' ),
	array( 'POST', '/font-awesome/v1/conflict-detection/until' ),
	array( 'POST', '/font-awesome/v1/conflict-detection/conflicts' ),
	array( 'DELETE', '/font-awesome/v1/conflict-detection/conflicts' ),
	array( 'POST', '/font-awesome/v1/conflict-detection/conflicts/blocklist' ),
);
// Exercise actual permissions while preventing settings writes and external icon API calls.
$font_awesome_probe = static function ( $result, $request ) {
	return str_starts_with( $request->get_route(), '/font-awesome/v1/' )
		? new WP_REST_Response( array( 'authorization_passed' => true ), 200 ) : $result;
};
// Run Duplicate Page's actual nonce/permission checks, stopping before content is copied.
$duplicate_page_probe = new class extends duplicate_page {
	public $duplicated_source = null;
	public function __construct() {}
	public function duplicate_edit_post( $post_id, $post_status_update = '' ) {
		$this->duplicated_source = $post_id;
	}
};

try {
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	$check( ! empty( $admins ), 'A site administrator is required.' );
	wp_set_current_user( $admins[0]->ID );
	foreach ( $tool_caps as $capability ) {
		$check( current_user_can( $capability ), "A site administrator cannot $capability." );
	}
	$check( current_user_can( 'edit_themes' ), 'A site administrator lost theme editing access.' );
	$server = rest_get_server();
	add_filter( 'rest_dispatch_request', $font_awesome_probe, 10, 2 );
	foreach ( $font_awesome_settings_routes as list( $method, $route ) ) {
		$check( 200 === rest_do_request( new WP_REST_Request( $method, $route ) )->get_status(), "A site administrator lost Font Awesome access at $route." );
	}
	$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1 ) );
	$check( ! empty( $pages ), 'A published page is required for the editing check.' );

	foreach ( brindle_helper_roles() as $slug => $definition ) {
		$users = get_users( array( 'role' => $slug, 'number' => 1 ) );
		$check( ! empty( $users ), "$slug needs a test user." );
		$user = wp_set_current_user( $users[0]->ID );
		$widgets_allowed = 'administrator' === $definition['base'];
		$appearance_allowed = 'brindle_employee' === $slug;
		$check( current_user_can( 'edit_user', $user->ID ), "$slug cannot edit its own profile." );
		$policy_id = (int) get_option( 'wp_page_for_privacy_policy' );
		if ( $policy_id ) {
			$check( current_user_can( 'edit_post', $policy_id ), "$slug cannot edit the privacy-policy page content." );
		}
		$check( current_user_can( 'create_users' ) === $widgets_allowed, "$slug has incorrect user creation access." );
		foreach ( array( 'edit_user', 'promote_user', 'delete_user', 'remove_user', 'create_app_password', 'switch_to_user' ) as $capability ) {
			$check( ! current_user_can( $capability, $admins[0]->ID ), "$slug can modify a true Administrator using $capability." );
		}
		$switch_probe = clone $user;
		$switch_probe->allcaps['switch_users'] = true;
		$check( ! user_can( $switch_probe, 'switch_to_user', $admins[0]->ID ), "$slug can impersonate a protected account through the User Switching override." );
		$client_editor = get_users( array( 'role' => 'brindle_client_editor', 'number' => 1 ) )[0];
		$employee = get_users( array( 'role' => 'brindle_employee', 'number' => 1 ) )[0];
		$check( current_user_can( 'promote_user', $client_editor->ID ) === $widgets_allowed, "$slug has incorrect client account access." );
		$check( current_user_can( 'promote_user', $employee->ID ) === ( 'brindle_employee' === $slug ), "$slug has incorrect employee account access." );
		$editable = apply_filters( 'editable_roles', wp_roles()->roles );
		$check( ! isset( $editable['administrator'] ) && ! isset( $editable['editor'] ), "$slug can assign an unrestricted role." );
		foreach ( array( 'update_core', 'install_languages', 'update_languages', 'import', 'export', 'view_site_health_checks', 'export_others_personal_data', 'erase_others_personal_data' ) as $capability ) {
			$check( ! current_user_can( $capability ), "$slug can still $capability." );
		}
		foreach ( array( 'GET', 'POST' ) as $method ) {
			$check( 403 === rest_do_request( new WP_REST_Request( $method, '/wp/v2/settings' ) )->get_status(), "$slug can access core settings through REST." );
		}
		foreach ( array( 'general', 'connectors', 'writing', 'reading', 'discussion', 'media', 'privacy' ) as $group ) {
			$check( ! current_user_can( apply_filters( 'option_page_capability_' . $group, 'manage_options' ) ), "$slug can save the $group settings group." );
		}
		$check( current_user_can( 'brindle_edit_menus' ), "$slug cannot manage menus." );
		$check( current_user_can( 'brindle_edit_widgets' ) === $widgets_allowed, "$slug has incorrect widget access." );
		foreach ( array( 'customize', 'edit_css', 'edit_theme_options' ) as $capability ) {
			$check( current_user_can( $capability ) === $appearance_allowed, "$slug has incorrect appearance access: $capability." );
		}
		foreach ( array( 'switch_themes', 'install_themes', 'update_themes', 'delete_themes' ) as $capability ) {
			$check( ! current_user_can( $capability ), "$slug has broad appearance access: $capability." );
		}
		foreach ( array_merge( $tool_caps, array( 'edit_themes' ) ) as $capability ) {
			$check( current_user_can( $capability ) === ( $appearance_allowed && 'brindle_manage_appearance' === $capability ), "$slug has incorrect access to $capability." );
		}
		foreach ( $font_awesome_settings_routes as list( $method, $route ) ) {
			$check( 403 === rest_do_request( new WP_REST_Request( $method, $route ) )->get_status(), "$slug can manage Font Awesome settings at $route." );
		}
		foreach ( array( array( 'POST', '/font-awesome/v1/api' ), array( 'GET', '/font-awesome/v1/api/token' ) ) as list( $method, $route ) ) {
			$response = rest_do_request( new WP_REST_Request( $method, $route ) );
			$check( 200 === $response->get_status() && ! empty( $response->get_data()['authorization_passed'] ), "$slug lost Font Awesome icon chooser access at $route." );
		}
		$check( current_user_can( 'edit_post', $pages[0]->ID ), "$slug cannot edit a regular page." );
		$duplicate_links = apply_filters( 'page_row_actions', array(), $pages[0] );
		$check( ! empty( $duplicate_links['duplicate'] ) && str_contains( $duplicate_links['duplicate'], 'action=dt_duplicate_post_as_draft' ), "$slug lost the Duplicate This page action." );
		$saved_get = $_GET;
		$saved_request = $_REQUEST;
		try {
			$_GET['post'] = $pages[0]->ID;
			$_REQUEST['nonce'] = wp_create_nonce( 'dt-duplicate-page-' . $pages[0]->ID );
			$duplicate_page_probe->duplicated_source = null;
			$duplicate_page_probe->dt_duplicate_post_as_draft();
			$check( $pages[0]->ID === $duplicate_page_probe->duplicated_source, "$slug cannot authorize page duplication." );
		} finally {
			$_GET = $saved_get;
			$_REQUEST = $saved_request;
		}
		$check( ! acf_current_user_can_admin(), "$slug can administer ACF." );
		foreach ( acf_get_internal_post_types() as $post_type ) {
			$check( ! current_user_can( get_post_type_object( $post_type )->cap->edit_posts ), "$slug can edit $post_type." );
		}
		foreach ( array( 'generateblocks_conditions_capability', 'generateblocks_overlays_capability', 'generateblocks_editor_access_capability', 'generateblocks_form_capability' ) as $filter ) {
			$check( ! current_user_can( apply_filters( $filter, 'manage_options', 'manage' ) ), "$slug has GenerateBlocks management access." );
			$check( current_user_can( apply_filters( $filter, 'edit_posts', 'use' ) ), "$slug lost GenerateBlocks editor use." );
		}
		foreach ( (array) acf_get_options_pages() as $page ) {
			$theme_settings = 'brindle-lola-2' === get_stylesheet() && in_array( $page['menu_slug'], array( 'theme-general-settings', 'acf-options-site-setting', 'acf-options-amenities-manager', 'acf-options-amenities-page-manager' ), true );
			$allowed = ! ( 'brindle_client_editor' === $slug && $theme_settings );
			$check( current_user_can( $page['capability'] ) === $allowed, "$slug has incorrect access to " . $page['menu_slug'] );
		}
		$check( ! current_user_can( apply_filters( 'wpmdb_ajax_cap', 'export' ) ), "$slug can use WP Migrate AJAX/REST." );
		foreach ( array( 'pusher-token-settings', 'pusher-license-settings', 'pusher-gh-settings', 'pusher-bb-settings', 'pusher-gl-settings', 'pusher-enable-logging' ) as $group ) {
			$check( ! current_user_can( apply_filters( 'option_page_capability_' . $group, 'manage_options' ) ), "$slug can save WP Pusher settings." );
		}
		$request = new WP_REST_Request( 'POST', '/generateblocks/v1/settings' );
		$request->set_param( 'settings', array() );
		$response = rest_do_request( $request );
		$check( 403 === $response->get_status(), "$slug can access the GenerateBlocks settings API." );
		$response = rest_do_request( new WP_REST_Request( 'POST', '/mdb-api/v1/get-log' ) );
		$check( in_array( $response->get_status(), array( 401, 403 ), true ), "$slug can access the WP Migrate API." );
		$response = rest_do_request( new WP_REST_Request( 'GET', '/generateblocks/v1/pattern-library/libraries' ) );
		$check( 200 === $response->get_status(), "$slug cannot browse editor pattern libraries." );
		foreach ( array( '/wp/v2/menus', '/wp/v2/menu-items', '/wp/v2/menu-locations' ) as $route ) {
			$request = new WP_REST_Request( 'GET', $route );
			$request->set_param( 'context', 'edit' );
			$check( 200 === rest_do_request( $request )->get_status(), "$slug cannot access $route." );
		}
		foreach ( array( '/wp/v2/widgets', '/wp/v2/widget-types', '/wp/v2/sidebars' ) as $route ) {
			$check( ( $widgets_allowed ? 200 : 403 ) === rest_do_request( new WP_REST_Request( 'GET', $route ) )->get_status(), "$slug has incorrect widget API access at $route." );
		}
		$request = new WP_REST_Request( 'GET', '/wp/v2/themes' );
		$request->set_param( 'context', 'edit' );
		$check( 403 === rest_do_request( $request )->get_status(), "$slug can access theme administration through REST." );
		$check( current_user_can( 'edit_theme_options' ) === $appearance_allowed, "$slug has incorrect appearance permission outside REST callbacks." );
		$appearance_probe = static function ( $result, $request ) {
			return preg_match( '#^/(generatepress-(pro|font-library)/v1/|wp/v2/font-collections)#', $request->get_route() )
				? new WP_REST_Response( array( 'authorization_passed' => true ), 200 ) : $result;
		};
		add_filter( 'rest_dispatch_request', $appearance_probe, 10, 2 );
		$response = rest_do_request( new WP_REST_Request( 'POST', '/generatepress-pro/v1/modules' ) );
		$check( ( $appearance_allowed ? 200 : 403 ) === $response->get_status(), "$slug has incorrect GeneratePress module access." );
		$response = rest_do_request( new WP_REST_Request( 'GET', '/generatepress-font-library/v1/get-fonts' ) );
		$check( ( $appearance_allowed ? 200 : 403 ) === $response->get_status(), "$slug has incorrect GeneratePress font API access." );
		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/font-collections' ) );
		$check( ( $appearance_allowed ? 200 : 403 ) === $response->get_status(), "$slug has incorrect core font API access." );
		remove_filter( 'rest_dispatch_request', $appearance_probe );
		// Exercise real write permission callbacks, then intercept dispatch before any writes.
		$write_probe = static function ( $result, $request ) {
			return ( 'POST' === $request->get_method() && in_array( $request->get_route(), array( '/wp/v2/menu-items', '/wp/v2/widgets', '/wp/v2/users' ), true ) ) || preg_match( '#^/wp/v2/users/\d+$#', $request->get_route() )
				? new WP_REST_Response( array( 'authorization_passed' => true ), 200 ) : $result;
		};
		add_filter( 'rest_dispatch_request', $write_probe, 10, 2 );
		try {
			$response = rest_do_request( new WP_REST_Request( 'POST', '/wp/v2/menu-items' ) );
			$check( 200 === $response->get_status() && ! empty( $response->get_data()['authorization_passed'] ), "$slug cannot authorize menu item writes." );
			$response = rest_do_request( new WP_REST_Request( 'POST', '/wp/v2/widgets' ) );
			$check( ( $widgets_allowed ? 200 : 403 ) === $response->get_status(), "$slug has incorrect widget write authorization." );
			foreach ( array( 'brindle_client_editor', 'administrator', 'brindle_employee' ) as $new_role ) {
				$request = new WP_REST_Request( 'POST', '/wp/v2/users' );
				$request->set_body_params( array( 'username' => 'brindle_permission_probe', 'email' => 'permission-probe@example.test', 'password' => wp_generate_password(), 'roles' => array( $new_role ) ) );
				$allowed = $widgets_allowed && in_array( $new_role, brindle_helper_operational_roles(), true );
				$check( ( $allowed ? 200 : 403 ) === rest_do_request( $request )->get_status(), "$slug has incorrect creation permission for $new_role." );
			}
			foreach ( array( 'POST', 'DELETE' ) as $method ) {
				$request = new WP_REST_Request( $method, '/wp/v2/users/' . $admins[0]->ID );
				$request->set_param( 'force', true );
				$request->set_param( 'reassign', $client_editor->ID );
				$check( 403 === rest_do_request( $request )->get_status(), "$slug can modify a true Administrator through REST." );
			}
		} finally {
			remove_filter( 'rest_dispatch_request', $write_probe );
		}

		if ( $http ) {
			$sessions = WP_Session_Tokens::get_instance( $user->ID );
			$expiration = time() + 600;
			$token = $sessions->create( $expiration );
			$cookie = SECURE_AUTH_COOKIE . '=' . wp_generate_auth_cookie( $user->ID, $expiration, 'secure_auth', $token )
				. '; ' . LOGGED_IN_COOKIE . '=' . wp_generate_auth_cookie( $user->ID, $expiration, 'logged_in', $token );
			$options = array( 'sslverify' => false, 'redirection' => 0, 'timeout' => 15, 'headers' => array( 'Cookie' => $cookie, 'User-Agent' => 'T3Code (user-directed AI agent; agent=Codex; model=gpt-6.1-sol)' ) );
			try {
				$role_blocked_urls = $appearance_allowed ? array_diff( $blocked_urls, $employee_appearance_urls ) : $blocked_urls;
				foreach ( $role_blocked_urls as $url ) {
					$response = wp_remote_get( admin_url( $url ), $options );
					$check( ! is_wp_error( $response ) && 403 === wp_remote_retrieve_response_code( $response ), "$slug was not denied at $url." );
				}
				if ( $appearance_allowed ) {
					// Opening native post-new.php creates auto-drafts; check lists without writing content.
					foreach ( array_diff( $employee_appearance_urls, array( 'post-new.php?post_type=gp_elements', 'post-new.php?post_type=popup_theme' ) ) as $url ) {
						$response = wp_remote_get( admin_url( $url ), $options );
						$check( ! is_wp_error( $response ) && in_array( wp_remote_retrieve_response_code( $response ), array( 200, 302 ), true ), "$slug cannot open $url." );
					}
				}
				if ( 'brindle-lola-2' === get_stylesheet() ) {
					foreach ( array( 'acf-options-site-setting', 'acf-options-amenities-manager', 'acf-options-amenities-page-manager' ) as $page_slug ) {
						$url = admin_url( 'admin.php?page=' . $page_slug );
						$response = wp_remote_get( $url, $options );
						$check( ( 'brindle_client_editor' === $slug ? 403 : 200 ) === wp_remote_retrieve_response_code( $response ), "$slug has incorrect access to $page_slug." );
						if ( 'brindle_client_editor' === $slug ) {
							$response = wp_remote_post( $url, array_merge( $options, array( 'body' => array( '_acf_nonce' => wp_create_nonce( 'options' ), 'acf' => array() ) ) ) );
							$check( 403 === wp_remote_retrieve_response_code( $response ), "$slug can submit $page_slug." );
						}
					}
				}
				$duplicate_settings = get_option( 'duplicate_page_options' );
				$body = array_merge( (array) $duplicate_settings, array( 'submit_duplicate_page' => 'Save Changes', 'duplicatepage_nonce_field' => wp_create_nonce( 'duplicatepage_action' ) ) );
				$response = wp_remote_post( admin_url( 'options-general.php?page=duplicate_page_settings' ), array_merge( $options, array( 'body' => $body ) ) );
				$check( 403 === wp_remote_retrieve_response_code( $response ), "$slug can submit Duplicate Page settings." );
				$check( $duplicate_settings === get_option( 'duplicate_page_options' ), "$slug changed Duplicate Page settings." );
				foreach ( array( 'nav-menus.php' => 200, 'widgets.php' => $widgets_allowed ? 200 : 403, 'edit.php?post_type=popup' => 200, 'profile.php' => 200, 'users.php' => $widgets_allowed ? 200 : 403, 'user-new.php' => $widgets_allowed ? 200 : 403, 'user-edit.php?user_id=' . $admins[0]->ID => 403 ) as $url => $status ) {
					$response = wp_remote_get( admin_url( $url ), $options );
					$check( $status === wp_remote_retrieve_response_code( $response ), "$slug has incorrect access at $url." );
				}
				$response = wp_remote_get( admin_url( 'index.php' ), $options );
				$check( 200 === wp_remote_retrieve_response_code( $response ), "$slug cannot open the dashboard." );
				$dom = new DOMDocument();
				$dom->loadHTML( wp_remote_retrieve_body( $response ), LIBXML_NOERROR | LIBXML_NOWARNING );
				$xpath = new DOMXPath( $dom );
				$appearance_links = array();
				foreach ( $xpath->query( '//*[@id="menu-appearance"]//ul//a/@href' ) as $href ) {
					$appearance_links[] = str_contains( $href->value, 'customize.php' ) ? 'customize.php' : $href->value;
				}
				$expected_links = $widgets_allowed ? array( 'nav-menus.php', 'widgets.php' ) : array( 'nav-menus.php' );
				if ( $appearance_allowed ) {
					$expected_links = array_merge( array( 'nav-menus.php', 'widgets.php' ), array_diff( $employee_appearance_urls, array( 'post-new.php?post_type=gp_elements', 'post-new.php?post_type=popup_theme' ) ) );
				}
				sort( $expected_links );
				sort( $appearance_links );
				$check( $expected_links === $appearance_links, "$slug has incorrect Appearance menu items: " . implode( ', ', $appearance_links ) );
				foreach ( $xpath->query( '//*[@id="adminmenu"]//a/@href' ) as $href ) {
					$path = wp_parse_url( $href->value, PHP_URL_PATH );
					$check( str_contains( $href->value, 'page=' ) || ! in_array( basename( $path ), array_merge( brindle_helper_core_settings_screens(), array( 'tools.php', 'import.php', 'export.php', 'site-health.php', 'update-core.php', 'export-personal-data.php', 'erase-personal-data.php' ) ), true ), "$slug still has a restricted core menu link: " . $href->value );
					$check( ! preg_match( '/wppusher|wp-migrate-db|hfcm-|generateblocks|wp-umbrella-settings|page=font-awesome|duplicate_page_settings|theme-editor\.php|post_type=acf-/', $href->value ), "$slug still has a restricted menu link: " . $href->value );
					$check( ! preg_match( $appearance_allowed ? '/edit-comments\.php/' : '/edit-comments\.php|post_type=gp_elements/', $href->value ), "$slug still has a hidden navigation link: " . $href->value );
				}
				$check( ! str_contains( wp_remote_retrieve_body( $response ), 'wp.coreCommands.initializeCommandPalette(' ), "$slug still initializes the dashboard Command K search." );
				$hidden_toolbar = $appearance_allowed ? array( 'comments', 'command-palette', 'gb_overlays-menu' ) : array( 'comments', 'command-palette', 'gp_elements-menu', 'gb_overlays-menu', 'seopress', 'new-gp_elements' );
				foreach ( $hidden_toolbar as $id ) {
					$check( 0 === $xpath->query( '//*[@id="wp-admin-bar-' . $id . '"]' )->length, "$slug still has the $id toolbar item." );
				}
				$check( ( $appearance_allowed ? 1 : 0 ) === $xpath->query( '//*[@id="wp-admin-bar-seopress"]' )->length, "$slug has incorrect SEO toolbar visibility." );
				$check( ( 'brindle_client_editor' !== $slug ) === ( $xpath->query( '//*[@id="adminmenu"]//a[contains(@href,"page=acf-options-site-setting")]' )->length > 0 ), "$slug has incorrect Theme Settings menu visibility." );
				$response = wp_remote_get( home_url( '/' ), $options );
				$check( 200 === wp_remote_retrieve_response_code( $response ), "$slug cannot view the homepage." );
				$dom->loadHTML( wp_remote_retrieve_body( $response ), LIBXML_NOERROR | LIBXML_NOWARNING );
				$xpath = new DOMXPath( $dom );
				$check( 1 === $xpath->query( '//*[@id="wp-admin-bar-my-account"]' )->length, "$slug has no authenticated frontend toolbar to check." );
				foreach ( array_diff( $hidden_toolbar, array( 'command-palette' ) ) as $id ) {
					$check( 0 === $xpath->query( '//*[@id="wp-admin-bar-' . $id . '"]' )->length, "$slug still has the $id frontend toolbar item." );
				}
				$check( ( $appearance_allowed ? 1 : 0 ) === $xpath->query( '//*[@id="wp-admin-bar-seopress"]' )->length, "$slug has incorrect frontend SEO toolbar visibility." );
			} finally {
				$sessions->destroy( $token );
			}
		}
		WP_CLI::log( "$slug: access policy passed; page editing and GenerateBlocks editor use retained." );
	}
} finally {
	remove_filter( 'rest_dispatch_request', $font_awesome_probe );
	wp_set_current_user( $original_user );
}
WP_CLI::success( 'Admin tool access checks passed' . ( $http ? ' (including authenticated admin URLs and menus).' : '.' ) );
