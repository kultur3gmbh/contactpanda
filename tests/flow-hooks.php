<?php

$root = dirname( __DIR__ );
$store = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-store.php' );
$plugin = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-plugin.php' );

if ( false === strpos( $store, "do_action( 'contactpanda_contact_created'" ) ) {
	fwrite( STDERR, "The contact-created extension event is missing.\n" );
	exit( 1 );
}
if ( false === strpos( $store, "do_action( 'contactpanda_marketing_confirmed'" ) || false === strpos( $plugin, "do_action( 'contactpanda_marketing_confirmed'" ) ) {
	fwrite( STDERR, "The marketing-confirmed extension event is incomplete.\n" );
	exit( 1 );
}

echo "ContactPanda flow hook checks passed.\n";
