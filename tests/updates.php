<?php
/** Local check: wp eval-file wp-content/plugins/brindle-helper/tests/updates.php */
defined( 'ABSPATH' ) || exit;

$check = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};
$checker = brindle_helper_update_checker();
$manifest = json_decode( file_get_contents( WP_PLUGIN_DIR . '/brindle-helper/update.json' ), true, 512, JSON_THROW_ON_ERROR );
$plugin = get_plugin_data( WP_PLUGIN_DIR . '/brindle-helper/brindle-helper.php', false, false );
$check( $plugin['Version'] === $manifest['version'], 'Manifest and plugin versions differ.' );
$check( 'https://raw.githubusercontent.com/jonschr/brindle-helper/master/update.json' === $checker->metadataUrl, 'Wrong JSON endpoint.' );
$check( 'brindle-helper/brindle-helper.php' === $checker->pluginFile, 'Wrong plugin update target.' );
$check( false !== has_filter( 'plugins_api', array( $checker, 'injectInfo' ) ), 'Plugin details hook is missing.' );
$check( false !== has_filter( 'site_transient_update_plugins', array( $checker, 'injectUpdate' ) ), 'WordPress update hook is missing.' );

// Exercise the real JSON parser with a future release, without a network call or installation.
$manifest['version'] = '99.0.0';
$manifest['download_url'] = 'https://github.com/jonschr/brindle-helper/archive/refs/tags/99.0.0.zip';
$requests = 0;
$mock = static function ( $response, $options, $url ) use ( $checker, $manifest, &$requests ) {
	if ( str_starts_with( $url, $checker->metadataUrl ) ) {
		++$requests;
		return array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'headers' => array( 'content-type' => 'application/json' ), 'body' => wp_json_encode( $manifest ), 'cookies' => array() );
	}
	return $response;
};
add_filter( 'pre_http_request', $mock, 10, 3 );
try {
	$update = $checker->requestUpdate();
	$check( 1 === $requests && $update && '99.0.0' === $update->version, 'JSON update discovery failed.' );
	$native_update = $update->toWpFormat();
	$check( $manifest['download_url'] === $native_update->package && 'brindle-helper/brindle-helper.php' === $native_update->plugin, 'Invalid native WordPress update data.' );
} finally {
	remove_filter( 'pre_http_request', $mock );
}
WP_CLI::success( 'JSON update discovery, plugin details and native update hooks passed (mocked release; no installation).' );
