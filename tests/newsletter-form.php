<?php

$root = dirname( __DIR__ );
$registration = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-registration.php' );

foreach ( array( 'newsletter_privacy_text', 'newsletter_consent_text', 'newsletter_submit_label', 'newsletter_error', 'newsletter_already_registered', 'contactpanda_newsletter_signup_form_settings', 'contactpanda_newsletter_signup_html', "'salutation' => \$salutation", "'confirmed' === sanitize_key" ) as $contract ) {
	if ( false === strpos( $registration, $contract ) ) {
		fwrite( STDERR, "Missing newsletter form contract {$contract}.\n" );
		exit( 1 );
	}
}

echo "ContactPanda newsletter form checks passed.\n";
