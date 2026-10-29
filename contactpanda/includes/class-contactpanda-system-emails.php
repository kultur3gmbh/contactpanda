<?php

defined( 'ABSPATH' ) || exit;

/** Shared visual frame for transactional emails sent by the ContactPanda family. */
final class ContactPanda_System_Emails {
	const OPTION = 'contactpanda_system_email_template';

	public static function boot() {
		add_filter( 'contactpanda_general_settings_sections', array( __CLASS__, 'settings_section' ), 20 );
		add_action( 'contactpanda_render_general_settings_section_system_emails', array( __CLASS__, 'render_settings' ) );
		add_action( 'admin_post_contactpanda_save_system_email_template', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_contactpanda_test_system_email_template', array( __CLASS__, 'send_test' ) );
	}

	public static function settings_section( $sections ) {
		$sections['system_emails'] = __( 'System emails', 'contactpanda' );
		return $sections;
	}

	public static function defaults() {
		return array(
			'logo_url'         => '',
			'background_color' => '#f3f5f7',
			'content_color'    => '#ffffff',
			'text_color'       => '#1d2327',
			'accent_color'     => '#3858e9',
			'font_family'      => 'Arial, Helvetica, sans-serif',
			'header_text'      => '',
			'signature'        => '',
			'footer_text'      => '',
			'show_company'     => 1,
		);
	}

	public static function settings() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You are not allowed to manage these settings.', 'contactpanda' ) );
		$settings = self::settings();
		$profile  = class_exists( 'ContactPanda_Company_Profile' ) ? ContactPanda_Company_Profile::get() : array();
		if ( isset( $_GET['system_email_saved'] ) ) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'System email template saved.', 'contactpanda' ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Template for system emails', 'contactpanda' ) . '</h2><p>' . esc_html__( 'This shared frame is used for transactional ContactPanda messages such as confirmations, account messages, form notifications, support replies, project notices, and automation administration messages. Newsletters keep their own design.', 'contactpanda' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-system-email-settings"><input type="hidden" name="action" value="contactpanda_save_system_email_template">';
		wp_nonce_field( 'contactpanda_save_system_email_template' );
		echo '<div class="contactpanda-system-email-grid"><div class="contactpanda-system-email-controls">';
		echo '<label><span>' . esc_html__( 'Logo', 'contactpanda' ) . '</span><span class="contactpanda-image-picker-source"><input type="url" name="logo_url" value="' . esc_attr( $settings['logo_url'] ?: ( $profile['logo_url'] ?? '' ) ) . '" data-contactpanda-image-url></span></label>';
		self::color( 'background_color', __( 'Email background', 'contactpanda' ), $settings );
		self::color( 'content_color', __( 'Content background', 'contactpanda' ), $settings );
		self::color( 'text_color', __( 'Text color', 'contactpanda' ), $settings );
		self::color( 'accent_color', __( 'Button and link color', 'contactpanda' ), $settings );
		echo '<label><span>' . esc_html__( 'Font family', 'contactpanda' ) . '</span><select name="font_family">';
		foreach ( array( 'Arial, Helvetica, sans-serif' => 'Arial', 'Verdana, Geneva, sans-serif' => 'Verdana', 'Georgia, Times, serif' => 'Georgia', 'system-ui, sans-serif' => __( 'Theme or system font', 'contactpanda' ) ) as $value => $label ) echo '<option value="' . esc_attr( $value ) . '"' . selected( $settings['font_family'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select></label><label><span>' . esc_html__( 'Header text', 'contactpanda' ) . '</span><input type="text" name="header_text" value="' . esc_attr( $settings['header_text'] ) . '"></label>';
		echo '<label><span>' . esc_html__( 'Signature', 'contactpanda' ) . '</span><textarea name="signature" rows="4">' . esc_textarea( $settings['signature'] ) . '</textarea></label>';
		echo '<label><span>' . esc_html__( 'Additional footer text', 'contactpanda' ) . '</span><textarea name="footer_text" rows="4">' . esc_textarea( $settings['footer_text'] ) . '</textarea></label>';
		echo '<label class="contactpanda-system-email-check"><input type="checkbox" name="show_company" value="1"' . checked( ! empty( $settings['show_company'] ), true, false ) . '> <span>' . esc_html__( 'Show company and legal details in the footer', 'contactpanda' ) . '</span></label>';
		echo '</div><div class="contactpanda-system-email-preview">' . self::render( __( 'Your message subject', 'contactpanda' ), '<h2>' . esc_html__( 'Hello!', 'contactpanda' ) . '</h2><p>' . esc_html__( 'The actual system message is inserted here automatically.', 'contactpanda' ) . '</p><p><a class="contactpanda-email-button" href="#">' . esc_html__( 'Example button', 'contactpanda' ) . '</a></p>' ) . '</div></div>';
		submit_button( __( 'Save system email template', 'contactpanda' ) );
		echo '</form><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-system-email-test"><input type="hidden" name="action" value="contactpanda_test_system_email_template">';
		wp_nonce_field( 'contactpanda_test_system_email_template' );
		echo '<label><span>' . esc_html__( 'Test recipient', 'contactpanda' ) . '</span><input type="email" name="test_email" value="' . esc_attr( wp_get_current_user()->user_email ) . '"></label> ';
		submit_button( __( 'Send test email', 'contactpanda' ), 'secondary', 'submit', false );
		echo '</form></section>';
	}

	private static function color( $name, $label, $settings ) {
		echo '<label class="contactpanda-settings-color-field"><span>' . esc_html( $label ) . '</span><input type="color" data-contactpanda-color-value="' . esc_attr( $settings[ $name ] ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $settings[ $name ] ) . '"></label>';
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You are not allowed to manage these settings.', 'contactpanda' ) );
		check_admin_referer( 'contactpanda_save_system_email_template' );
		$defaults = self::defaults();
		$settings = array(
			'logo_url'         => esc_url_raw( wp_unslash( $_POST['logo_url'] ?? '' ) ),
			'font_family'      => sanitize_text_field( wp_unslash( $_POST['font_family'] ?? $defaults['font_family'] ) ),
			'header_text'      => sanitize_text_field( wp_unslash( $_POST['header_text'] ?? '' ) ),
			'signature'        => wp_kses_post( wp_unslash( $_POST['signature'] ?? '' ) ),
			'footer_text'      => wp_kses_post( wp_unslash( $_POST['footer_text'] ?? '' ) ),
			'show_company'     => empty( $_POST['show_company'] ) ? 0 : 1,
		);
		foreach ( array( 'background_color', 'content_color', 'text_color', 'accent_color' ) as $key ) {
			$value = sanitize_hex_color( wp_unslash( $_POST[ $key ] ?? '' ) );
			$settings[ $key ] = $value ?: $defaults[ $key ];
		}
		update_option( self::OPTION, $settings, false );
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=general&general_section=system_emails&system_email_saved=1' ) );
		exit;
	}

	public static function send_test() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You are not allowed to manage these settings.', 'contactpanda' ) );
		check_admin_referer( 'contactpanda_test_system_email_template' );
		$email = sanitize_email( wp_unslash( $_POST['test_email'] ?? '' ) );
		if ( is_email( $email ) ) self::send( $email, __( 'ContactPanda system email test', 'contactpanda' ), '<h2>' . esc_html__( 'The template is ready.', 'contactpanda' ) . '</h2><p>' . esc_html__( 'This is how future transactional ContactPanda emails will look.', 'contactpanda' ) . '</p>' );
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=general&general_section=system_emails&system_email_saved=1' ) );
		exit;
	}

	public static function send( $to, $subject, $content, $headers = array(), $attachments = array() ) {
		$headers = is_array( $headers ) ? $headers : array( $headers );
		$headers[] = 'Content-Type: text/html; charset=UTF-8';
		return wp_mail( $to, $subject, self::render( $subject, $content ), array_values( array_unique( $headers ) ), $attachments );
	}

	public static function render( $subject, $content ) {
		$s = self::settings();
		$p = class_exists( 'ContactPanda_Company_Profile' ) ? ContactPanda_Company_Profile::get() : array();
		$logo = $s['logo_url'] ?: ( $p['logo_url'] ?? '' );
		$company = trim( implode( ' · ', array_filter( array( $p['company_name'] ?? '', trim( ( $p['street'] ?? '' ) . ' ' . ( $p['postcode'] ?? '' ) . ' ' . ( $p['city'] ?? '' ) ), $p['email'] ?? '', $p['phone'] ?? '' ) ) ) );
		$legal = ! empty( $s['show_company'] ) ? trim( $company . ( ! empty( $p['legal_footer'] ) ? "\n" . $p['legal_footer'] : '' ) ) : '';
		$button_css = 'background:' . $s['accent_color'] . ';border-radius:4px;color:#fff!important;display:inline-block;padding:11px 18px;text-decoration:none';
		$content = preg_replace( '/class=(["\'])contactpanda-email-button\1/', 'style="' . esc_attr( $button_css ) . '"', (string) $content );
		return '<!doctype html><html><body style="margin:0;padding:0;background:' . esc_attr( $s['background_color'] ) . ';color:' . esc_attr( $s['text_color'] ) . ';font-family:' . esc_attr( $s['font_family'] ) . '"><table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" style="padding:28px 14px"><table role="presentation" width="640" style="max-width:640px;width:100%;background:' . esc_attr( $s['content_color'] ) . ';border-radius:8px" cellspacing="0" cellpadding="0"><tr><td style="padding:28px">' . ( $logo ? '<p style="margin:0 0 22px"><img src="' . esc_url( $logo ) . '" alt="" style="display:block;max-height:72px;max-width:240px"></p>' : '' ) . ( $s['header_text'] ? '<p style="font-size:13px;margin:0 0 18px;color:' . esc_attr( $s['accent_color'] ) . '">' . esc_html( $s['header_text'] ) . '</p>' : '' ) . '<div style="font-size:16px;line-height:1.6">' . wp_kses_post( $content ) . '</div>' . ( $s['signature'] ? '<div style="margin-top:24px">' . wpautop( wp_kses_post( $s['signature'] ) ) . '</div>' : '' ) . '</td></tr><tr><td style="border-top:1px solid #e2e5e8;padding:20px 28px;font-size:12px;line-height:1.5;color:#646970">' . ( $s['footer_text'] ? wpautop( wp_kses_post( $s['footer_text'] ) ) : '' ) . ( $legal ? nl2br( esc_html( $legal ) ) : '' ) . '</td></tr></table><div style="font-size:12px;color:#646970;margin-top:12px">' . esc_html( $subject ) . '</div></td></tr></table></body></html>';
	}
}
