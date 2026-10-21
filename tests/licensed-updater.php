<?php
/** Offline tests for the protected licensed updater. */
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
class WP_Error { private $code; private $message; public function __construct( $code, $message = '' ) { $this->code = $code; $this->message = $message; } public function get_error_code() { return $this->code; } public function get_error_message() { return $this->message; } }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function __( $value, $domain = '' ) { return $value; }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function add_filter() {} function add_action() {}
function sanitize_text_field( $value ) { return (string) $value; }
function esc_url_raw( $value ) { return (string) $value; }
function wp_kses_post( $value ) { return (string) $value; }
function plugin_basename( $value ) { return ltrim( (string) $value, '/' ); }
function get_site_transient( $key ) { return $GLOBALS['cache'][ $key ] ?? false; }
function set_site_transient( $key, $value, $ttl ) { $GLOBALS['cache'][ $key ] = $value; }
function delete_site_transient( $key ) { unset( $GLOBALS['cache'][ $key ] ); $GLOBALS['deleted'][] = $key; }
function wp_delete_file( $file ) { if ( is_file( $file ) ) unlink( $file ); }
function download_url( $url, $timeout ) { $GLOBALS['downloads'][] = $url; $file = tempnam( sys_get_temp_dir(), 'cplu-' ); file_put_contents( $file, $GLOBALS['bytes'] ); return $file; }
function verify( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
class ContactPanda_License { public static function product_update( $product, $entitlement, $version, $channel ) { $GLOBALS['metadata_calls'][] = $product; return array_shift( $GLOBALS['metadata_queue'] ); } }
require __DIR__ . '/../contactpanda/includes/class-contactpanda-licensed-updater.php';
function updater( $product, $plugin ) { return new ContactPanda_Licensed_Updater( array( 'product' => $product, 'entitlement' => $product, 'plugin' => $plugin, 'slug' => $product, 'name' => $product, 'version' => '1.0.0' ) ); }
$GLOBALS['cache'] = array(); $GLOBALS['deleted'] = array(); $GLOBALS['downloads'] = array(); $GLOBALS['metadata_calls'] = array();
$GLOBALS['bytes'] = 'verified release bytes';
$stale = array( 'available' => true, 'version' => '1.1.0', 'package' => 'https://example.test/expired', 'sha256' => hash( 'sha256', $GLOBALS['bytes'] ) );
$fresh = $stale; $fresh['package'] = 'https://example.test/fresh';
$GLOBALS['metadata_queue'] = array( $stale, $fresh );
$plus = updater( 'plus', 'contactpanda-plus/contactpanda-plus.php' );
$pro = updater( 'pro', 'contactpanda-pro/contactpanda-pro.php' );
$GLOBALS['metadata_queue'][] = $fresh;
$plus->updates( (object) array( 'checked' => array( 'contactpanda-plus/contactpanda-plus.php' => '1.0.0' ) ) );
$file = $plus->verify_download( false, $stale['package'], null, array( 'plugin' => 'contactpanda-plus/contactpanda-plus.php' ) );
verify( is_string( $file ) && 'https://example.test/fresh' === $GLOBALS['downloads'][0], 'Expired URL replaced immediately before download' ); wp_delete_file( $file );
verify( false === $pro->verify_download( false, $stale['package'], null, array( 'plugin' => 'contactpanda-plus/contactpanda-plus.php' ) ), 'Parallel updater does not intercept another plugin' );
$GLOBALS['bytes'] = 'tampered'; $GLOBALS['metadata_queue'] = array( $fresh );
verify( 'contactpanda_update_checksum' === $plus->verify_download( false, $fresh['package'], null, array( 'plugins' => array( 'contactpanda-plus/contactpanda-plus.php', 'contactpanda-pro/contactpanda-pro.php' ) ) )->get_error_code(), 'Checksum mismatch rejected in bulk update' );
$plus->clear_update_cache( null, array( 'type' => 'plugin', 'plugin' => 'contactpanda-plus/contactpanda-plus.php' ) );
verify( in_array( 'update_plugins', $GLOBALS['deleted'], true ), 'WordPress update cache cleared after success' );
echo "Licensed updater stale URL, parallel routing, checksum and cache tests passed.\n";
