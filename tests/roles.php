<?php
/**
 * Run on a development site: wp eval-file wp-content/plugins/brindle-helper/tests/roles.php
 */
defined( 'ABSPATH' ) || exit;

$check = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$definitions    = brindle_helper_roles();
$original_roles = wp_roles();
$plugin_caps    = array( 'activate_plugins', 'install_plugins', 'update_plugins', 'delete_plugins', 'edit_plugins' );
$restricted_caps = array_merge( $plugin_caps, array( 'edit_themes', 'switch_themes', 'install_themes', 'update_themes', 'delete_themes', 'edit_theme_options', 'update_core', 'install_languages', 'update_languages', 'import', 'export', 'view_site_health_checks', 'export_others_personal_data', 'erase_others_personal_data', 'brindle_manage_wppusher', 'brindle_manage_wp_migrate', 'brindle_manage_acf', 'brindle_manage_generateblocks', 'brindle_manage_hfcm', 'brindle_manage_appearance', 'brindle_manage_core_settings', 'brindle_manage_wp_umbrella', 'brindle_manage_font_awesome', 'brindle_manage_duplicate_page' ) );
// Test copying in memory: other plugins can modify stored roles after creation.
$GLOBALS['wp_roles'] = new WP_Roles();
wp_roles()->use_db = false;
try {
	foreach ( $definitions as $slug => $definition ) {
		remove_role( $slug );
	}
	brindle_helper_register_roles();
	foreach ( $definitions as $slug => $definition ) {
		$role = get_role( $slug );
		$expected = array_replace( get_role( $definition['base'] )->capabilities, array_fill_keys( $restricted_caps, false ), array( 'brindle_edit_menus' => true, 'brindle_edit_widgets' => 'administrator' === $definition['base'] ) );
		if ( 'brindle_employee' === $slug ) {
			$expected['edit_theme_options'] = true;
			$expected['brindle_manage_appearance'] = true;
		}
		$check( $role && $role->capabilities == $expected, "$slug does not match its source role with plugin management disabled." );
	}
	get_role( 'brindle_client_administrator' )->remove_cap( 'manage_options' );
	$before = wp_roles()->roles;
	brindle_helper_register_roles();
	$check( $before === wp_roles()->roles, 'Repeated registration changed customized roles.' );
	get_role( 'brindle_client_administrator' )->add_cap( 'activate_plugins' );
	brindle_helper_register_roles();
	$check( $before === wp_roles()->roles, 'Existing role restrictions were not reapplied correctly.' );
	$check( get_role( 'administrator' )->has_cap( 'activate_plugins' ), 'The original administrator role lost plugin access.' );
} finally {
	$GLOBALS['wp_roles'] = $original_roles;
}

$original_user = get_current_user_id();
try {
	foreach ( $definitions as $slug => $definition ) {
		$user = new WP_User();
		$user->caps = array( $slug => true );
		$user->get_role_caps();
		$check( $user->has_cap( 'manage_options' ) === ( 'administrator' === $definition['base'] ), "$slug has incorrect settings access." );
		foreach ( $plugin_caps as $capability ) {
			$check( ! $user->has_cap( $capability ), "$slug can still $capability." );
		}
	}

	$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	$check( ! empty( $admins ), 'An administrator is required for the rendering check.' );
	wp_set_current_user( $admins[0]->ID );
	$check( brindle_helper_can_view_settings(), 'A site administrator cannot access Brindle Helper.' );
	ob_start();
	brindle_helper_render_settings();
	$html = ob_get_clean();
	$dom  = new DOMDocument();
	$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING );
	$xpath = new DOMXPath( $dom );
	$check( 1 === $xpath->query( '//table' )->length, 'Expected one comparison table.' );
	$check( 4 === $xpath->query( '//thead/tr/th' )->length, 'Expected a permission column and three role columns.' );

	$comparisons = array( 'manage_options' => array( 'Yes', 'No', 'Yes' ), 'edit_posts' => array( 'Yes', 'Yes', 'Yes' ) );
	$comparisons['brindle_edit_menus'] = array( 'Yes', 'Yes', 'Yes' );
	$comparisons['brindle_edit_widgets'] = array( 'Yes', 'No', 'Yes' );
	foreach ( $restricted_caps as $capability ) {
		$comparisons[ $capability ] = array( 'No', 'No', in_array( $capability, array( 'edit_theme_options', 'brindle_manage_appearance' ), true ) ? 'Yes' : 'No' );
	}
	foreach ( $comparisons as $capability => $expected ) {
		$cells = $xpath->query( '//tr[th/code="' . $capability . '"]/td' );
		$actual = array();
		foreach ( $cells as $cell ) {
			$actual[] = preg_replace( '/[✓−\s]+/u', '', $cell->textContent );
		}
		$check( $expected === $actual, "$capability comparison is incorrect." );
	}
	$check( $xpath->query( '//span[contains(@class,"is-allowed")]' )->length > 0, 'Yes styling is missing.' );
	$check( $xpath->query( '//span[contains(@class,"is-denied")]' )->length > 0, 'No styling is missing.' );
	$check( 'Plugin management' === trim( $xpath->evaluate( 'string(//tr[th/code="activate_plugins"]/ancestor::tbody/tr[1]/th)' ) ), 'Plugin permissions are not grouped together.' );
	$check( 'Posts' === trim( $xpath->evaluate( 'string(//tr[th/code="edit_posts"]/ancestor::tbody/tr[1]/th)' ) ), 'Post permissions are not grouped together.' );
	$all_capabilities = array();
	foreach ( $definitions as $slug => $definition ) {
		$all_capabilities = array_merge( $all_capabilities, array_keys( get_role( $slug )->capabilities ) );
	}
	$rendered_capabilities = array();
	foreach ( $xpath->query( '//tbody/tr/th/code' ) as $code ) {
		$rendered_capabilities[] = $code->textContent;
	}
	sort( $rendered_capabilities );
	$all_capabilities = array_values( array_unique( $all_capabilities ) );
	sort( $all_capabilities );
	$check( $rendered_capabilities === $all_capabilities, 'Sections duplicated or omitted permissions.' );

	foreach ( get_users( array( 'role__in' => array_keys( $definitions ) ) ) as $user ) {
		$check( false !== strpos( $html, esc_html( $user->display_name ) ), 'An assigned user is missing from the table.' );
		wp_set_current_user( $user->ID );
		$check( ! brindle_helper_can_view_settings(), "$user->user_login can access Brindle Helper." );
		global $submenu;
		$saved_submenu = $submenu;
		$submenu = array();
		try {
			brindle_helper_admin_menu();
			$check( empty( $submenu ), "$user->user_login can see the Brindle Helper menu." );
		} finally {
			$submenu = $saved_submenu;
		}
		$deny_handler = static function () {
			return static function ( $message, $title, $args ) {
				throw new RuntimeException( 'Access denied: ' . $args['response'] );
			};
		};
		add_filter( 'wp_die_handler', $deny_handler, PHP_INT_MAX );
		try {
			brindle_helper_render_settings();
			$check( false, "$user->user_login can directly render Brindle Helper." );
		} catch ( RuntimeException $error ) {
			$check( 'Access denied: 403' === $error->getMessage(), $error->getMessage() );
		} finally {
			remove_filter( 'wp_die_handler', $deny_handler, PHP_INT_MAX );
		}
		foreach ( array_merge( $restricted_caps, array( 'activate_plugin', 'deactivate_plugin', 'deactivate_plugins', 'upload_plugins', 'resume_plugin' ) ) as $capability ) {
			$allowed = in_array( 'brindle_employee', $user->roles, true ) && in_array( $capability, array( 'edit_theme_options', 'brindle_manage_appearance' ), true );
			$check( current_user_can( $capability, 'brindle-helper/brindle-helper.php' ) === $allowed, "$user->user_login has incorrect access to $capability." );
		}
	}
	wp_set_current_user( 0 );
	$check( ! brindle_helper_can_view_settings(), 'An anonymous user can access Brindle Helper.' );
} finally {
	wp_set_current_user( $original_user );
}

WP_CLI::success( 'Role restrictions, settings access, permission sections, and Yes/No rendering passed.' );
