<?php
/**
 * Plugin Name: ContactPanda Basic – User-Friendly CRM for WordPress
 * Description: Free contact and company management for WordPress with a complete WooCommerce customer overview and optional Groundhogg compatibility.
 * Version: 0.3.6
 * Update URI: https://github.com/kultur3gmbh/contactpanda
 * Author: Kultur3 GmbH
 * Text Domain: contactpanda
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 6.4
 */

defined( 'ABSPATH' ) || exit;

define( 'CONTACTPANDA_VERSION', '0.3.6' );
define( 'CONTACTPANDA_ASSET_VERSION', '0.3.6-20261019-1' );
define( 'CONTACTPANDA_FILE', __FILE__ );
define( 'CONTACTPANDA_PATH', plugin_dir_path( __FILE__ ) );
define( 'CONTACTPANDA_URL', plugin_dir_url( __FILE__ ) );

require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-plugin.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-modules.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-schema.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-store.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-trash.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-data-tools.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-custom-fields.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-github-updater.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-registration.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-my-account.php';

add_action(
	'plugins_loaded',
	static function () {
		new ContactPanda_GitHub_Updater(
			array(
				'slug'       => 'contactpanda',
				'name'       => 'ContactPanda Basic',
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
add_action( 'plugins_loaded', array( 'ContactPanda_Trash', 'boot' ), 30 );
add_action( 'plugins_loaded', array( 'ContactPanda_Data_Tools', 'boot' ), 30 );
add_action( 'plugins_loaded', array( 'ContactPanda_Custom_Fields', 'boot' ), 30 );
add_action( 'plugins_loaded', array( 'ContactPanda_Registration', 'boot' ), 30 );
add_action( 'plugins_loaded', array( 'ContactPanda_My_Account', 'boot' ), 31 );

register_activation_hook( __FILE__, array( 'ContactPanda_Registration', 'activate' ) );
