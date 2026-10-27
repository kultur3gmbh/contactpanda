<?php

defined( 'ABSPATH' ) || exit;

/** Central company identity shared by every ContactPanda family member. */
final class ContactPanda_Company_Profile {
	const OPTION = 'contactpanda_company_profile';

	public static function boot() {
		add_filter( 'contactpanda_general_settings_sections', array( __CLASS__, 'settings_section' ), 15 );
		add_action( 'contactpanda_render_general_settings_section_company', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_contactpanda_save_company_profile', array( __CLASS__, 'save' ) );
	}

	public static function settings_section( $sections ) {
		$sections['company'] = __( 'Company details', 'contactpanda' );
		return $sections;
	}

	public static function defaults() {
		return array(
			'company_name'       => get_bloginfo( 'name' ),
			'legal_form'         => '',
			'management'         => '',
			'company_register'   => '',
			'street'             => (string) get_option( 'woocommerce_store_address', '' ),
			'postcode'           => (string) get_option( 'woocommerce_store_postcode', '' ),
			'city'               => (string) get_option( 'woocommerce_store_city', '' ),
			'country'            => substr( (string) get_option( 'woocommerce_default_country', '' ), 0, 2 ),
			'email'              => (string) get_option( 'admin_email', '' ),
			'phone'              => '',
			'website'            => home_url( '/' ),
			'tax_number'         => '',
			'bank_name'          => '',
			'iban'               => '',
			'bic'                => '',
			'payment_terms_days' => 14,
			'logo_url'           => '',
			'alternate_logo_url' => '',
			'stamp_url'          => '',
			'signature_url'      => '',
			'legal_footer'       => '',
		);
	}

	/**
	 * Read the shared profile without losing values saved by earlier Pro builds.
	 *
	 * The legacy option remains untouched and is used only as a one-way fallback.
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) || empty( array_filter( $stored ) ) ) {
			$legacy = get_option( 'contactpanda_pro_company_profile', array() );
			if ( is_array( $legacy ) ) {
				$stored = $legacy;
			}
		}
		return wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage these settings.', 'contactpanda' ) );
		}
		$profile = self::get();
		if ( isset( $_GET['company_profile_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Company details saved.', 'contactpanda' ) . '</p></div>';
		}
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Company details', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Maintain identity, contact, tax, bank, and branding details once. ContactPanda extensions use this shared source for documents, messages, and customer-facing pages.', 'contactpanda' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-company-profile"><input type="hidden" name="action" value="contactpanda_save_company_profile">';
		wp_nonce_field( 'contactpanda_save_company_profile' );
		self::heading( __( 'Identity and address', 'contactpanda' ) );
		self::row( $profile, array( 'company_name' => __( 'Company name', 'contactpanda' ), 'legal_form' => __( 'Legal form', 'contactpanda' ), 'management' => __( 'Management', 'contactpanda' ), 'company_register' => __( 'Company register', 'contactpanda' ) ) );
		self::row( $profile, array( 'street' => __( 'Street and house number', 'contactpanda' ), 'postcode' => __( 'Postal code', 'contactpanda' ), 'city' => __( 'City', 'contactpanda' ), 'country' => __( 'Country code', 'contactpanda' ) ) );
		self::heading( __( 'Contact and tax details', 'contactpanda' ) );
		self::row( $profile, array( 'email' => __( 'Email address', 'contactpanda' ), 'phone' => __( 'Telephone', 'contactpanda' ), 'website' => __( 'Website', 'contactpanda' ), 'tax_number' => __( 'Tax number or VAT ID', 'contactpanda' ) ), array( 'email' => 'email', 'website' => 'url' ) );
		self::heading( __( 'Payments and documents', 'contactpanda' ) );
		self::row( $profile, array( 'bank_name' => __( 'Bank name', 'contactpanda' ), 'iban' => 'IBAN', 'bic' => 'BIC', 'payment_terms_days' => __( 'Default payment term in days', 'contactpanda' ) ), array( 'payment_terms_days' => 'number' ) );
		self::row( $profile, array( 'logo_url' => __( 'Logo', 'contactpanda' ), 'alternate_logo_url' => __( 'Alternative logo', 'contactpanda' ), 'stamp_url' => __( 'Stamp image', 'contactpanda' ), 'signature_url' => __( 'Signature image', 'contactpanda' ) ), array(), true );
		echo '<label class="contactpanda-company-profile-wide"><span>' . esc_html__( 'Legal footer', 'contactpanda' ) . '</span><textarea name="legal_footer" rows="4">' . esc_textarea( $profile['legal_footer'] ) . '</textarea></label>';
		echo '<p class="description">' . esc_html__( 'Available WooCommerce store values are used only as initial suggestions. Saving here never changes WooCommerce settings.', 'contactpanda' ) . '</p>';
		submit_button( __( 'Save company details', 'contactpanda' ) );
		echo '</form></section>';
	}

	private static function heading( $label ) {
		echo '<h3 class="contactpanda-settings-subheading">' . esc_html( $label ) . '</h3>';
	}

	private static function row( $profile, $fields, $types = array(), $images = false ) {
		echo '<div class="contactpanda-company-profile-row">';
		foreach ( $fields as $name => $label ) {
			$type = $types[ $name ] ?? 'text';
			echo '<label><span>' . esc_html( $label ) . '</span>';
			if ( $images ) {
				echo '<span class="contactpanda-image-picker-source"><input type="url" name="' . esc_attr( $name ) . '" value="' . esc_attr( $profile[ $name ] ?? '' ) . '" data-contactpanda-image-url></span>';
			} else {
				echo '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $profile[ $name ] ?? '' ) . '">';
			}
			echo '</label>';
		}
		echo '</div>';
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage these settings.', 'contactpanda' ) );
		}
		check_admin_referer( 'contactpanda_save_company_profile' );
		$text = array( 'company_name', 'legal_form', 'management', 'company_register', 'street', 'postcode', 'city', 'country', 'phone', 'tax_number', 'bank_name', 'iban', 'bic' );
		$profile = array();
		foreach ( $text as $key ) {
			$profile[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) );
		}
		$profile['email'] = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		foreach ( array( 'website', 'logo_url', 'alternate_logo_url', 'stamp_url', 'signature_url' ) as $key ) {
			$profile[ $key ] = esc_url_raw( wp_unslash( $_POST[ $key ] ?? '' ) );
		}
		$profile['payment_terms_days'] = min( 365, max( 0, absint( $_POST['payment_terms_days'] ?? 14 ) ) );
		$profile['legal_footer'] = sanitize_textarea_field( wp_unslash( $_POST['legal_footer'] ?? '' ) );
		update_option( self::OPTION, $profile, false );
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=general&general_section=company&company_profile_saved=1' ) );
		exit;
	}
}
