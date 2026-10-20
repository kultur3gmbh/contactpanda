<?php

$root       = dirname( __DIR__ );
$main       = file_get_contents( $root . '/contactpanda/contactpanda.php' );
$license    = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-license.php' );
$schema     = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-newsletter-schema.php' );
$newsletter = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-newsletters.php' );
$campaigns  = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-newsletter-campaigns.php' );
$segments   = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-segments.php' );
$updater    = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-github-updater.php' );

foreach ( array( 'ContactPanda_License::newsletter_entitled', 'contactpanda_boot_newsletter_module', "'licensed-basic'" ) as $contract ) {
	if ( false === strpos( $main, $contract ) ) {
		fwrite( STDERR, "Missing Basic newsletter bootstrap contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( "'newsletter_granted'", 'Permanently enabled', 'contactpanda_updates_allowed', 'Features that were already granted remain available' ) as $contract ) {
	if ( false === strpos( $license, $contract ) ) {
		fwrite( STDERR, "Missing durable license contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'cp_newsletter_templates', 'cp_segments', 'cp_newsletter_campaigns', 'cp_newsletter_recipients', 'cp_newsletter_suppressions' ) as $contract ) {
	if ( false === strpos( $schema, $contract ) ) {
		fwrite( STDERR, "Missing newsletter schema contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'final class ContactPanda_Basic_Newsletters', 'final class ContactPanda_Basic_Newsletter_Campaigns', 'final class ContactPanda_Basic_Segments' ) as $contract ) {
	if ( false === strpos( $newsletter . $campaigns . $segments, $contract ) ) {
		fwrite( STDERR, "Missing shared newsletter class {$contract}.\n" );
		exit( 1 );
	}
}

if ( false === strpos( $updater, "apply_filters( 'contactpanda_updates_allowed'" ) ) {
	fwrite( STDERR, "The updater is not connected to update-period eligibility.\n" );
	exit( 1 );
}

echo "ContactPanda Basic 0.6.0 contracts passed.\n";
