<?php

defined( 'ABSPATH' ) || exit;

/** Shared updater for privately distributed ContactPanda extensions. */
final class ContactPanda_Licensed_Updater {
	private $config;
	private $metadata = null;

	public function __construct( array $config ) {
		$this->config = wp_parse_args(
			$config,
			array(
				'product'     => '',
				'entitlement' => '',
				'plugin'      => '',
				'slug'        => '',
				'name'        => '',
				'version'     => '',
				'channel'     => 'stable',
			)
		);
		if ( ! $this->config['product'] || ! $this->config['entitlement'] || ! $this->config['plugin'] ) {
			return;
		}
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'updates' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'verify_download' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_update_cache' ), 10, 2 );
		add_action( 'contactpanda_license_state_refreshed', array( $this, 'clear_metadata_cache' ) );
		add_action( 'admin_init', array( $this, 'maybe_clear_forced_check_cache' ), 1 );
	}

	public function updates( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}
		$plugin = $this->config['plugin'];
		unset( $transient->response[ $plugin ], $transient->no_update[ $plugin ] );
		$data = $this->metadata();
		$installed = ! empty( $transient->checked[ $plugin ] ) ? sanitize_text_field( $transient->checked[ $plugin ] ) : $this->config['version'];
		if ( is_wp_error( $data ) || empty( $data['available'] ) || empty( $data['version'] ) || version_compare( $data['version'], $installed, '<=' ) ) {
			if ( is_array( $data ) && ! empty( $data['version'] ) ) {
				$transient->no_update[ $plugin ] = $this->item( $data, $installed, '' );
			}
			return $transient;
		}
		$item = $this->item( $data, sanitize_text_field( $data['version'] ), esc_url_raw( $data['package'] ?? '' ) );
		if ( $item->package ) {
			$transient->response[ $plugin ] = $item;
		}
		return $transient;
	}

	private function item( array $data, $version, $package ) {
		return (object) array(
			'id'           => 'contactpanda-' . $this->config['product'],
			'slug'         => $this->config['slug'],
			'plugin'       => $this->config['plugin'],
			'new_version'  => sanitize_text_field( $version ),
			'url'          => esc_url_raw( $data['homepage'] ?? 'https://mycontactpanda.com/' ),
			'package'      => $package,
			'requires'     => sanitize_text_field( $data['requires'] ?? '' ),
			'requires_php' => sanitize_text_field( $data['requires_php'] ?? '' ),
			'tested'       => sanitize_text_field( $data['tested'] ?? '' ),
		);
	}

	public function clear_update_cache( $upgrader, $options ) {
		if ( 'plugin' !== ( $options['type'] ?? '' ) ) return;
		$plugins = isset( $options['plugins'] ) ? (array) $options['plugins'] : array( $options['plugin'] ?? '' );
		if ( ! in_array( $this->config['plugin'], $plugins, true ) ) return;
		$this->clear_metadata_cache();
	}

	/** Remove both the product response and WordPress' combined update result. */
	public function clear_metadata_cache() {
		delete_site_transient( $this->cache_key() );
		delete_site_transient( 'update_plugins' );
		$this->metadata = null;
	}

	/** Honour WordPress' "Check again" action for protected ContactPanda products. */
	public function maybe_clear_forced_check_cache() {
		if ( ! current_user_can( 'update_plugins' ) || empty( $_GET['force-check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only core update refresh flag.
			return;
		}
		$this->clear_metadata_cache();
	}

	public function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->config['slug'] ) {
			return $result;
		}
		$data = $this->metadata();
		if ( is_wp_error( $data ) || empty( $data['available'] ) ) {
			return $result;
		}
		return (object) array(
			'name'          => $this->config['name'],
			'slug'          => $this->config['slug'],
			'version'       => sanitize_text_field( $data['version'] ?? '' ),
			'requires'      => sanitize_text_field( $data['requires'] ?? '' ),
			'requires_php'  => sanitize_text_field( $data['requires_php'] ?? '' ),
			'tested'        => sanitize_text_field( $data['tested'] ?? '' ),
			'download_link' => esc_url_raw( $data['package'] ?? '' ),
			'sections'      => array( 'changelog' => wp_kses_post( $data['changelog'] ?? '' ) ),
		);
	}

	public function verify_download( $reply, $package, $upgrader, $hook_extra ) {
		if ( ! $this->handles_upgrade( $hook_extra ) ) {
			return $reply;
		}
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}

		// Update transients can outlive the signed package URL. Always request a
		// fresh URL at the last possible moment instead of trusting $package.
		$data = $this->metadata( true );
		if ( is_wp_error( $data ) ) {
			return new WP_Error( 'contactpanda_update_metadata', __( 'ContactPanda could not refresh the secure update link. Please try the update again.', 'contactpanda' ) );
		}
		if ( empty( $data['available'] ) || empty( $data['package'] ) || empty( $data['sha256'] ) ) {
			return new WP_Error( 'contactpanda_update_unavailable', __( 'The secure ContactPanda update package is not available for this installation.', 'contactpanda' ) );
		}
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$file = download_url( esc_url_raw( $data['package'] ), 300 );
		if ( is_wp_error( $file ) ) {
			return new WP_Error( 'contactpanda_update_download', __( 'The secure ContactPanda update package could not be downloaded. Please try again.', 'contactpanda' ) );
		}
		if ( ! hash_equals( strtolower( $data['sha256'] ), hash_file( 'sha256', $file ) ) ) {
			wp_delete_file( $file );
			return new WP_Error( 'contactpanda_update_checksum', __( 'The ContactPanda update package could not be verified.', 'contactpanda' ) );
		}
		return $file;
	}

	private function handles_upgrade( $hook_extra ) {
		$plugins = array();
		if ( ! empty( $hook_extra['plugin'] ) ) {
			$plugins[] = $hook_extra['plugin'];
		}
		if ( ! empty( $hook_extra['plugins'] ) ) {
			$plugins = array_merge( $plugins, (array) $hook_extra['plugins'] );
		}
		return in_array( $this->config['plugin'], array_map( 'plugin_basename', $plugins ), true );
	}

	private function metadata( $refresh = false ) {
		if ( $refresh ) {
			delete_site_transient( $this->cache_key() );
			$this->metadata = null;
		}
		if ( null !== $this->metadata ) {
			return $this->metadata;
		}
		$key    = $this->cache_key();
		$cached = get_site_transient( $key );
		if ( is_array( $cached ) ) {
			$this->metadata = $cached;
			return $cached;
		}
		$this->metadata = ContactPanda_License::product_update( $this->config['product'], $this->config['entitlement'], $this->config['version'], $this->config['channel'] );
		// Never retain a negative answer. A release may be published a moment later,
		// and WordPress' next update refresh must then reach the license server.
		if ( is_array( $this->metadata ) && ! empty( $this->metadata['available'] ) ) {
			set_site_transient( $key, $this->metadata, 10 * MINUTE_IN_SECONDS );
		}
		return $this->metadata;
	}

	private function cache_key() {
		return 'contactpanda_update_' . md5( $this->config['product'] . '|' . $this->config['channel'] . '|' . $this->config['version'] );
	}
}
