( function ( blocks, element, i18n, serverSideRender ) {
	'use strict';
	if ( ! blocks || ! element || ! serverSideRender ) return;
	const el = element.createElement;
	blocks.registerBlockType( 'contactpanda/customer-portal', {
		apiVersion: 2,
		title: i18n.__( 'ContactPanda customer portal', 'contactpanda' ),
		description: i18n.__( 'Shows the signed-in customer their profile, preferences, and available ContactPanda services.', 'contactpanda' ),
		icon: 'id-alt',
		category: 'widgets',
		editorScript: 'contactpanda-portal-block',
		edit: function ( props ) { return el( serverSideRender, { block: 'contactpanda/customer-portal', attributes: props.attributes } ); },
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.element, window.wp.i18n, window.wp.serverSideRender );
