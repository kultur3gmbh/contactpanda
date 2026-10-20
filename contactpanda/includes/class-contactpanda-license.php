<?php

defined( 'ABSPATH' ) || exit;

/**
 * Stores durable product entitlements separately from update eligibility.
 *
 * A successful activation grants the licensed feature permanently on this
 * installation. Expiry affects only updates and support; it never disables
 * newsletter creation, sending, data access, or exports.
 */
final class ContactPanda_License {
	const OPTION = 'contactpanda_license_state';
	const PRODUCT = 'contactpanda-basic-newsletter';

	public static function boot() {
		add_filter( 'contactpanda_settings_tabs', array( __CLASS__, 'settings_tab' ), 5 );
		add_action( 'contactpanda_render_settings_tab_license', array( __CLASS__, 'render_settings' ) );
		add_action( 'admin_post_contactpanda_activate_license', array( __CLASS__, 'activate' ) );
		add_action( 'admin_post_contactpanda_disconnect_license', array( __CLASS__, 'disconnect' ) );
		add_filter( 'contactpanda_updates_allowed', array( __CLASS__, 'updates_allowed' ) );
	}

	public static function settings_tab( $tabs ) {
		$tabs['license'] = __( 'License', 'contactpanda' );
		return $tabs;
	}

	public static function state() {
		return wp_parse_args(
			(array) get_option( self::OPTION, array() ),
			array(
				'license_key'           => '',
				'license_label'         => '',
				'newsletter_granted'    => 0,
				'activated_at'          => '',
				'last_checked_at'       => '',
				'updates_until'         => '',
				'support_until'         => '',
				'last_status'           => '',
			)
		);
	}

	/**
	 * The entitlement is intentionally sticky after its first valid grant.
	 */
	public static function newsletter_entitled() {
		$state = self::state();
		$granted = ! empty( $state['newsletter_granted'] );
		return (bool) apply_filters( 'contactpanda_newsletter_entitled', $granted, $state );
	}

	public static function updates_allowed( $allowed ) {
		if ( ! $allowed ) {
			return false;
		}
		$state = self::state();
		if ( empty( $state['newsletter_granted'] ) || empty( $state['updates_until'] ) ) {
			return true;
		}
		$expiry = strtotime( $state['updates_until'] . ' 23:59:59 UTC' );
		return ! $expiry || $expiry >= time();
	}

	private static function capability() {
		return 'manage_options';
	}

	private static function endpoint() {
		$default = defined( 'CONTACTPANDA_LICENSE_ENDPOINT' ) ? CONTACTPANDA_LICENSE_ENDPOINT : 'https://mycontactpanda.com/wp-json/contactpanda-license/v1/activate';
		return esc_url_raw( apply_filters( 'contactpanda_license_endpoint', $default ) );
	}

	private static function status_message( $code ) {
		$messages = array(
			'activated' => __( 'The newsletter license has been activated. Newsletter functionality remains available permanently on this installation.', 'contactpanda' ),
			'disconnected' => __( 'The license connection has been removed. Features that were already granted remain available.', 'contactpanda' ),
			'invalid' => __( 'The license could not be activated. Please check the license key and try again.', 'contactpanda' ),
			'network' => __( 'The license service could not be reached. No existing feature or license information was changed.', 'contactpanda' ),
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
			$class = in_array( $notice, array( 'activated', 'disconnected' ), true ) ? 'notice-success' : 'notice-error';
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( self::status_message( $notice ) ) . '</p></div>';
		}

		$entitled = self::newsletter_entitled();
		$updates  = apply_filters( 'contactpanda_updates_allowed', true );
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'ContactPanda license', 'contactpanda' ) . '</h2>';
		echo '<p>' . esc_html__( 'A Newsletter license unlocks the complete newsletter module in ContactPanda Basic. Once activated, the module remains fully usable even after the update and support period has ended.', 'contactpanda' ) . '</p>';
		echo '<div class="contactpanda-license-status"><p><strong>' . esc_html__( 'Newsletter module', 'contactpanda' ) . ':</strong> ' . esc_html( $entitled ? __( 'Permanently enabled', 'contactpanda' ) : __( 'Not enabled', 'contactpanda' ) ) . '</p>';
		if ( $entitled ) {
			echo '<p><strong>' . esc_html__( 'Updates and support', 'contactpanda' ) . ':</strong> ' . esc_html( $updates ? __( 'Available', 'contactpanda' ) : __( 'Expired — the newsletter module remains fully functional', 'contactpanda' ) ) . '</p>';
		}
		if ( $state['license_label'] ) {
			echo '<p><strong>' . esc_html__( 'License', 'contactpanda' ) . ':</strong> ' . esc_html( $state['license_label'] ) . '</p>';
		}
		if ( $state['updates_until'] ) {
			echo '<p><strong>' . esc_html__( 'Updates until', 'contactpanda' ) . ':</strong> ' . esc_html( mysql2date( get_option( 'date_format' ), $state['updates_until'] . ' 00:00:00' ) ) . '</p>';
		}
		echo '</div>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-settings-grid"><input type="hidden" name="action" value="contactpanda_activate_license">';
		wp_nonce_field( 'contactpanda_activate_license' );
		echo '<label><span>' . esc_html__( 'License key', 'contactpanda' ) . '</span><input type="password" name="license_key" autocomplete="off" required value="" placeholder="CP-…"></label><p><button class="button button-primary">' . esc_html__( 'Activate or renew license', 'contactpanda' ) . '</button></p></form>';
		if ( $state['license_key'] ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_disconnect_license">';
			wp_nonce_field( 'contactpanda_disconnect_license' );
			echo '<button class="button">' . esc_html__( 'Disconnect license key', 'contactpanda' ) . '</button><p class="description">' . esc_html__( 'Disconnecting removes the stored key but does not disable previously granted newsletter functionality.', 'contactpanda' ) . '</p></form>';
		}
		echo '</section>';
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

		$payload = array(
			'product'      => self::PRODUCT,
			'license_key'  => $key,
			'site_url'     => home_url( '/' ),
			'network_url'  => network_home_url( '/' ),
			'version'      => CONTACTPANDA_VERSION,
			'locale'       => get_locale(),
		);
		$response = wp_safe_remote_post(
			self::endpoint(),
			array(
				'timeout'     => 20,
				'redirection' => 0,
				'sslverify'   => true,
				'headers'     => array( 'Accept' => 'application/json' ),
				'body'        => $payload,
			)
		);
		$response = apply_filters( 'contactpanda_license_activation_response', $response, $payload );
		if ( is_wp_error( $response ) ) {
			self::redirect( 'network' );
		}
		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$entitlements = is_array( $data['entitlements'] ?? null ) ? array_map( 'sanitize_key', $data['entitlements'] ) : array();
		if ( 200 !== $code || ! is_array( $data ) || empty( $data['valid'] ) || ! in_array( 'newsletter', $entitlements, true ) ) {
			self::redirect( 'invalid' );
		}

		$state = self::state();
		$state['license_key']        = $key;
		$state['license_label']      = sanitize_text_field( $data['license_label'] ?? self::masked_key( $key ) );
		$state['newsletter_granted'] = 1;
		$state['activated_at']       = $state['activated_at'] ?: current_time( 'mysql', true );
		$state['last_checked_at']    = current_time( 'mysql', true );
		$state['updates_until']      = self::date_value( $data['updates_until'] ?? '' );
		$state['support_until']      = self::date_value( $data['support_until'] ?? '' );
		$state['last_status']        = 'valid';
		update_option( self::OPTION, $state, false );
		delete_site_transient( 'contactpanda_github_release' );
		self::redirect( 'activated' );
	}

	public static function disconnect() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'contactpanda_disconnect_license' );
		$state = self::state();
		$state['license_key'] = '';
		$state['last_status'] = 'disconnected';
		update_option( self::OPTION, $state, false );
		self::redirect( 'disconnected' );
	}

	private static function date_value( $value ) {
		$value = sanitize_text_field( (string) $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) ? $value : '';
	}

	private static function masked_key( $key ) {
		return strlen( $key ) > 8 ? substr( $key, 0, 4 ) . '…' . substr( $key, -4 ) : '••••••••';
	}

	private static function redirect( $notice ) {
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=license&license_notice=' . sanitize_key( $notice ) ) );
		exit;
	}
}
