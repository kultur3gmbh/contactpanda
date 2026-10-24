<?php

$root = dirname( __DIR__ );
$plugin = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-plugin.php' );

foreach ( array( 'contactpanda_person_detail_tabs', 'contactpanda_render_person_detail_tab_', 'normalized_extension_tabs' ) as $contract ) {
	if ( false === strpos( $plugin, $contract ) ) {
		fwrite( STDERR, "Missing person-detail extension contract {$contract}.\n" );
		exit( 1 );
	}
}

echo "ContactPanda person-detail extension checks passed.\n";
