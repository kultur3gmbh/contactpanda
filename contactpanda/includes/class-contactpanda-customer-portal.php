<?php

defined( 'ABSPATH' ) || exit;

/** WooCommerce-independent customer profile and preference portal. */
final class ContactPanda_Customer_Portal {
	const SHORTCODE = 'contactpanda_customer_portal';
	const BLOCK = 'contactpanda/customer-portal';
	const EMAIL_META = 'contactpanda_pending_account_email';
	const NOTICE = 'contactpanda_portal_notice_';

	public static function boot() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ) );
		add_action( 'admin_post_contactpanda_portal_save_profile', array( __CLASS__, 'save_profile' ) );
		add_action( 'admin_post_contactpanda_portal_unsubscribe', array( __CLASS__, 'unsubscribe' ) );
		add_action( 'admin_post_nopriv_contactpanda_portal_unsubscribe', array( __CLASS__, 'unsubscribe' ) );
		add_action( 'admin_post_contactpanda_portal_privacy_request', array( __CLASS__, 'privacy_request' ) );
		add_action( 'admin_post_nopriv_contactpanda_portal_privacy_request', array( __CLASS__, 'privacy_request' ) );
		add_action( 'template_redirect', array( __CLASS__, 'confirm_email_change' ) );
		add_action( 'fusion_builder_before_init', array( __CLASS__, 'register_avada_element' ) );
	}

	public static function register_block() {
		wp_register_script(
			'contactpanda-portal-block',
			CONTACTPANDA_URL . 'assets/contactpanda-portal-block.js',
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
			CONTACTPANDA_ASSET_VERSION,
			true
		);
		register_block_type(
			self::BLOCK,
			array(
				'api_version'     => 2,
				'editor_script'   => 'contactpanda-portal-block',
				'render_callback' => array( __CLASS__, 'block' ),
				'attributes'      => array( 'defaultTab' => array( 'type' => 'string', 'default' => 'profile' ) ),
			)
		);
	}

	public static function register_avada_element() {
		if ( ! function_exists( 'fusion_builder_map' ) ) {
			return;
		}
		fusion_builder_map(
			array(
				'name'       => __( 'ContactPanda customer portal', 'contactpanda' ),
				'shortcode'  => self::SHORTCODE,
				'icon'       => 'fusiona-user',
				'params'     => array(),
			)
		);
	}

	public static function maybe_enqueue_assets() {
		if ( ! self::is_portal_page() ) {
			return;
		}
		wp_enqueue_style( 'contactpanda-portal', CONTACTPANDA_URL . 'assets/contactpanda-customer-portal.css', array(), CONTACTPANDA_ASSET_VERSION );
	}

	public static function shortcode( $atts = array() ) {
		$atts = shortcode_atts( array( 'tab' => '' ), $atts, self::SHORTCODE );
		return self::render( array( 'default_tab' => sanitize_key( $atts['tab'] ) ) );
	}

	public static function block( $attributes = array() ) {
		return self::render( array( 'default_tab' => sanitize_key( $attributes['defaultTab'] ?? '' ) ) );
	}

	public static function render( $context = array() ) {
		self::enqueue_assets();
		$preference = self::preference_context();
		if ( ! is_user_logged_in() && ! $preference ) {
			return '<div class="contactpanda-portal contactpanda-portal-login"><p>' . esc_html__( 'Please sign in to manage your data and preferences.', 'contactpanda' ) . '</p><p><a class="button" href="' . esc_url( wp_login_url( self::current_url() ) ) . '">' . esc_html__( 'Sign in', 'contactpanda' ) . '</a></p></div>';
		}

		if ( $preference ) {
			return self::render_preference_centre( $preference );
		}

		$identity = ContactPanda_Identity::resolve_user( get_current_user_id(), false );
		$tabs = self::tabs( $identity );
		$requested = isset( $_GET['cp_portal_tab'] ) ? sanitize_key( wp_unslash( $_GET['cp_portal_tab'] ) ) : sanitize_key( $context['default_tab'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display routing only.
		$active = isset( $tabs[ $requested ] ) ? $requested : ( isset( $tabs['profile'] ) ? 'profile' : array_key_first( $tabs ) );
		ob_start();
		echo '<div class="contactpanda-portal" data-contactpanda-portal><header class="contactpanda-portal-header"><div><p class="contactpanda-portal-eyebrow">CONTACTPANDA</p><h1>' . esc_html__( 'My data', 'contactpanda' ) . '</h1><p>' . esc_html__( 'Manage your profile, communication preferences, and connected services in one place.', 'contactpanda' ) . '</p></div></header>';
		self::notice();
		echo '<nav class="contactpanda-portal-tabs" aria-label="' . esc_attr__( 'Customer portal', 'contactpanda' ) . '">';
		foreach ( $tabs as $slug => $tab ) {
			echo '<a class="' . ( $slug === $active ? 'is-active' : '' ) . '" href="' . esc_url( self::tab_url( $slug ) ) . '"' . ( $slug === $active ? ' aria-current="page"' : '' ) . '>' . esc_html( $tab['label'] ) . '</a>';
		}
		echo '</nav><main class="contactpanda-portal-panel">';
		if ( isset( $tabs[ $active ]['callback'] ) && is_callable( $tabs[ $active ]['callback'] ) ) {
			call_user_func( $tabs[ $active ]['callback'], $identity, array( 'tab' => $active, 'authenticated' => true ) );
		}
		echo '</main></div>';
		return ob_get_clean();
	}

	public static function tabs( $identity = array() ) {
		$tabs = array(
			'profile' => array( 'label' => __( 'My profile', 'contactpanda' ), 'callback' => array( __CLASS__, 'render_profile' ), 'priority' => 10 ),
			'preferences' => array( 'label' => __( 'Communication', 'contactpanda' ), 'callback' => array( __CLASS__, 'render_preferences' ), 'priority' => 20 ),
			'privacy' => array( 'label' => __( 'Privacy', 'contactpanda' ), 'callback' => array( __CLASS__, 'render_privacy' ), 'priority' => 90 ),
		);
		$tabs = apply_filters( 'contactpanda_customer_portal_tabs', $tabs, $identity );
		uasort( $tabs, static function ( $a, $b ) { return absint( $a['priority'] ?? 50 ) <=> absint( $b['priority'] ?? 50 ); } );
		return $tabs;
	}

	public static function render_profile( $identity, $context = array() ) {
		if ( is_wp_error( $identity ) ) {
			echo '<div class="contactpanda-portal-message is-error"><p>' . esc_html( $identity->get_error_message() ) . '</p></div>';
			return;
		}
		$user = wp_get_current_user();
		$meta = (array) ( $identity['meta'] ?? array() );
		$value = static function ( $key, $fallback = '' ) use ( $identity, $meta ) {
			if ( isset( $identity[ $key ] ) && '' !== (string) $identity[ $key ] ) return $identity[ $key ];
			return isset( $meta[ $key ] ) && '' !== (string) $meta[ $key ] ? $meta[ $key ] : $fallback;
		};
		$return_url = ! empty( $context['return_url'] ) ? esc_url_raw( $context['return_url'] ) : self::tab_url( 'profile' );
		echo '<section><h2>' . esc_html__( 'My profile', 'contactpanda' ) . '</h2><p>' . esc_html__( 'These details belong to your WordPress account and your linked ContactPanda person.', 'contactpanda' ) . '</p><form class="contactpanda-portal-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_portal_save_profile"><input type="hidden" name="return_url" value="' . esc_attr( $return_url ) . '">';
		wp_nonce_field( 'contactpanda_portal_save_profile' );
		echo '<div class="contactpanda-portal-grid">';
		self::field( 'salutation', __( 'Salutation', 'contactpanda' ), $value( 'anrede' ) );
		self::field( 'title_prefix', __( 'Prefix title', 'contactpanda' ), $value( 'titel_vorangestellt', get_user_meta( $user->ID, 'contactpanda_title_prefix', true ) ) );
		self::field( 'first_name', __( 'First name', 'contactpanda' ), $value( 'first_name', $user->first_name ), true );
		self::field( 'last_name', __( 'Last name', 'contactpanda' ), $value( 'last_name', $user->last_name ), true );
		self::field( 'title_suffix', __( 'Suffix title', 'contactpanda' ), $value( 'titel_nachgestellt', get_user_meta( $user->ID, 'contactpanda_title_suffix', true ) ) );
		self::field( 'email', __( 'Email address', 'contactpanda' ), $user->user_email, true, 'email', 'contactpanda-portal-field-wide' );
		self::field( 'phone', __( 'Phone', 'contactpanda' ), $value( 'primary_phone', get_user_meta( $user->ID, 'contactpanda_profile_phone', true ) ), false, 'tel', 'contactpanda-portal-field-wide' );
		self::field( 'company', __( 'Company (optional)', 'contactpanda' ), $value( 'company', get_user_meta( $user->ID, 'billing_company', true ) ), false, 'text', 'contactpanda-portal-field-wide' );
		self::field( 'address_1', __( 'Street and house number', 'contactpanda' ), $value( 'address_1', get_user_meta( $user->ID, 'contactpanda_profile_address_1', true ) ), false, 'text', 'contactpanda-portal-field-wide' );
		self::field( 'address_2', __( 'Address line 2', 'contactpanda' ), $value( 'address_2', get_user_meta( $user->ID, 'contactpanda_profile_address_2', true ) ), false, 'text', 'contactpanda-portal-field-wide' );
		self::field( 'postcode', __( 'Postal code', 'contactpanda' ), $value( 'postcode', get_user_meta( $user->ID, 'contactpanda_profile_postcode', true ) ) );
		self::field( 'city', __( 'City', 'contactpanda' ), $value( 'city', get_user_meta( $user->ID, 'contactpanda_profile_city', true ) ) );
		self::field( 'state', __( 'State / Province', 'contactpanda' ), $value( 'state', get_user_meta( $user->ID, 'contactpanda_profile_state', true ) ) );
		self::field( 'country', __( 'Country code', 'contactpanda' ), $value( 'country', get_user_meta( $user->ID, 'contactpanda_profile_country', true ) ?: 'AT' ) );
		echo '</div><p class="description">' . esc_html__( 'Changing the email address requires confirmation through the new address. Newsletter consent is not changed by this.', 'contactpanda' ) . '</p><button class="button button-primary">' . esc_html__( 'Save changes', 'contactpanda' ) . '</button></form></section>';
	}

	public static function render_preferences( $identity ) {
		if ( is_wp_error( $identity ) ) {
			echo '<p>' . esc_html( $identity->get_error_message() ) . '</p>';
			return;
		}
		$email = sanitize_email( $identity['email'] ?? wp_get_current_user()->user_email );
		$status = $identity['optin_status'] ?? 'unconfirmed';
		$marketable = ContactPanda_Plugin::extension_is_marketable( $identity['object'] ?: $identity );
		echo '<section><h2>' . esc_html__( 'Communication', 'contactpanda' ) . '</h2><div class="contactpanda-portal-status ' . ( $marketable ? 'is-active' : 'is-inactive' ) . '"><strong>' . esc_html( $marketable ? __( 'Newsletter subscribed', 'contactpanda' ) : __( 'Newsletter not subscribed', 'contactpanda' ) ) . '</strong><span>' . esc_html( $email ) . '</span></div>';
		if ( $marketable ) {
			self::unsubscribe_form( $identity['id'], $email, self::tab_url( 'preferences' ) );
		} else {
			echo '<p>' . esc_html__( 'A new newsletter subscription requires a separate double opt-in confirmation.', 'contactpanda' ) . '</p>';
		}
		do_action( 'contactpanda_customer_portal_subscriptions', $identity, array( 'authenticated' => true, 'status' => $status ) );
		echo '</section>';
	}

	public static function render_privacy() {
		$user = wp_get_current_user();
		echo '<section><h2>' . esc_html__( 'Privacy', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Export and deletion requests use the WordPress privacy workflow and require confirmation by email.', 'contactpanda' ) . '</p><div class="contactpanda-portal-actions">';
		self::privacy_form( 'export_personal_data', __( 'Request data export', 'contactpanda' ), $user->user_email, self::tab_url( 'privacy' ) );
		self::privacy_form( 'remove_personal_data', __( 'Request data deletion', 'contactpanda' ), $user->user_email, self::tab_url( 'privacy' ) );
		echo '</div></section>';
	}

	public static function save_profile() {
		if ( ! is_user_logged_in() ) auth_redirect();
		check_admin_referer( 'contactpanda_portal_save_profile' );
		$user = wp_get_current_user();
		$data = array();
		foreach ( array( 'salutation', 'title_prefix', 'first_name', 'last_name', 'title_suffix', 'phone', 'company', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' ) as $key ) {
			$data[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) );
		}
		if ( '' === $data['first_name'] || '' === $data['last_name'] ) {
			self::redirect_notice( false, __( 'Please enter your first and last name.', 'contactpanda' ) );
		}
		$result = wp_update_user( array( 'ID' => $user->ID, 'first_name' => $data['first_name'], 'last_name' => $data['last_name'] ) );
		if ( is_wp_error( $result ) ) self::redirect_notice( false, $result->get_error_message() );
		$meta = array( 'title_prefix' => 'contactpanda_title_prefix', 'title_suffix' => 'contactpanda_title_suffix', 'phone' => 'contactpanda_profile_phone', 'company' => 'billing_company', 'address_1' => 'contactpanda_profile_address_1', 'address_2' => 'contactpanda_profile_address_2', 'postcode' => 'contactpanda_profile_postcode', 'city' => 'contactpanda_profile_city', 'state' => 'contactpanda_profile_state', 'country' => 'contactpanda_profile_country' );
		foreach ( $meta as $field => $key ) update_user_meta( $user->ID, $key, $data[ $field ] );
		$identity = ContactPanda_Identity::resolve_user( $user->ID, true );
		if ( is_wp_error( $identity ) ) self::redirect_notice( false, $identity->get_error_message() );
		$saved = ContactPanda_Identity::update_profile( $identity, $data );
		if ( is_wp_error( $saved ) ) self::redirect_notice( false, $saved->get_error_message() );
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		if ( $email && 0 !== strcasecmp( $email, $user->user_email ) ) {
			$request = self::request_email_change( $user, $email );
			if ( is_wp_error( $request ) ) self::redirect_notice( false, $request->get_error_message() );
			$message = __( 'Your profile was saved. Confirm the message sent to the new email address to complete the email change.', 'contactpanda' );
		} else {
			$message = __( 'Your profile has been saved.', 'contactpanda' );
		}
		do_action( 'contactpanda_portal_profile_saved', $identity['id'], $user->ID, $data );
		self::redirect_notice( true, $message );
	}

	public static function confirm_email_change() {
		if ( empty( $_GET['contactpanda_email_confirm'] ) || ! is_user_logged_in() ) return;
		$user = wp_get_current_user();
		$pending = get_user_meta( $user->ID, self::EMAIL_META, true );
		$token = sanitize_text_field( wp_unslash( $_GET['contactpanda_email_confirm'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- one-time confirmation token.
		if ( ! is_array( $pending ) || empty( $pending['hash'] ) || empty( $pending['email'] ) || time() > absint( $pending['expires'] ?? 0 ) || ! hash_equals( $pending['hash'], hash( 'sha256', $token ) ) ) {
			self::redirect_notice( false, __( 'The email confirmation is invalid or expired.', 'contactpanda' ) );
		}
		$existing = get_user_by( 'email', $pending['email'] );
		if ( $existing && absint( $existing->ID ) !== absint( $user->ID ) ) self::redirect_notice( false, __( 'This email address already belongs to another WordPress account.', 'contactpanda' ) );
		$result = wp_update_user( array( 'ID' => $user->ID, 'user_email' => sanitize_email( $pending['email'] ) ) );
		if ( is_wp_error( $result ) ) self::redirect_notice( false, $result->get_error_message() );
		$identity = ContactPanda_Identity::resolve_user( $user->ID, false );
		if ( ! is_wp_error( $identity ) && ! empty( $identity['id'] ) ) ContactPanda_Identity::update_email( $identity, $pending['email'] );
		delete_user_meta( $user->ID, self::EMAIL_META );
		do_action( 'contactpanda_portal_email_confirmed', $identity['id'] ?? 0, $user->ID, $pending['email'] );
		self::redirect_notice( true, __( 'Your email address has been confirmed and updated.', 'contactpanda' ) );
	}

	public static function unsubscribe() {
		$preference = self::preference_context();
		if ( is_user_logged_in() ) {
			check_admin_referer( 'contactpanda_portal_unsubscribe' );
			$identity = ContactPanda_Identity::resolve_user( get_current_user_id(), false );
		} else {
			$identity = $preference;
			if ( ! $identity ) wp_die( esc_html__( 'This preference link is invalid or expired.', 'contactpanda' ), '', array( 'response' => 403 ) );
		}
		if ( is_wp_error( $identity ) || empty( $identity['id'] ) ) wp_die( esc_html__( 'The ContactPanda person could not be resolved.', 'contactpanda' ), '', array( 'response' => 404 ) );
		$email = sanitize_email( $identity['email'] ?? '' );
		$now = current_time( 'mysql' );
		ContactPanda_Plugin::extension_unsubscribe_contact( $identity['id'], 'preference_centre', sprintf( __( 'Email address %1$s finally unsubscribed through the preference centre at %2$s.', 'contactpanda' ), $email, $now ), $email );
		do_action( 'contactpanda_portal_subscription_changed', $identity['id'], 'newsletter', 'unsubscribed', array( 'source' => 'preference_centre', 'email' => $email ) );
		self::redirect_notice( true, __( 'The email address has been finally unsubscribed from the newsletter.' , 'contactpanda' ) );
	}

	public static function privacy_request() {
		$type = sanitize_key( wp_unslash( $_POST['request_type'] ?? '' ) );
		if ( ! in_array( $type, array( 'export_personal_data', 'remove_personal_data' ), true ) ) wp_die( esc_html__( 'Invalid privacy request.', 'contactpanda' ), '', array( 'response' => 400 ) );
		check_admin_referer( 'contactpanda_portal_privacy_' . $type );
		$email = is_user_logged_in() ? wp_get_current_user()->user_email : sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		if ( ! is_user_logged_in() ) {
			$preference = self::preference_context();
			if ( ! $preference || ! hash_equals( strtolower( (string) $preference['email'] ), strtolower( (string) $email ) ) ) {
				wp_die( esc_html__( 'This preference link is invalid or expired.', 'contactpanda' ), '', array( 'response' => 403 ) );
			}
		}
		if ( ! $email ) wp_die( esc_html__( 'A valid email address is required.', 'contactpanda' ), '', array( 'response' => 400 ) );
		$request_id = wp_create_user_request( $email, $type );
		if ( is_wp_error( $request_id ) ) self::redirect_notice( false, $request_id->get_error_message() );
		$result = wp_send_user_request( $request_id );
		if ( is_wp_error( $result ) ) self::redirect_notice( false, $result->get_error_message() );
		do_action( 'contactpanda_portal_privacy_requested', $type, $email, $request_id );
		self::redirect_notice( true, __( 'Please confirm the privacy request using the email we have sent you.', 'contactpanda' ) );
	}

	public static function preference_url( $contact_id, $scope = 'preferences', $ttl = 2592000 ) {
		$contact = ContactPanda_Plugin::extension_contact( absint( $contact_id ) );
		$email = $contact ? sanitize_email( ContactPanda_Plugin::extension_value( $contact, array( 'email', 'get_email' ) ) ) : '';
		if ( ! $email ) return '';
		$expires = time() + max( HOUR_IN_SECONDS, absint( $ttl ) );
		$scope = sanitize_key( $scope );
		$signature = hash_hmac( 'sha256', absint( $contact_id ) . '|' . $expires . '|' . $scope . '|' . strtolower( $email ), wp_salt( 'auth' ) );
		return add_query_arg( array( 'cp_contact' => absint( $contact_id ), 'cp_expires' => $expires, 'cp_scope' => $scope, 'cp_token' => $signature ), self::portal_url() );
	}

	public static function portal_url() {
		$settings = (array) get_option( ContactPanda_Registration::GENERAL_OPTION, array() );
		$page_id = absint( $settings['portal_page_id'] ?? 0 );
		$url = $page_id ? get_permalink( $page_id ) : '';
		if ( ! $url && self::is_portal_page() ) {
			$url = get_permalink( get_queried_object_id() );
		}
		return apply_filters( 'contactpanda_customer_portal_url', $url ?: home_url( '/' ) );
	}

	public static function is_configured() {
		$settings = (array) get_option( ContactPanda_Registration::GENERAL_OPTION, array() );
		$page_id = absint( $settings['portal_page_id'] ?? 0 );
		return ( $page_id && 'publish' === get_post_status( $page_id ) ) || self::is_portal_page();
	}

	public static function tab_url( $tab, $args = array() ) {
		return add_query_arg( array_merge( array( 'cp_portal_tab' => sanitize_key( $tab ) ), $args ), self::portal_url() );
	}

	private static function preference_context() {
		$id = absint( $_REQUEST['cp_contact'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- signed preference context.
		$expires = absint( $_REQUEST['cp_expires'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- signed preference context.
		$scope = sanitize_key( wp_unslash( $_REQUEST['cp_scope'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- signed preference context.
		$token = sanitize_text_field( wp_unslash( $_REQUEST['cp_token'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- signed preference context.
		if ( ! $id || ! $expires || time() > $expires || ! $scope || ! $token ) return false;
		$contact = ContactPanda_Plugin::extension_contact( $id );
		if ( ! $contact ) return false;
		$email = sanitize_email( ContactPanda_Plugin::extension_value( $contact, array( 'email', 'get_email' ) ) );
		$expected = hash_hmac( 'sha256', $id . '|' . $expires . '|' . $scope . '|' . strtolower( $email ), wp_salt( 'auth' ) );
		if ( ! hash_equals( $expected, $token ) ) return false;
		return array( 'id' => $id, 'email' => $email, 'first_name' => ContactPanda_Plugin::extension_value( $contact, array( 'first_name', 'get_first_name' ) ), 'last_name' => ContactPanda_Plugin::extension_value( $contact, array( 'last_name', 'get_last_name' ) ), 'optin_status' => ContactPanda_Plugin::extension_optin_status( $contact ), 'source' => ContactPanda_Store::standalone() ? 'standalone' : 'groundhogg', 'object' => is_object( $contact ) ? $contact : null, 'scope' => $scope, 'token_args' => array( 'cp_contact' => $id, 'cp_expires' => $expires, 'cp_scope' => $scope, 'cp_token' => $token ) );
	}

	private static function render_preference_centre( $identity ) {
		$marketable = ContactPanda_Plugin::extension_is_marketable( $identity['object'] ?: $identity );
		ob_start();
		echo '<div class="contactpanda-portal contactpanda-preference-centre"><header class="contactpanda-portal-header"><div><p class="contactpanda-portal-eyebrow">' . esc_html( get_bloginfo( 'name' ) ) . '</p><h1>' . esc_html__( 'Communication preferences', 'contactpanda' ) . '</h1><p>' . esc_html( trim( ( $identity['first_name'] ?? '' ) . ' ' . ( $identity['last_name'] ?? '' ) ) ) . '<br>' . esc_html( $identity['email'] ) . '</p></div></header>';
		self::notice();
		echo '<section class="contactpanda-portal-panel"><div class="contactpanda-portal-status ' . ( $marketable ? 'is-active' : 'is-inactive' ) . '"><strong>' . esc_html( $marketable ? __( 'Newsletter subscribed', 'contactpanda' ) : __( 'Newsletter not subscribed', 'contactpanda' ) ) . '</strong></div>';
		if ( $marketable ) self::unsubscribe_form( $identity['id'], $identity['email'], add_query_arg( $identity['token_args'], self::portal_url() ), $identity['token_args'] );
		do_action( 'contactpanda_customer_portal_subscriptions', $identity, array( 'authenticated' => false, 'scope' => $identity['scope'] ) );
		echo '<div class="contactpanda-portal-actions">';
		self::privacy_form( 'export_personal_data', __( 'Request data export', 'contactpanda' ), $identity['email'], add_query_arg( $identity['token_args'], self::portal_url() ), $identity['token_args'] );
		self::privacy_form( 'remove_personal_data', __( 'Request data deletion', 'contactpanda' ), $identity['email'], add_query_arg( $identity['token_args'], self::portal_url() ), $identity['token_args'] );
		echo '</div></section></div>';
		return ob_get_clean();
	}

	private static function unsubscribe_form( $contact_id, $email, $return_url, $token_args = array() ) {
		echo '<form class="contactpanda-portal-inline-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_portal_unsubscribe"><input type="hidden" name="return_url" value="' . esc_attr( $return_url ) . '">';
		foreach ( $token_args as $key => $value ) echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
		if ( is_user_logged_in() ) wp_nonce_field( 'contactpanda_portal_unsubscribe' );
		echo '<button class="button" type="submit" onclick="return confirm(' . esc_attr( wp_json_encode( __( 'Finally unsubscribe this email address from the newsletter?', 'contactpanda' ) ) ) . ')">' . esc_html__( 'Unsubscribe from newsletter', 'contactpanda' ) . '</button></form>';
	}

	private static function privacy_form( $type, $label, $email, $return_url, $token_args = array() ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_portal_privacy_request"><input type="hidden" name="request_type" value="' . esc_attr( $type ) . '"><input type="hidden" name="email" value="' . esc_attr( $email ) . '"><input type="hidden" name="return_url" value="' . esc_attr( $return_url ) . '">';
		foreach ( $token_args as $key => $value ) echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
		wp_nonce_field( 'contactpanda_portal_privacy_' . $type );
		echo '<button class="button">' . esc_html( $label ) . '</button></form>';
	}

	private static function request_email_change( $user, $email ) {
		if ( ! is_email( $email ) ) return new WP_Error( 'contactpanda_portal_email', __( 'Please enter a valid email address.', 'contactpanda' ) );
		$existing = get_user_by( 'email', $email );
		if ( $existing && absint( $existing->ID ) !== absint( $user->ID ) ) return new WP_Error( 'contactpanda_portal_email_exists', __( 'This email address already belongs to another WordPress account.', 'contactpanda' ) );
		$token = wp_generate_password( 48, false, false );
		update_user_meta( $user->ID, self::EMAIL_META, array( 'email' => $email, 'hash' => hash( 'sha256', $token ), 'expires' => time() + DAY_IN_SECONDS ) );
		$url = add_query_arg( 'contactpanda_email_confirm', rawurlencode( $token ), self::portal_url() );
		$sent = wp_mail( $email, sprintf( __( 'Confirm your email address for %s', 'contactpanda' ), get_bloginfo( 'name' ) ), sprintf( __( "Hello %1$s,\n\nconfirm your new email address using this link:\n%2$s\n\nThe link is valid for 24 hours.", 'contactpanda' ), $user->display_name, $url ) );
		return $sent ? true : new WP_Error( 'contactpanda_portal_email_send', __( 'The confirmation email could not be sent.', 'contactpanda' ) );
	}

	private static function field( $name, $label, $value, $required = false, $type = 'text', $class = '' ) {
		echo '<label class="contactpanda-portal-field ' . esc_attr( $class ) . '"><span>' . esc_html( $label ) . ( $required ? ' <em>*</em>' : '' ) . '</span><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( $required ? ' required' : '' ) . '></label>';
	}

	public static function is_portal_page() {
		$settings = (array) get_option( ContactPanda_Registration::GENERAL_OPTION, array() );
		if ( ! empty( $settings['portal_page_id'] ) && is_page( absint( $settings['portal_page_id'] ) ) ) return true;
		global $post;
		return $post instanceof WP_Post && ( has_shortcode( $post->post_content, self::SHORTCODE ) || has_block( self::BLOCK, $post ) );
	}

	private static function enqueue_assets() {
		wp_enqueue_style( 'contactpanda-portal', CONTACTPANDA_URL . 'assets/contactpanda-customer-portal.css', array(), CONTACTPANDA_ASSET_VERSION );
	}

	private static function current_url() {
		$request_uri = wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' );
		return esc_url_raw( home_url( '/' . ltrim( $request_uri, '/' ) ) );
	}

	private static function redirect_notice( $success, $message ) {
		$notice_id = strtolower( wp_generate_password( 16, false, false ) );
		$key = self::NOTICE . $notice_id;
		set_transient( $key, array( 'success' => (bool) $success, 'message' => sanitize_text_field( $message ) ), MINUTE_IN_SECONDS );
		$return = wp_validate_redirect( esc_url_raw( wp_unslash( $_REQUEST['return_url'] ?? self::portal_url() ) ), self::portal_url() );
		wp_safe_redirect( add_query_arg( 'cp_portal_notice', $notice_id, $return ) );
		exit;
	}

	private static function notice() {
		$notice_id = sanitize_key( wp_unslash( $_GET['cp_portal_notice'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- opaque one-time display token.
		if ( ! $notice_id ) return;
		$key = self::NOTICE . $notice_id;
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) ) return;
		delete_transient( $key );
		echo '<div class="contactpanda-portal-message ' . ( ! empty( $notice['success'] ) ? 'is-success' : 'is-error' ) . '" role="status"><p>' . esc_html( $notice['message'] ) . '</p></div>';
	}
}
