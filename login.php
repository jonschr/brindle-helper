<?php
/**
 * Login presentation uses core's forms and authentication flow unchanged.
 */
defined( 'ABSPATH' ) || exit;

function brindle_helper_login_assets() {
	wp_enqueue_style( 'brindle-helper-login', plugins_url( 'login.css', __FILE__ ), array( 'login' ), '0.3.0' );
	wp_enqueue_script( 'brindle-helper-login', plugins_url( 'login.js', __FILE__ ), array(), '0.3.0', true );
}
add_action( 'login_enqueue_scripts', 'brindle_helper_login_assets' );

function brindle_helper_login_header_url() {
	return 'https://brindledigital.com/';
}
add_filter( 'login_headerurl', 'brindle_helper_login_header_url' );

function brindle_helper_login_header_text() {
	return esc_html__( 'Brindle Digital Marketing (opens in a new tab)', 'brindle-helper' );
}
add_filter( 'login_headertext', 'brindle_helper_login_header_text' );
