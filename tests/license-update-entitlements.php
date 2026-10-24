<?php
/** Offline contract test for package updates granted by individual modules. */
define( 'ABSPATH', __DIR__ );
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
require __DIR__ . '/../contactpanda/includes/class-contactpanda-license.php';
function verify_entitlement( $expected, $grants, $family, $label ) {
	$actual = ContactPanda_License::update_entitlement_matches( $grants, $family );
	if ( $expected !== $actual ) {
		fwrite( STDERR, $label . " failed.\n" );
		exit( 1 );
	}
}
verify_entitlement( true, array( 'forms' ), 'plus', 'Plus module grants Plus updates' );
verify_entitlement( true, array( 'support_desk' ), 'pro', 'Support Desk grants Pro updates' );
verify_entitlement( true, array( 'project_management' ), 'business', 'Project management grants Business updates' );
verify_entitlement( true, array( 'all' ), 'business', 'All grants every update family' );
verify_entitlement( false, array( 'support_desk' ), 'business', 'Pro does not grant Business updates' );
verify_entitlement( false, array( 'project_management' ), 'pro', 'Business does not grant Pro updates' );
echo "License update entitlement tests passed.\n";
