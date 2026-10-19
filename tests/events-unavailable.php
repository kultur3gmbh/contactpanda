<?php

define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );

function get_option( $key, $default = false ) { return $default; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function wp_cache_get() { return false; }
function wp_cache_set() { return true; }

final class ContactPanda_Events_Unavailable_DB {
	public $prefix = 'fixture_';
	public function esc_like( $value ) { return $value; }
	public function prepare( $query, $value ) { return $value; }
	public function get_var() { return null; }
}

$wpdb = new ContactPanda_Events_Unavailable_DB();
require_once dirname( __DIR__ ) . '/contactpanda/includes/class-contactpanda-events.php';

if ( ContactPanda_Events::sources() || ContactPanda_Events::available() ) {
	throw new RuntimeException( 'Optional event controls must disappear when no supported source is active.' );
}

echo "ContactPanda unavailable event source tests passed.\n";
