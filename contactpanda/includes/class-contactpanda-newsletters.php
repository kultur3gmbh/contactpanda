<?php

defined( 'ABSPATH' ) || exit;

final class ContactPanda_Basic_Newsletters {
	const NONCE_ACTION = 'contactpanda_newsletter_editor';
	const OPTION = 'contactpanda_newsletter_settings';

	public static function boot() {
		add_filter( 'contactpanda_settings_tabs', array( __CLASS__, 'settings_tab' ), 100 );
		add_action( 'contactpanda_render_settings_tab_newsletter', array( __CLASS__, 'render_settings' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 15 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_contactpanda_save_template', array( __CLASS__, 'save_template' ) );
		add_action( 'admin_post_contactpanda_template_action', array( __CLASS__, 'template_action' ) );
		add_action( 'wp_ajax_contactpanda_newsletter_preview', array( __CLASS__, 'preview_ajax' ) );
		add_action( 'wp_ajax_contactpanda_newsletter_test', array( __CLASS__, 'test_ajax' ) );
		add_action( 'wp_ajax_contactpanda_newsletter_products', array( __CLASS__, 'products_ajax' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_contactpanda_import_woocommerce_email_style', array( __CLASS__, 'import_woocommerce_style' ) );
		add_filter( 'contactpanda_newsletter_confirmation_ttl', array( __CLASS__, 'confirmation_ttl' ), 10, 2 );
		add_action( 'contactpanda_newsletter_confirmation_sent', array( __CLASS__, 'schedule_confirmation_reminders' ), 10, 3 );
		add_action( 'contactpanda_newsletter_confirmation_reminder', array( __CLASS__, 'send_confirmation_reminder' ), 10, 5 );
		add_action( 'contactpanda_plus_newsletter_confirmation_reminder', array( __CLASS__, 'send_confirmation_reminder' ), 10, 5 );
	}

	public static function settings_tab( $tabs ) {
		$tabs['newsletter'] = __( 'Email defaults', 'contactpanda' ); $ordered = array();
		foreach ( array( 'general', 'integrations', 'misc', 'forms', 'registration', 'newsletter', 'other' ) as $key ) if ( isset( $tabs[ $key ] ) ) $ordered[ $key ] = $tabs[ $key ];
		foreach ( $tabs as $key => $label ) if ( ! isset( $ordered[ $key ] ) ) $ordered[ $key ] = $label;
		return $ordered;
	}

	private static function capability() {
		return class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options';
	}

	public static function register_settings() {
		register_setting( 'contactpanda_newsletter', self::OPTION, array( __CLASS__, 'sanitize_settings' ) );
	}

	private static function defaults() {
		return array(
			'company_name'     => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'legal_name'       => '',
			'logo_id'          => 0,
			'logo_url'         => '',
			'street'           => '',
			'postcode'         => '',
			'city'             => '',
			'country'          => 'Österreich',
			'phone'            => '',
			'email'            => sanitize_email( get_option( 'admin_email' ) ),
			'website'          => home_url( '/' ),
			'vat_id'           => '',
			'company_register' => '',
			'privacy_url'      => get_privacy_policy_url(),
			'imprint_url'      => '',
			'footer_text'      => '',
			'from_name'        => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'from_email'       => sanitize_email( get_option( 'admin_email' ) ),
			'reply_to'         => sanitize_email( get_option( 'admin_email' ) ),
			'base_color'       => '#4253b5',
			'background_color' => '#f1f4f7',
			'body_color'       => '#ffffff',
			'text_color'       => '#39495a',
			'font_family'      => 'Arial, sans-serif',
			'container_width'  => 600,
			'email_layout'     => 'contactpanda',
			'reminder_count'   => 0,
			'reminder_delays'  => array( 2, 5, 10 ),
			'reminder_subject' => __( 'Reminder: confirm your newsletter subscription at {site_name}', 'contactpanda' ),
			'reminder_message' => __( "Hello {first_name},\n\nplease confirm your newsletter subscription by opening this link:\n{confirmation_link}", 'contactpanda' ),
		);
	}

	private static function settings() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	public static function public_settings() {
		return self::settings();
	}

	public static function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$current = self::settings();
		$output = array();
		$output['company_name'] = sanitize_text_field( $current['company_name'] ?: $current['legal_name'] ?: $defaults['company_name'] ); // Legacy compatibility for saved templates.
		foreach ( array( 'legal_name', 'street', 'postcode', 'city', 'country', 'phone', 'vat_id', 'company_register', 'from_name' ) as $key ) {
			$output[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : $defaults[ $key ];
		}
		foreach ( array( 'email', 'from_email', 'reply_to' ) as $key ) {
			$output[ $key ] = isset( $input[ $key ] ) ? sanitize_email( $input[ $key ] ) : $defaults[ $key ];
		}
		foreach ( array( 'website', 'privacy_url', 'imprint_url', 'logo_url' ) as $key ) {
			$output[ $key ] = isset( $input[ $key ] ) ? esc_url_raw( $input[ $key ] ) : $defaults[ $key ];
		}
		$output['logo_id'] = isset( $input['logo_id'] ) ? absint( $input['logo_id'] ) : 0;
		$output['footer_text'] = isset( $input['footer_text'] ) ? wp_kses_post( $input['footer_text'] ) : '';
		foreach ( array( 'base_color', 'background_color', 'body_color', 'text_color' ) as $key ) {
			$output[ $key ] = isset( $input[ $key ] ) && sanitize_hex_color( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : $defaults[ $key ];
		}
		$output['font_family'] = isset( $input['font_family'] ) ? sanitize_text_field( $input['font_family'] ) : $defaults['font_family'];
		$output['container_width'] = isset( $input['container_width'] ) ? min( 760, max( 480, absint( $input['container_width'] ) ) ) : $defaults['container_width'];
		$output['email_layout'] = isset( $input['email_layout'] ) && 'woocommerce' === sanitize_key( $input['email_layout'] ) && class_exists( 'WooCommerce' ) ? 'woocommerce' : 'contactpanda';
		$output['reminder_count'] = min( 3, max( 0, absint( $input['reminder_count'] ?? 0 ) ) );
		$output['reminder_delays'] = array(); foreach ( array_slice( (array) ( $input['reminder_delays'] ?? array() ), 0, 3 ) as $delay ) $output['reminder_delays'][] = min( 30, max( 1, absint( $delay ) ) ); $output['reminder_delays'] = array_pad( $output['reminder_delays'], 3, 7 );
		$output['reminder_subject'] = sanitize_text_field( $input['reminder_subject'] ?? $defaults['reminder_subject'] );
		$output['reminder_message'] = wp_kses_post( $input['reminder_message'] ?? $defaults['reminder_message'] );
		return $output;
	}

	public static function render_settings() {
		$settings = self::settings();
		$notice = get_transient( 'contactpanda_newsletter_settings_notice_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'contactpanda_newsletter_settings_notice_' . get_current_user_id() );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
		}
		if ( class_exists( 'WooCommerce' ) ) {
			echo '<section class="contactpanda-card contactpanda-settings-card contactpanda-woocommerce-email-import"><h2>' . esc_html__( 'Adopt WooCommerce settings', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Copy the current WooCommerce email colors, sender details, header image, and footer text into the editable ContactPanda fields below. This is a one-time copy and does not switch the selected email layout.', 'contactpanda' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_import_woocommerce_email_style">';
			wp_nonce_field( 'contactpanda_import_woocommerce_email_style' );
			echo '<button type="submit" class="button">' . esc_html__( 'Adopt WooCommerce settings', 'contactpanda' ) . '</button></form></section>';
		}
		echo '<form method="post" action="options.php"><section class="contactpanda-card contactpanda-settings-card contactpanda-newsletter-global-settings"><h2>' . esc_html__( 'Email defaults', 'contactpanda' ) . '</h2><p>' . esc_html__( 'These details provide the prefilled footer block and the legally required unsubscribe information.', 'contactpanda' ) . '</p>';
		settings_fields( 'contactpanda_newsletter' );
		echo '<h3>' . esc_html__( 'Sender and company details', 'contactpanda' ) . '</h3><div class="contactpanda-settings-grid">';
		self::settings_field( 'from_name', __( 'Sender name', 'contactpanda' ), $settings );
		self::settings_field( 'legal_name', __( 'Full legal company name (optional)', 'contactpanda' ), $settings );
		self::settings_field( 'from_email', __( 'Sender email address', 'contactpanda' ), $settings, 'email' );
		self::settings_field( 'reply_to', __( 'Default reply-to address', 'contactpanda' ), $settings, 'email' );
		self::settings_field( 'email', __( 'Company email address', 'contactpanda' ), $settings, 'email' );
		self::settings_field( 'phone', __( 'Phone', 'contactpanda' ), $settings );
		self::settings_field( 'website', __( 'Website', 'contactpanda' ), $settings, 'url' );
		echo '</div><h3>' . esc_html__( 'Address and legal information', 'contactpanda' ) . '</h3><div class="contactpanda-settings-grid">';
		foreach ( array( 'street' => __( 'Street and house number', 'contactpanda' ), 'postcode' => __( 'Postal code', 'contactpanda' ), 'city' => __( 'City', 'contactpanda' ), 'country' => __( 'Country', 'contactpanda' ), 'vat_id' => __( 'VAT ID', 'contactpanda' ), 'company_register' => __( 'Company register', 'contactpanda' ) ) as $key => $label ) {
			self::settings_field( $key, $label, $settings );
		}
		self::settings_field( 'privacy_url', __( 'Privacy policy URL', 'contactpanda' ), $settings, 'url' );
		self::settings_field( 'imprint_url', __( 'Imprint URL', 'contactpanda' ), $settings, 'url' );
		echo '</div><h3>' . esc_html__( 'Logo and footer text', 'contactpanda' ) . '</h3><div class="contactpanda-newsletter-logo-setting"><input type="hidden" name="' . esc_attr( self::OPTION ) . '[logo_id]" value="' . esc_attr( $settings['logo_id'] ) . '"><input type="url" name="' . esc_attr( self::OPTION ) . '[logo_url]" value="' . esc_attr( $settings['logo_url'] ) . '" placeholder="https://"><button type="button" class="button contactpanda-newsletter-media-button">' . esc_html__( 'Choose logo from media library', 'contactpanda' ) . '</button>' . ( $settings['logo_url'] ? '<span class="contactpanda-newsletter-logo-preview"><img src="' . esc_url( $settings['logo_url'] ) . '" alt="' . esc_attr__( 'Current newsletter logo', 'contactpanda' ) . '"></span>' : '' ) . '</div>';
		echo '<textarea class="large-text" rows="4" name="' . esc_attr( self::OPTION ) . '[footer_text]" placeholder="' . esc_attr__( 'Optional additional footer text', 'contactpanda' ) . '">' . esc_textarea( $settings['footer_text'] ) . '</textarea>';
		echo '<h3>' . esc_html__( 'Email appearance', 'contactpanda' ) . '</h3><div class="contactpanda-settings-grid">';
		foreach ( array( 'base_color' => __( 'Accent color', 'contactpanda' ), 'background_color' => __( 'Background color', 'contactpanda' ), 'body_color' => __( 'Content background', 'contactpanda' ), 'text_color' => __( 'Text color', 'contactpanda' ) ) as $key => $label ) {
			self::settings_color_field( $key, $label, $settings );
		}
		self::settings_font_field( $settings );
		self::settings_field( 'container_width', __( 'Email width in pixels', 'contactpanda' ), $settings, 'number', ' min="480" max="760"' );
		echo '<fieldset class="contactpanda-email-layout-switch"><legend>' . esc_html__( 'Email layout', 'contactpanda' ) . '</legend><div class="contactpanda-style-switch"><label class="contactpanda-style-option"><input type="radio" name="' . esc_attr( self::OPTION ) . '[email_layout]" value="contactpanda"' . checked( $settings['email_layout'], 'contactpanda', false ) . '><span><strong>' . esc_html__( 'ContactPanda email layout', 'contactpanda' ) . '</strong><small>' . esc_html__( 'Use the neutral and responsive ContactPanda email frame.', 'contactpanda' ) . '</small></span></label>';
		if ( class_exists( 'WooCommerce' ) ) {
			echo '<label class="contactpanda-style-option"><input type="radio" name="' . esc_attr( self::OPTION ) . '[email_layout]" value="woocommerce"' . checked( $settings['email_layout'], 'woocommerce', false ) . '><span><strong>' . esc_html__( 'WooCommerce email layout', 'contactpanda' ) . '</strong><small>' . esc_html__( 'Use the configured WooCommerce email frame with ContactPanda content and unsubscribe protection.', 'contactpanda' ) . '</small></span></label>';
		}
		echo '</div></fieldset>';
		echo '</div>';
		echo '<h3>' . esc_html__( 'Double opt-in reminders', 'contactpanda' ) . '</h3><p>' . esc_html__( 'Optionally remind unconfirmed newsletter subscribers. Reminders stop automatically after confirmation or expiry and contain no marketing content.', 'contactpanda' ) . '</p><div class="contactpanda-settings-grid">';
		echo '<label><span>' . esc_html__( 'Number of reminders', 'contactpanda' ) . '</span><select name="' . esc_attr( self::OPTION ) . '[reminder_count]">'; for ( $i = 0; $i <= 3; $i++ ) echo '<option value="' . esc_attr( $i ) . '"' . selected( absint( $settings['reminder_count'] ), $i, false ) . '>' . esc_html( $i ) . '</option>'; echo '</select></label>';
		for ( $i = 0; $i < 3; $i++ ) echo '<label><span>' . esc_html( sprintf( __( 'Reminder %d after days', 'contactpanda' ), $i + 1 ) ) . '</span><input type="number" min="1" max="30" name="' . esc_attr( self::OPTION ) . '[reminder_delays][]" value="' . esc_attr( absint( $settings['reminder_delays'][ $i ] ?? ( $i + 1 ) * 3 ) ) . '"></label>';
		self::settings_field( 'reminder_subject', __( 'Reminder subject', 'contactpanda' ), $settings );
		echo '<label><span>' . esc_html__( 'Reminder email text', 'contactpanda' ) . '</span><textarea rows="6" name="' . esc_attr( self::OPTION ) . '[reminder_message]">' . esc_textarea( $settings['reminder_message'] ) . '</textarea></label></div>';
		submit_button( __( 'Save newsletter settings', 'contactpanda' ) );
		echo '</section></form>';
	}

	public static function schedule_confirmation_reminders( $verification_key, $token, $payload ) {
		$settings = self::settings(); $count = min( 3, absint( $settings['reminder_count'] ) );
		for ( $i = 0; $i < $count; $i++ ) {
			$days = min( 30, max( 1, absint( $settings['reminder_delays'][ $i ] ?? ( $i + 1 ) * 3 ) ) );
			wp_schedule_single_event( time() + $days * DAY_IN_SECONDS, 'contactpanda_newsletter_confirmation_reminder', array( sanitize_key( $verification_key ), sanitize_text_field( $token ), sanitize_email( $payload['email'] ?? '' ), sanitize_text_field( $payload['first_name'] ?? '' ), $i + 1 ) );
		}
	}

	public static function confirmation_ttl( $ttl, $payload ) {
		$settings = self::settings();
		$count = min( 3, absint( $settings['reminder_count'] ) );
		if ( ! $count ) return $ttl;
		$delays = array_slice( (array) $settings['reminder_delays'], 0, $count );
		return max( absint( $ttl ), ( max( array_map( 'absint', $delays ) ) + 1 ) * DAY_IN_SECONDS );
	}

	public static function send_confirmation_reminder( $verification_key, $token, $email, $first_name, $number ) {
		if ( ! get_transient( 'contactpanda_newsletter_verify_' . sanitize_key( $verification_key ) ) || ! is_email( $email ) ) return;
		$settings = self::settings(); $link = add_query_arg( 'contactpanda_newsletter_verify', sanitize_text_field( $token ), home_url( '/' ) );
		$replace = array( '{first_name}' => esc_html( $first_name ), '{site_name}' => esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ), '{confirmation_link}' => '<a href="' . esc_url( $link ) . '">' . esc_html__( 'Confirm newsletter subscription', 'contactpanda' ) . '</a>', '{reminder_number}' => absint( $number ) );
		$subject = strtr( $settings['reminder_subject'], $replace ); $message = strtr( wpautop( $settings['reminder_message'] ), $replace );
		if ( false === strpos( $settings['reminder_message'], '{confirmation_link}' ) ) $message .= '<p>' . $replace['{confirmation_link}'] . '</p>';
		wp_mail( $email, $subject, $message, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	private static function settings_field( $key, $label, $settings, $type = 'text', $extra = '' ) {
		echo '<label><span>' . esc_html( $label ) . '</span><input type="' . esc_attr( $type ) . '" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $settings[ $key ] ) . '"' . $extra . '></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed numeric attributes only.
	}

	private static function settings_color_field( $key, $label, $settings ) {
		$value = sanitize_hex_color( $settings[ $key ] ?? '' ) ?: '#ffffff';
		echo '<label class="contactpanda-settings-color-field"><span>' . esc_html( $label ) . '</span><span class="contactpanda-settings-color-control"><input type="color" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '"><code class="contactpanda-settings-color-value">' . esc_html( $value ) . '</code></span><span class="contactpanda-theme-color-palette">';
		foreach ( self::theme_colors() as $color ) {
			echo '<button type="button" class="contactpanda-settings-color-swatch" data-color="' . esc_attr( $color ) . '" title="' . esc_attr( $color ) . '" aria-label="' . esc_attr( sprintf( __( 'Use color %s', 'contactpanda' ), $color ) ) . '" style="background:' . esc_attr( $color ) . '"></button>';
		}
		echo '</span></label>';
	}

	private static function settings_font_field( $settings ) {
		$current = sanitize_text_field( $settings['font_family'] ?? '' );
		$fonts = array(
			'Arial, sans-serif'                                      => 'Arial',
			'Helvetica, Arial, sans-serif'                           => 'Helvetica',
			'Verdana, Geneva, sans-serif'                            => 'Verdana',
			'Tahoma, Geneva, sans-serif'                             => 'Tahoma',
			'"Trebuchet MS", Arial, sans-serif'                      => 'Trebuchet MS',
			'Georgia, "Times New Roman", serif'                      => 'Georgia',
			'"Times New Roman", Times, serif'                        => 'Times New Roman',
			'-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif' => __( 'System font', 'contactpanda' ),
		);
		if ( $current && ! isset( $fonts[ $current ] ) ) {
			$fonts[ $current ] = sprintf( __( 'Previously saved: %s', 'contactpanda' ), $current );
		}
		echo '<label><span>' . esc_html__( 'Font family', 'contactpanda' ) . '</span><select name="' . esc_attr( self::OPTION ) . '[font_family]">';
		foreach ( $fonts as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label>';
	}

	public static function import_woocommerce_style() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ) );
		}
		check_admin_referer( 'contactpanda_import_woocommerce_email_style' );
		$settings = self::settings();
		$map = array(
			'from_name'        => 'woocommerce_email_from_name',
			'from_email'       => 'woocommerce_email_from_address',
			'logo_url'         => 'woocommerce_email_header_image',
			'base_color'       => 'woocommerce_email_base_color',
			'background_color' => 'woocommerce_email_background_color',
			'body_color'       => 'woocommerce_email_body_background_color',
			'text_color'       => 'woocommerce_email_text_color',
			'footer_text'      => 'woocommerce_email_footer_text',
			'font_family'      => 'woocommerce_email_font_family',
		);
		foreach ( $map as $contactpanda_key => $woocommerce_key ) {
			$value = get_option( $woocommerce_key, '' );
			if ( '' !== $value ) {
				if ( 'logo_url' === $contactpanda_key && is_numeric( $value ) ) {
					$settings['logo_id'] = absint( $value );
					$value = wp_get_attachment_url( absint( $value ) ) ?: '';
				}
				$settings[ $contactpanda_key ] = $value;
			}
		}
		update_option( self::OPTION, self::sanitize_settings( $settings ), false );
		set_transient( 'contactpanda_newsletter_settings_notice_' . get_current_user_id(), __( 'The WooCommerce email style was copied to ContactPanda.', 'contactpanda' ), MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=newsletter' ) );
		exit;
	}

	public static function menu() {
		add_submenu_page(
			'contactpanda',
			__( 'Email templates', 'contactpanda' ),
			__( 'Email templates', 'contactpanda' ),
			self::capability(),
			'contactpanda-newsletters',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function assets( $hook ) {
		$is_editor = 'contactpanda_page_contactpanda-newsletters' === $hook;
		$is_settings = 'contactpanda_page_contactpanda-settings' === $hook && isset( $_GET['tab'] ) && 'newsletter' === sanitize_key( wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- asset routing only.
		if ( ! $is_editor && ! $is_settings ) {
			return;
		}
		wp_enqueue_style( 'contactpanda', CONTACTPANDA_URL . 'assets/contactpanda-admin.css', array(), CONTACTPANDA_ASSET_VERSION );
		wp_enqueue_media();
		if ( $is_editor ) {
			wp_enqueue_editor();
		}
		wp_enqueue_script( 'contactpanda-newsletters', CONTACTPANDA_URL . 'assets/contactpanda-newsletters.js', array(), CONTACTPANDA_ASSET_VERSION, true );
		wp_localize_script(
			'contactpanda-newsletters',
			'CONTACTPANDA_NEWSLETTER',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'woocommerce' => class_exists( 'WooCommerce' ),
				'events' => (bool) apply_filters( 'contactpanda_events_available', false ),
				'productFilters' => $is_editor ? self::product_filters() : array( 'categories' => array(), 'producers' => array(), 'products' => array() ),
				'eventFilters' => $is_editor ? self::event_filters() : array(),
				'postFilters' => $is_editor ? self::post_filters() : array( 'categories' => array(), 'posts' => array() ),
				'footerHtml' => self::render_footer( self::settings() ),
				'logoUrl' => self::settings()['logo_url'],
				'themeColors' => self::theme_colors(),
				'i18n'    => array(
					'heading'       => __( 'Heading', 'contactpanda' ),
					'text'          => __( 'Text', 'contactpanda' ),
					'image'         => __( 'Image', 'contactpanda' ),
					'video'         => __( 'Video', 'contactpanda' ),
					'gallery'       => __( 'Image gallery', 'contactpanda' ),
					'columns'       => __( 'Columns', 'contactpanda' ),
					'button'        => __( 'Button', 'contactpanda' ),
					'phone_button'  => __( 'Phone button', 'contactpanda' ),
					'email_button'  => __( 'Email button', 'contactpanda' ),
					'whatsapp_button' => __( 'WhatsApp button', 'contactpanda' ),
					'divider'       => __( 'Divider', 'contactpanda' ),
					'spacer'        => __( 'Spacer', 'contactpanda' ),
					'imageUrl'      => __( 'Image URL', 'contactpanda' ),
					'alternativeText'=> __( 'Alternative text', 'contactpanda' ),
					'videoUrl'      => __( 'YouTube, Vimeo, or video URL', 'contactpanda' ),
					'videoThumbnail'=> __( 'Preview image URL', 'contactpanda' ),
					'videoTitle'    => __( 'Video title', 'contactpanda' ),
					'videoButton'   => __( 'Button text', 'contactpanda' ),
					'choosePreview' => __( 'Choose preview image', 'contactpanda' ),
					'galleryImages' => __( 'Gallery images', 'contactpanda' ),
					'chooseGallery' => __( 'Choose up to six images', 'contactpanda' ),
					'galleryColumns'=> __( 'Columns', 'contactpanda' ),
					'desktopColumns'=> __( 'Desktop columns', 'contactpanda' ),
					'tabletColumns' => __( 'Tablet columns', 'contactpanda' ),
					'mobileColumns' => __( 'Mobile columns', 'contactpanda' ),
					'galleryLayout' => __( 'Gallery layout', 'contactpanda' ),
					'grid'          => __( 'Grid', 'contactpanda' ),
					'masonry'       => __( 'Masonry', 'contactpanda' ),
					'imageFormat'   => __( 'Image format', 'contactpanda' ),
					'fixedFormat'   => __( 'Fixed format', 'contactpanda' ),
					'originalFormat'=> __( 'Original proportions', 'contactpanda' ),
					'galleryEmpty'  => __( 'Choose images for this gallery.', 'contactpanda' ),
					'galleryLimit'  => __( 'You can select up to six images.', 'contactpanda' ),
					'captionSettings'=> __( 'Caption', 'contactpanda' ),
					'caption'       => __( 'Caption text', 'contactpanda' ),
					'captionPosition'=> __( 'Caption position', 'contactpanda' ),
					'captionBackground'=> __( 'Caption background (hex or rgba)', 'contactpanda' ),
					'fontSize'      => __( 'Font size', 'contactpanda' ),
					'fontWeight'    => __( 'Font weight', 'contactpanda' ),
					'normal'        => __( 'Normal', 'contactpanda' ),
					'semibold'      => __( 'Semi-bold', 'contactpanda' ),
					'bold'          => __( 'Bold', 'contactpanda' ),
					'providerBadge' => __( 'Show provider logo', 'contactpanda' ),
					'badgePosition' => __( 'Provider logo position', 'contactpanda' ),
					'phoneNumber'   => __( 'Phone number', 'contactpanda' ),
					'emailAddress'  => __( 'Email address', 'contactpanda' ),
					'prefilledMessage'=> __( 'Prefilled WhatsApp message', 'contactpanda' ),
					'dropBlockHere' => __( 'Drag a block into this column.', 'contactpanda' ),
					'columnsHint'   => __( 'Drag any content block from the left into a column. Existing blocks can also be moved between columns.', 'contactpanda' ),
					'removeColumnContent'=> __( 'The removed column contains blocks. Remove this column and its content?', 'contactpanda' ),
					'buttonLabel'   => __( 'Label', 'contactpanda' ),
					'targetUrl'     => __( 'Target URL', 'contactpanda' ),
					'spacerHeight'  => __( 'Height in pixels', 'contactpanda' ),
					'dividerNote'   => __( 'A subtle divider line is inserted.', 'contactpanda' ),
					'moveUp'        => __( 'Move up', 'contactpanda' ),
					'moveDown'      => __( 'Move down', 'contactpanda' ),
					'remove'        => __( 'Remove', 'contactpanda' ),
					'previewFailed' => __( 'The preview could not be created.', 'contactpanda' ),
					'testFailed'    => __( 'The test email could not be sent.', 'contactpanda' ),
					'sending'       => __( 'Sending test email…', 'contactpanda' ),
					'confirmArchive'=> __( 'Archive this template?', 'contactpanda' ),
					'chooseImage'    => __( 'Choose image', 'contactpanda' ),
					'useImage'       => __( 'Use this image', 'contactpanda' ),
					'editBlock'      => __( 'Edit block', 'contactpanda' ),
					'duplicate'      => __( 'Duplicate', 'contactpanda' ),
					'dragHint'       => __( 'Drag blocks here or double-click a block on the left.', 'contactpanda' ),
					'blockSettings'  => __( 'Block settings', 'contactpanda' ),
					'selectBlock'    => __( 'Select a block in the email to edit it.', 'contactpanda' ),
					'content'        => __( 'Content', 'contactpanda' ),
					'alignment'      => __( 'Alignment', 'contactpanda' ),
					'left'           => __( 'Left', 'contactpanda' ),
					'center'         => __( 'Center', 'contactpanda' ),
					'right'          => __( 'Right', 'contactpanda' ),
					'width'          => __( 'Width in percent', 'contactpanda' ),
					'openMedia'      => __( 'Select from media library', 'contactpanda' ),
					'products'       => __( 'Products', 'contactpanda' ),
					'events'         => __( 'Events', 'contactpanda' ),
					'posts'          => __( 'Posts', 'contactpanda' ),
					'productSource'  => __( 'Product selection', 'contactpanda' ),
					'latestProducts' => __( 'Latest products', 'contactpanda' ),
					'category'       => __( 'Category', 'contactpanda' ),
					'producer'       => __( 'Producer / brand', 'contactpanda' ),
					'featured'       => __( 'Featured products', 'contactpanda' ),
					'onSale'         => __( 'Products on sale', 'contactpanda' ),
					'manual'         => __( 'Select products manually', 'contactpanda' ),
					'manualPostsChoice' => __( 'Select posts manually', 'contactpanda' ),
					'productCount'   => __( 'Number of products', 'contactpanda' ),
					'eventCount'     => __( 'Number of events', 'contactpanda' ),
					'postCount'      => __( 'Number of posts', 'contactpanda' ),
					'contentType'    => __( 'Content type', 'contactpanda' ),
					'productCategory'=> __( 'Product category', 'contactpanda' ),
					'producerType'   => __( 'Producer field', 'contactpanda' ),
					'producerValue'  => __( 'Producer', 'contactpanda' ),
					'manualProducts' => __( 'Products', 'contactpanda' ),
					'productsUnavailable' => __( 'WooCommerce is currently unavailable.', 'contactpanda' ),
					'noProducts'     => __( 'No matching products were found.', 'contactpanda' ),
					'loadingProducts'=> __( 'Loading products…', 'contactpanda' ),
					'widthUnit'      => __( 'Width unit', 'contactpanda' ),
					'percent'        => __( 'Percent', 'contactpanda' ),
					'pixels'         => __( 'Pixels', 'contactpanda' ),
					'insertLink'     => __( 'Insert link', 'contactpanda' ),
					'eventsBlock'    => __( 'Events', 'contactpanda' ),
					'footer'         => __( 'Footer', 'contactpanda' ),
					'backgroundColor'=> __( 'Background color', 'contactpanda' ),
					'textColor'      => __( 'Text color', 'contactpanda' ),
					'borderColor'    => __( 'Border color', 'contactpanda' ),
					'headingLevel'   => __( 'Heading level', 'contactpanda' ),
					'columns'        => __( 'Columns', 'contactpanda' ),
					'selectEvents'   => __( 'Select events', 'contactpanda' ),
					'postsBlock'      => __( 'Posts', 'contactpanda' ),
					'postSource'      => __( 'Post selection', 'contactpanda' ),
					'latestPosts'     => __( 'Latest posts', 'contactpanda' ),
					'postCategory'    => __( 'Post category', 'contactpanda' ),
					'manualPosts'     => __( 'Select posts manually', 'contactpanda' ),
					'displayedContent'=> __( 'Displayed content', 'contactpanda' ),
					'showImage'       => __( 'Image', 'contactpanda' ),
					'showTitle'       => __( 'Title', 'contactpanda' ),
					'showExcerpt'     => __( 'Excerpt', 'contactpanda' ),
					'atLeastOneContent' => __( 'At least one content item must remain enabled.', 'contactpanda' ),
					'imageRatio'      => __( 'Image format', 'contactpanda' ),
					'ratioSquare'     => __( 'Square (1:1)', 'contactpanda' ),
					'ratioPortrait'   => __( 'Portrait (4:5)', 'contactpanda' ),
					'ratioLandscape'  => __( 'Landscape (4:3)', 'contactpanda' ),
					'showPrice'       => __( 'Show price', 'contactpanda' ),
					'showCategories'  => __( 'Show categories', 'contactpanda' ),
					'showBrands'      => __( 'Show producer / brand', 'contactpanda' ),
					'fullWidth'       => __( 'Full width', 'contactpanda' ),
					'uppercase'       => __( 'Uppercase text', 'contactpanda' ),
					'yes'             => __( 'Yes', 'contactpanda' ),
					'no'              => __( 'No', 'contactpanda' ),
					'fixedFooter'     => __( 'The footer is fixed and can be changed under Settings.', 'contactpanda' ),
					'contentTab'      => __( 'Content', 'contactpanda' ),
					'designTab'       => __( 'Design', 'contactpanda' ),
					'padding'         => __( 'Padding', 'contactpanda' ),
					'margin'          => __( 'Margin', 'contactpanda' ),
					'cornerRadius'    => __( 'Corner rounding', 'contactpanda' ),
					'linkValues'      => __( 'Link all values', 'contactpanda' ),
					'top'             => __( 'Top', 'contactpanda' ),
					'rightSide'       => __( 'Right', 'contactpanda' ),
					'bottom'          => __( 'Bottom', 'contactpanda' ),
					'leftSide'        => __( 'Left', 'contactpanda' ),
					'topLeft'         => __( 'Top left', 'contactpanda' ),
					'topRight'        => __( 'Top right', 'contactpanda' ),
					'bottomRight'     => __( 'Bottom right', 'contactpanda' ),
					'bottomLeft'      => __( 'Bottom left', 'contactpanda' ),
					'unit'            => __( 'Unit', 'contactpanda' ),
				),
			)
		);
	}

	public static function detected_theme_colors() {
		$colors = array();
		$sources = array( get_theme_mods(), get_option( 'fusion_options', array() ) );
		$walk = static function ( $value ) use ( &$colors, &$walk ) {
			if ( is_array( $value ) ) { foreach ( $value as $item ) $walk( $item ); return; }
			if ( is_string( $value ) && preg_match_all( '/#[0-9a-fA-F]{6}\b/', $value, $matches ) ) foreach ( $matches[0] as $color ) $colors[] = strtolower( $color );
		};
		foreach ( $sources as $source ) $walk( $source );
		return array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_hex_color', $colors ) ) ) ), 0, 16 );
	}

	public static function theme_colors() {
		$settings = self::settings();
		$colors = array( $settings['base_color'], $settings['background_color'], $settings['body_color'], $settings['text_color'] );
		$colors = array_merge( $colors, self::detected_theme_colors() );
		return array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_hex_color', $colors ) ) ) ), 0, 16 );
	}

	private static function event_filters() {
		if ( ! apply_filters( 'contactpanda_events_available', false ) ) return array();
		$posts = get_posts( array( 'post_type' => 'tribe_events', 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'event_date', 'order' => 'DESC' ) );
		return array_map( static function ( $post ) { return array( 'id' => (int) $post->ID, 'name' => get_the_title( $post ), 'image' => get_the_post_thumbnail_url( $post, 'medium' ) ?: '' ); }, $posts );
	}

	private static function post_filters() {
		$types = get_post_types( array( 'public' => true, 'show_ui' => true ), 'objects' );
		foreach ( array( 'attachment', 'product', 'tribe_events' ) as $excluded_type ) {
			unset( $types[ $excluded_type ] );
		}
		$output = array();
		foreach ( $types as $type => $object ) {
			$taxonomy = '';
			$taxonomies = get_object_taxonomies( $type, 'objects' );
			if ( isset( $taxonomies['category'] ) && ! empty( $taxonomies['category']->public ) ) {
				$taxonomy = 'category';
			} else {
				foreach ( $taxonomies as $taxonomy_name => $taxonomy_object ) {
					if ( 'post_format' !== $taxonomy_name && ! empty( $taxonomy_object->public ) && ! empty( $taxonomy_object->show_ui ) ) {
						$taxonomy = $taxonomy_name;
						break;
					}
				}
			}
			$terms = $taxonomy ? get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true, 'number' => 200 ) ) : array();
			$posts = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'date', 'order' => 'DESC' ) );
			$output[] = array(
				'id'         => $type,
				'name'       => $object->labels->name,
				'taxonomy'   => $taxonomy,
				'categories' => is_wp_error( $terms ) ? array() : array_map( static function ( $term ) { return array( 'id' => (int) $term->term_id, 'name' => $term->name ); }, $terms ),
				'posts'      => array_map( static function ( $post ) use ( $taxonomy ) { $term_ids = $taxonomy ? wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) ) : array(); return array( 'id' => (int) $post->ID, 'name' => get_the_title( $post ), 'image' => get_the_post_thumbnail_url( $post, 'medium' ) ?: '', 'excerpt' => wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post ) ), 24 ), 'term_ids' => is_wp_error( $term_ids ) ? array() : array_map( 'intval', $term_ids ) ); }, $posts ),
			);
		}
		return array( 'types' => $output );
	}

	private static function preferred_post_taxonomy( $post_type ) {
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		if ( isset( $taxonomies['category'] ) && ! empty( $taxonomies['category']->public ) ) {
			return 'category';
		}
		foreach ( $taxonomies as $taxonomy_name => $taxonomy_object ) {
			if ( 'post_format' !== $taxonomy_name && ! empty( $taxonomy_object->public ) && ! empty( $taxonomy_object->show_ui ) ) {
				return $taxonomy_name;
			}
		}
		return '';
	}

	private static function product_filters() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'categories' => array(), 'producers' => array(), 'products' => array() );
		}
		$categories = array();
		$category_terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'number' => 200 ) );
		if ( ! is_wp_error( $category_terms ) ) {
			foreach ( $category_terms as $term ) {
				$categories[] = array( 'id' => absint( $term->term_id ), 'name' => $term->name );
			}
		}
		$producers = array();
		$preferred = array( 'product_brand', 'pa_produzent', 'pa_hersteller', 'pwb-brand', 'yith_product_brand', 'berocket_brand' );
		foreach ( get_object_taxonomies( 'product', 'objects' ) as $taxonomy => $object ) {
			if ( in_array( $taxonomy, array( 'product_cat', 'product_tag', 'product_type', 'product_visibility', 'product_shipping_class' ), true ) ) {
				continue;
			}
			if ( ! in_array( $taxonomy, $preferred, true ) && 0 !== strpos( $taxonomy, 'pa_' ) ) {
				continue;
			}
			$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true, 'number' => 200 ) );
			if ( is_wp_error( $terms ) || ! $terms ) {
				continue;
			}
			$producers[] = array(
				'taxonomy' => $taxonomy,
				'label'    => $object->labels->singular_name ?: $object->label,
				'terms'    => array_map( static function ( $term ) { return array( 'id' => absint( $term->term_id ), 'name' => $term->name ); }, $terms ),
			);
		}
		$products = array();
		foreach ( wc_get_products( array( 'limit' => 200, 'status' => 'publish', 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects' ) ) as $product ) {
			$products[] = array( 'id' => $product->get_id(), 'name' => $product->get_name() );
		}
		return array( 'categories' => $categories, 'producers' => $producers, 'products' => $products );
	}

	public static function render_page() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to manage email templates.', 'contactpanda' ) );
		}
		$installed = ContactPanda_Newsletter_Schema::install_newsletters();
		if ( is_wp_error( $installed ) ) {
			wp_die( esc_html( $installed->get_error_message() ) );
		}
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only route.
		if ( in_array( $action, array( 'new', 'edit' ), true ) ) {
			self::render_editor();
			return;
		}
		self::render_list();
	}

	private static function render_list() {
		global $wpdb;
		$table = ContactPanda_Newsletter_Schema::newsletter_table_names()['templates'];
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$page = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
		$per_page = 20;
		$where = "WHERE status <> 'deleted'";
		$params = array();
		if ( '' !== $search ) {
			$where .= ' AND (name LIKE %s OR subject LIKE %s)';
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$params = array( $like, $like );
		}
		$count_sql = "SELECT COUNT(*) FROM `{$table}` {$where}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared when variables exist.
		$query = "SELECT * FROM `{$table}` {$where} ORDER BY updated_at DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( $query, array_merge( $params, array( $per_page, ( $page - 1 ) * $per_page ) ) ), ARRAY_A );
		$notice = get_transient( 'contactpanda_newsletter_notice_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'contactpanda_newsletter_notice_' . get_current_user_id() );
		}

		echo '<div class="wrap contactpanda-wrap contactpanda-newsletter-page"><header class="contactpanda-heading"><div class="contactpanda-avatar"><span class="dashicons dashicons-email-alt"></span></div><div><p class="contactpanda-eyebrow">ContactPanda</p><h1>' . esc_html__( 'Email templates', 'contactpanda' ) . '</h1><p>' . esc_html__( 'Create reusable, responsive email templates independently of Groundhogg.', 'contactpanda' ) . '</p></div><a class="button button-primary contactpanda-heading-action" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-newsletters&action=new' ) ) . '">' . esc_html__( 'Create template', 'contactpanda' ) . '</a></header>';
		if ( is_array( $notice ) ) {
			echo '<div class="notice notice-' . ( ! empty( $notice['success'] ) ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
		}
		echo '<section class="contactpanda-card"><form method="get" class="contactpanda-newsletter-search"><input type="hidden" name="page" value="contactpanda-newsletters"><label class="screen-reader-text" for="contactpanda-template-search">' . esc_html__( 'Search templates', 'contactpanda' ) . '</label><input id="contactpanda-template-search" type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search templates', 'contactpanda' ) . '"><button class="button">' . esc_html__( 'Search', 'contactpanda' ) . '</button></form>';
		if ( ! $rows ) {
			echo '<div class="contactpanda-empty"><span class="dashicons dashicons-email-alt"></span><h3>' . esc_html__( 'No templates found', 'contactpanda' ) . '</h3><p>' . esc_html__( 'Create the first email template or change the search.', 'contactpanda' ) . '</p></div></section></div>';
			return;
		}
		echo '<table class="widefat striped contactpanda-table contactpanda-template-table"><thead><tr><th>' . esc_html__( 'Name', 'contactpanda' ) . '</th><th>' . esc_html__( 'Subject', 'contactpanda' ) . '</th><th>' . esc_html__( 'Used in newsletters', 'contactpanda' ) . '</th><th>' . esc_html__( 'Used in automations', 'contactpanda' ) . '</th><th>' . esc_html__( 'Status', 'contactpanda' ) . '</th><th>' . esc_html__( 'Last modified', 'contactpanda' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$edit_url = admin_url( 'admin.php?page=contactpanda-newsletters&action=edit&template_id=' . absint( $row['id'] ) );
			$imported = ! empty( $row['imported_readonly'] );
			echo '<tr><td><strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $row['name'] ) . '</a></strong><div class="row-actions"><span><a href="' . esc_url( $edit_url ) . '">' . esc_html( $imported ? __( 'View archive', 'contactpanda' ) : __( 'Edit', 'contactpanda' ) ) . '</a> | </span>';
			self::row_action_form( $row['id'], 'duplicate', __( 'Duplicate', 'contactpanda' ) );
			if ( ! $imported ) {
				echo ' | ';
				self::row_action_form( $row['id'], 'archive', __( 'Archive', 'contactpanda' ), 'contactpanda-archive-template' );
			}
			$status_labels = array( 'draft' => __( 'Draft', 'contactpanda' ), 'published' => __( 'Published', 'contactpanda' ), 'archived' => __( 'Archived', 'contactpanda' ) );
			$status = $imported ? __( 'Imported archive', 'contactpanda' ) : ( $status_labels[ $row['status'] ] ?? $row['status'] );
			$usage = self::template_usage( absint( $row['id'] ) );
			echo '</div></td><td>' . esc_html( $row['subject'] ?: '—' ) . '</td><td>' . esc_html( count( $usage['campaigns'] ) ) . ( $usage['campaigns'] ? '<br><small>' . esc_html( implode( ', ', array_slice( $usage['campaigns'], 0, 3 ) ) ) . '</small>' : '' ) . '</td><td>' . esc_html( count( $usage['flows'] ) ) . ( $usage['flows'] ? '<br><small>' . esc_html( implode( ', ', array_slice( $usage['flows'], 0, 3 ) ) ) . '</small>' : '' ) . '</td><td>' . esc_html( $status ) . '</td><td>' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['updated_at'] ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		if ( $total > $per_page ) {
			$base = add_query_arg( array( 'page' => 'contactpanda-newsletters', 's' => $search, 'paged' => '%#%' ), admin_url( 'admin.php' ) );
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array( 'base' => $base, 'format' => '', 'current' => $page, 'total' => (int) ceil( $total / $per_page ) ) ) ) . '</div></div>';
		}
		echo '</section></div>';
	}

	public static function template_usage( $template_id ) {
		global $wpdb; $t = ContactPanda_Newsletter_Schema::newsletter_table_names(); $usage = array( 'campaigns' => array(), 'flows' => array() );
		$usage['campaigns'] = (array) $wpdb->get_col( $wpdb->prepare( "SELECT name FROM `{$t['campaigns']}` WHERE template_id=%d AND status NOT IN ('deleted','cancelled') ORDER BY updated_at DESC", $template_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		$flows = array();
		if ( class_exists( 'ContactPanda_Plus_Schema' ) ) {
			$plus_tables = ContactPanda_Plus_Schema::table_names();
			$flows_table = $plus_tables['flows'] ?? '';
			if ( $flows_table && $flows_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $flows_table ) ) ) ) {
				$flows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT name,draft_graph_json,published_graph_json FROM `{$flows_table}` WHERE status <> 'deleted' AND (draft_graph_json LIKE %s OR published_graph_json LIKE %s)", '%"template_id":' . absint( $template_id ) . '%', '%"template_id":' . absint( $template_id ) . '%' ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internally generated table.
			}
		}
		foreach ( $flows as $flow ) { foreach ( array( 'draft_graph_json', 'published_graph_json' ) as $key ) { $graph = json_decode( (string) $flow[ $key ], true ); foreach ( (array) ( $graph['nodes'] ?? array() ) as $node ) if ( 'email' === ( $node['type'] ?? '' ) && $template_id === absint( $node['config']['template_id'] ?? 0 ) ) { $usage['flows'][] = $flow['name']; break 2; } } }
		$usage['flows'] = array_values( array_unique( $usage['flows'] ) ); return $usage;
	}

	private static function row_action_form( $id, $operation, $label, $class = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-inline-row-action ' . esc_attr( $class ) . '"><input type="hidden" name="action" value="contactpanda_template_action"><input type="hidden" name="operation" value="' . esc_attr( $operation ) . '"><input type="hidden" name="template_id" value="' . esc_attr( $id ) . '">';
		wp_nonce_field( self::NONCE_ACTION );
		echo '<button type="submit" class="button-link">' . esc_html( $label ) . '</button></form>';
	}

	private static function render_editor() {
		$id = isset( $_GET['template_id'] ) ? absint( $_GET['template_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only editor route.
		$template = $id ? self::get_template( $id ) : false;
		if ( $id && ! $template ) {
			wp_die( esc_html__( 'The email template was not found.', 'contactpanda' ) );
		}
		if ( $template && ! empty( $template['imported_readonly'] ) ) {
			self::render_imported_template( $template );
			return;
		}
		if ( ! $template ) {
			$newsletter_settings = self::settings();
			$template = array(
				'id' => 0, 'name' => '', 'subject' => '', 'preview_text' => '',
				'from_name' => $newsletter_settings['from_name'],
				'reply_to' => $newsletter_settings['reply_to'], 'status' => 'draft',
				'email_background' => $newsletter_settings['background_color'], 'content_width' => $newsletter_settings['container_width'],
				'accent_color' => $newsletter_settings['base_color'], 'content_background' => $newsletter_settings['body_color'], 'text_color' => $newsletter_settings['text_color'], 'font_family' => $newsletter_settings['font_family'],
				'content_json' => wp_json_encode( array( array( 'type' => 'heading', 'content' => __( 'Your newsletter heading', 'contactpanda' ) ), array( 'type' => 'text', 'content' => __( 'Write your newsletter text here.', 'contactpanda' ) ) ) ),
			);
		}
		$blocks = json_decode( (string) $template['content_json'], true );
		if ( ! is_array( $blocks ) ) {
			$blocks = array();
		}
		echo '<div class="wrap contactpanda-wrap contactpanda-newsletter-editor-page"><header class="contactpanda-heading"><div><p class="contactpanda-eyebrow">ContactPanda</p><h1>' . esc_html( $id ? __( 'Edit email template', 'contactpanda' ) : __( 'Create email template', 'contactpanda' ) ) . '</h1><p>' . esc_html__( 'Build a reusable email from responsive content blocks.', 'contactpanda' ) . '</p></div><a class="button contactpanda-heading-action" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-newsletters' ) ) . '">' . esc_html__( 'Back to templates', 'contactpanda' ) . '</a></header>';
		$notice = get_transient( 'contactpanda_newsletter_notice_' . get_current_user_id() );
		if ( is_array( $notice ) ) {
			delete_transient( 'contactpanda_newsletter_notice_' . get_current_user_id() );
			echo '<div class="notice notice-' . ( ! empty( $notice['success'] ) ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
		}
		echo '<form id="contactpanda-newsletter-editor" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_save_template"><input type="hidden" name="template_id" value="' . esc_attr( $template['id'] ) . '"><input type="hidden" name="from_name" value="' . esc_attr( $template['from_name'] ) . '"><input type="hidden" name="reply_to" value="' . esc_attr( $template['reply_to'] ) . '"><input type="hidden" id="contactpanda-content-json" name="content_json" value="' . esc_attr( wp_json_encode( $blocks ) ) . '">';
		wp_nonce_field( self::NONCE_ACTION );
		echo '<section class="contactpanda-card contactpanda-newsletter-topbar"><div class="contactpanda-newsletter-meta"><div class="contactpanda-newsletter-primary-fields">';
		self::editor_field( 'name', __( 'Template name', 'contactpanda' ), $template['name'], 'text', true );
		self::editor_field( 'subject', __( 'Email subject', 'contactpanda' ), $template['subject'], 'text', true );
		self::editor_field( 'preview_text', __( 'Preview text', 'contactpanda' ), $template['preview_text'] );
		$published = 'published' === ( $template['status'] ?? 'draft' );
		echo '<div class="contactpanda-newsletter-editor-actions"><div class="contactpanda-newsletter-primary-buttons"><button type="submit" name="template_status" value="draft" class="button">' . esc_html__( 'Save as draft', 'contactpanda' ) . '</button><button type="submit" name="template_status" value="published" class="button button-primary">' . esc_html( $published ? __( 'Update', 'contactpanda' ) : __( 'Publish', 'contactpanda' ) ) . '</button><button type="button" class="button" id="contactpanda-preview-template">' . esc_html__( 'Preview', 'contactpanda' ) . '</button></div><div class="contactpanda-newsletter-test-row"><label><span>' . esc_html__( 'Test recipient', 'contactpanda' ) . '</span><input type="email" id="contactpanda-test-email" value="' . esc_attr( wp_get_current_user()->user_email ) . '"></label><button type="button" class="button" id="contactpanda-send-test">' . esc_html__( 'Send test', 'contactpanda' ) . '</button></div><p id="contactpanda-test-message" class="contactpanda-newsletter-message" aria-live="polite"></p></div>';
		$placeholder_labels = array(
			'{salutation}'       => __( 'Salutation', 'contactpanda' ),
			'{title_prefix}'     => __( 'Title before the name', 'contactpanda' ),
			'{first_name}'       => __( 'First name', 'contactpanda' ),
			'{last_name}'        => __( 'Last name', 'contactpanda' ),
			'{title_suffix}'     => __( 'Title after the name', 'contactpanda' ),
			'{full_name}'        => __( 'Full name', 'contactpanda' ),
			'{company}'          => __( 'Company', 'contactpanda' ),
			'{address}'          => __( 'Address', 'contactpanda' ),
			'{unsubscribe_link}' => __( 'Unsubscribe link', 'contactpanda' ),
		);
		echo '<div class="contactpanda-newsletter-placeholders"><strong>' . esc_html__( 'Available placeholders', 'contactpanda' ) . '</strong><div class="contactpanda-newsletter-placeholder-list">';
		foreach ( $placeholder_labels as $placeholder => $placeholder_label ) {
			echo '<span><span>' . esc_html( $placeholder_label ) . '</span> <code>' . esc_html( $placeholder ) . '</code></span>';
		}
		echo '</div><p>' . esc_html__( 'Add a fallback with a vertical bar, for example {first_name|there}.', 'contactpanda' ) . '</p></div></div></div><aside class="contactpanda-newsletter-template-sidebar"><h2>' . esc_html__( 'Email appearance', 'contactpanda' ) . '</h2><div class="contactpanda-newsletter-sidebar-fields">';
		self::editor_field( 'email_background', __( 'Email background color', 'contactpanda' ), $template['email_background'] ?? self::settings()['background_color'], 'color' );
		self::editor_field( 'accent_color', __( 'Accent color', 'contactpanda' ), $template['accent_color'] ?? self::settings()['base_color'], 'color' );
		self::editor_field( 'content_background', __( 'Content background', 'contactpanda' ), $template['content_background'] ?? self::settings()['body_color'], 'color' );
		self::editor_field( 'text_color', __( 'Text color', 'contactpanda' ), $template['text_color'] ?? self::settings()['text_color'], 'color' );
		self::editor_font_field( $template['font_family'] ?? self::settings()['font_family'] );
		self::editor_field( 'content_width', __( 'Email content width', 'contactpanda' ), $template['content_width'] ?? self::settings()['container_width'], 'number' );
		echo '<button type="submit" name="reapply_defaults" value="1" class="button" onclick="return confirm(' . esc_attr( wp_json_encode( __( 'Reapply the global email appearance? Content blocks remain unchanged.', 'contactpanda' ) ) ) . ');">' . esc_html__( 'Reapply global defaults', 'contactpanda' ) . '</button>';
		echo '</div></aside></section>';
		echo '<div class="contactpanda-newsletter-editor-layout"><aside class="contactpanda-card contactpanda-newsletter-palette"><h2>' . esc_html__( 'Content blocks', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Drag a block into the email or double-click it to append it. A single click only selects the block.', 'contactpanda' ) . '</p><div class="contactpanda-block-palette" aria-label="' . esc_attr__( 'Add content block', 'contactpanda' ) . '">';
		$palette = array( 'heading' => 'heading', 'text' => 'text', 'image' => 'format-image', 'gallery' => 'format-gallery', 'video' => 'video-alt3', 'columns' => 'columns', 'button' => 'button', 'phone_button' => 'phone', 'email_button' => 'email-alt', 'whatsapp_button' => 'format-chat', 'divider' => 'minus', 'spacer' => 'image-flip-vertical' );
		if ( class_exists( 'WooCommerce' ) ) {
			$palette['products'] = 'cart';
		}
		if ( apply_filters( 'contactpanda_events_available', false ) ) $palette['events'] = 'calendar-alt';
		$palette['posts'] = 'admin-post';
		foreach ( $palette as $type => $icon ) {
			echo '<button type="button" draggable="true" class="contactpanda-add-block" data-type="' . esc_attr( $type ) . '"><span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span><span>' . esc_html( self::block_label( $type ) ) . '</span></button>';
		}
		echo '</div><p class="contactpanda-palette-help"><a href="' . esc_url( admin_url( 'admin.php?page=contactpanda-settings&tab=newsletter' ) ) . '">' . esc_html__( 'Edit footer defaults and email style', 'contactpanda' ) . '</a></p></aside>';
		echo '<main class="contactpanda-card contactpanda-newsletter-builder"><div class="contactpanda-email-canvas" id="contactpanda-newsletter-blocks"></div><p id="contactpanda-no-blocks" class="contactpanda-email-drop-hint">' . esc_html__( 'Drag blocks here or double-click a block on the left.', 'contactpanda' ) . '</p></main>';
		echo '<aside class="contactpanda-card contactpanda-newsletter-inspector"><h2>' . esc_html__( 'Block settings', 'contactpanda' ) . '</h2><div id="contactpanda-block-inspector"><p class="contactpanda-muted">' . esc_html__( 'Select a block in the email to edit it.', 'contactpanda' ) . '</p></div></aside></div></form>';
		echo '<div id="contactpanda-newsletter-preview-modal" class="contactpanda-consent-modal contactpanda-preview-modal" hidden><div class="contactpanda-preview-panel"><header class="contactpanda-preview-header"><div><h2>' . esc_html__( 'Newsletter preview', 'contactpanda' ) . '</h2><div class="contactpanda-preview-modes"><button type="button" class="button is-active" data-preview-width="email" title="' . esc_attr__( 'Desktop', 'contactpanda' ) . '" aria-label="' . esc_attr__( 'Desktop', 'contactpanda' ) . '"><span class="dashicons dashicons-desktop"></span></button><button type="button" class="button" data-preview-width="768px" title="' . esc_attr__( 'Tablet', 'contactpanda' ) . '" aria-label="' . esc_attr__( 'Tablet', 'contactpanda' ) . '"><span class="dashicons dashicons-tablet"></span></button><button type="button" class="button" data-preview-width="390px" title="' . esc_attr__( 'Mobile', 'contactpanda' ) . '" aria-label="' . esc_attr__( 'Mobile', 'contactpanda' ) . '"><span class="dashicons dashicons-smartphone"></span></button></div></div><button type="button" class="button contactpanda-preview-close" id="contactpanda-close-newsletter-preview" title="' . esc_attr__( 'Close', 'contactpanda' ) . '" aria-label="' . esc_attr__( 'Close', 'contactpanda' ) . '"><span class="dashicons dashicons-no-alt"></span></button></header><div class="contactpanda-newsletter-preview-stage"><iframe id="contactpanda-newsletter-preview-frame" title="' . esc_attr__( 'Newsletter preview', 'contactpanda' ) . '"></iframe></div></div></div>';
		echo '</div>';
	}

	private static function render_imported_template( $template ) {
		echo '<div class="wrap contactpanda-wrap contactpanda-newsletter-page"><header class="contactpanda-heading"><div><p class="contactpanda-eyebrow">ContactPanda</p><h1>' . esc_html( $template['name'] ) . '</h1><p>' . esc_html__( 'Protected Groundhogg email archive', 'contactpanda' ) . '</p></div><a class="button contactpanda-heading-action" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-newsletters' ) ) . '">' . esc_html__( 'Back to templates', 'contactpanda' ) . '</a></header>';
		echo '<section class="contactpanda-card"><div class="notice notice-info inline"><p>' . esc_html__( 'This imported original is read only. Duplicate it to create an editable ContactPanda template.', 'contactpanda' ) . '</p></div><p><strong>' . esc_html__( 'Subject', 'contactpanda' ) . ':</strong> ' . esc_html( $template['subject'] ?: '—' ) . '</p><p><strong>' . esc_html__( 'Groundhogg ID', 'contactpanda' ) . ':</strong> ' . esc_html( $template['external_id'] ) . '</p>';
		self::row_action_form( $template['id'], 'duplicate', __( 'Create editable copy', 'contactpanda' ) );
		echo '<div class="contactpanda-newsletter-preview-stage" style="margin-top:20px"><iframe sandbox="allow-popups allow-popups-to-escape-sandbox" style="width:100%;min-height:680px;border:1px solid #dcdcde;background:#fff" title="' . esc_attr__( 'Imported email preview', 'contactpanda' ) . '" srcdoc="' . esc_attr( $template['content_html'] ) . '"></iframe></div></section></div>';
	}

	private static function editor_field( $name, $label, $value, $type = 'text', $required = false ) {
		if ( 'color' === $type ) {
			echo '<label class="contactpanda-settings-color-field contactpanda-email-color-field"><span>' . esc_html( $label ) . '</span><span class="contactpanda-settings-color-control"><input type="color" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( $required ? ' required' : '' ) . '><code class="contactpanda-settings-color-value">' . esc_html( $value ) . '</code></span><span class="contactpanda-theme-color-palette">';
			foreach ( self::theme_colors() as $color ) echo '<button type="button" class="contactpanda-settings-color-swatch" data-color="' . esc_attr( $color ) . '" title="' . esc_attr( $color ) . '" aria-label="' . esc_attr( sprintf( __( 'Use color %s', 'contactpanda' ), $color ) ) . '" style="background:' . esc_attr( $color ) . '"></button>';
			echo '</span></label>';
			return;
		}
		echo '<label><span>' . esc_html( $label ) . '</span><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( $required ? ' required' : '' ) . '></label>';
	}

	private static function editor_font_field( $current ) {
		$fonts = array(
			'Arial, sans-serif'                         => 'Arial',
			'Helvetica, Arial, sans-serif'              => 'Helvetica',
			'Georgia, serif'                            => 'Georgia',
			'"Times New Roman", Times, serif'           => 'Times New Roman',
			'Verdana, Arial, sans-serif'                 => 'Verdana',
			'Tahoma, Arial, sans-serif'                  => 'Tahoma',
			'"Trebuchet MS", Arial, sans-serif'         => 'Trebuchet MS',
		);
		$current = sanitize_text_field( $current );
		if ( $current && ! isset( $fonts[ $current ] ) ) $fonts[ $current ] = sprintf( __( 'Previously saved: %s', 'contactpanda' ), $current );
		echo '<label><span>' . esc_html__( 'Font family', 'contactpanda' ) . '</span><select name="font_family">';
		foreach ( $fonts as $value => $label ) echo '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select></label>';
	}

	private static function block_label( $type ) {
		$labels = array(
			'heading' => __( 'Heading', 'contactpanda' ), 'text' => __( 'Text', 'contactpanda' ),
			'image' => __( 'Image', 'contactpanda' ), 'gallery' => __( 'Image gallery', 'contactpanda' ), 'video' => __( 'Video', 'contactpanda' ), 'button' => __( 'Button', 'contactpanda' ),
			'columns' => __( 'Columns', 'contactpanda' ), 'phone_button' => __( 'Phone button', 'contactpanda' ), 'email_button' => __( 'Email button', 'contactpanda' ), 'whatsapp_button' => __( 'WhatsApp button', 'contactpanda' ),
			'divider' => __( 'Divider', 'contactpanda' ), 'spacer' => __( 'Spacer', 'contactpanda' ),
			'products' => __( 'Products', 'contactpanda' ), 'events' => __( 'Events', 'contactpanda' ), 'posts' => __( 'Posts', 'contactpanda' ), 'footer' => __( 'Footer', 'contactpanda' ),
		);
		return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
	}

	public static function save_template() {
		self::guard_request();
		global $wpdb;
		ContactPanda_Newsletter_Schema::install_newsletters();
		$table = ContactPanda_Newsletter_Schema::newsletter_table_names()['templates'];
		$id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
		if ( $id ) {
			$existing = self::get_template( $id );
			if ( $existing && ! empty( $existing['imported_readonly'] ) ) {
				self::notice_redirect( false, __( 'Imported originals are read only. Duplicate the template before editing it.', 'contactpanda' ), $id );
			}
		}
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		if ( '' === $name || '' === $subject ) {
			self::notice_redirect( false, __( 'Template name and subject are required.', 'contactpanda' ), $id );
		}
		$blocks = self::sanitize_blocks( isset( $_POST['content_json'] ) ? wp_unslash( $_POST['content_json'] ) : '[]' );
		$use_defaults = ! empty( $_POST['reapply_defaults'] ); $global_style = self::settings();
		$email_background = ! $use_defaults && isset( $_POST['email_background'] ) && sanitize_hex_color( wp_unslash( $_POST['email_background'] ) ) ? sanitize_hex_color( wp_unslash( $_POST['email_background'] ) ) : $global_style['background_color'];
		$accent_color = $use_defaults ? $global_style['base_color'] : ( sanitize_hex_color( wp_unslash( $_POST['accent_color'] ?? '' ) ) ?: $global_style['base_color'] );
		$content_background = $use_defaults ? $global_style['body_color'] : ( sanitize_hex_color( wp_unslash( $_POST['content_background'] ?? '' ) ) ?: $global_style['body_color'] );
		$text_color = $use_defaults ? $global_style['text_color'] : ( sanitize_hex_color( wp_unslash( $_POST['text_color'] ?? '' ) ) ?: $global_style['text_color'] );
		$font_family = $use_defaults ? $global_style['font_family'] : sanitize_text_field( wp_unslash( $_POST['font_family'] ?? $global_style['font_family'] ) );
		$content_width = $use_defaults ? absint( $global_style['container_width'] ) : min( 760, max( 480, absint( $_POST['content_width'] ?? $global_style['container_width'] ) ) );
		$data = array(
			'name' => $name,
			'subject' => $subject,
			'preview_text' => isset( $_POST['preview_text'] ) ? sanitize_text_field( wp_unslash( $_POST['preview_text'] ) ) : '',
			'from_name' => isset( $_POST['from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['from_name'] ) ) : '',
			'reply_to' => isset( $_POST['reply_to'] ) ? sanitize_email( wp_unslash( $_POST['reply_to'] ) ) : '',
			'status' => isset( $_POST['template_status'] ) && 'published' === sanitize_key( wp_unslash( $_POST['template_status'] ) ) ? 'published' : 'draft',
			'content_json' => wp_json_encode( $blocks ),
			'email_background' => $email_background,
			'accent_color' => $accent_color, 'content_background' => $content_background, 'text_color' => $text_color, 'font_family' => $font_family,
			'content_width' => $content_width,
			'content_html' => self::render_email( $blocks, isset( $_POST['preview_text'] ) ? sanitize_text_field( wp_unslash( $_POST['preview_text'] ) ) : '', array( 'background_color' => $email_background, 'base_color' => $accent_color, 'body_color' => $content_background, 'text_color' => $text_color, 'font_family' => $font_family, 'container_width' => $content_width ) ),
			'updated_at' => current_time( 'mysql' ),
		);
		if ( $id ) {
			$success = false !== $wpdb->update( $table, $data, array( 'id' => $id ) );
		} else {
			$data['created_by'] = get_current_user_id();
			$data['created_at'] = current_time( 'mysql' );
			$success = false !== $wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}
		self::notice_redirect( $success, $success ? __( 'The email template was saved.', 'contactpanda' ) : __( 'The email template could not be saved.', 'contactpanda' ), $id );
	}

	public static function template_action() {
		self::guard_request();
		global $wpdb;
		$table = ContactPanda_Newsletter_Schema::newsletter_table_names()['templates'];
		$id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
		$operation = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		$template = self::get_template( $id );
		$success = false;
		$message = __( 'The template action could not be completed.', 'contactpanda' );
		if ( $template && 'archive' === $operation ) {
			$usage = self::template_usage( $id );
			if ( $usage['campaigns'] || $usage['flows'] ) { $message = __( 'This template is still used by a newsletter or automation and cannot be archived.', 'contactpanda' ); }
			else { $success = false !== $wpdb->update( $table, array( 'status' => 'archived', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) ); $message = $success ? __( 'The template was archived.', 'contactpanda' ) : $message; }
		} elseif ( $template && 'duplicate' === $operation ) {
			unset( $template['id'] );
			$template['name'] = sprintf( __( '%s (copy)', 'contactpanda' ), $template['name'] );
			$template['status'] = 'draft';
			$template['external_source'] = '';
			$template['external_id'] = 0;
			$template['imported_readonly'] = 0;
			$template['created_by'] = get_current_user_id();
			$template['created_at'] = current_time( 'mysql' );
			$template['updated_at'] = current_time( 'mysql' );
			$success = false !== $wpdb->insert( $table, $template );
			$message = $success ? __( 'The template was duplicated.', 'contactpanda' ) : $message;
		}
		set_transient( 'contactpanda_newsletter_notice_' . get_current_user_id(), array( 'success' => $success, 'message' => $message ), MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-newsletters' ) );
		exit;
	}

	public static function preview_ajax() {
		self::guard_ajax();
		$blocks = self::sanitize_blocks( isset( $_POST['content_json'] ) ? wp_unslash( $_POST['content_json'] ) : '[]' );
		$preview_text = isset( $_POST['preview_text'] ) ? sanitize_text_field( wp_unslash( $_POST['preview_text'] ) ) : '';
		$overrides = self::posted_email_style();
		wp_send_json_success( array( 'html' => self::replace_placeholders( self::render_email( $blocks, $preview_text, $overrides ) ) ) );
	}

	public static function test_ajax() {
		self::guard_ajax();
		$recipient = isset( $_POST['recipient'] ) ? sanitize_email( wp_unslash( $_POST['recipient'] ) ) : '';
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$reply_to = isset( $_POST['reply_to'] ) ? sanitize_email( wp_unslash( $_POST['reply_to'] ) ) : '';
		$from_name = isset( $_POST['from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['from_name'] ) ) : '';
		if ( ! is_email( $recipient ) || '' === $subject ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid recipient and subject.', 'contactpanda' ) ), 400 );
		}
		$blocks = self::sanitize_blocks( isset( $_POST['content_json'] ) ? wp_unslash( $_POST['content_json'] ) : '[]' );
		$html = self::replace_placeholders( self::render_email( $blocks, isset( $_POST['preview_text'] ) ? sanitize_text_field( wp_unslash( $_POST['preview_text'] ) ) : '', self::posted_email_style() ) );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$newsletter_settings = self::settings();
		$site_sender = sanitize_email( $newsletter_settings['from_email'] ?: get_option( 'admin_email' ) );
		if ( $site_sender ) {
			$headers[] = 'From: ' . ( $from_name ?: wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) . ' <' . $site_sender . '>';
		}
		if ( $reply_to ) {
			$headers[] = 'Reply-To: ' . ( $from_name ? $from_name . ' ' : '' ) . '<' . $reply_to . '>';
		}
		$sent = wp_mail( $recipient, '[TEST] ' . $subject, $html, $headers );
		if ( ! $sent ) {
			wp_send_json_error( array( 'message' => __( 'WordPress could not hand the test email to the configured mail system.', 'contactpanda' ) ), 500 );
		}
		wp_send_json_success( array( 'message' => sprintf( __( 'The test email was sent to %s.', 'contactpanda' ), $recipient ) ) );
	}

	public static function products_ajax() {
		self::guard_ajax();
		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is currently unavailable.', 'contactpanda' ) ), 503 );
		}
		$blocks = self::sanitize_blocks( wp_json_encode( array( isset( $_POST['block'] ) ? json_decode( wp_unslash( $_POST['block'] ), true ) : array() ) ) );
		$block = $blocks && 'products' === $blocks[0]['type'] ? $blocks[0] : array( 'type' => 'products', 'source' => 'latest', 'count' => 2 );
		$products = array_map(
			static function ( $product ) {
				$categories = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
				$brands = array(); foreach ( array( 'product_brand', 'pwb-brand', 'yith_product_brand', 'pa_hersteller', 'pa_produzent' ) as $taxonomy ) { if ( taxonomy_exists( $taxonomy ) ) { $terms = wp_get_post_terms( $product->get_id(), $taxonomy, array( 'fields' => 'names' ) ); if ( ! is_wp_error( $terms ) && $terms ) { $brands = $terms; break; } } }
				return array(
					'id'    => $product->get_id(),
					'name'  => $product->get_name(),
					'image' => wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ) ?: wc_placeholder_img_src( 'woocommerce_thumbnail' ),
					'price' => html_entity_decode( wp_strip_all_tags( $product->get_price_html() ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
					'categories' => is_wp_error( $categories ) ? '' : implode( ', ', $categories ),
					'brands' => implode( ', ', $brands ),
				);
			},
			self::resolve_products( $block )
		);
		wp_send_json_success( array( 'products' => $products ) );
	}

	private static function posted_email_style() {
		$settings = self::settings();
		$background = isset( $_POST['email_background'] ) ? sanitize_hex_color( wp_unslash( $_POST['email_background'] ) ) : '';
		return array( 'background_color' => $background ?: $settings['background_color'], 'base_color' => sanitize_hex_color( wp_unslash( $_POST['accent_color'] ?? '' ) ) ?: $settings['base_color'], 'body_color' => sanitize_hex_color( wp_unslash( $_POST['content_background'] ?? '' ) ) ?: $settings['body_color'], 'text_color' => sanitize_hex_color( wp_unslash( $_POST['text_color'] ?? '' ) ) ?: $settings['text_color'], 'font_family' => sanitize_text_field( wp_unslash( $_POST['font_family'] ?? $settings['font_family'] ) ), 'container_width' => min( 760, max( 480, absint( $_POST['content_width'] ?? $settings['container_width'] ) ) ) );
	}

	private static function sanitize_blocks( $json ) {
		$decoded = json_decode( (string) $json, true );
		$blocks = array();
		foreach ( is_array( $decoded ) ? $decoded : array() as $block ) {
			$type = isset( $block['type'] ) ? sanitize_key( $block['type'] ) : '';
			if ( ! in_array( $type, array( 'heading', 'text', 'image', 'gallery', 'video', 'columns', 'button', 'phone_button', 'email_button', 'whatsapp_button', 'divider', 'spacer', 'products', 'events', 'posts' ), true ) ) {
				continue;
			}
			$clean = array(
				'id'        => isset( $block['id'] ) ? sanitize_key( $block['id'] ) : wp_generate_uuid4(),
				'type'      => $type,
				'alignment' => isset( $block['alignment'] ) && in_array( $block['alignment'], array( 'left', 'center', 'right' ), true ) ? $block['alignment'] : 'left',
				'background_color' => isset( $block['background_color'] ) && sanitize_hex_color( $block['background_color'] ) ? sanitize_hex_color( $block['background_color'] ) : '',
			);
			$default_padding = array( 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 );
			$default_margin = array( 'top' => 0, 'right' => 0, 'bottom' => 20, 'left' => 0 );
			$default_radius = array( 'top_left' => 0, 'top_right' => 0, 'bottom_right' => 0, 'bottom_left' => 0 );
			$clean['padding'] = self::sanitize_box_values( $block['padding'] ?? array(), $default_padding );
			$clean['margin'] = self::sanitize_box_values( $block['margin'] ?? array(), $default_margin );
			$clean['padding_linked'] = ! isset( $block['padding_linked'] ) || ! empty( $block['padding_linked'] );
			$clean['margin_linked'] = ! isset( $block['margin_linked'] ) || ! empty( $block['margin_linked'] );
			$clean['border_radius'] = self::sanitize_box_values( $block['border_radius'] ?? array(), $default_radius );
			$clean['radius_linked'] = ! isset( $block['radius_linked'] ) || ! empty( $block['radius_linked'] );
			$clean['radius_unit'] = isset( $block['radius_unit'] ) && '%' === $block['radius_unit'] ? '%' : 'px';
			if ( in_array( $type, array( 'heading', 'text' ), true ) ) {
				$clean['content'] = isset( $block['content'] ) ? wp_kses_post( $block['content'] ) : '';
				if ( 'heading' === $type ) {
					$clean['level'] = isset( $block['level'] ) && in_array( strtolower( $block['level'] ), array( 'h1', 'h2', 'h3', 'h4' ), true ) ? strtolower( $block['level'] ) : 'h2';
					$clean['text_color'] = isset( $block['text_color'] ) && sanitize_hex_color( $block['text_color'] ) ? sanitize_hex_color( $block['text_color'] ) : '';
				}
			} elseif ( 'image' === $type ) {
				$clean['url'] = isset( $block['url'] ) ? esc_url_raw( $block['url'] ) : '';
				$clean['alt'] = isset( $block['alt'] ) ? sanitize_text_field( $block['alt'] ) : '';
				$clean['attachment_id'] = isset( $block['attachment_id'] ) ? absint( $block['attachment_id'] ) : 0;
				$clean['width_unit'] = isset( $block['width_unit'] ) && 'px' === $block['width_unit'] ? 'px' : '%';
				$maximum = 'px' === $clean['width_unit'] ? 760 : 100;
				$clean['width'] = min( $maximum, max( 10, isset( $block['width'] ) ? absint( $block['width'] ) : ( 'px' === $clean['width_unit'] ? 600 : 100 ) ) );
				$clean = array_merge( $clean, self::sanitize_caption_values( $block ) );
			} elseif ( 'video' === $type ) {
				$clean['url'] = isset( $block['url'] ) ? esc_url_raw( $block['url'] ) : '';
				$clean['thumbnail_url'] = isset( $block['thumbnail_url'] ) ? esc_url_raw( $block['thumbnail_url'] ) : '';
				$clean['thumbnail_id'] = isset( $block['thumbnail_id'] ) ? absint( $block['thumbnail_id'] ) : 0;
				$clean['title'] = isset( $block['title'] ) ? sanitize_text_field( $block['title'] ) : '';
				$clean['button_label'] = isset( $block['button_label'] ) ? sanitize_text_field( $block['button_label'] ) : __( 'Watch video', 'contactpanda' );
				$clean['show_provider_badge'] = ! isset( $block['show_provider_badge'] ) || ! empty( $block['show_provider_badge'] );
				$clean['badge_position'] = isset( $block['badge_position'] ) && 'top' === $block['badge_position'] ? 'top' : 'bottom';
			} elseif ( 'gallery' === $type ) {
				$clean['columns'] = min( 3, max( 1, absint( $block['columns'] ?? 3 ) ) );
				$clean['tablet_columns'] = min( 3, max( 1, absint( $block['tablet_columns'] ?? 2 ) ) );
				$clean['mobile_columns'] = min( 3, max( 1, absint( $block['mobile_columns'] ?? 1 ) ) );
				$clean['layout'] = isset( $block['layout'] ) && 'masonry' === $block['layout'] ? 'masonry' : 'grid';
				$clean['image_format'] = isset( $block['image_format'] ) && 'original' === $block['image_format'] ? 'original' : 'fixed';
				$clean['image_ratio'] = isset( $block['image_ratio'] ) && in_array( $block['image_ratio'], array( '1:1', '4:5', '4:3' ), true ) ? $block['image_ratio'] : '1:1';
				$clean['images'] = array();
				foreach ( array_slice( is_array( $block['images'] ?? null ) ? $block['images'] : array(), 0, 6 ) as $image ) {
					$url = esc_url_raw( $image['url'] ?? '' );
					if ( ! $url ) continue;
					$clean['images'][] = array_merge( array( 'url' => $url, 'attachment_id' => absint( $image['attachment_id'] ?? 0 ), 'alt' => sanitize_text_field( $image['alt'] ?? '' ), 'link' => esc_url_raw( $image['link'] ?? '' ) ), self::sanitize_caption_values( $image ) );
				}
			} elseif ( 'columns' === $type ) {
				$clean['columns'] = min( 3, max( 2, absint( $block['columns'] ?? 2 ) ) );
				$clean['children'] = array();
				foreach ( array_slice( is_array( $block['children'] ?? null ) ? $block['children'] : array(), 0, $clean['columns'] ) as $column ) {
					$children = self::sanitize_blocks( wp_json_encode( is_array( $column ) ? $column : array() ) );
					$clean['children'][] = array_values( array_filter( $children, static function ( $child ) { return 'columns' !== ( $child['type'] ?? '' ); } ) );
				}
				while ( count( $clean['children'] ) < $clean['columns'] ) $clean['children'][] = array();
			} elseif ( in_array( $type, array( 'phone_button', 'email_button', 'whatsapp_button' ), true ) ) {
				$clean['label'] = sanitize_text_field( $block['label'] ?? self::block_label( $type ) );
				$clean['value'] = sanitize_text_field( $block['value'] ?? '' );
				$clean['message'] = sanitize_textarea_field( $block['message'] ?? '' );
				foreach ( array( 'button_color', 'text_color' ) as $color ) $clean[ $color ] = isset( $block[ $color ] ) && sanitize_hex_color( $block[ $color ] ) ? sanitize_hex_color( $block[ $color ] ) : '';
				$clean['full_width'] = ! empty( $block['full_width'] );
			} elseif ( 'button' === $type ) {
				$clean['label'] = isset( $block['label'] ) ? sanitize_text_field( $block['label'] ) : __( 'Read more', 'contactpanda' );
				$clean['url'] = isset( $block['url'] ) ? esc_url_raw( $block['url'] ) : '';
				foreach ( array( 'button_color', 'border_color', 'text_color' ) as $color ) $clean[ $color ] = isset( $block[ $color ] ) && sanitize_hex_color( $block[ $color ] ) ? sanitize_hex_color( $block[ $color ] ) : '';
				$clean['full_width'] = ! empty( $block['full_width'] );
				$clean['uppercase'] = ! empty( $block['uppercase'] );
			} elseif ( 'spacer' === $type ) {
				$clean['height'] = min( 120, max( 8, isset( $block['height'] ) ? absint( $block['height'] ) : 24 ) );
			} elseif ( 'products' === $type ) {
				$clean['source'] = isset( $block['source'] ) && in_array( $block['source'], array( 'latest', 'category', 'producer', 'featured', 'sale', 'manual' ), true ) ? $block['source'] : 'latest';
				$clean['count'] = min( 6, max( 1, absint( $block['count'] ?? 2 ) ) );
				$clean['columns'] = min( 3, max( 1, absint( $block['columns'] ?? 2 ) ) );
				$clean['image_ratio'] = isset( $block['image_ratio'] ) && in_array( $block['image_ratio'], array( '1:1', '4:5', '4:3' ), true ) ? $block['image_ratio'] : '1:1';
				$clean['category_id'] = isset( $block['category_id'] ) ? absint( $block['category_id'] ) : 0;
				$clean['producer_taxonomy'] = isset( $block['producer_taxonomy'] ) ? sanitize_key( $block['producer_taxonomy'] ) : '';
				$clean['producer_term_id'] = isset( $block['producer_term_id'] ) ? absint( $block['producer_term_id'] ) : 0;
				$ids = isset( $block['product_ids'] ) && is_array( $block['product_ids'] ) ? array_map( 'absint', $block['product_ids'] ) : array();
				$clean['product_ids'] = array_slice( array_values( array_filter( array_unique( $ids ) ) ), 0, 6 );
				$clean['show_price'] = ! isset( $block['show_price'] ) || ! empty( $block['show_price'] );
				$clean['show_categories'] = ! empty( $block['show_categories'] );
				$clean['show_brands'] = ! empty( $block['show_brands'] );
			} elseif ( 'events' === $type ) {
				$ids = isset( $block['event_ids'] ) && is_array( $block['event_ids'] ) ? array_map( 'absint', $block['event_ids'] ) : array();
				$clean['event_ids'] = array_slice( array_values( array_filter( array_unique( $ids ) ) ), 0, 6 );
				$clean['count'] = min( 6, max( 1, absint( $block['count'] ?? 2 ) ) );
				$clean['columns'] = min( 3, max( 1, absint( $block['columns'] ?? 2 ) ) );
				$clean['image_ratio'] = isset( $block['image_ratio'] ) && in_array( $block['image_ratio'], array( '1:1', '4:5', '4:3' ), true ) ? $block['image_ratio'] : '1:1';
			} elseif ( 'posts' === $type ) {
				$post_type = isset( $block['post_type'] ) ? sanitize_key( $block['post_type'] ) : 'post';
				$post_type_object = get_post_type_object( $post_type );
				if ( ! $post_type_object || empty( $post_type_object->public ) || in_array( $post_type, array( 'attachment', 'product', 'tribe_events' ), true ) ) {
					$post_type = 'post';
				}
				$post_taxonomy = isset( $block['post_taxonomy'] ) ? sanitize_key( $block['post_taxonomy'] ) : '';
				if ( ! $post_taxonomy ) {
					$post_taxonomy = self::preferred_post_taxonomy( $post_type );
				}
				if ( $post_taxonomy && ! in_array( $post_taxonomy, get_object_taxonomies( $post_type ), true ) ) {
					$post_taxonomy = '';
				}
				$clean['source'] = isset( $block['source'] ) && in_array( $block['source'], array( 'latest', 'category', 'manual' ), true ) ? $block['source'] : 'latest';
				$clean['post_type'] = $post_type;
				$clean['post_taxonomy'] = $post_taxonomy;
				$clean['count'] = min( 6, max( 1, absint( $block['count'] ?? 2 ) ) );
				$clean['columns'] = min( 3, max( 1, absint( $block['columns'] ?? 2 ) ) );
				$clean['image_ratio'] = isset( $block['image_ratio'] ) && in_array( $block['image_ratio'], array( '1:1', '4:5', '4:3' ), true ) ? $block['image_ratio'] : '1:1';
				$clean['category_id'] = absint( $block['category_id'] ?? 0 );
				$ids = isset( $block['post_ids'] ) && is_array( $block['post_ids'] ) ? array_map( 'absint', $block['post_ids'] ) : array();
				$clean['post_ids'] = array_slice( array_values( array_filter( array_unique( $ids ) ) ), 0, 6 );
				$clean['show_image'] = ! isset( $block['show_image'] ) || ! empty( $block['show_image'] );
				$clean['show_title'] = ! isset( $block['show_title'] ) || ! empty( $block['show_title'] );
				$clean['show_excerpt'] = ! isset( $block['show_excerpt'] ) || ! empty( $block['show_excerpt'] );
				if ( ! $clean['show_image'] && ! $clean['show_title'] && ! $clean['show_excerpt'] ) {
					$clean['show_title'] = true;
				}
			}
			$blocks[] = $clean;
		}
		return array_slice( $blocks, 0, 100 );
	}

	private static function sanitize_box_values( $values, $defaults ) {
		$values = is_array( $values ) ? $values : array();
		$result = array();
		foreach ( $defaults as $key => $default ) {
			$result[ $key ] = min( 200, max( 0, isset( $values[ $key ] ) ? absint( $values[ $key ] ) : absint( $default ) ) );
		}
		return $result;
	}

	private static function sanitize_css_color( $value, $fallback = '' ) {
		$value = trim( (string) $value );
		$hex = sanitize_hex_color( $value );
		if ( $hex ) return $hex;
		if ( preg_match( '/^rgba?\(\s*(?:\d{1,3}\s*,\s*){2}\d{1,3}(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/', $value ) ) return $value;
		return $fallback;
	}

	private static function sanitize_caption_values( $source ) {
		return array(
			'caption' => sanitize_text_field( $source['caption'] ?? '' ),
			'caption_position' => isset( $source['caption_position'] ) && in_array( $source['caption_position'], array( 'top', 'center', 'bottom' ), true ) ? $source['caption_position'] : 'bottom',
			'caption_background' => self::sanitize_css_color( $source['caption_background'] ?? '', 'rgba(0,0,0,0.65)' ),
			'caption_color' => sanitize_hex_color( $source['caption_color'] ?? '' ) ?: '#ffffff',
			'caption_size' => min( 36, max( 10, absint( $source['caption_size'] ?? 14 ) ) ),
			'caption_weight' => isset( $source['caption_weight'] ) && in_array( (string) $source['caption_weight'], array( '400', '600', '700' ), true ) ? (string) $source['caption_weight'] : '600',
			'caption_uppercase' => ! empty( $source['caption_uppercase'] ),
			'caption_radius' => min( 40, max( 0, absint( $source['caption_radius'] ?? 4 ) ) ),
		);
	}

	private static function block_box_style( $block ) {
		$padding = self::sanitize_box_values( $block['padding'] ?? array(), array( 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 ) );
		$margin = self::sanitize_box_values( $block['margin'] ?? array(), array( 'top' => 0, 'right' => 0, 'bottom' => 20, 'left' => 0 ) );
		$radius = self::sanitize_box_values( $block['border_radius'] ?? array(), array( 'top_left' => 0, 'top_right' => 0, 'bottom_right' => 0, 'bottom_left' => 0 ) );
		$unit = isset( $block['radius_unit'] ) && '%' === $block['radius_unit'] ? '%' : 'px';
		$style = 'box-sizing:border-box;';
		if ( ! empty( $block['background_color'] ) ) $style .= 'background:' . sanitize_hex_color( $block['background_color'] ) . ';';
		$style .= sprintf( 'padding:%dpx %dpx %dpx %dpx;', $padding['top'], $padding['right'], $padding['bottom'], $padding['left'] );
		$style .= sprintf( 'margin:%dpx %dpx %dpx %dpx;', $margin['top'], $margin['right'], $margin['bottom'], $margin['left'] );
		$style .= sprintf( 'border-radius:%d%s %d%s %d%s %d%s;', $radius['top_left'], $unit, $radius['top_right'], $unit, $radius['bottom_right'], $unit, $radius['bottom_left'], $unit );
		return $style;
	}

	private static function caption_html( $source, $font ) {
		if ( empty( $source['caption'] ) ) return '';
		$position = $source['caption_position'] ?? 'bottom';
		$position_style = 'top' === $position ? 'top:5px;' : ( 'center' === $position ? 'top:50%;transform:translate(-50%,-50%);' : 'bottom:5px;' );
		$transform = 'center' === $position ? '' : 'transform:translateX(-50%);';
		return '<span class="contactpanda-email-caption" style="position:absolute;z-index:2;left:50%;' . esc_attr( $position_style . $transform ) . 'display:inline-block;box-sizing:border-box;max-width:calc(100% - 10px);padding:5px 9px;background:' . self::sanitize_css_color( $source['caption_background'] ?? '', 'rgba(0,0,0,0.65)' ) . ';color:' . ( sanitize_hex_color( $source['caption_color'] ?? '' ) ?: '#ffffff' ) . ';border-radius:' . absint( $source['caption_radius'] ?? 4 ) . 'px;font:' . esc_attr( $source['caption_weight'] ?? '600' ) . ' ' . absint( $source['caption_size'] ?? 14 ) . 'px/1.25 ' . esc_attr( $font ) . ';text-align:center;text-transform:' . ( ! empty( $source['caption_uppercase'] ) ? 'uppercase' : 'none' ) . ';white-space:normal">' . esc_html( $source['caption'] ) . '</span>';
	}

	private static function render_fragment( $blocks, $settings ) {
		$html = self::render_email( $blocks, '', $settings );
		$styles = '';
		if ( preg_match_all( '/<style[^>]*>(.*?)<\/style>/s', $html, $style_matches ) ) foreach ( $style_matches[1] as $style ) $styles .= '<style>' . $style . '</style>';
		return preg_match( '/<!--contactpanda-content-start-->(.*)<!--contactpanda-content-end-->/sU', $html, $match ) ? $styles . $match[1] : '';
	}

	private static function render_email( $blocks, $preview_text = '', $overrides = array() ) {
		$settings = wp_parse_args( (array) $overrides, self::settings() );
		$font = esc_attr( $settings['font_family'] );
		$text_color = sanitize_hex_color( $settings['text_color'] ) ?: '#39495a';
		$base_color = sanitize_hex_color( $settings['base_color'] ) ?: '#4253b5';
		$body = '';
		$responsive_css = '';
		foreach ( $blocks as $block ) {
			$type = $block['type'];
			$alignment = isset( $block['alignment'] ) ? $block['alignment'] : 'left';
			$box_style = self::block_box_style( $block );
			if ( 'heading' === $type ) {
				$level = in_array( $block['level'] ?? 'h2', array( 'h1', 'h2', 'h3', 'h4' ), true ) ? $block['level'] : 'h2';
				$size = array( 'h1' => 34, 'h2' => 28, 'h3' => 23, 'h4' => 19 )[ $level ];
				$body .= '<' . $level . ' style="' . esc_attr( $box_style ) . 'color:' . esc_attr( ! empty( $block['text_color'] ) ? $block['text_color'] : $text_color ) . ';text-align:' . esc_attr( $alignment ) . ';font:700 ' . $size . 'px/1.25 ' . $font . '">' . wp_kses_post( $block['content'] ) . '</' . $level . '>';
			} elseif ( 'text' === $type ) {
				$body .= '<div style="' . esc_attr( $box_style ) . 'color:' . esc_attr( $text_color ) . ';text-align:' . esc_attr( $alignment ) . ';font:16px/1.6 ' . $font . '">' . wpautop( wp_kses_post( $block['content'] ) ) . '</div>';
			} elseif ( 'image' === $type && ! empty( $block['url'] ) ) {
				$unit = isset( $block['width_unit'] ) && 'px' === $block['width_unit'] ? 'px' : '%';
				$body .= '<p style="' . esc_attr( $box_style ) . 'text-align:' . esc_attr( $alignment ) . '"><span style="position:relative;display:inline-block;width:' . absint( $block['width'] ?? 100 ) . $unit . ';max-width:100%"><img src="' . esc_url( $block['url'] ) . '" alt="' . esc_attr( $block['alt'] ) . '" style="display:block;width:100%;max-width:100%;height:auto;border:0">' . self::caption_html( $block, $font ) . '</span></p>';
			} elseif ( 'video' === $type && ! empty( $block['url'] ) ) {
				$thumbnail = $block['thumbnail_url'] ?? '';
				if ( ! $thumbnail && preg_match( '~(?:youtu\\.be/|youtube(?:-nocookie)?\\.com/(?:watch\\?v=|embed/|shorts/))([A-Za-z0-9_-]{6,})~', $block['url'], $match ) ) $thumbnail = 'https://i.ytimg.com/vi/' . rawurlencode( $match[1] ) . '/hqdefault.jpg';
				$label = $block['button_label'] ?: __( 'Watch video', 'contactpanda' );
				$provider = preg_match( '/youtu(?:\.be|be\.com)/i', $block['url'] ) ? 'YouTube' : ( preg_match( '/vimeo\.com/i', $block['url'] ) ? 'Vimeo' : '' );
				$badge = $provider && ! empty( $block['show_provider_badge'] ) ? '<span class="contactpanda-video-provider-email" style="position:absolute;right:8px;' . ( 'top' === ( $block['badge_position'] ?? 'bottom' ) ? 'top:8px' : 'bottom:8px' ) . ';display:inline-block;border-radius:4px;background:' . ( 'YouTube' === $provider ? '#ff0000' : '#1ab7ea' ) . ';color:#fff;padding:4px 7px;font:700 11px/1 ' . esc_attr( $font ) . '">▶ ' . esc_html( $provider ) . '</span>' : '';
				$button = '<span class="contactpanda-email-video-button" style="display:inline-block;margin-top:' . ( $thumbnail ? '12px' : '0' ) . ';border-radius:5px;background:' . esc_attr( $base_color ) . ';color:#fff;padding:10px 18px;font:700 15px/1.2 ' . $font . ';text-align:center">▶ ' . esc_html( $label ) . '</span>';
				$visual = $thumbnail ? '<span class="contactpanda-email-video-visual" style="display:block;position:relative"><img src="' . esc_url( $thumbnail ) . '" alt="' . esc_attr( $block['title'] ?: $label ) . '" style="display:block;width:100%;max-width:100%;height:auto;border:0">' . $badge . '</span>' . $button : $button;
				$body .= '<p style="' . esc_attr( $box_style ) . 'text-align:' . esc_attr( $alignment ) . '"><a class="contactpanda-email-video-link" href="' . esc_url( $block['url'] ) . '" target="_blank" rel="noopener noreferrer" style="display:inline-block;max-width:100%;text-decoration:none">' . $visual . '</a></p>';
			} elseif ( 'gallery' === $type && ! empty( $block['images'] ) ) {
				$columns = min( 3, max( 1, absint( $block['columns'] ?? 3 ) ) ); $width = 100 / $columns;
				$tablet_columns = min( 3, max( 1, absint( $block['tablet_columns'] ?? 2 ) ) );
				$mobile_columns = min( 3, max( 1, absint( $block['mobile_columns'] ?? 1 ) ) );
				$gallery_class = 'contactpanda-gallery-' . sanitize_html_class( $block['id'] ?? wp_generate_uuid4() );
				$responsive_css .= '@media only screen and (max-width:900px){.' . $gallery_class . ' .contactpanda-gallery-item{width:' . ( 100 / $tablet_columns ) . '%!important}.' . $gallery_class . '.is-masonry{column-count:' . $tablet_columns . '!important}}';
				$responsive_css .= '@media only screen and (max-width:600px){.' . $gallery_class . ' .contactpanda-gallery-item{width:' . ( 100 / $mobile_columns ) . '%!important}.' . $gallery_class . '.is-masonry{column-count:' . $mobile_columns . '!important}}';
				$cell_width = max( 120, (int) floor( ( absint( $settings['container_width'] ?? 600 ) - 16 ) / $columns ) );
				$ratio = array( '4:5' => 1.25, '4:3' => .75, '1:1' => 1 )[ $block['image_ratio'] ?? '1:1' ] ?? 1;
				$fixed_height = max( 90, (int) round( $cell_width * $ratio ) );
				$image_html = static function ( $image ) use ( $block, $fixed_height, $font ) {
					$fixed = 'fixed' === ( $block['image_format'] ?? 'fixed' );
					$attributes = $fixed ? ' height="' . absint( $fixed_height ) . '"' : '';
					$style = 'display:block;width:100%;max-width:100%;' . ( $fixed ? 'height:' . absint( $fixed_height ) . 'px;object-fit:cover;' : 'height:auto;' ) . 'border:0';
					$html = '<span style="position:relative;display:block"><img src="' . esc_url( $image['url'] ) . '" alt="' . esc_attr( $image['alt'] ) . '"' . $attributes . ' style="' . esc_attr( $style ) . '">' . self::caption_html( $image, $font ) . '</span>';
					return ! empty( $image['link'] ) ? '<a href="' . esc_url( $image['link'] ) . '" target="_blank" rel="noopener noreferrer" style="display:block;text-decoration:none">' . $html . '</a>' : $html;
				};
				if ( 'masonry' === ( $block['layout'] ?? 'grid' ) ) { $body .= '<div class="' . esc_attr( $gallery_class ) . ' is-masonry" style="' . esc_attr( $box_style ) . 'column-count:' . absint( $columns ) . ';column-gap:8px">'; foreach ( $block['images'] as $image ) $body .= '<div style="break-inside:avoid;margin:0 0 8px">' . $image_html( $image ) . '</div>'; $body .= '</div>'; }
				else { $body .= '<div class="' . esc_attr( $gallery_class ) . '" style="' . esc_attr( $box_style ) . 'font-size:0">'; foreach ( $block['images'] as $image ) $body .= '<div class="contactpanda-gallery-item" style="display:inline-block;box-sizing:border-box;width:' . esc_attr( $width ) . '%;padding:4px;vertical-align:top">' . $image_html( $image ) . '</div>'; $body .= '</div>'; }
			} elseif ( 'columns' === $type && ! empty( $block['children'] ) ) {
				$columns = min( 3, max( 2, absint( $block['columns'] ?? 2 ) ) ); $width = 100 / $columns;
				$body .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="' . esc_attr( $box_style ) . '"><tr>';
				foreach ( array_slice( $block['children'], 0, $columns ) as $children ) $body .= '<td class="contactpanda-column-cell" width="' . esc_attr( $width ) . '%" valign="top" style="box-sizing:border-box;padding:6px">' . self::render_fragment( $children, $settings ) . '</td>';
				$body .= '</tr></table>';
			} elseif ( in_array( $type, array( 'phone_button', 'email_button', 'whatsapp_button' ), true ) && ! empty( $block['value'] ) ) {
				$value = trim( (string) $block['value'] ); $icon = '☎'; $href = 'tel:' . preg_replace( '/[^+0-9]/', '', $value );
				if ( 'email_button' === $type ) { $icon = '✉'; $href = 'mailto:' . sanitize_email( $value ); }
				if ( 'whatsapp_button' === $type ) { $icon = '●'; $href = 'https://wa.me/' . preg_replace( '/\D+/', '', $value ); if ( ! empty( $block['message'] ) ) $href .= '?text=' . rawurlencode( $block['message'] ); }
				$button_color = ! empty( $block['button_color'] ) ? $block['button_color'] : $base_color;
				$body .= '<p style="' . esc_attr( $box_style ) . 'text-align:' . esc_attr( $alignment ) . '"><a href="' . esc_url( $href, array( 'tel', 'mailto', 'https' ) ) . '" style="display:' . ( ! empty( $block['full_width'] ) ? 'block' : 'inline-block' ) . ';box-sizing:border-box;' . ( ! empty( $block['full_width'] ) ? 'width:100%;text-align:center;' : '' ) . 'border-radius:5px;background:' . esc_attr( $button_color ) . ';color:' . esc_attr( ! empty( $block['text_color'] ) ? $block['text_color'] : '#ffffff' ) . ';padding:12px 20px;font:700 15px/1.2 ' . $font . ';text-decoration:none"><span aria-hidden="true">' . esc_html( $icon ) . '</span>&nbsp; ' . esc_html( $block['label'] ) . '</a></p>';
			} elseif ( 'button' === $type && ! empty( $block['url'] ) ) {
				$button_color = ! empty( $block['button_color'] ) ? $block['button_color'] : $base_color;
				$body .= '<p style="' . esc_attr( $box_style ) . 'text-align:' . esc_attr( $alignment ) . '"><a href="' . esc_url( $block['url'] ) . '" style="display:' . ( ! empty( $block['full_width'] ) ? 'block' : 'inline-block' ) . ';box-sizing:border-box;' . ( ! empty( $block['full_width'] ) ? 'width:100%;text-align:center;' : '' ) . 'border:1px solid ' . esc_attr( ! empty( $block['border_color'] ) ? $block['border_color'] : $button_color ) . ';border-radius:5px;background:' . esc_attr( $button_color ) . ';color:' . esc_attr( ! empty( $block['text_color'] ) ? $block['text_color'] : '#ffffff' ) . ';padding:12px 20px;font:700 15px/1.2 ' . $font . ';text-decoration:none;text-transform:' . ( ! empty( $block['uppercase'] ) ? 'uppercase' : 'none' ) . '">' . esc_html( $block['label'] ) . '</a></p>';
			} elseif ( 'divider' === $type ) {
				$body .= '<div style="' . esc_attr( $box_style ) . '"><hr style="margin:0;border:0;border-top:1px solid #dfe5eb"></div>';
			} elseif ( 'spacer' === $type ) {
				$body .= '<div style="' . esc_attr( $box_style ) . 'height:' . absint( $block['height'] ) . 'px;line-height:' . absint( $block['height'] ) . 'px">&nbsp;</div>';
			} elseif ( 'products' === $type ) {
				$content = self::render_products( $block, $settings );
				$body .= '<div style="' . esc_attr( $box_style ) . '">' . $content . '</div>';
			} elseif ( 'events' === $type ) {
				$content = self::render_events( $block, $settings );
				$body .= '<div style="' . esc_attr( $box_style ) . '">' . $content . '</div>';
			} elseif ( 'posts' === $type ) {
				$content = self::render_posts( $block, $settings );
				$body .= '<div style="' . esc_attr( $box_style ) . '">' . $content . '</div>';
			}
		}
		$footer = self::render_footer( $settings );
		if ( false === strpos( $footer, '{unsubscribe_link}' ) ) {
			$footer .= '<p>{unsubscribe_link}</p>';
		}
		if ( 'woocommerce' === ( $settings['email_layout'] ?? '' ) && class_exists( 'WooCommerce' ) ) {
			ob_start(); do_action( 'woocommerce_email_header', '', null ); echo '<!--contactpanda-content-start-->' . wp_kses_post( $body ) . '<!--contactpanda-content-end-->'; echo '<div style="margin-top:28px;padding-top:18px;border-top:1px solid #dfe5eb;text-align:center;color:#6b7785;font:11px/1.5 ' . $font . '">' . wp_kses_post( $footer ) . '</div>'; do_action( 'woocommerce_email_footer', null ); return (string) ob_get_clean();
		}
		$logo = $settings['logo_url'] ? '<p style="margin:0 0 24px;text-align:center"><img src="' . esc_url( $settings['logo_url'] ) . '" alt="' . esc_attr( $settings['company_name'] ) . '" style="display:inline-block;max-width:220px;max-height:90px;width:auto;height:auto"></p>' : '';
		$mobile_css = '@media only screen and (max-width:600px){body.contactpanda-email-body{padding:10px!important;background:' . esc_attr( $settings['body_color'] ) . '!important}.contactpanda-email-container{border-radius:0!important}.contactpanda-email-content{padding:18px 10px!important}.contactpanda-email-footer{padding:15px 10px!important}.contactpanda-product-cell,.contactpanda-post-cell,.contactpanda-event-cell,.contactpanda-column-cell{display:block!important;width:100%!important;box-sizing:border-box!important}.contactpanda-video-provider-email{display:none!important}.contactpanda-email-video-link{display:block!important;width:100%!important;max-width:100%!important;text-align:center!important}.contactpanda-email-video-button{display:block!important;box-sizing:border-box!important;width:100%!important}.contactpanda-email-caption{position:static!important;left:auto!important;top:auto!important;bottom:auto!important;display:block!important;width:100%!important;max-width:none!important;margin:0!important;transform:none!important;box-sizing:border-box!important}}';
		return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>' . $responsive_css . $mobile_css . '</style><title></title></head><body class="contactpanda-email-body" style="margin:0;background:' . esc_attr( $settings['background_color'] ) . ';padding:24px"><div style="display:none;max-height:0;overflow:hidden;opacity:0">' . esc_html( $preview_text ) . '</div><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td align="center"><table class="contactpanda-email-container" role="presentation" width="' . absint( $settings['container_width'] ) . '" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:' . absint( $settings['container_width'] ) . 'px;background:' . esc_attr( $settings['body_color'] ) . ';border-radius:8px"><tr><td class="contactpanda-email-content" style="padding:36px">' . $logo . '<!--contactpanda-content-start-->' . $body . '<!--contactpanda-content-end--></td></tr><tr><td class="contactpanda-email-footer" style="padding:18px 36px;border-top:1px solid #dfe5eb;background:#f7f8fa;text-align:center;color:#6b7785;font:11px/1.5 ' . $font . '">' . $footer . '</td></tr></table></td></tr></table></body></html>';
	}

	public static function template( $id ) {
		return self::get_template( $id );
	}

	public static function templates() {
		global $wpdb;
		ContactPanda_Newsletter_Schema::install();
		$table = ContactPanda_Newsletter_Schema::newsletter_table_names()['templates'];
		return $wpdb->get_results( "SELECT * FROM `{$table}` WHERE status='published' AND imported_readonly=0 ORDER BY updated_at DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
	}

	public static function render_for_recipient( $template, $recipient, $unsubscribe_url, $context = array() ) {
		$blocks = json_decode( (string) ( $template['content_json'] ?? '' ), true );
		$style = array();
		if ( ! empty( $template['email_background'] ) ) $style['background_color'] = $template['email_background'];
		if ( ! empty( $template['accent_color'] ) ) $style['base_color'] = $template['accent_color'];
		if ( ! empty( $template['content_background'] ) ) $style['body_color'] = $template['content_background'];
		if ( ! empty( $template['text_color'] ) ) $style['text_color'] = $template['text_color'];
		if ( ! empty( $template['font_family'] ) ) $style['font_family'] = $template['font_family'];
		if ( ! empty( $template['content_width'] ) ) $style['container_width'] = $template['content_width'];
		$html = self::render_email( is_array( $blocks ) ? $blocks : array(), (string) ( $template['preview_text'] ?? '' ), $style );
		return self::personalize( $html, $recipient, $unsubscribe_url, true, $context );
	}

	public static function personalize_text( $text, $recipient, $context = array() ) {
		return wp_strip_all_tags( self::personalize( $text, $recipient, '', false, $context ) );
	}

	private static function personalize( $text, $recipient, $unsubscribe_url, $html = true, $context = array() ) {
		$contact = ! empty( $recipient['contact_id'] ) ? ContactPanda_Plugin::extension_contact( absint( $recipient['contact_id'] ) ) : array();
		$meta = is_array( $contact ) && isset( $contact['meta'] ) ? (array) $contact['meta'] : array();
		$first = sanitize_text_field( $recipient['first_name'] ?? ContactPanda_Plugin::extension_value( $contact, array( 'first_name' ) ) );
		$last = sanitize_text_field( $recipient['last_name'] ?? ContactPanda_Plugin::extension_value( $contact, array( 'last_name' ) ) );
		$prefix = sanitize_text_field( $meta['titel_vorangestellt'] ?? '' );
		$suffix = sanitize_text_field( $meta['titel_nachgestellt'] ?? '' );
		$full_name = trim( implode( ' ', array_filter( array( $prefix, $first, $last ) ) ) . ( $suffix ? ', ' . $suffix : '' ) );
		$company = sanitize_text_field( $meta['company_name'] ?? $meta['company'] ?? '' );
		$address = trim( implode( ', ', array_filter( array( $meta['address_1'] ?? '', trim( ( $meta['postcode'] ?? '' ) . ' ' . ( $meta['city'] ?? '' ) ), $meta['country'] ?? '' ) ) ) );
		$salutation_key = sanitize_key( $meta['anrede'] ?? 'neutral' );
		$salutation = array( 'mr' => __( 'Dear Mr', 'contactpanda' ), 'ms' => __( 'Dear Ms', 'contactpanda' ), 'family' => __( 'Dear family', 'contactpanda' ), 'neutral' => __( 'Hello', 'contactpanda' ) )[ $salutation_key ] ?? __( 'Hello', 'contactpanda' );
		$event_data = is_array( $context['event_data'] ?? null ) ? $context['event_data'] : array();
		$values = array( 'first_name' => $first, 'last_name' => $last, 'title_prefix' => $prefix, 'title_suffix' => $suffix, 'full_name' => $full_name, 'company' => $company, 'address' => $address, 'salutation' => $salutation );
		foreach ( array( 'order_number', 'order_date', 'order_total', 'product_name', 'product_quantity', 'product_price', 'product_url', 'event_name', 'event_date', 'event_time', 'event_location', 'event_url', 'registration_status' ) as $key ) $values[ $key ] = sanitize_text_field( $event_data[ $key ] ?? '' );
		foreach ( $values as $key => $value ) $text = str_replace( '{' . $key . '}', $html ? esc_html( $value ) : $value, $text );
		$text = preg_replace_callback( '/\{([a-z_]+)\|([^{}]*)\}/', static function ( $match ) use ( $values, $html ) { if ( ! array_key_exists( $match[1], $values ) ) return $match[0]; $value = $values[ $match[1] ] ?: $match[2]; return $html ? esc_html( $value ) : $value; }, $text );
		$unsubscribe = $unsubscribe_url ? '<a href="' . esc_url( $unsubscribe_url ) . '" style="color:#6676c5">' . esc_html__( 'Unsubscribe from newsletter', 'contactpanda' ) . '</a>' : '<a href="#" style="color:#6676c5">' . esc_html__( 'Unsubscribe from newsletter', 'contactpanda' ) . '</a>';
		return str_replace( '{unsubscribe_link}', $html ? $unsubscribe : '', $text );
	}

	private static function resolve_products( $block ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}
		$count = min( 6, max( 1, absint( $block['count'] ?? 2 ) ) );
		$source = isset( $block['source'] ) ? $block['source'] : 'latest';
		$args = array( 'limit' => $count, 'status' => 'publish', 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects' );
		if ( 'category' === $source && ! empty( $block['category_id'] ) ) {
			$term = get_term( absint( $block['category_id'] ), 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$args['category'] = array( $term->slug );
			}
		} elseif ( 'featured' === $source ) {
			$args['featured'] = true;
		} elseif ( 'sale' === $source ) {
			$args['include'] = array_slice( wc_get_product_ids_on_sale(), 0, $count );
		} elseif ( 'manual' === $source ) {
			$args['include'] = array_slice( (array) ( $block['product_ids'] ?? array() ), 0, $count );
			$args['orderby'] = 'include';
		} elseif ( 'producer' === $source && ! empty( $block['producer_taxonomy'] ) && ! empty( $block['producer_term_id'] ) && taxonomy_exists( $block['producer_taxonomy'] ) ) {
			$ids = get_posts(
				array(
					'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => $count, 'fields' => 'ids',
					'tax_query' => array( array( 'taxonomy' => $block['producer_taxonomy'], 'field' => 'term_id', 'terms' => absint( $block['producer_term_id'] ) ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- bounded editor query.
				)
			);
			$args['include'] = $ids;
			$args['orderby'] = 'include';
		}
		if ( isset( $args['include'] ) && ! $args['include'] ) {
			return array();
		}
		return array_values( array_filter( wc_get_products( $args ), static function ( $product ) { return $product instanceof WC_Product && $product->is_visible(); } ) );
	}

	private static function render_products( $block, $settings ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return '<p style="color:#7b8794;font:14px/1.5 Arial,sans-serif">' . esc_html__( 'WooCommerce is currently unavailable.', 'contactpanda' ) . '</p>';
		}
		$products = self::resolve_products( $block );
		if ( ! $products ) {
			return '<p style="color:#7b8794;font:14px/1.5 Arial,sans-serif">' . esc_html__( 'No matching products were found.', 'contactpanda' ) . '</p>';
		}
		$font = esc_attr( $settings['font_family'] );
		$image_ratio = str_replace( ':', '/', isset( $block['image_ratio'] ) && in_array( $block['image_ratio'], array( '1:1', '4:5', '4:3' ), true ) ? $block['image_ratio'] : '1:1' );
		$html = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 22px">';
		$columns = min( 3, max( 1, absint( $block['columns'] ?? 2 ) ) );
		foreach ( array_chunk( $products, $columns ) as $row ) {
			$html .= '<tr>';
			foreach ( $row as $product ) {
				$image = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ) ?: wc_placeholder_img_src( 'woocommerce_thumbnail' );
				$details = array();
				if ( ! empty( $block['show_categories'] ) ) { $names = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) ); if ( ! is_wp_error( $names ) && $names ) $details[] = implode( ', ', $names ); }
				if ( ! empty( $block['show_brands'] ) ) foreach ( array( 'product_brand', 'pwb-brand', 'yith_product_brand', 'pa_hersteller', 'pa_produzent' ) as $taxonomy ) { if ( taxonomy_exists( $taxonomy ) ) { $names = wp_get_post_terms( $product->get_id(), $taxonomy, array( 'fields' => 'names' ) ); if ( ! is_wp_error( $names ) && $names ) { $details[] = implode( ', ', $names ); break; } } }
				$html .= '<td class="contactpanda-product-cell" width="' . floor( 100 / $columns ) . '%" valign="top" style="padding:10px;text-align:center"><a href="' . esc_url( $product->get_permalink() ) . '" style="color:' . esc_attr( $settings['text_color'] ) . ';text-decoration:none"><img src="' . esc_url( $image ) . '" alt="' . esc_attr( $product->get_name() ) . '" style="display:block;width:100%;aspect-ratio:' . esc_attr( $image_ratio ) . ';object-fit:cover;margin:0 auto 10px;border:0"><strong style="font:700 15px/1.35 ' . $font . '">' . esc_html( $product->get_name() ) . '</strong></a>' . ( $details ? '<div style="margin-top:5px;color:#6b7785;font:12px/1.35 ' . $font . '">' . esc_html( implode( ' · ', $details ) ) . '</div>' : '' ) . ( ! empty( $block['show_price'] ) ? '<div style="margin-top:6px;color:' . esc_attr( $settings['base_color'] ) . ';font:700 14px/1.3 ' . $font . '">' . wp_kses_post( $product->get_price_html() ) . '</div>' : '' ) . '</td>';
			}
			for ( $empty = count( $row ); $empty < $columns; $empty++ ) $html .= '<td width="' . floor( 100 / $columns ) . '%"></td>';
			$html .= '</tr>';
		}
		return $html . '</table>';
	}

	private static function render_posts( $block, $settings ) {
		$count = min( 6, max( 1, absint( $block['count'] ?? 2 ) ) ); $source = $block['source'] ?? 'latest';
		$post_type = isset( $block['post_type'] ) ? sanitize_key( $block['post_type'] ) : 'post';
		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object || empty( $post_type_object->public ) || in_array( $post_type, array( 'attachment', 'product', 'tribe_events' ), true ) ) $post_type = 'post';
		$post_taxonomy = ! empty( $block['post_taxonomy'] ) ? sanitize_key( $block['post_taxonomy'] ) : self::preferred_post_taxonomy( $post_type );
		$args = array( 'post_type' => $post_type, 'post_status' => 'publish', 'posts_per_page' => $count, 'orderby' => 'date', 'order' => 'DESC' );
		if ( 'category' === $source && ! empty( $block['category_id'] ) && $post_taxonomy && in_array( $post_taxonomy, get_object_taxonomies( $post_type ), true ) ) $args['tax_query'] = array( array( 'taxonomy' => $post_taxonomy, 'field' => 'term_id', 'terms' => absint( $block['category_id'] ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- bounded newsletter query.
		elseif ( 'manual' === $source ) { $args['post__in'] = array_slice( (array) ( $block['post_ids'] ?? array() ), 0, $count ); $args['orderby'] = 'post__in'; }
		$posts = get_posts( $args ); if ( ! $posts ) return '<p style="color:#7b8794">' . esc_html__( 'No matching posts were found.', 'contactpanda' ) . '</p>';
		$columns = min( 3, max( 1, absint( $block['columns'] ?? 2 ) ) ); $font = esc_attr( $settings['font_family'] ); $image_ratio = str_replace( ':', '/', isset( $block['image_ratio'] ) && in_array( $block['image_ratio'], array( '1:1', '4:5', '4:3' ), true ) ? $block['image_ratio'] : '1:1' ); $html = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 22px">';
		foreach ( array_chunk( $posts, $columns ) as $row ) { $html .= '<tr>'; foreach ( $row as $post ) { $image = get_the_post_thumbnail_url( $post, 'medium' ); $html .= '<td class="contactpanda-post-cell" width="' . floor( 100 / $columns ) . '%" valign="top" style="padding:10px">' . ( ! empty( $block['show_image'] ) && $image ? '<img src="' . esc_url( $image ) . '" alt="" style="display:block;width:100%;aspect-ratio:' . esc_attr( $image_ratio ) . ';object-fit:cover;margin-bottom:10px">' : '' ) . ( ! empty( $block['show_title'] ) ? '<a href="' . esc_url( get_permalink( $post ) ) . '" style="color:' . esc_attr( $settings['text_color'] ) . ';text-decoration:none"><strong style="font:700 16px/1.35 ' . $font . '">' . esc_html( get_the_title( $post ) ) . '</strong></a>' : '' ) . ( ! empty( $block['show_excerpt'] ) ? '<p style="font:13px/1.5 ' . $font . '">' . esc_html( wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post ) ), 24 ) ) . '</p>' : '' ) . '</td>'; } $html .= '</tr>'; }
		return $html . '</table>';
	}

	private static function render_events( $block, $settings ) {
		$count = min( 6, max( 1, absint( $block['count'] ?? 2 ) ) );
		$columns = min( 3, max( 1, absint( $block['columns'] ?? 2 ) ) );
		$image_ratio = str_replace( ':', '/', isset( $block['image_ratio'] ) && in_array( $block['image_ratio'], array( '1:1', '4:5', '4:3' ), true ) ? $block['image_ratio'] : '1:1' );
		$ids = array_slice( array_filter( array_map( 'absint', (array) ( $block['event_ids'] ?? array() ) ) ), 0, $count );
		if ( ! $ids ) return '<p style="color:#7b8794">' . esc_html__( 'No events selected.', 'contactpanda' ) . '</p>';
		$html = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 22px">';
		foreach ( array_chunk( $ids, $columns ) as $row ) {
			$html .= '<tr>';
			foreach ( $row as $event_id ) {
				if ( 'publish' !== get_post_status( $event_id ) ) continue;
				$date = function_exists( 'tribe_get_start_date' ) ? tribe_get_start_date( $event_id, false, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : get_the_date( '', $event_id );
				$image = get_the_post_thumbnail_url( $event_id, 'medium' );
				$html .= '<td class="contactpanda-event-cell" width="' . floor( 100 / $columns ) . '%" valign="top" style="padding:10px">' . ( $image ? '<img src="' . esc_url( $image ) . '" alt="" style="display:block;width:100%;aspect-ratio:' . esc_attr( $image_ratio ) . ';object-fit:cover;margin-bottom:10px">' : '' ) . '<strong style="font:700 17px/1.35 ' . esc_attr( $settings['font_family'] ) . '"><a href="' . esc_url( get_permalink( $event_id ) ) . '" style="color:' . esc_attr( $settings['text_color'] ) . '">' . esc_html( get_the_title( $event_id ) ) . '</a></strong><br><span style="font:14px/1.5 ' . esc_attr( $settings['font_family'] ) . '">' . esc_html( $date ) . '</span></td>';
			}
			$html .= '</tr>';
		}
		return $html . '</table>';
	}

	private static function render_footer( $settings ) {
		$company = $settings['legal_name'] ?: $settings['company_name'];
		$address = array_filter( array( $settings['street'], trim( $settings['postcode'] . ' ' . $settings['city'] ), $settings['country'] ) );
		$legal = array_filter( array( $settings['vat_id'], $settings['company_register'] ) );
		$contact = array_filter( array( $settings['phone'], $settings['email'], $settings['website'] ) );
		$links = array();
		if ( $settings['privacy_url'] ) {
			$links[] = '<a style="color:inherit" href="' . esc_url( $settings['privacy_url'] ) . '">' . esc_html__( 'Privacy policy', 'contactpanda' ) . '</a>';
		}
		if ( $settings['imprint_url'] ) {
			$links[] = '<a style="color:inherit" href="' . esc_url( $settings['imprint_url'] ) . '">' . esc_html__( 'Imprint', 'contactpanda' ) . '</a>';
		}
		$parts = array();
		if ( $company ) {
			$parts[] = '<strong>' . esc_html( $company ) . '</strong>';
		}
		if ( $address ) {
			$parts[] = esc_html( implode( ', ', $address ) );
		}
		if ( $legal ) {
			$parts[] = esc_html( implode( ' · ', $legal ) );
		}
		if ( $contact ) {
			$parts[] = esc_html( implode( ' · ', $contact ) );
		}
		if ( $settings['footer_text'] ) {
			$parts[] = wp_kses_post( wpautop( $settings['footer_text'] ) );
		}
		if ( $links ) {
			$parts[] = implode( ' · ', $links );
		}
		$parts[] = '{unsubscribe_link}';
		return implode( '<br>', $parts );
	}

	private static function replace_placeholders( $html ) {
		$user = wp_get_current_user();
		$replacements = array(
			'{first_name}' => $user->first_name ?: __( 'First name', 'contactpanda' ),
			'{last_name}' => $user->last_name ?: __( 'Last name', 'contactpanda' ),
			'{company}' => get_bloginfo( 'name' ),
			'{salutation}' => __( 'Hello', 'contactpanda' ),
			'{unsubscribe_link}' => '<a href="#" style="color:#6676c5">' . esc_html__( 'Unsubscribe from newsletter', 'contactpanda' ) . '</a>',
		);
		return strtr( $html, $replacements );
	}

	private static function get_template( $id ) {
		global $wpdb;
		$table = ContactPanda_Newsletter_Schema::newsletter_table_names()['templates'];
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", absint( $id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
	}

	private static function guard_request() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to manage email templates.', 'contactpanda' ) );
		}
		check_admin_referer( self::NONCE_ACTION );
	}

	private static function guard_ajax() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'contactpanda' ) ), 403 );
		}
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
	}

	private static function notice_redirect( $success, $message, $id = 0 ) {
		set_transient( 'contactpanda_newsletter_notice_' . get_current_user_id(), array( 'success' => $success, 'message' => $message ), MINUTE_IN_SECONDS );
		$url = $id ? admin_url( 'admin.php?page=contactpanda-newsletters&action=edit&template_id=' . absint( $id ) ) : admin_url( 'admin.php?page=contactpanda-newsletters' );
		wp_safe_redirect( $url );
		exit;
	}
}
