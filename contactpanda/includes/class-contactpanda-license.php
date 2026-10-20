<?php

defined( 'ABSPATH' ) || exit;

/**
 * Provider-neutral ContactPanda license client.
 *
 * Licenses may grant one or more durable module entitlements. A normal expiry
 * ends updates and support but never disables an already granted module.
 * Explicitly blocked licenses are excluded from the effective entitlements.
 */
final class ContactPanda_License {
	const OPTION           = 'contactpanda_license_state';
	const INSTALLATION     = 'contactpanda_installation_id';
	const PRODUCT          = 'contactpanda-suite';
	const CHECK_HOOK       = 'contactpanda_license_daily_check';
	const RESPONSE_VERSION = 1;

	public static function boot() {
		add_filter( 'contactpanda_general_settings_sections', array( __CLASS__, 'settings_section' ), 20 );
		add_action( 'contactpanda_render_general_settings_section_license', array( __CLASS__, 'render_settings' ) );
		add_action( 'admin_post_contactpanda_activate_license', array( __CLASS__, 'activate' ) );
		add_action( 'admin_post_contactpanda_disconnect_license', array( __CLASS__, 'disconnect' ) );
		add_action( 'admin_post_contactpanda_check_licenses', array( __CLASS__, 'manual_check' ) );
		add_action( self::CHECK_HOOK, array( __CLASS__, 'scheduled_check' ) );
		if ( ! wp_next_scheduled( self::CHECK_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CHECK_HOOK );
		}
	}

	public static function settings_section( $sections ) {
		$sections['license'] = __( 'Licenses', 'contactpanda' );
		return $sections;
	}

	private static function defaults() {
		return array(
			'version'         => 2,
			'licenses'        => array(),
			'legacy_grants'   => array(),
			'last_checked_at' => '',
		);
	}

	/**
	 * Read version-two state and transparently retain grants made by 0.6.0-rc.
	 */
	public static function state() {
		$raw = (array) get_option( self::OPTION, array() );
		if ( isset( $raw['licenses'] ) ) {
			$state = wp_parse_args( $raw, self::defaults() );
		} else {
			$grants = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_entitlement' ), (array) ( $raw['granted_entitlements'] ?? array() ) ) ) ) );
			if ( ! empty( $raw['newsletter_granted'] ) && ! in_array( 'newsletter', $grants, true ) ) {
				$grants[] = 'newsletter';
			}
			if ( ! empty( $raw['master_granted'] ) && ! in_array( 'all', $grants, true ) ) {
				$grants[] = 'all';
			}
			$state = self::defaults();
			$state['legacy_grants'] = $grants;
			if ( ! empty( $raw['license_key'] ) ) {
				$id = 'legacy-' . substr( hash( 'sha256', (string) $raw['license_key'] ), 0, 16 );
				$state['licenses'][ $id ] = array(
					'activation_id'    => '',
					'activation_token' => '',
					'license_label'    => sanitize_text_field( $raw['license_label'] ?? self::masked_key( $raw['license_key'] ) ),
					'license_type'     => sanitize_key( $raw['license_type'] ?? 'customer' ),
					'entitlements'     => $grants,
					'limits'           => array(),
					'updates_until'    => self::date_value( $raw['updates_until'] ?? '' ),
					'support_until'    => self::date_value( $raw['support_until'] ?? '' ),
					'activated_at'     => sanitize_text_field( $raw['activated_at'] ?? '' ),
					'last_checked_at'  => sanitize_text_field( $raw['last_checked_at'] ?? '' ),
					'status'           => sanitize_key( $raw['last_status'] ?? 'disconnected' ),
				);
			}
		}

		$state['licenses'] = is_array( $state['licenses'] ) ? $state['licenses'] : array();
		foreach ( $state['licenses'] as $id => $license ) {
			if ( ! is_array( $license ) ) {
				unset( $state['licenses'][ $id ] );
				continue;
			}
			$state['licenses'][ $id ] = self::normalize_license( $license );
		}
		$state['legacy_grants'] = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_entitlement' ), (array) $state['legacy_grants'] ) ) ) );
		return $state;
	}

	private static function normalize_license( $license ) {
		$license = wp_parse_args(
			(array) $license,
			array(
				'activation_id'    => '',
				'activation_token' => '',
				'license_label'    => '',
				'license_type'     => 'customer',
				'entitlements'     => array(),
				'limits'           => array(),
				'updates_until'    => '',
				'support_until'    => '',
				'activated_at'     => '',
				'last_checked_at'  => '',
				'status'           => 'valid',
			)
		);
		$license['activation_id']    = sanitize_text_field( $license['activation_id'] );
		$license['activation_token'] = sanitize_text_field( $license['activation_token'] );
		$license['license_label']    = sanitize_text_field( $license['license_label'] );
		$license['license_type']     = sanitize_key( $license['license_type'] );
		$license['entitlements']     = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_entitlement' ), (array) $license['entitlements'] ) ) ) );
		$license['limits']           = is_array( $license['limits'] ) ? array_map( 'sanitize_text_field', $license['limits'] ) : array();
		$license['updates_until']    = self::date_value( $license['updates_until'] );
		$license['support_until']    = self::date_value( $license['support_until'] );
		$license['status']           = sanitize_key( $license['status'] );
		return $license;
	}

	private static function normalize_entitlement( $item ) {
		return '*' === trim( (string) $item ) ? 'all' : sanitize_key( $item );
	}

	public static function installation_id() {
		$id = (string) get_option( self::INSTALLATION, '' );
		if ( ! preg_match( '/^[a-f0-9-]{36}$/D', $id ) ) {
			$id = wp_generate_uuid4();
			update_option( self::INSTALLATION, $id, false );
		}
		return $id;
	}

	/**
	 * Normal expiry is deliberately ignored here. Only an explicit block revokes.
	 */
	public static function entitled( $module ) {
		$module = sanitize_key( $module );
		$state  = self::state();
		$grants = $state['legacy_grants'];
		foreach ( $state['licenses'] as $license ) {
			if ( 'blocked' === $license['status'] ) {
				continue;
			}
			$grants = array_merge( $grants, $license['entitlements'] );
		}
		$granted = in_array( 'all', $grants, true ) || in_array( $module, $grants, true );
		return (bool) apply_filters( 'contactpanda_license_entitled', $granted, $module, $state );
	}

	public static function newsletter_entitled() {
		$state   = self::state();
		$granted = self::entitled( 'newsletter' );
		return (bool) apply_filters( 'contactpanda_newsletter_entitled', $granted, $state );
	}

	public static function updates_allowed_for( $module ) {
		$module = sanitize_key( $module );
		$state  = self::state();
		foreach ( $state['licenses'] as $license ) {
			if ( in_array( $license['status'], array( 'blocked', 'disconnected' ), true ) || ( ! in_array( 'all', $license['entitlements'], true ) && ! in_array( $module, $license['entitlements'], true ) ) ) {
				continue;
			}
			if ( 'expired' !== $license['status'] && ( empty( $license['updates_until'] ) || strtotime( $license['updates_until'] . ' 23:59:59 UTC' ) >= time() ) ) {
				return true;
			}
		}
		return false;
	}

	private static function capability() {
		return 'manage_options';
	}

	private static function endpoint( $action = 'activate' ) {
		$base = defined( 'CONTACTPANDA_LICENSE_ENDPOINT' ) ? CONTACTPANDA_LICENSE_ENDPOINT : 'https://mycontactpanda.com/wp-json/contactpanda-license/v1/activate';
		$base = preg_replace( '#/(?:activate|check|deactivate|updates/check)/?$#', '', (string) $base );
		$base = trailingslashit( $base ) . implode( '/', array_map( 'sanitize_key', explode( '/', trim( (string) $action, '/' ) ) ) );
		return esc_url_raw( apply_filters( 'contactpanda_license_endpoint', $base, $action ) );
	}

	/**
	 * Request signed update metadata for a licensed product.
	 *
	 * The activation token is transmitted only in the HTTPS request body. It is
	 * never placed in a package URL or persisted by the updater cache.
	 */
	public static function product_update( $product, $entitlement, $installed_version, $channel = 'stable' ) {
		$product          = sanitize_key( $product );
		$entitlement      = sanitize_key( $entitlement );
		$channel          = 'beta' === sanitize_key( $channel ) ? 'beta' : 'stable';
		$installed_version = sanitize_text_field( $installed_version );
		$state            = self::state();
		$last_error       = new WP_Error( 'contactpanda_update_not_licensed' );

		foreach ( $state['licenses'] as $license ) {
			if ( empty( $license['activation_id'] ) || empty( $license['activation_token'] ) || in_array( $license['status'], array( 'blocked', 'disconnected', 'expired' ), true ) ) {
				continue;
			}
			if ( ! in_array( 'all', $license['entitlements'], true ) && ! in_array( $entitlement, $license['entitlements'], true ) ) {
				continue;
			}
			if ( $license['updates_until'] && strtotime( $license['updates_until'] . ' 23:59:59 UTC' ) < time() ) {
				continue;
			}
			$data = self::request(
				'updates/check',
				array(
					'activation_id'    => $license['activation_id'],
					'activation_token' => $license['activation_token'],
					'update_product'   => $product,
					'installed_version'=> $installed_version,
					'channel'          => $channel,
				)
			);
			if ( is_wp_error( $data ) ) {
				$last_error = $data;
				continue;
			}
			return $data;
		}
		return $last_error;
	}

	private static function status_message( $code ) {
		$messages = array(
			'activated'    => __( 'The ContactPanda license has been activated. Granted modules remain available after the update and support period ends.', 'contactpanda' ),
			'disconnected' => __( 'The website was disconnected from the license server. Previously granted modules remain available.', 'contactpanda' ),
			'checked'      => __( 'The license information was updated.', 'contactpanda' ),
			'invalid'      => __( 'The license could not be activated. Please check the license key and try again.', 'contactpanda' ),
			'network'      => __( 'The license service could not be reached. No existing feature or license information was changed.', 'contactpanda' ),
			'signature'    => __( 'The license response could not be verified. No existing license information was changed.', 'contactpanda' ),
		);
		return $messages[ $code ] ?? '';
	}

	public static function render_settings() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}
		$state  = self::state();
		$notice = sanitize_key( wp_unslash( $_GET['license_notice'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only result code.
		if ( $notice && self::status_message( $notice ) ) {
			$class = in_array( $notice, array( 'activated', 'disconnected', 'checked' ), true ) ? 'notice-success' : 'notice-error';
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( self::status_message( $notice ) ) . '</p></div>';
		}

		$catalog = apply_filters( 'contactpanda_license_modules', array( 'newsletter' => __( 'Newsletter', 'contactpanda' ) ) );
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'ContactPanda licenses', 'contactpanda' ) . '</h2>';
		echo '<p>' . esc_html__( 'ContactPanda remains free and updateable without a license. Additional licenses can unlock Newsletter, Plus, or selected modules. A normal expiry ends updates and support, but granted modules remain usable.', 'contactpanda' ) . '</p>';
		echo '<div class="contactpanda-license-status"><p><strong>' . esc_html__( 'ContactPanda license', 'contactpanda' ) . ':</strong> ' . esc_html__( 'Free license active', 'contactpanda' ) . '</p></div>';

		if ( $state['licenses'] ) {
			echo '<h3>' . esc_html__( 'Activated licenses', 'contactpanda' ) . '</h3>';
			foreach ( $state['licenses'] as $id => $license ) {
				$labels = array();
				foreach ( $license['entitlements'] as $module ) {
					$labels[] = $catalog[ $module ] ?? ( 'all' === $module ? __( 'All modules', 'contactpanda' ) : $module );
				}
				echo '<div class="contactpanda-card contactpanda-license-entry">';
				echo '<h4>' . esc_html( $license['license_label'] ?: __( 'ContactPanda license', 'contactpanda' ) ) . '</h4>';
				echo '<p><strong>' . esc_html__( 'Type', 'contactpanda' ) . ':</strong> ' . esc_html( self::type_label( $license['license_type'] ) ) . '<br>';
				echo '<strong>' . esc_html__( 'Modules', 'contactpanda' ) . ':</strong> ' . esc_html( $labels ? implode( ', ', $labels ) : __( 'No modules assigned', 'contactpanda' ) ) . '<br>';
				echo '<strong>' . esc_html__( 'Status', 'contactpanda' ) . ':</strong> ' . esc_html( self::status_label( $license['status'] ) );
				if ( $license['updates_until'] ) {
					echo '<br><strong>' . esc_html__( 'Updates until', 'contactpanda' ) . ':</strong> ' . esc_html( mysql2date( get_option( 'date_format' ), $license['updates_until'] . ' 00:00:00' ) );
				}
				if ( $license['last_checked_at'] ) {
					echo '<br><strong>' . esc_html__( 'Last checked', 'contactpanda' ) . ':</strong> ' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $license['last_checked_at'] ) );
				}
				echo '</p>';
				if ( $license['activation_id'] && $license['activation_token'] ) {
					echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_disconnect_license"><input type="hidden" name="license_id" value="' . esc_attr( $id ) . '">';
					wp_nonce_field( 'contactpanda_disconnect_license_' . $id );
					echo '<button class="button">' . esc_html__( 'Disconnect this website', 'contactpanda' ) . '</button></form>';
				}
				echo '</div>';
			}
		}

		echo '<h3>' . esc_html__( 'Module status', 'contactpanda' ) . '</h3><div class="contactpanda-license-modules">';
		foreach ( $catalog as $module => $label ) {
			echo '<p><strong>' . esc_html( $label ) . '</strong>: ' . esc_html( self::entitled( $module ) ? __( 'Enabled', 'contactpanda' ) : __( 'Not enabled', 'contactpanda' ) ) . '</p>';
		}
		echo '</div>';

		echo '<h3>' . esc_html__( 'Add or renew a license', 'contactpanda' ) . '</h3><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-settings-grid"><input type="hidden" name="action" value="contactpanda_activate_license">';
		wp_nonce_field( 'contactpanda_activate_license' );
		echo '<label><span>' . esc_html__( 'License key', 'contactpanda' ) . '</span><input type="password" name="license_key" autocomplete="off" required value="" placeholder="CP-…"></label><p><button class="button button-primary">' . esc_html__( 'Activate or renew license', 'contactpanda' ) . '</button></p></form>';
		if ( array_filter( wp_list_pluck( $state['licenses'], 'activation_token' ) ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_check_licenses">';
			wp_nonce_field( 'contactpanda_check_licenses' );
			echo '<button class="button">' . esc_html__( 'Check licenses now', 'contactpanda' ) . '</button></form>';
		}
		echo '<p class="description">' . esc_html__( 'Only technical license and installation data is sent to mycontactpanda.com. Contacts, orders, messages, and newsletter content are never transmitted.', 'contactpanda' ) . '</p></section>';
	}

	private static function type_label( $type ) {
		$labels = array(
			'master'   => __( 'Master license', 'contactpanda' ),
			'customer' => __( 'Customer license', 'contactpanda' ),
			'partner'  => __( 'Partner license', 'contactpanda' ),
		);
		return $labels[ $type ] ?? __( 'Module license', 'contactpanda' );
	}

	private static function status_label( $status ) {
		$labels = array(
			'valid'        => __( 'Active', 'contactpanda' ),
			'expired'      => __( 'Updates expired — modules remain usable', 'contactpanda' ),
			'blocked'      => __( 'Blocked', 'contactpanda' ),
			'disconnected' => __( 'Disconnected', 'contactpanda' ),
		);
		return $labels[ $status ] ?? __( 'Unknown', 'contactpanda' );
	}

	private static function telemetry() {
		return array(
			'api_version'       => self::RESPONSE_VERSION,
			'product'           => self::PRODUCT,
			'installation_id'   => self::installation_id(),
			'site_url'          => home_url( '/' ),
			'network_url'       => network_home_url( '/' ),
			'site_name'         => get_bloginfo( 'name' ),
			'locale'            => get_locale(),
			'wordpress_version' => get_bloginfo( 'version' ),
			'php_version'       => PHP_VERSION,
			'basic_version'     => defined( 'CONTACTPANDA_VERSION' ) ? CONTACTPANDA_VERSION : '',
			'plus_version'      => defined( 'CONTACTPANDA_PLUS_VERSION' ) ? CONTACTPANDA_PLUS_VERSION : '',
		);
	}

	private static function request( $action, $payload ) {
		$body = array_merge( self::telemetry(), $payload );
		$response = wp_safe_remote_post(
			self::endpoint( $action ),
			array(
				'timeout'     => 20,
				'redirection' => 0,
				'sslverify'   => true,
				'headers'     => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json; charset=utf-8',
				),
				'body'        => wp_json_encode( $body ),
			)
		);
		$response = apply_filters( 'contactpanda_license_response', $response, $action, $body );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 500 || ! is_array( $data ) ) {
			return new WP_Error( 'contactpanda_license_invalid' );
		}
		return self::verified_data( $data );
	}

	/**
	 * Verify Ed25519 payloads when the deployment public key is configured.
	 * TLS remains mandatory. CONTACTPANDA_LICENSE_REQUIRE_SIGNATURE can enforce
	 * signatures after mycontactpanda.com has been provisioned.
	 */
	private static function verified_data( $data ) {
		$public_key = defined( 'CONTACTPANDA_LICENSE_PUBLIC_KEY' ) ? trim( (string) CONTACTPANDA_LICENSE_PUBLIC_KEY ) : '';
		$required   = defined( 'CONTACTPANDA_LICENSE_REQUIRE_SIGNATURE' ) && CONTACTPANDA_LICENSE_REQUIRE_SIGNATURE;
		if ( empty( $data['signature'] ) ) {
			return $required ? new WP_Error( 'contactpanda_license_signature' ) : $data;
		}
		if ( '' === $public_key ) {
			return $required ? new WP_Error( 'contactpanda_license_signature' ) : $data;
		}

		$signature = base64_decode( (string) $data['signature'], true );
		$key       = base64_decode( $public_key, true );
		$algorithm = sanitize_key( $data['signature_algorithm'] ?? 'ed25519' );
		$payload   = $data;
		unset( $payload['signature'], $payload['signature_algorithm'], $payload['signature_key_id'] );
		$canonical = self::canonical_json( $payload );
		$valid     = false;
		if ( 'ed25519' === $algorithm && function_exists( 'sodium_crypto_sign_verify_detached' ) && false !== $signature && false !== $key ) {
			$valid = sodium_crypto_sign_verify_detached( $signature, $canonical, $key );
		} elseif ( 'rsa-sha256' === $algorithm && function_exists( 'openssl_verify' ) && defined( 'OPENSSL_ALGO_SHA256' ) && false !== $signature && false !== $key ) {
			$valid = 1 === openssl_verify( $canonical, $signature, $key, OPENSSL_ALGO_SHA256 );
		}
		return $valid ? $payload : new WP_Error( 'contactpanda_license_signature' );
	}

	private static function canonical_json( $value ) {
		$value = self::normalize_signature_value( $value );
		return (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	private static function normalize_signature_value( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$keys    = array_keys( $value );
		$is_list = empty( $value ) || $keys === range( 0, count( $value ) - 1 );
		if ( ! $is_list ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::normalize_signature_value( $item );
		}
		return $value;
	}

	public static function activate() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'contactpanda_activate_license' );
		$key = sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) );
		if ( '' === $key || strlen( $key ) > 190 ) {
			self::redirect( 'invalid' );
		}
		$data = self::request( 'activate', array( 'license_key' => $key ) );
		if ( is_wp_error( $data ) ) {
			self::redirect( 'contactpanda_license_signature' === $data->get_error_code() ? 'signature' : 'network' );
		}
		if ( empty( $data['valid'] ) || empty( $data['activation_id'] ) || empty( $data['activation_token'] ) ) {
			self::redirect( 'invalid' );
		}
		$license = self::license_from_response( $data );
		if ( empty( $license['entitlements'] ) ) {
			self::redirect( 'invalid' );
		}
		$state = self::state();
		$id    = sanitize_text_field( $data['license_id'] ?? $data['activation_id'] );
		$state['licenses'][ $id ] = $license;
		$state['last_checked_at']  = current_time( 'mysql', true );
		update_option( self::OPTION, $state, false );
		delete_site_transient( 'contactpanda_github_release' );
		self::redirect( 'activated' );
	}

	private static function license_from_response( $data, $existing = array() ) {
		$existing     = self::normalize_license( $existing );
		$entitlements = is_array( $data['entitlements'] ?? null ) ? array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_entitlement' ), $data['entitlements'] ) ) ) ) : $existing['entitlements'];
		$status       = sanitize_key( $data['status'] ?? 'valid' );
		$status       = 'active' === $status ? 'valid' : ( 'deactivated' === $status ? 'disconnected' : $status );
		return self::normalize_license(
			array(
				'activation_id'    => sanitize_text_field( $data['activation_id'] ?? $existing['activation_id'] ),
				'activation_token' => sanitize_text_field( $data['activation_token'] ?? $existing['activation_token'] ),
				'license_label'    => sanitize_text_field( $data['license_label'] ?? $existing['license_label'] ),
				'license_type'     => sanitize_key( $data['license_type'] ?? $existing['license_type'] ),
				'entitlements'     => $entitlements,
				'limits'           => is_array( $data['limits'] ?? null ) ? $data['limits'] : $existing['limits'],
				'updates_until'    => self::date_value( $data['updates_until'] ?? $existing['updates_until'] ),
				'support_until'    => self::date_value( $data['support_until'] ?? $existing['support_until'] ),
				'activated_at'     => $existing['activated_at'] ?: current_time( 'mysql', true ),
				'last_checked_at'  => current_time( 'mysql', true ),
				'status'           => $status,
			)
		);
	}

	public static function manual_check() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'contactpanda_check_licenses' );
		$result = self::check_all();
		self::redirect( is_wp_error( $result ) ? 'network' : 'checked' );
	}

	public static function scheduled_check() {
		self::check_all();
	}

	private static function check_all() {
		$state       = self::state();
		$had_success = false;
		foreach ( $state['licenses'] as $id => $license ) {
			if ( empty( $license['activation_id'] ) || empty( $license['activation_token'] ) || 'disconnected' === $license['status'] ) {
				continue;
			}
			$data = self::request(
				'check',
				array(
					'activation_id'    => $license['activation_id'],
					'activation_token' => $license['activation_token'],
				)
			);
			if ( is_wp_error( $data ) || ! isset( $data['valid'] ) ) {
				continue;
			}
			if ( ! empty( $data['valid'] ) || 'blocked' === ( $data['status'] ?? '' ) ) {
				$state['licenses'][ $id ] = self::license_from_response( $data, $license );
				$had_success = true;
			}
		}
		if ( $had_success ) {
			$state['last_checked_at'] = current_time( 'mysql', true );
			update_option( self::OPTION, $state, false );
			return true;
		}
		return new WP_Error( 'contactpanda_license_network' );
	}

	public static function disconnect() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ), '', array( 'response' => 403 ) );
		}
		$id = sanitize_text_field( wp_unslash( $_POST['license_id'] ?? '' ) );
		check_admin_referer( 'contactpanda_disconnect_license_' . $id );
		$state = self::state();
		if ( empty( $state['licenses'][ $id ] ) ) {
			self::redirect( 'invalid' );
		}
		$license = $state['licenses'][ $id ];
		if ( $license['activation_id'] && $license['activation_token'] ) {
			self::request(
				'deactivate',
				array(
					'activation_id'    => $license['activation_id'],
					'activation_token' => $license['activation_token'],
				)
			);
		}
		$state['licenses'][ $id ]['activation_token'] = '';
		$state['licenses'][ $id ]['status']           = 'disconnected';
		update_option( self::OPTION, $state, false );
		self::redirect( 'disconnected' );
	}

	private static function date_value( $value ) {
		$value = sanitize_text_field( (string) $value );
		$value = substr( $value, 0, 10 );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) ? $value : '';
	}

	private static function masked_key( $key ) {
		return strlen( $key ) > 8 ? substr( $key, 0, 4 ) . '…' . substr( $key, -4 ) : '••••••••';
	}

	private static function redirect( $notice ) {
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=general&general_section=license&license_notice=' . sanitize_key( $notice ) ) );
		exit;
	}
}
