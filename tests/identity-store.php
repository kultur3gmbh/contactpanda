<?php

$root = dirname( __DIR__ );
$schema = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-schema.php' );
$store = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-store.php' );
$identity = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-identity-store.php' );
$plugin = file_get_contents( $root . '/contactpanda/contactpanda.php' );

foreach ( array( 'contact_channels', 'external_identities', 'merge_history' ) as $table ) {
	if ( false === strpos( $schema, "'{$table}'" ) ) {
		fwrite( STDERR, "Missing normalized identity table {$table}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'normalize_channel', 'contact_id_by_channel', 'contact_id_by_identity', 'add_channel', 'add_identity', 'merge_contacts', 'record_merge' ) as $contract ) {
	if ( false === strpos( $identity, "function {$contract}" ) ) {
		fwrite( STDERR, "Missing identity-store contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'ContactPanda_Identity_Store::sync_contact', 'ContactPanda_Identity_Store::merge_contacts', 'ContactPanda_Identity_Store::record_merge', 'contactpanda_duplicate_candidates' ) as $contract ) {
	if ( false === strpos( $store, $contract ) ) {
		fwrite( STDERR, "Missing store integration {$contract}.\n" );
		exit( 1 );
	}
}

if ( false === strpos( $plugin, "class-contactpanda-identity-store.php" ) ) {
	fwrite( STDERR, "The identity store is not loaded by ContactPanda Basic.\n" );
	exit( 1 );
}

echo "ContactPanda identity-store checks passed.\n";
