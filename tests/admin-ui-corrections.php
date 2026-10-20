<?php

$root       = dirname( __DIR__ );
$plugin     = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-plugin.php' );
$store      = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-store.php' );
$data_tools = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-data-tools.php' );
$script     = file_get_contents( $root . '/contactpanda/assets/contactpanda-admin.js' );
$styles     = file_get_contents( $root . '/contactpanda/assets/contactpanda-admin.css' );

foreach ( array( 'Drop a CSV file here', 'Drop a vCard file here', 'contactpanda-file-drop-actions' ) as $contract ) {
	if ( false === strpos( $data_tools, $contract ) ) {
		fwrite( STDERR, "Missing import dropzone contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'contactpanda-search-primary', 'contactpanda-search-secondary', 'Search by name, email, phone, or company', "esc_html__( 'Execute', 'contactpanda' )" ) as $contract ) {
	if ( false === strpos( $plugin, $contract ) ) {
		fwrite( STDERR, "Missing people-list UI contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'contactpanda-table-scroll', 'contactpanda-people-table', 'contactpanda-email-column' ) as $contract ) {
	if ( false === strpos( $plugin, $contract ) ) {
		fwrite( STDERR, "Missing responsive people-table contract {$contract}.\n" );
		exit( 1 );
	}
}

if ( false === strpos( $plugin, "'contactpanda_page_contactpanda-data-transfer'" ) ) {
	fwrite( STDERR, "The Import / Export page does not load the ContactPanda administrator assets.\n" );
	exit( 1 );
}

foreach ( array( 'contactpanda-company-relation-modal', 'Assign another company', 'Assign company', 'Remove this company assignment?' ) as $contract ) {
	if ( false === strpos( $plugin, $contract ) ) {
		fwrite( STDERR, "Missing company-assignment popup contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'contactpanda-open-company-relation', 'contactpanda-close-company-relation', 'contactpanda-file-drop' ) as $contract ) {
	if ( false === strpos( $script, $contract ) ) {
		fwrite( STDERR, "Missing administrator interaction contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'contactpanda-file-drop-actions', 'contactpanda-search-primary', 'contactpanda-company-relation-modal', 'contactpanda-table-scroll', 'contactpanda-email-column' ) as $contract ) {
	if ( false === strpos( $styles, $contract ) ) {
		fwrite( STDERR, "Missing administrator style contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'activity_is_note', 'partition_contact_notes', 'address_update', 'user_account', 'user_roles' ) as $contract ) {
	if ( false === strpos( $store . $plugin, $contract ) ) {
		fwrite( STDERR, "Missing note/activity separation contract {$contract}.\n" );
		exit( 1 );
	}
}

echo "ContactPanda administrator UI correction checks passed.\n";
