<?php

defined( 'ABSPATH' ) || exit;

final class ContactPanda_Basic_Newsletter_Campaigns {
	// Keep the historic hook name so queued mailings survive the 0.6.0 move
	// from Plus into Basic without being rescheduled or duplicated.
	const CRON_HOOK = 'contactpanda_plus_process_newsletter_queue';

	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 31 );
		add_action( 'admin_post_contactpanda_save_campaign', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_contactpanda_campaign_action', array( __CLASS__, 'campaign_action' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'process_queue' ), 10, 1 );
		add_action( 'admin_post_contactpanda_newsletter_unsubscribe', array( __CLASS__, 'unsubscribe' ) );
		add_action( 'admin_post_nopriv_contactpanda_newsletter_unsubscribe', array( __CLASS__, 'unsubscribe' ) );
		add_action( 'admin_post_contactpanda_newsletter_open', array( __CLASS__, 'track_open' ) );
		add_action( 'admin_post_nopriv_contactpanda_newsletter_open', array( __CLASS__, 'track_open' ) );
		add_action( 'admin_post_contactpanda_newsletter_click', array( __CLASS__, 'track_click' ) );
		add_action( 'admin_post_nopriv_contactpanda_newsletter_click', array( __CLASS__, 'track_click' ) );
		add_action( 'wp_ajax_contactpanda_campaign_preview', array( __CLASS__, 'preview_ajax' ) );
		add_action( 'wp_ajax_contactpanda_campaign_test', array( __CLASS__, 'test_ajax' ) );
		add_action( 'wp_ajax_contactpanda_campaign_statuses', array( __CLASS__, 'statuses_ajax' ) );
		add_filter( 'contactpanda_misc_settings_sections', array( __CLASS__, 'misc_sections' ) );
		add_action( 'contactpanda_render_misc_settings_section_campaigns', array( __CLASS__, 'render_campaign_groups' ) );
		add_action( 'admin_post_contactpanda_campaign_group', array( __CLASS__, 'campaign_group_action' ) );
	}

	public static function misc_sections( $sections ) { $sections['campaigns'] = __( 'Campaigns', 'contactpanda' ); return $sections; }

	private static function campaign_groups() {
		global $wpdb; ContactPanda_Newsletter_Schema::install(); $table = ContactPanda_Newsletter_Schema::table_names()['campaign_groups'];
		return (array) $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY name ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
	}

	public static function render_campaign_groups() {
		$groups = self::campaign_groups();
		$edit_id = absint( $_GET['edit_campaign_group'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only editor selection.
		$editing = false; foreach ( $groups as $candidate ) if ( absint( $candidate['id'] ) === $edit_id ) $editing = $candidate;
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Campaigns', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Group related newsletter mailings in campaigns.', 'contactpanda' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-settings-grid"><input type="hidden" name="action" value="contactpanda_campaign_group"><input type="hidden" name="operation" value="save"><input type="hidden" name="id" value="' . esc_attr( $edit_id ) . '">'; wp_nonce_field( 'contactpanda_campaign_group' );
		echo '<label><span>' . esc_html__( 'Campaign name', 'contactpanda' ) . '</span><input name="name" value="' . esc_attr( $editing['name'] ?? '' ) . '" required></label><label><span>' . esc_html__( 'Description', 'contactpanda' ) . '</span><input name="description" value="' . esc_attr( $editing['description'] ?? '' ) . '"></label><p><button class="button button-primary">' . esc_html( $editing ? __( 'Save changes', 'contactpanda' ) : __( 'Add campaign', 'contactpanda' ) ) . '</button>' . ( $editing ? ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-settings&tab=misc&misc_section=campaigns' ) ) . '">' . esc_html__( 'Cancel', 'contactpanda' ) . '</a>' : '' ) . '</p></form><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Campaign', 'contactpanda' ) . '</th><th>' . esc_html__( 'Description', 'contactpanda' ) . '</th><th>' . esc_html__( 'Mailings', 'contactpanda' ) . '</th><th>' . esc_html__( 'Actions', 'contactpanda' ) . '</th></tr></thead><tbody>';
		global $wpdb; $campaigns_table = ContactPanda_Newsletter_Schema::table_names()['campaigns'];
		foreach ( $groups as $group ) {
			$count = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$campaigns_table}` WHERE campaign_group_id=%d", $group['id'] ) ) );
			$id = absint( $group['id'] ); $form_id = 'contactpanda-campaign-group-' . $id;
			$delete = wp_nonce_url( admin_url( 'admin-post.php?action=contactpanda_campaign_group&operation=delete&id=' . $id ), 'contactpanda_campaign_group' );
			$mailings = add_query_arg( 'campaign_group', $id, admin_url( 'admin.php?page=contactpanda-campaigns' ) );
			echo '<tr class="contactpanda-list-inline-edit"><td><button type="button" class="button-link contactpanda-inline-edit-open"><strong>' . esc_html( $group['name'] ) . '</strong></button><div class="contactpanda-inline-edit-fields" hidden><input form="' . esc_attr( $form_id ) . '" name="name" required value="' . esc_attr( $group['name'] ) . '"></div></td><td><span class="contactpanda-inline-edit-display">' . esc_html( $group['description'] ?: '—' ) . '</span><div class="contactpanda-inline-edit-fields" hidden><input form="' . esc_attr( $form_id ) . '" name="description" value="' . esc_attr( $group['description'] ) . '"><p><button form="' . esc_attr( $form_id ) . '" class="button button-primary">' . esc_html__( 'Save', 'contactpanda' ) . '</button> <button type="button" class="button contactpanda-inline-edit-cancel">' . esc_html__( 'Cancel', 'contactpanda' ) . '</button></p></div></td><td><a href="' . esc_url( $mailings ) . '">' . esc_html( $count ) . '</a></td><td><button type="button" class="button-link contactpanda-inline-edit-open">' . esc_html__( 'Edit', 'contactpanda' ) . '</button> · <a href="' . esc_url( $delete ) . '" onclick="return confirm(' . esc_attr( wp_json_encode( __( 'Delete this campaign group? Its mailings and statistics will be retained without a campaign assignment.', 'contactpanda' ) ) ) . ');">' . esc_html__( 'Delete', 'contactpanda' ) . '</a><form id="' . esc_attr( $form_id ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_campaign_group"><input type="hidden" name="operation" value="save"><input type="hidden" name="id" value="' . esc_attr( $id ) . '">'; wp_nonce_field( 'contactpanda_campaign_group' ); echo '</form></td></tr>';
		}
		if ( ! $groups ) echo '<tr><td colspan="4">' . esc_html__( 'No campaign has been created yet.', 'contactpanda' ) . '</td></tr>';
		echo '</tbody></table></section>';
	}

	public static function campaign_group_action() {
		if ( ! current_user_can( self::capability() ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ) );
		check_admin_referer( 'contactpanda_campaign_group' ); global $wpdb; ContactPanda_Newsletter_Schema::install(); $table = ContactPanda_Newsletter_Schema::table_names()['campaign_groups'];
		$operation = sanitize_key( wp_unslash( $_REQUEST['operation'] ?? '' ) );
		if ( 'delete' === $operation ) { $id = absint( $_GET['id'] ?? 0 ); $campaigns = ContactPanda_Newsletter_Schema::table_names()['campaigns']; $wpdb->update( $campaigns, array( 'campaign_group_id' => 0, 'updated_at' => current_time( 'mysql' ) ), array( 'campaign_group_id' => $id ) ); $wpdb->delete( $table, array( 'id' => $id ) ); }
		elseif ( 'save' === $operation ) { $name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ); if ( $name ) { $id = absint( $_POST['id'] ?? 0 ); $existing = $id ?: absint( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE name=%s", $name ) ) ); $data = array( 'name' => $name, 'description' => sanitize_text_field( wp_unslash( $_POST['description'] ?? '' ) ), 'updated_at' => current_time( 'mysql' ) ); if ( $existing ) $wpdb->update( $table, $data, array( 'id' => $existing ) ); else { $data['created_at'] = current_time( 'mysql' ); $wpdb->insert( $table, $data ); } } }
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=misc&misc_section=campaigns' ) ); exit;
	}

	public static function menu() {
		add_submenu_page( 'contactpanda', __( 'Newsletters', 'contactpanda' ), __( 'Newsletter', 'contactpanda' ), self::capability(), 'contactpanda-campaigns', array( __CLASS__, 'render' ) );
	}

	public static function render() {
		if ( ! current_user_can( self::capability() ) ) { wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ) ); }
		ContactPanda_Newsletter_Schema::install();
		global $wpdb;
		$t = ContactPanda_Newsletter_Schema::table_names();
		$search = sanitize_text_field( wp_unslash( $_GET['mailing_search'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$status_filter = sanitize_key( wp_unslash( $_GET['mailing_status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$group_filter = absint( $_GET['campaign_group'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_from = sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) ); $date_to = sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$where = array( '1=1' ); $params = array();
		if ( $search ) { $where[] = 'c.name LIKE %s'; $params[] = '%' . $wpdb->esc_like( $search ) . '%'; }
		if ( 'open' === $status_filter ) $where[] = "c.status NOT IN ('completed','cancelled','imported')";
		elseif ( 'completed' === $status_filter ) $where[] = "c.status IN ('completed','cancelled','imported')";
		if ( $group_filter ) { $where[] = 'c.campaign_group_id=%d'; $params[] = $group_filter; }
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) { $where[] = 'c.created_at >= %s'; $params[] = $date_from . ' 00:00:00'; }
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) { $where[] = 'c.created_at <= %s'; $params[] = $date_to . ' 23:59:59'; }
		$sql = "SELECT c.*,s.name AS segment_name,t.name AS template_name,g.name AS campaign_group_name FROM `{$t['campaigns']}` c LEFT JOIN `{$t['segments']}` s ON s.id=c.segment_id LEFT JOIN `{$t['templates']}` t ON t.id=c.template_id LEFT JOIN `{$t['campaign_groups']}` g ON g.id=c.campaign_group_id WHERE " . implode( ' AND ', $where ) . ' ORDER BY c.created_at DESC LIMIT 100'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$campaigns = $wpdb->get_results( $params ? $wpdb->prepare( $sql, $params ) : $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared when needed.
		echo '<div class="wrap contactpanda-wrap"><div class="contactpanda-heading"><div><p class="contactpanda-eyebrow">CONTACTPANDA</p><h1>' . esc_html__( 'Newsletter mailing', 'contactpanda' ) . '</h1><p>' . esc_html__( 'Prepare, check and send newsletters through a controlled queue.', 'contactpanda' ) . '</p></div><button type="button" class="button button-primary contactpanda-heading-action" data-contactpanda-mailing-open>' . esc_html__( 'Create mailing', 'contactpanda' ) . '</button></div>';
		$editing = ! empty( $_GET['edit_campaign'] ) ? self::campaign( absint( $_GET['edit_campaign'] ) ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only editor selection.
		echo '<section class="contactpanda-card"><h2>' . esc_html__( 'Newsletter mailings', 'contactpanda' ) . '</h2><form method="get" class="contactpanda-mailing-filters"><input type="hidden" name="page" value="contactpanda-campaigns"><input type="search" name="mailing_search" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search mailing name', 'contactpanda' ) . '"><select name="mailing_status"><option value="">' . esc_html__( 'All statuses', 'contactpanda' ) . '</option><option value="open"' . selected( $status_filter, 'open', false ) . '>' . esc_html__( 'Open', 'contactpanda' ) . '</option><option value="completed"' . selected( $status_filter, 'completed', false ) . '>' . esc_html__( 'Completed', 'contactpanda' ) . '</option></select><label>' . esc_html__( 'From', 'contactpanda' ) . ' <input type="date" name="date_from" value="' . esc_attr( $date_from ) . '"></label><label>' . esc_html__( 'To', 'contactpanda' ) . ' <input type="date" name="date_to" value="' . esc_attr( $date_to ) . '"></label><button class="button">' . esc_html__( 'Search', 'contactpanda' ) . '</button></form><table class="widefat striped contactpanda-mailings-table"><thead><tr><th>' . esc_html__( 'Mailing', 'contactpanda' ) . '</th><th>' . esc_html__( 'Subject', 'contactpanda' ) . '</th><th>' . esc_html__( 'Recipients', 'contactpanda' ) . '</th><th>' . esc_html__( 'Delivery time', 'contactpanda' ) . '</th><th>' . esc_html__( 'Delivered', 'contactpanda' ) . '</th><th>' . esc_html__( 'Opened', 'contactpanda' ) . '</th><th>' . esc_html__( 'Clicked', 'contactpanda' ) . '</th><th>' . esc_html__( 'Actions', 'contactpanda' ) . '</th></tr></thead><tbody>';
		foreach ( $campaigns as $campaign ) {
			$imported = 'imported' === $campaign['status'];
			$detail_url = admin_url( 'admin.php?page=contactpanda-campaigns&campaign_id=' . absint( $campaign['id'] ) );
			$edit_url = admin_url( 'admin.php?page=contactpanda-campaigns&edit_campaign=' . absint( $campaign['id'] ) );
			$links = '<a href="' . esc_url( $detail_url ) . '">' . esc_html( $imported ? __( 'View archive', 'contactpanda' ) : __( 'Recipients', 'contactpanda' ) ) . '</a>';
			if ( ! $imported && in_array( $campaign['status'], array( 'draft', 'scheduled', 'paused' ), true ) ) $links .= ' <span aria-hidden="true">|</span> <a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'contactpanda' ) . '</a>';
			$actions = '';
			if ( ! $imported && in_array( $campaign['status'], array( 'draft', 'paused' ), true ) ) $actions .= self::action_link( $campaign['id'], 'start', __( 'Start', 'contactpanda' ), 'button contactpanda-start-mailing' );
			if ( ! $imported && in_array( $campaign['status'], array( 'queued', 'sending' ), true ) ) {
				$actions .= '<span class="button contactpanda-mailing-state contactpanda-mailing-running" aria-live="polite">' . esc_html__( 'Sending in progress', 'contactpanda' ) . '</span>';
			} elseif ( ! $imported && 'scheduled' === $campaign['status'] && ! empty( $campaign['scheduled_at'] ) ) {
				$scheduled_label = sprintf(
					__( 'Sending starts on %1$s at %2$s', 'contactpanda' ),
					mysql2date( get_option( 'date_format' ), $campaign['scheduled_at'] ),
					mysql2date( get_option( 'time_format' ), $campaign['scheduled_at'] )
				);
				$actions .= '<span class="button contactpanda-mailing-state contactpanda-mailing-scheduled">' . esc_html( $scheduled_label ) . '</span>';
			}
			if ( ! $imported && 'completed' === $campaign['status'] ) $actions .= '<span class="button contactpanda-mailing-state contactpanda-mailing-completed">' . esc_html__( 'Completed', 'contactpanda' ) . '</span>';
			if ( ! $imported && 'cancelled' === $campaign['status'] ) $actions .= '<span class="button contactpanda-mailing-state contactpanda-mailing-cancelled">' . esc_html__( 'Cancelled', 'contactpanda' ) . '</span>';
			if ( ! $imported && ! in_array( $campaign['status'], array( 'completed', 'cancelled' ), true ) ) $actions .= self::action_link( $campaign['id'], 'cancel', __( 'Cancel', 'contactpanda' ), 'button contactpanda-cancel-mailing' );
			$recipient_summary = $imported
				? sprintf( __( '%1$d sent · %2$d opened · %3$d clicked · %4$d unsubscribed', 'contactpanda' ), $campaign['sent_count'], $campaign['opened_count'], $campaign['clicked_count'], $campaign['unsubscribed_count'] )
				: sprintf( __( '%1$d sent · %2$d queued · %3$d skipped · %4$d failed', 'contactpanda' ), $campaign['sent_count'], $campaign['queued_count'], $campaign['skipped_count'], $campaign['failed_count'] );
			if ( ! empty( $campaign['scheduled_at'] ) ) {
				$delivery_time = sprintf( __( '%1$s at %2$s', 'contactpanda' ), mysql2date( get_option( 'date_format' ), $campaign['scheduled_at'] ), mysql2date( get_option( 'time_format' ), $campaign['scheduled_at'] ) );
			} elseif ( ! empty( $campaign['started_at'] ) ) {
				$delivery_time = sprintf( __( '%1$s at %2$s', 'contactpanda' ), mysql2date( get_option( 'date_format' ), $campaign['started_at'] ), mysql2date( get_option( 'time_format' ), $campaign['started_at'] ) );
			} elseif ( 'draft' === $campaign['status'] ) {
				$delivery_time = __( 'Draft', 'contactpanda' );
			} else {
				$delivery_time = self::status_label( $campaign['status'] );
			}
			$eligible = max( 0, absint( $campaign['total_recipients'] ) - absint( $campaign['skipped_count'] ) );
			$delivered = absint( $campaign['sent_count'] );
			$rate = static function ( $count, $base ) { return $base ? number_format_i18n( ( 100 * $count ) / $base, 1 ) . '%' : '0%'; };
			$processed = absint( $campaign['sent_count'] ) + absint( $campaign['failed_count'] ) + absint( $campaign['skipped_count'] );
			$progress = absint( $campaign['total_recipients'] ) ? min( 100, (int) round( 100 * $processed / absint( $campaign['total_recipients'] ) ) ) : 0;
			if ( 'completed' === $campaign['status'] ) $progress = 100;
			$progress_bar = ( ! $imported && 'draft' !== $campaign['status'] ) ? '<span class="contactpanda-mailing-progress" aria-label="' . esc_attr( sprintf( __( 'Mailing progress: %d%%', 'contactpanda' ), $progress ) ) . '"><i style="width:' . esc_attr( $progress ) . '%"></i><small>' . esc_html( $progress ) . '%</small></span>' : '';
			echo '<tr class="contactpanda-mailing-main-row" data-contactpanda-campaign-id="' . esc_attr( absint( $campaign['id'] ) ) . '" data-contactpanda-campaign-status="' . esc_attr( $campaign['status'] ) . '"><td><strong>' . esc_html( $campaign['name'] ) . '</strong><span class="contactpanda-mailing-row-actions">' . wp_kses_post( $links ) . '</span></td><td>' . esc_html( $campaign['subject'] ?: '—' ) . '</td><td><span>' . esc_html( $campaign['segment_name'] ?: __( 'All eligible contacts', 'contactpanda' ) ) . '</span><small class="contactpanda-recipient-summary">' . esc_html( $recipient_summary ) . '</small>' . $progress_bar . '</td><td><span class="contactpanda-delivery-time">' . esc_html( $delivery_time ) . '</span></td><td data-contactpanda-mailing-delivered>' . esc_html( $delivered . ' · ' . $rate( $delivered, $eligible ) ) . '</td><td data-contactpanda-mailing-opened>' . esc_html( absint( $campaign['opened_count'] ) . ' · ' . $rate( absint( $campaign['opened_count'] ), $delivered ) ) . '</td><td data-contactpanda-mailing-clicked>' . esc_html( absint( $campaign['clicked_count'] ) . ' · ' . $rate( absint( $campaign['clicked_count'] ), $delivered ) ) . '</td><td class="contactpanda-mailing-actions">' . $actions . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( ! $campaigns ) { echo '<tr><td colspan="8">' . esc_html__( 'No campaign has been created yet.', 'contactpanda' ) . '</td></tr>'; }
		echo '</tbody></table></section>';
		self::render_form( $editing && in_array( $editing['status'], array( 'draft', 'scheduled', 'paused' ), true ) ? $editing : false );
		if ( ! empty( $_GET['campaign_id'] ) ) { self::render_recipients( absint( $_GET['campaign_id'] ) ); } // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only details.
		echo '</div>';
	}

	private static function render_recipients( $campaign_id ) {
		global $wpdb; $t = ContactPanda_Newsletter_Schema::table_names();
		$campaign = self::campaign( $campaign_id );
		if ( ! $campaign ) return;
		echo '<div class="contactpanda-consent-modal contactpanda-mailing-modal contactpanda-recipient-modal"><section class="contactpanda-card contactpanda-mailing-details"><header class="contactpanda-mailing-modal-header"><h2>' . esc_html__( 'Mailing details', 'contactpanda' ) . '</h2><a class="button-link" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-campaigns' ) ) . '" aria-label="' . esc_attr__( 'Close', 'contactpanda' ) . '"><span class="dashicons dashicons-no-alt"></span></a></header>';
		if ( $campaign && 'imported' === $campaign['status'] ) {
			echo '<h3>' . esc_html__( 'Imported Groundhogg broadcast', 'contactpanda' ) . '</h3><div class="notice notice-info inline"><p>' . esc_html__( 'This completed broadcast is a protected archive. It cannot be started, changed, or sent again.', 'contactpanda' ) . '</p></div>';
			echo '<p><strong>' . esc_html__( 'Groundhogg ID', 'contactpanda' ) . ':</strong> ' . esc_html( $campaign['external_id'] ) . ' &nbsp; <strong>' . esc_html__( 'Sent', 'contactpanda' ) . ':</strong> ' . esc_html( $campaign['sent_count'] ) . ' &nbsp; <strong>' . esc_html__( 'Opened', 'contactpanda' ) . ':</strong> ' . esc_html( $campaign['opened_count'] ) . ' &nbsp; <strong>' . esc_html__( 'Clicked', 'contactpanda' ) . ':</strong> ' . esc_html( $campaign['clicked_count'] ) . ' &nbsp; <strong>' . esc_html__( 'Unsubscribed', 'contactpanda' ) . ':</strong> ' . esc_html( $campaign['unsubscribed_count'] ) . '</p>';
			echo '<div class="contactpanda-newsletter-preview-stage"><iframe sandbox="allow-popups allow-popups-to-escape-sandbox" style="width:100%;min-height:680px;border:1px solid #dcdcde;background:#fff" title="' . esc_attr__( 'Imported broadcast preview', 'contactpanda' ) . '" srcdoc="' . esc_attr( $campaign['archived_html'] ) . '"></iframe></div></section></div>';
			return;
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t['recipients']}` WHERE campaign_id=%d ORDER BY status ASC,email ASC LIMIT 500", $campaign_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$reasons = array( 'consent_missing' => __( 'Consent missing', 'contactpanda' ), 'suppressed' => __( 'Globally suppressed', 'contactpanda' ), 'invalid_email' => __( 'Invalid email address', 'contactpanda' ), 'wp_mail_failed' => __( 'Mail delivery failed', 'contactpanda' ) );
		echo '<h3>' . esc_html__( 'Recipient check', 'contactpanda' ) . '</h3><p>' . esc_html__( 'Skipped contacts are never sent automatically. If actual consent exists, document the manual override in the person’s Marketing & Consent section and prepare a new campaign.', 'contactpanda' ) . '</p><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Recipient', 'contactpanda' ) . '</th><th>' . esc_html__( 'Status', 'contactpanda' ) . '</th><th>' . esc_html__( 'Reason', 'contactpanda' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$name = trim( $row['first_name'] . ' ' . $row['last_name'] ); $person_url = admin_url( 'admin.php?page=contactpanda&person=' . absint( $row['contact_id'] ) );
			echo '<tr><td><a href="' . esc_url( $person_url ) . '">' . esc_html( $name ?: $row['email'] ) . '</a><br><small>' . esc_html( $row['email'] ) . '</small></td><td>' . esc_html( ucfirst( $row['status'] ) ) . '</td><td>' . esc_html( $reasons[ $row['reason'] ] ?? $row['reason'] ) . '</td></tr>';
		}
		if ( ! $rows ) { echo '<tr><td colspan="3">' . esc_html__( 'No recipients have been prepared yet.', 'contactpanda' ) . '</td></tr>'; }
		echo '</tbody></table></section></div>';
	}

	private static function render_form( $campaign = false ) {
		$templates = ContactPanda_Basic_Newsletters::templates(); $segments = ContactPanda_Basic_Segments::all(); $groups = self::campaign_groups();
		$newsletter_defaults = ContactPanda_Basic_Newsletters::public_settings();
		$value = static function ( $key, $default = '' ) use ( $campaign ) { return $campaign && isset( $campaign[ $key ] ) ? $campaign[ $key ] : $default; };
		$open = $campaign || ! empty( $_GET['new_campaign'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presentation state only.
		echo '<div id="contactpanda-mailing-editor-modal" class="contactpanda-consent-modal contactpanda-mailing-modal"' . ( $open ? '' : ' hidden' ) . '><section class="contactpanda-card contactpanda-mailing-editor"><header class="contactpanda-mailing-modal-header"><h2>' . esc_html( $campaign ? __( 'Edit newsletter mailing', 'contactpanda' ) : __( 'Newsletter mailing', 'contactpanda' ) ) . '</h2><button type="button" class="button-link contactpanda-close-mailing-modal" aria-label="' . esc_attr__( 'Close', 'contactpanda' ) . '"><span class="dashicons dashicons-no-alt"></span></button></header><form id="contactpanda-campaign-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_save_campaign"><input type="hidden" name="campaign_id" value="' . esc_attr( $value( 'id', 0 ) ) . '">'; wp_nonce_field( 'contactpanda_save_campaign' );
		echo '<div class="contactpanda-mailing-editor-grid"><div class="contactpanda-mailing-column"><div class="contactpanda-searchable-select"><span>' . esc_html__( 'Email template', 'contactpanda' ) . '</span><input type="search" class="contactpanda-select-search" placeholder="' . esc_attr__( 'Search email templates', 'contactpanda' ) . '" autocomplete="off"><select name="template_id" required><option value="">—</option>';
		foreach ( $templates as $template ) { echo '<option value="' . esc_attr( $template['id'] ) . '"' . selected( absint( $value( 'template_id' ) ), absint( $template['id'] ), false ) . '>' . esc_html( $template['name'] ) . '</option>'; }
		echo '</select></div><label><span>' . esc_html__( 'Mailing name', 'contactpanda' ) . '</span><input required name="name" value="' . esc_attr( $value( 'name' ) ) . '"></label><label><span>' . esc_html__( 'Subject', 'contactpanda' ) . '</span><input required name="subject" value="' . esc_attr( $value( 'subject' ) ) . '"></label><div class="contactpanda-searchable-select"><span>' . esc_html__( 'Campaign', 'contactpanda' ) . '</span><input type="search" class="contactpanda-select-search" placeholder="' . esc_attr__( 'Search campaigns', 'contactpanda' ) . '" autocomplete="off"><select name="campaign_group_id"><option value="0">—</option>';
		foreach ( $groups as $group ) echo '<option value="' . esc_attr( $group['id'] ) . '"' . selected( absint( $value( 'campaign_group_id' ) ), absint( $group['id'] ), false ) . '>' . esc_html( $group['name'] ) . '</option>';
		echo '</select></div><div class="contactpanda-searchable-select"><span>' . esc_html__( 'Saved search', 'contactpanda' ) . '</span><input type="search" class="contactpanda-select-search" placeholder="' . esc_attr__( 'Search saved searches', 'contactpanda' ) . '" autocomplete="off"><select name="segment_id"><option value="0">' . esc_html__( 'All eligible contacts', 'contactpanda' ) . '</option>';
		foreach ( $segments as $segment ) { echo '<option value="' . esc_attr( $segment['id'] ) . '"' . selected( absint( $value( 'segment_id' ) ), absint( $segment['id'] ), false ) . '>' . esc_html( $segment['name'] ) . '</option>'; }
		echo '</select></div><button type="button" class="button contactpanda-open-inline-search">' . esc_html__( 'Create new search', 'contactpanda' ) . '</button><fieldset class="contactpanda-audience-mode"><legend>' . esc_html__( 'Audience type', 'contactpanda' ) . '</legend><label><input type="radio" name="audience_mode" value="dynamic"' . checked( $value( 'audience_mode', 'dynamic' ), 'dynamic', false ) . '> ' . esc_html__( 'Dynamic – resolve matching contacts when sending starts', 'contactpanda' ) . '</label><label><input type="radio" name="audience_mode" value="static"' . checked( $value( 'audience_mode', 'dynamic' ), 'static', false ) . '> ' . esc_html__( 'Static – keep the contacts matching when this mailing is saved', 'contactpanda' ) . '</label><small>' . esc_html__( 'Current consent and suppression status is always checked again immediately before every message is sent.', 'contactpanda' ) . '</small></fieldset></div><div class="contactpanda-mailing-column contactpanda-mailing-actions-panel"><h3>' . esc_html__( 'Mailing settings', 'contactpanda' ) . '</h3><label><span>' . esc_html__( 'Sender name', 'contactpanda' ) . '</span><input required name="from_name" value="' . esc_attr( $value( 'from_name' ) ?: $newsletter_defaults['from_name'] ) . '"></label><label><span>' . esc_html__( 'Sending email address', 'contactpanda' ) . '</span><input required type="email" name="from_email" value="' . esc_attr( $value( 'from_email' ) ?: $newsletter_defaults['from_email'] ) . '"></label><label><span>' . esc_html__( 'Reply-to address', 'contactpanda' ) . '</span><input required type="email" name="reply_to" value="' . esc_attr( $value( 'reply_to' ) ?: $newsletter_defaults['reply_to'] ) . '"></label><label><span>' . esc_html__( 'Language', 'contactpanda' ) . '</span><select name="language"><option value="de_DE"' . selected( $value( 'language', 'de_DE' ), 'de_DE', false ) . '>Deutsch</option><option value="en_US"' . selected( $value( 'language' ), 'en_US', false ) . '>English</option></select></label>';
		$scheduled = $value( 'scheduled_at' ); $later = ! empty( $scheduled );
		echo '<fieldset class="contactpanda-campaign-timing"><legend>' . esc_html__( 'Delivery time', 'contactpanda' ) . '</legend><label><input type="radio" name="send_timing" value="now"' . checked( ! $later, true, false ) . '> ' . esc_html__( 'Immediately after starting', 'contactpanda' ) . '</label><label><input type="radio" name="send_timing" value="later"' . checked( $later, true, false ) . '> ' . esc_html__( 'Schedule for a date and time', 'contactpanda' ) . '</label><input type="datetime-local" name="scheduled_at" value="' . esc_attr( $scheduled ? mysql2date( 'Y-m-d\TH:i', $scheduled ) : '' ) . '"' . ( $later ? '' : ' hidden disabled' ) . '></fieldset><label><span>' . esc_html__( 'Emails per hour', 'contactpanda' ) . '</span><input required type="number" name="mails_per_hour" min="1" max="3000" step="1" value="' . esc_attr( absint( $value( 'mails_per_hour', 300 ) ) ?: 300 ) . '"><small>' . esc_html__( 'ContactPanda divides the mailing into controlled batches. Actual timing depends on the WordPress cron system and the configured mail service.', 'contactpanda' ) . '</small></label><div class="contactpanda-test-send"><strong>' . esc_html__( 'Test mailing', 'contactpanda' ) . '</strong><input type="email" id="contactpanda-campaign-test-email" value="' . esc_attr( wp_get_current_user()->user_email ) . '"><button type="button" class="button" id="contactpanda-campaign-test">' . esc_html__( 'Send test', 'contactpanda' ) . '</button></div><button type="button" class="button" id="contactpanda-campaign-preview">' . esc_html__( 'Preview', 'contactpanda' ) . '</button>'; submit_button( __( 'Save', 'contactpanda' ), 'primary', 'submit', false ); echo '<span id="contactpanda-campaign-message" aria-live="polite"></span></div></div></form></section></div>';
		echo '<div id="contactpanda-campaign-preview-modal" class="contactpanda-consent-modal contactpanda-preview-modal" hidden><div class="contactpanda-preview-panel"><header class="contactpanda-preview-header"><h2>' . esc_html__( 'Newsletter preview', 'contactpanda' ) . '</h2><div class="contactpanda-preview-modes"><button type="button" class="button is-active" data-campaign-preview-width="100%">' . esc_html__( 'Desktop', 'contactpanda' ) . '</button> <button type="button" class="button" data-campaign-preview-width="768px">' . esc_html__( 'Tablet', 'contactpanda' ) . '</button> <button type="button" class="button" data-campaign-preview-width="390px">' . esc_html__( 'Mobile', 'contactpanda' ) . '</button> <button type="button" class="button" id="contactpanda-close-campaign-preview">' . esc_html__( 'Close', 'contactpanda' ) . '</button></div></header><div class="contactpanda-newsletter-preview-stage"><iframe id="contactpanda-campaign-preview-frame" title="' . esc_attr__( 'Newsletter preview', 'contactpanda' ) . '"></iframe></div></div></div>';
		echo '<div class="contactpanda-consent-modal contactpanda-search-modal" hidden><div class="contactpanda-search-modal-panel">';
		ContactPanda_Basic_Segments::render_inline_search( '', 0, true );
		echo '</div></div>';
	}

	public static function save() {
		self::guard( 'contactpanda_save_campaign' ); global $wpdb; ContactPanda_Newsletter_Schema::install(); $t = ContactPanda_Newsletter_Schema::table_names();
		$template_id = absint( $_POST['template_id'] ?? 0 ); $template = ContactPanda_Basic_Newsletters::template( $template_id );
		if ( ! $template || ! empty( $template['imported_readonly'] ) || 'published' !== ( $template['status'] ?? '' ) ) { wp_die( esc_html__( 'Please select a published email template.', 'contactpanda' ) ); }
		$now = current_time( 'mysql' ); $id = absint( $_POST['campaign_id'] ?? 0 );
		$language = preg_replace( '/[^A-Za-z0-9_@.\-]/', '', (string) wp_unslash( $_POST['language'] ?? 'de_DE' ) );
		$from_name = sanitize_text_field( wp_unslash( $_POST['from_name'] ?? '' ) );
		$from_email = sanitize_email( wp_unslash( $_POST['from_email'] ?? '' ) );
		$reply_to = sanitize_email( wp_unslash( $_POST['reply_to'] ?? '' ) );
		if ( ! $from_name || ! is_email( $from_email ) || ! is_email( $reply_to ) ) {
			wp_die( esc_html__( 'Please enter valid sender and reply-to details.', 'contactpanda' ) );
		}
		$scheduled_at = 'later' === sanitize_key( wp_unslash( $_POST['send_timing'] ?? 'now' ) ) ? self::mysql_datetime( $_POST['scheduled_at'] ?? '' ) : null;
		$audience_mode = 'static' === sanitize_key( wp_unslash( $_POST['audience_mode'] ?? 'dynamic' ) ) ? 'static' : 'dynamic';
		$data = array( 'name' => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ), 'subject' => sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) ), 'template_id' => $template_id, 'segment_id' => absint( $_POST['segment_id'] ?? 0 ), 'audience_mode' => $audience_mode, 'audience_resolved_at' => null, 'campaign_group_id' => absint( $_POST['campaign_group_id'] ?? 0 ), 'language' => $language ?: 'de_DE', 'from_name' => $from_name, 'from_email' => $from_email, 'reply_to' => $reply_to, 'mails_per_hour' => min( 3000, max( 1, absint( $_POST['mails_per_hour'] ?? 300 ) ) ), 'status' => 'draft', 'scheduled_at' => $scheduled_at, 'updated_at' => $now );
		if ( $id ) {
			$existing = self::campaign( $id ); if ( ! $existing || ! in_array( $existing['status'], array( 'draft', 'scheduled', 'paused' ), true ) ) wp_die( esc_html__( 'This campaign can no longer be edited.', 'contactpanda' ) );
			wp_unschedule_hook( self::CRON_HOOK, array( $id ) ); $wpdb->update( $t['campaigns'], $data, array( 'id' => $id ) );
		} else {
			$data['created_by'] = get_current_user_id(); $data['created_at'] = $now; $wpdb->insert( $t['campaigns'], $data ); $id = absint( $wpdb->insert_id );
		}
		if ( 'static' === $audience_mode ) {
			self::prepare_recipients( $id );
			$wpdb->update( $t['campaigns'], array( 'audience_resolved_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) );
		} else {
			$wpdb->delete( $t['recipients'], array( 'campaign_id' => $id ) );
			$wpdb->update( $t['campaigns'], array( 'total_recipients' => 0, 'queued_count' => 0, 'sent_count' => 0, 'failed_count' => 0, 'skipped_count' => 0, 'audience_resolved_at' => null, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-campaigns' ) ); exit;
	}

	private static function prepare_recipients( $campaign_id ) {
		global $wpdb; $t = ContactPanda_Newsletter_Schema::table_names(); $campaign = self::campaign( $campaign_id ); if ( ! $campaign ) { return; }
		$contacts = ContactPanda_Plugin::extension_contacts();
		if ( $campaign['segment_id'] ) { $contacts = ContactPanda_Basic_Segments::filter_contacts( $contacts, $campaign['segment_id'] ); }
		$wpdb->delete( $t['recipients'], array( 'campaign_id' => $campaign_id ) ); $counts = array( 'queued' => 0, 'skipped' => 0 );
		foreach ( $contacts as $contact ) {
			$email = sanitize_email( ContactPanda_Plugin::extension_value( $contact, array( 'email', 'get_email' ) ) ); $contact_id = absint( ContactPanda_Plugin::extension_value( $contact, array( 'id', 'ID' ) ) );
			$status = 'queued'; $reason = '';
			if ( ! is_email( $email ) ) { $status = 'skipped'; $reason = 'invalid_email'; }
			elseif ( ! ContactPanda_Plugin::extension_is_marketable( $contact ) ) { $status = 'skipped'; $reason = 'consent_missing'; }
			elseif ( self::is_suppressed( $email ) ) { $status = 'skipped'; $reason = 'suppressed'; }
			$wpdb->insert( $t['recipients'], array( 'campaign_id' => $campaign_id, 'contact_id' => $contact_id, 'email' => strtolower( $email ), 'first_name' => sanitize_text_field( ContactPanda_Plugin::extension_value( $contact, array( 'first_name' ) ) ), 'last_name' => sanitize_text_field( ContactPanda_Plugin::extension_value( $contact, array( 'last_name' ) ) ), 'status' => $status, 'reason' => $reason, 'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ) );
			$counts[ $status ]++;
		}
		$wpdb->update( $t['campaigns'], array( 'total_recipients' => count( $contacts ), 'queued_count' => $counts['queued'], 'skipped_count' => $counts['skipped'], 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $campaign_id ) );
	}

	public static function campaign_action() {
		self::guard( 'contactpanda_campaign_action' ); global $wpdb; $t = ContactPanda_Newsletter_Schema::table_names(); $id = absint( $_GET['campaign_id'] ?? 0 ); $command = sanitize_key( wp_unslash( $_GET['command'] ?? '' ) ); $campaign = self::campaign( $id );
		if ( ! $campaign ) { wp_die( esc_html__( 'Campaign not found.', 'contactpanda' ) ); }
		if ( 'imported' === $campaign['status'] ) { wp_die( esc_html__( 'Imported broadcasts are read-only archives.', 'contactpanda' ) ); }
		if ( 'start' === $command ) {
			$timestamp = $campaign['scheduled_at'] ? get_gmt_from_date( $campaign['scheduled_at'], 'U' ) : time(); $status = $timestamp > time() ? 'scheduled' : 'queued';
			$wpdb->update( $t['campaigns'], array( 'status' => $status, 'started_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) ); self::schedule( $id, max( time() + 2, $timestamp ) );
		} elseif ( 'pause' === $command ) { $wpdb->update( $t['campaigns'], array( 'status' => 'paused', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) ); }
		elseif ( 'cancel' === $command ) { $wpdb->update( $t['campaigns'], array( 'status' => 'cancelled', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) ); }
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-campaigns' ) ); exit;
	}

	public static function process_queue( $campaign_id ) {
		global $wpdb; $t = ContactPanda_Newsletter_Schema::table_names(); $campaign = self::campaign( $campaign_id );
		if ( ! $campaign || ! in_array( $campaign['status'], array( 'queued', 'sending', 'scheduled' ), true ) ) { return; }
		if ( 'dynamic' === ( $campaign['audience_mode'] ?? 'dynamic' ) && empty( $campaign['audience_resolved_at'] ) ) {
			self::prepare_recipients( $campaign_id );
			$wpdb->update( $t['campaigns'], array( 'audience_resolved_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $campaign_id ) );
			$campaign = self::campaign( $campaign_id );
		}
		$wpdb->update( $t['campaigns'], array( 'status' => 'sending', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $campaign_id ) );
		$mails_per_hour = min( 3000, max( 1, absint( $campaign['mails_per_hour'] ?? 300 ) ) );
		$batch_size = max( 1, (int) ceil( $mails_per_hour / 60 ) );
		$batch_interval = max( 60, (int) ceil( ( 3600 * $batch_size ) / $mails_per_hour ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t['recipients']}` WHERE campaign_id=%d AND status IN ('queued','retry') AND attempts<3 ORDER BY id ASC LIMIT %d", $campaign_id, $batch_size ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$template = ContactPanda_Basic_Newsletters::template( $campaign['template_id'] ); $settings = ContactPanda_Basic_Newsletters::public_settings();
		foreach ( $rows as $row ) {
			$live_contact = ! empty( $row['contact_id'] ) ? ContactPanda_Plugin::extension_contact( absint( $row['contact_id'] ) ) : false;
			if ( ! $live_contact || ! ContactPanda_Plugin::extension_is_marketable( $live_contact ) ) { self::recipient_result( $row, 'skipped', 'consent_missing', '' ); continue; }
			if ( self::is_suppressed( $row['email'] ) ) { self::recipient_result( $row, 'skipped', 'suppressed', '' ); continue; }
			$url = self::unsubscribe_url( $row ); $html = ContactPanda_Basic_Newsletters::render_for_recipient( $template, $row, $url );
			$html = self::add_tracking( $html, $row );
			$from_email = sanitize_email( $campaign['from_email'] ?? '' ) ?: sanitize_email( $settings['from_email'] ) ?: sanitize_email( get_option( 'admin_email' ) ); $reply_to = sanitize_email( $campaign['reply_to'] ?? '' ) ?: sanitize_email( $settings['reply_to'] ) ?: $from_email;
			$from_name = sanitize_text_field( $campaign['from_name'] ?? '' ) ?: sanitize_text_field( $settings['from_name'] );
			$headers = array( 'Content-Type: text/html; charset=UTF-8', 'From: ' . $from_name . ' <' . $from_email . '>', 'Reply-To: ' . $reply_to );
			$subject = ContactPanda_Basic_Newsletters::personalize_text( $campaign['subject'], $row );
			$sent = wp_mail( $row['email'], $subject, $html, $headers ); $attempts = absint( $row['attempts'] ) + 1;
			self::recipient_result( $row, $sent ? 'sent' : ( $attempts < 3 ? 'retry' : 'failed' ), $sent ? '' : 'wp_mail_failed', $sent ? '' : __( 'WordPress could not hand the message to the configured mail system.', 'contactpanda' ), $attempts );
		}
		self::refresh_counts( $campaign_id ); $remaining = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t['recipients']}` WHERE campaign_id=%d AND status IN ('queued','retry') AND attempts<3", $campaign_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $remaining ) { self::schedule( $campaign_id, time() + $batch_interval ); } else { $wpdb->update( $t['campaigns'], array( 'status' => 'completed', 'completed_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $campaign_id ) ); }
	}

	private static function recipient_result( $row, $status, $reason, $error = '', $attempts = null ) {
		global $wpdb; $data = array( 'status' => $status, 'reason' => $reason, 'error_message' => $error, 'updated_at' => current_time( 'mysql' ) ); if ( null !== $attempts ) { $data['attempts'] = $attempts; } if ( 'sent' === $status ) { $data['sent_at'] = current_time( 'mysql' ); }
		$wpdb->update( ContactPanda_Newsletter_Schema::table_names()['recipients'], $data, array( 'id' => $row['id'] ) );
	}

	private static function refresh_counts( $id ) {
		global $wpdb; $t = ContactPanda_Newsletter_Schema::table_names(); $counts = array(); foreach ( array( 'queued', 'retry', 'sent', 'failed', 'skipped' ) as $status ) { $counts[ $status ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t['recipients']}` WHERE campaign_id=%d AND status=%s", $id, $status ) ); } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$opened = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t['recipients']}` WHERE campaign_id=%d AND opened_at IS NOT NULL", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$clicked = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t['recipients']}` WHERE campaign_id=%d AND clicked_at IS NOT NULL", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->update( $t['campaigns'], array( 'queued_count' => $counts['queued'] + $counts['retry'], 'sent_count' => $counts['sent'], 'failed_count' => $counts['failed'], 'skipped_count' => $counts['skipped'], 'opened_count' => $opened, 'clicked_count' => $clicked, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) );
	}

	private static function add_tracking( $html, $row ) {
		$html = preg_replace_callback(
			'/href=(?:"|\')([^"\']+)(?:"|\')/i',
			static function ( $match ) use ( $row ) {
				$url = html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' );
				if ( ! preg_match( '#^https?://#i', $url ) || false !== strpos( $url, 'contactpanda_newsletter_unsubscribe' ) ) return $match[0];
				return 'href="' . esc_url( self::click_url( $row, $url ) ) . '"';
			},
			$html
		);
		$pixel = '<img src="' . esc_url( self::open_url( $row ) ) . '" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;opacity:0">';
		return false !== stripos( $html, '</body>' ) ? str_ireplace( '</body>', $pixel . '</body>', $html ) : $html . $pixel;
	}

	private static function open_url( $row ) {
		return add_query_arg( array( 'action' => 'contactpanda_newsletter_open', 'recipient' => absint( $row['id'] ), 'token' => self::token( $row ) ), admin_url( 'admin-post.php' ) );
	}

	private static function click_url( $row, $target ) {
		$encoded = rtrim( strtr( base64_encode( $target ), '+/', '-_' ), '=' );
		$token = hash_hmac( 'sha256', absint( $row['id'] ) . '|' . absint( $row['campaign_id'] ) . '|' . $encoded, wp_salt( 'auth' ) );
		return add_query_arg( array( 'action' => 'contactpanda_newsletter_click', 'recipient' => absint( $row['id'] ), 'target' => $encoded, 'token' => $token ), admin_url( 'admin-post.php' ) );
	}

	public static function track_open() {
		$row = self::tracking_recipient();
		if ( $row && hash_equals( self::token( $row ), sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) ) ) ) {
			global $wpdb; $table = ContactPanda_Newsletter_Schema::table_names()['recipients'];
			if ( empty( $row['opened_at'] ) ) $wpdb->update( $table, array( 'opened_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $row['id'] ) );
			self::refresh_counts( absint( $row['campaign_id'] ) );
		}
		nocache_headers(); header( 'Content-Type: image/gif' ); echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==' ); exit; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}

	public static function track_click() {
		$row = self::tracking_recipient();
		$encoded = sanitize_text_field( wp_unslash( $_GET['target'] ?? '' ) );
		$padding = strlen( $encoded ) % 4; if ( $padding ) $encoded .= str_repeat( '=', 4 - $padding );
		$target = base64_decode( strtr( $encoded, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$signature_target = rtrim( $encoded, '=' );
		$expected = $row ? hash_hmac( 'sha256', absint( $row['id'] ) . '|' . absint( $row['campaign_id'] ) . '|' . $signature_target, wp_salt( 'auth' ) ) : '';
		$token = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
		if ( ! $row || ! $target || ! $expected || ! hash_equals( $expected, $token ) || ! preg_match( '#^https?://#i', $target ) ) wp_die( esc_html__( 'This link is invalid.', 'contactpanda' ), '', array( 'response' => 403 ) );
		global $wpdb; $table = ContactPanda_Newsletter_Schema::table_names()['recipients']; $now = current_time( 'mysql' );
		$data = array( 'updated_at' => $now ); if ( empty( $row['opened_at'] ) ) $data['opened_at'] = $now; if ( empty( $row['clicked_at'] ) ) $data['clicked_at'] = $now;
		$wpdb->update( $table, $data, array( 'id' => $row['id'] ) ); self::refresh_counts( absint( $row['campaign_id'] ) );
		wp_redirect( esc_url_raw( $target ), 302 ); exit; // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- validated external newsletter target.
	}

	private static function tracking_recipient() {
		global $wpdb; ContactPanda_Newsletter_Schema::install(); $id = absint( $_GET['recipient'] ?? 0 ); $table = ContactPanda_Newsletter_Schema::table_names()['recipients'];
		return $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id=%d", $id ), ARRAY_A ) : false; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function statuses_ajax() {
		self::guard_ajax();
		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['ids'] ?? array() ) ) ) ) );
		$ids = array_slice( $ids, 0, 100 );
		$statuses = array();
		foreach ( $ids as $id ) {
			$campaign = self::campaign( $id );
			if ( ! $campaign ) continue;
			$eligible = max( 0, absint( $campaign['total_recipients'] ) - absint( $campaign['skipped_count'] ) );
			$delivered = absint( $campaign['sent_count'] );
			$rate = static function ( $count, $base ) { return $base ? number_format_i18n( ( 100 * $count ) / $base, 1 ) . '%' : '0%'; };
			$processed = absint( $campaign['sent_count'] ) + absint( $campaign['failed_count'] ) + absint( $campaign['skipped_count'] );
			$progress = absint( $campaign['total_recipients'] ) ? min( 100, (int) round( 100 * $processed / absint( $campaign['total_recipients'] ) ) ) : 0;
			if ( 'completed' === $campaign['status'] ) $progress = 100;
			$statuses[ $id ] = array(
				'status'            => sanitize_key( $campaign['status'] ),
				'terminal'          => in_array( $campaign['status'], array( 'completed', 'cancelled' ), true ),
				'recipientSummary'  => sprintf( __( '%1$d sent · %2$d queued · %3$d skipped · %4$d failed', 'contactpanda' ), $campaign['sent_count'], $campaign['queued_count'], $campaign['skipped_count'], $campaign['failed_count'] ),
				'delivered'         => $delivered . ' · ' . $rate( $delivered, $eligible ),
				'opened'            => absint( $campaign['opened_count'] ) . ' · ' . $rate( absint( $campaign['opened_count'] ), $delivered ),
				'clicked'           => absint( $campaign['clicked_count'] ) . ' · ' . $rate( absint( $campaign['clicked_count'] ), $delivered ),
				'progress'          => $progress,
				'progressLabel'     => sprintf( __( 'Mailing progress: %d%%', 'contactpanda' ), $progress ),
				'statusLabel'       => self::status_label( $campaign['status'] ),
			);
		}
		wp_send_json_success( array( 'statuses' => $statuses ) );
	}

	public static function preview_ajax() {
		self::guard_ajax();
		$template = ContactPanda_Basic_Newsletters::template( absint( $_POST['template_id'] ?? 0 ) );
		if ( ! $template ) wp_send_json_error( array( 'message' => __( 'Please select an email template.', 'contactpanda' ) ), 400 );
		$user = wp_get_current_user();
		$recipient = array( 'contact_id' => 0, 'first_name' => $user->first_name, 'last_name' => $user->last_name, 'email' => $user->user_email );
		wp_send_json_success( array( 'html' => ContactPanda_Basic_Newsletters::render_for_recipient( $template, $recipient, '#' ) ) );
	}

	public static function test_ajax() {
		self::guard_ajax();
		$template = ContactPanda_Basic_Newsletters::template( absint( $_POST['template_id'] ?? 0 ) );
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$subject = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$from_name = sanitize_text_field( wp_unslash( $_POST['from_name'] ?? '' ) );
		$from_email = sanitize_email( wp_unslash( $_POST['from_email'] ?? '' ) );
		$reply_to = sanitize_email( wp_unslash( $_POST['reply_to'] ?? '' ) );
		if ( ! $template || ! is_email( $email ) || ! $subject || ! $from_name || ! is_email( $from_email ) || ! is_email( $reply_to ) ) wp_send_json_error( array( 'message' => __( 'Select a template and enter valid subject, sender and test recipient details.', 'contactpanda' ) ), 400 );
		$user = wp_get_current_user(); $recipient = array( 'contact_id' => 0, 'first_name' => $user->first_name, 'last_name' => $user->last_name, 'email' => $email );
		$headers = array( 'Content-Type: text/html; charset=UTF-8', 'From: ' . $from_name . ' <' . $from_email . '>', 'Reply-To: ' . $reply_to );
		$sent = wp_mail( $email, '[TEST] ' . ContactPanda_Basic_Newsletters::personalize_text( $subject, $recipient ), ContactPanda_Basic_Newsletters::render_for_recipient( $template, $recipient, '#' ), $headers );
		$sent ? wp_send_json_success( array( 'message' => sprintf( __( 'The test email was sent to %s.', 'contactpanda' ), $email ) ) ) : wp_send_json_error( array( 'message' => __( 'WordPress could not send the test email.', 'contactpanda' ) ), 500 );
	}

	public static function unsubscribe() {
		global $wpdb; ContactPanda_Newsletter_Schema::install(); $request = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ? $_POST : $_GET; $id = absint( $request['recipient'] ?? 0 ); $token = sanitize_text_field( wp_unslash( $request['token'] ?? '' ) ); $t = ContactPanda_Newsletter_Schema::table_names(); $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t['recipients']}` WHERE id=%d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row || ! hash_equals( self::token( $row ), $token ) ) { wp_die( esc_html__( 'This unsubscribe link is invalid.', 'contactpanda' ), '', array( 'response' => 403 ) ); }
		if ( self::is_suppressed( $row['email'] ) || 'unsubscribed' === ( $row['reason'] ?? '' ) ) {
			wp_die( '<h1>' . esc_html__( 'Already finally unsubscribed', 'contactpanda' ) . '</h1><p>' . esc_html__( 'This email address has already been finally unsubscribed from the newsletter.', 'contactpanda' ) . '</p>', esc_html__( 'Newsletter unsubscribe', 'contactpanda' ), array( 'response' => 200 ) );
		}
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			$parts = explode( '@', sanitize_email( $row['email'] ) ); $masked = $parts && count( $parts ) === 2 ? substr( $parts[0], 0, 2 ) . str_repeat( '*', max( 2, strlen( $parts[0] ) - 2 ) ) . '@' . $parts[1] : __( 'this email address', 'contactpanda' );
			echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html__( 'Confirm newsletter unsubscribe', 'contactpanda' ) . '</title><style>body{font:16px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f4f6f8;color:#253342;margin:0}.cp-box{max-width:620px;margin:10vh auto;background:#fff;border-radius:14px;padding:38px;box-shadow:0 12px 40px rgba(20,35,50,.12)}h1{margin-top:0}button{border:0;border-radius:7px;background:#334fe8;color:#fff;padding:13px 20px;font-weight:700;cursor:pointer}</style></head><body><main class="cp-box"><h1>' . esc_html__( 'Confirm final unsubscribe', 'contactpanda' ) . '</h1><p>' . esc_html( sprintf( __( 'Do you want to finally unsubscribe %s from the newsletter?', 'contactpanda' ), $masked ) ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_newsletter_unsubscribe"><input type="hidden" name="recipient" value="' . esc_attr( $id ) . '"><input type="hidden" name="token" value="' . esc_attr( $token ) . '"><input type="hidden" name="confirmation" value="' . esc_attr( self::confirmation_token( $row ) ) . '"><button type="submit">' . esc_html__( 'Finally unsubscribe', 'contactpanda' ) . '</button></form></main></body></html>';
			exit;
		}
		$confirmation = sanitize_text_field( wp_unslash( $_POST['confirmation'] ?? '' ) );
		if ( ! hash_equals( self::confirmation_token( $row ), $confirmation ) ) wp_die( esc_html__( 'The unsubscribe confirmation is invalid or expired.', 'contactpanda' ), '', array( 'response' => 403 ) );
		$now = current_time( 'mysql' );
		$wpdb->replace( $t['suppressions'], array( 'contact_id' => $row['contact_id'], 'email_hash' => hash( 'sha256', strtolower( $row['email'] ) ), 'reason' => 'unsubscribed', 'source' => 'newsletter_link', 'created_at' => $now ) );
		$wpdb->update( $t['recipients'], array( 'reason' => 'unsubscribed', 'updated_at' => $now ), array( 'id' => $row['id'] ) );
		$unsubscribed = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t['recipients']}` WHERE campaign_id=%d AND reason='unsubscribed'", $row['campaign_id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->update( $t['campaigns'], array( 'unsubscribed_count' => $unsubscribed, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $row['campaign_id'] ) );
		ContactPanda_Plugin::extension_unsubscribe_contact( $row['contact_id'], 'newsletter_link', sprintf( __( 'Email address %1$s finally unsubscribed through campaign %2$d at %3$s.', 'contactpanda' ), sanitize_email( $row['email'] ), $row['campaign_id'], $now ), $row['email'] );
		do_action( 'contactpanda_plus_email_event', 'unsubscribe', array( 'contact_id' => absint( $row['contact_id'] ), 'email' => sanitize_email( $row['email'] ), 'campaign_id' => absint( $row['campaign_id'] ), 'source' => 'newsletter_link', 'occurred_at' => $now ) );
		wp_die( '<h1>' . esc_html__( 'Finally unsubscribed', 'contactpanda' ) . '</h1><p>' . esc_html__( 'Your email address has been finally unsubscribed from the newsletter.', 'contactpanda' ) . '</p>', esc_html__( 'Newsletter unsubscribe', 'contactpanda' ), array( 'response' => 200 ) );
	}

	private static function campaign( $id ) { global $wpdb; $table = ContactPanda_Newsletter_Schema::table_names()['campaigns']; return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id=%d", absint( $id ) ), ARRAY_A ); } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	private static function is_suppressed( $email ) { global $wpdb; $table = ContactPanda_Newsletter_Schema::table_names()['suppressions']; return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE email_hash=%s", hash( 'sha256', strtolower( $email ) ) ) ); } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	private static function token( $row ) { return hash_hmac( 'sha256', absint( $row['id'] ) . '|' . absint( $row['campaign_id'] ), wp_salt( 'auth' ) ); }
	private static function confirmation_token( $row ) { return hash_hmac( 'sha256', 'confirm|' . absint( $row['id'] ) . '|' . strtolower( sanitize_email( $row['email'] ) ), wp_salt( 'secure_auth' ) ); }
	private static function unsubscribe_url( $row ) { return add_query_arg( array( 'action' => 'contactpanda_newsletter_unsubscribe', 'recipient' => absint( $row['id'] ), 'token' => self::token( $row ) ), admin_url( 'admin-post.php' ) ); }
	private static function schedule( $id, $timestamp ) { if ( ! wp_next_scheduled( self::CRON_HOOK, array( absint( $id ) ) ) ) { wp_schedule_single_event( $timestamp, self::CRON_HOOK, array( absint( $id ) ) ); } }
	private static function action_link( $id, $command, $label, $class = '' ) { return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=contactpanda_campaign_action&campaign_id=' . absint( $id ) . '&command=' . $command ), 'contactpanda_campaign_action' ) ) . '">' . esc_html( $label ) . '</a>'; }
	private static function status_label( $status ) { $labels = array( 'draft' => __( 'Draft', 'contactpanda' ), 'scheduled' => __( 'Scheduled', 'contactpanda' ), 'queued' => __( 'Queued', 'contactpanda' ), 'sending' => __( 'Sending', 'contactpanda' ), 'paused' => __( 'Paused', 'contactpanda' ), 'completed' => __( 'Completed', 'contactpanda' ), 'cancelled' => __( 'Cancelled', 'contactpanda' ), 'imported' => __( 'Imported archive', 'contactpanda' ) ); return $labels[ $status ] ?? $status; }
	private static function mysql_datetime( $value ) { $value = sanitize_text_field( wp_unslash( $value ) ); if ( ! $value ) { return null; } try { $date = new DateTimeImmutable( $value, wp_timezone() ); return $date->format( 'Y-m-d H:i:s' ); } catch ( Exception $e ) { return null; } }
	private static function guard( $nonce ) { if ( ! current_user_can( self::capability() ) ) { wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ) ); } check_admin_referer( $nonce ); }
	private static function guard_ajax() { if ( ! current_user_can( self::capability() ) ) wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'contactpanda' ) ), 403 ); check_ajax_referer( 'contactpanda_newsletter_editor', 'nonce' ); }
	private static function capability() { return class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options'; }
}
