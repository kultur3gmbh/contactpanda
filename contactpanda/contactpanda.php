<?php
/**
 * Plugin Name: ContactPanda
 * Description: Free contact and company management for WordPress with WooCommerce, event, Amelia, and optional Groundhogg overviews.
 * Version: 0.8.1
 * Update URI: https://github.com/kultur3gmbh/contactpanda
 * Author: Kultur3 GmbH
 * Text Domain: contactpanda
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 6.4
 */

defined( 'ABSPATH' ) || exit;

define( 'CONTACTPANDA_VERSION', '0.8.1' );
define( 'CONTACTPANDA_ASSET_VERSION', '0.8.1-20261024-1' );
define( 'CONTACTPANDA_EXTENSION_API_VERSION', '1.0' );
define( 'CONTACTPANDA_FILE', __FILE__ );
define( 'CONTACTPANDA_PATH', plugin_dir_path( __FILE__ ) );
define( 'CONTACTPANDA_URL', plugin_dir_url( __FILE__ ) );

require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-plugin.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-modules.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-license.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-schema.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-newsletter-schema.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-store.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-trash.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-data-tools.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-custom-fields.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-events.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-github-updater.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-licensed-updater.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-registration.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-my-account.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-newsletters.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-newsletter-campaigns.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-segments.php';

add_action(
	'plugins_loaded',
	static function () {
		new ContactPanda_GitHub_Updater(
			array(
				'slug'       => 'contactpanda',
				'name'       => 'ContactPanda',
				'repo'       => 'contactpanda',
				'zip_prefix' => 'contactpanda-basic',
				'plugin'     => plugin_basename( CONTACTPANDA_FILE ),
				'version'    => CONTACTPANDA_VERSION,
				'enabled'    => 'CONTACTPANDA_GITHUB_UPDATES',
			)
		);
	},
	20
);

add_action( 'plugins_loaded', array( 'ContactPanda_Plugin', 'boot' ), 30 );
add_action( 'plugins_loaded', array( 'ContactPanda_License', 'boot' ), 30 );
add_action( 'plugins_loaded', array( 'ContactPanda_Trash', 'boot' ), 30 );
add_action( 'plugins_loaded', array( 'ContactPanda_Data_Tools', 'boot' ), 30 );
add_action( 'plugins_loaded', array( 'ContactPanda_Custom_Fields', 'boot' ), 30 );
add_action( 'plugins_loaded', array( 'ContactPanda_Events', 'boot' ), 30 );
add_action( 'plugins_loaded', array( 'ContactPanda_Registration', 'boot' ), 30 );
add_action( 'plugins_loaded', array( 'ContactPanda_My_Account', 'boot' ), 31 );

/**
 * The licensed newsletter is a Basic feature. Plus can grant the same public
 * entitlement through the filter before this late bootstrap runs.
 */
function contactpanda_boot_newsletter_module() {
	$enabled = ContactPanda_License::newsletter_entitled();
	$existing_newsletter = ContactPanda_Modules::get( 'newsletter' );
	$existing_segments   = ContactPanda_Modules::get( 'segments' );
	ContactPanda_Modules::register(
		'newsletter',
		array(
			'name'    => __( 'Newsletter', 'contactpanda' ),
			'package' => $existing_newsletter ? $existing_newsletter['package'] : 'licensed-basic',
			'version' => CONTACTPANDA_VERSION,
			'enabled' => $enabled,
		)
	);
	ContactPanda_Modules::register(
		'segments',
		array(
			'name'    => __( 'Saved searches', 'contactpanda' ),
			'package' => $existing_segments ? $existing_segments['package'] : 'licensed-basic',
			'version' => CONTACTPANDA_VERSION,
			'enabled' => $enabled,
		)
	);
	if ( ! $enabled ) {
		return;
	}
	// ContactPanda Plus before 0.6.0 still owns these classes. Let that version
	// finish its normal boot instead of registering duplicate screens or hooks.
	if ( class_exists( 'ContactPanda_Newsletters', false ) && defined( 'CONTACTPANDA_PLUS_VERSION' ) && version_compare( CONTACTPANDA_PLUS_VERSION, '0.6.0', '<' ) ) {
		return;
	}
	$result = ContactPanda_Newsletter_Schema::maybe_upgrade();
	if ( is_wp_error( $result ) ) {
		add_action(
			'admin_notices',
			static function () use ( $result ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
			}
		);
		return;
	}
	ContactPanda_Basic_Segments::boot();
	if ( ! class_exists( 'ContactPanda_Segments', false ) ) {
		class_alias( 'ContactPanda_Basic_Segments', 'ContactPanda_Segments' );
	}
	if ( ! ContactPanda_Store::standalone() ) {
		return;
	}
	ContactPanda_Basic_Newsletters::boot();
	ContactPanda_Basic_Newsletter_Campaigns::boot();
	// Preserve the established public class names for current extensions while
	// avoiding redeclaration when an older Plus version already supplies them.
	if ( ! class_exists( 'ContactPanda_Newsletters', false ) ) {
		class_alias( 'ContactPanda_Basic_Newsletters', 'ContactPanda_Newsletters' );
	}
	if ( ! class_exists( 'ContactPanda_Newsletter_Campaigns', false ) ) {
		class_alias( 'ContactPanda_Basic_Newsletter_Campaigns', 'ContactPanda_Newsletter_Campaigns' );
	}
}
add_action( 'plugins_loaded', 'contactpanda_boot_newsletter_module', 45 );

function contactpanda_newsletter_admin_assets( $hook ) {
	if ( ! ContactPanda_License::newsletter_entitled() ) {
		return;
	}
	if ( in_array( $hook, array( 'contactpanda_page_contactpanda-campaigns', 'contactpanda_page_contactpanda-settings' ), true ) ) {
		wp_enqueue_style( 'contactpanda', CONTACTPANDA_URL . 'assets/contactpanda-admin.css', array(), CONTACTPANDA_ASSET_VERSION );
	}
	if ( 'contactpanda_page_contactpanda-campaigns' === $hook ) {
		wp_enqueue_style( 'contactpanda-newsletter-campaigns', CONTACTPANDA_URL . 'assets/contactpanda-newsletter-campaigns.css', array( 'contactpanda' ), CONTACTPANDA_ASSET_VERSION );
		wp_enqueue_script( 'contactpanda-campaigns', CONTACTPANDA_URL . 'assets/contactpanda-campaigns.js', array(), CONTACTPANDA_ASSET_VERSION, true );
		wp_localize_script( 'contactpanda-campaigns', 'CONTACTPANDA_CAMPAIGNS', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'contactpanda_newsletter_editor' ), 'noOptions' => __( 'No matching entries found.', 'contactpanda' ), 'noRecipients' => __( 'No eligible contacts found.', 'contactpanda' ), 'recipientSearchLoading' => __( 'Checking recipients…', 'contactpanda' ), 'recipientSearchSummary' => __( '%1$d contacts found; %2$d eligible; %3$d with documented marketing consent; %4$d without documented consent; %5$d permanently excluded.', 'contactpanda' ), 'manualRecipientSummary' => __( '%d people selected. Protected opt-outs and invalid addresses will still be excluded before sending.', 'contactpanda' ), 'recipientSearchTruncated' => __( 'Only the first 200 eligible matches are shown. Refine the search to see further contacts.', 'contactpanda' ), 'marketingConsent' => __( 'Marketing consent', 'contactpanda' ), 'withoutMarketingConsent' => __( 'No documented marketing consent', 'contactpanda' ), 'campaignCreating' => __( 'Creating campaign…', 'contactpanda' ), 'recipientSearchFailed' => __( 'The recipients could not be checked.', 'contactpanda' ), 'campaignCreateFailed' => __( 'The campaign could not be created.', 'contactpanda' ), 'previewFailed' => __( 'The preview could not be created.', 'contactpanda' ), 'testFailed' => __( 'The test email could not be sent.', 'contactpanda' ), 'sendNow' => __( 'Send now', 'contactpanda' ), 'scheduleDeliveryEmpty' => __( 'Schedule delivery', 'contactpanda' ), 'scheduleDelivery' => __( 'Schedule delivery for %1$s at %2$s', 'contactpanda' ), 'confirmSendNow' => __( 'Send this mailing to %d eligible recipients now?', 'contactpanda' ), 'confirmSchedule' => __( 'Schedule this mailing for %1$s? %2$d eligible recipients will be prepared.', 'contactpanda' ), 'overrideRequired' => __( 'Select a reason and confirm the administrative exception.', 'contactpanda' ), 'pollInterval' => 12000 ) );
	}
	if ( 'contactpanda_page_contactpanda-settings' === $hook ) {
		wp_enqueue_script( 'contactpanda-segments', CONTACTPANDA_URL . 'assets/contactpanda-segments.js', array(), CONTACTPANDA_ASSET_VERSION, true );
		wp_localize_script( 'contactpanda-segments', 'CONTACTPANDA_SEGMENTS', ContactPanda_Basic_Segments::editor_config() );
	}
	if ( in_array( $hook, array( 'contactpanda_page_contactpanda-people', 'contactpanda_page_contactpanda-campaigns' ), true ) ) {
		wp_enqueue_script( 'contactpanda-inline-search', CONTACTPANDA_URL . 'assets/contactpanda-inline-search.js', array(), CONTACTPANDA_ASSET_VERSION, true );
		wp_localize_script( 'contactpanda-inline-search', 'CONTACTPANDA_INLINE_SEARCH', ContactPanda_Basic_Segments::inline_config( 'contactpanda_page_contactpanda-campaigns' === $hook ) );
	}
}
add_action( 'admin_enqueue_scripts', 'contactpanda_newsletter_admin_assets', 20 );

register_activation_hook( __FILE__, array( 'ContactPanda_Registration', 'activate' ) );

function contactpanda_deactivate_license_schedule() {
	wp_clear_scheduled_hook( ContactPanda_License::CHECK_HOOK );
}
register_deactivation_hook( __FILE__, 'contactpanda_deactivate_license_schedule' );
