<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow;

/**
 * Centralized settings accessor — reads from the wm_bci_workflow option with
 * sensible defaults matching the current hard-coded theme values.
 */
final class Config {

	public const APPROVED_AT_META_KEY             = 'waters_meet_bci_approved_at';
	public const GOOGLE_SYNC_STATUS_META_KEY      = 'waters_meet_bci_google_sync_status';
	public const GOOGLE_SYNC_ATTEMPTED_AT_META_KEY = 'waters_meet_bci_google_sync_attempted_at';
	public const GOOGLE_SYNC_SYNCED_AT_META_KEY   = 'waters_meet_bci_google_sync_synced_at';
	public const GOOGLE_SYNC_ERROR_META_KEY       = 'waters_meet_bci_google_sync_error';
	public const CSV_IMPORT_HASH_META_KEY         = 'waters_meet_bci_google_sheet_csv_import_hash';
	public const CSV_IMPORT_IMPORTED_AT_META_KEY  = 'waters_meet_bci_google_sheet_csv_imported_at';

	/** @var array<string,mixed> */
	private array $options;

	/** @var array<string,string> */
	private static array $default_field_map = array(
		'opportunity_type' => '1',
		'submitter_name'   => '3',
		'title'            => '4',
		'organization'     => '5',
		'start_date'       => '6',
		'grant_deadline'   => '9',
		'end_date'         => '10',
		'start_time'       => '12',
		'cost'             => '14',
		'address'          => '15',
		'description'      => '17',
		'info_url'         => '18',
		'file_upload'      => '19',
		'end_time'         => '21',
		'approval_status'  => '22',
	);

	public function __construct() {
		$raw = get_option( 'wm_bci_workflow', array() );
		$this->options = is_array( $raw ) ? $raw : array();
	}

	public function form_id(): int {
		return (int) ( $this->options['form_id'] ?? 4 );
	}

	public function approval_field_id(): string {
		return (string) ( $this->options['approval_field_id'] ?? '22' );
	}

	/**
	 * @return array<string,string>
	 */
	public function field_map(): array {
		$stored = $this->options['field_map'] ?? array();

		if ( is_array( $stored ) && ! empty( $stored ) ) {
			return array_merge( self::$default_field_map, $stored );
		}

		return self::$default_field_map;
	}

	public function field( string $key ): string {
		$map = $this->field_map();
		return $map[ $key ] ?? '';
	}

	public function notification_name(): string {
		return (string) ( $this->options['notification_name'] ?? 'Admin Notification' );
	}

	public function approval_notification_recipients(): string {
		return self::normalize_recipient_list( (string) ( $this->options['approval_notification_recipients'] ?? '' ) );
	}

	/**
	 * @return array<int,int>
	 */
	public function auto_approved_user_ids(): array {
		return self::normalize_user_ids( $this->options['auto_approved_user_ids'] ?? array() );
	}

	public function calendar_page_slug(): string {
		return (string) ( $this->options['calendar_page_slug'] ?? 'bci-resources' );
	}

	public function calendar_feed_name(): string {
		return (string) ( $this->options['calendar_feed_name'] ?? 'BCI Community Opportunity Submission' );
	}

	/**
	 * @return array<string,string>
	 */
	public function calendar_event_colors(): array {
		return self::normalize_calendar_event_colors( $this->options['calendar_event_colors'] ?? array() );
	}

	public function calendar_event_color( string $type ): string {
		$type = trim( $type );

		if ( '' === $type ) {
			return '';
		}

		$colors = $this->calendar_event_colors();

		return $colors[ $type ] ?? '';
	}

	/**
	 * Event color palette.
	 *
	 * @return array<string,string>
	 */
	public static function calendar_event_palette(): array {
		return array(
			'#004966' => 'Dark Blue',
			'#d9a242' => 'Gold',
			'#b34d34' => 'Rust',
			'#7e5f8e' => 'Plum',
			'#5c6e7a' => 'Slate',
			'#520066' => 'Purple',
			'#c2385a' => 'Rose',
			'#418359' => 'Green',
		);
	}

	public function google_sync_url(): string {
		$option = trim( (string) ( $this->options['google_sync_url'] ?? '' ) );

		if ( '' !== $option ) {
			return $option;
		}

		return defined( 'WATERS_MEET_BCI_GOOGLE_SYNC_URL' )
			? trim( (string) WATERS_MEET_BCI_GOOGLE_SYNC_URL )
			: '';
	}

	public function google_sync_secret(): string {
		$option = (string) ( $this->options['google_sync_secret'] ?? '' );

		if ( '' !== $option ) {
			return $option;
		}

		return defined( 'WATERS_MEET_BCI_GOOGLE_SYNC_SECRET' )
			? (string) WATERS_MEET_BCI_GOOGLE_SYNC_SECRET
			: '';
	}

	public function is_google_sync_configured(): bool {
		return '' !== $this->google_sync_url() && '' !== $this->google_sync_secret();
	}

	/**
	 * @return array<string,string>
	 */
	public function approval_statuses(): array {
		return array(
			'pending'  => 'Pending',
			'approved' => 'Approved',
			'rejected' => 'Rejected',
		);
	}

	public function status_label( string $status ): string {
		$statuses = $this->approval_statuses();
		$key      = sanitize_key( $status );
		return $statuses[ $key ] ?? '';
	}

	/**
	 * @return array{valid: array<int, string>, invalid: array<int, string>}
	 */
	public static function split_recipient_list( string $raw ): array {
		$parts = preg_split( '/[\r\n,]+/', $raw );

		if ( false === $parts ) {
			return array(
				'valid'   => array(),
				'invalid' => array(),
			);
		}

		$valid   = array();
		$invalid = array();
		$seen    = array();

		foreach ( $parts as $part ) {
			$email = trim( $part );

			if ( '' === $email ) {
				continue;
			}

			$sanitized = sanitize_email( $email );

			if ( '' === $sanitized || false === is_email( $sanitized ) ) {
				$invalid[] = $email;
				continue;
			}

			$key = strtolower( $sanitized );

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$valid[]      = $sanitized;
		}

		return array(
			'valid'   => $valid,
			'invalid' => $invalid,
		);
	}

	public static function normalize_recipient_list( string $raw ): string {
		$recipients = self::split_recipient_list( $raw );

		return implode( ', ', $recipients['valid'] );
	}

	/**
	 * @param mixed $raw
	 * @return array<int,int>
	 */
	public static function normalize_user_ids( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$ids  = array();
		$seen = array();

		foreach ( $raw as $value ) {
			$user_id = absint( $value );

			if ( ! $user_id || isset( $seen[ $user_id ] ) ) {
				continue;
			}

			$seen[ $user_id ] = true;
			$ids[]            = $user_id;
		}

		return $ids;
	}

	/**
	 * @param mixed $raw
	 * @return array<string,string>
	 */
	public static function normalize_calendar_event_colors( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$allowed_colors = array_fill_keys( array_keys( self::calendar_event_palette() ), true );
		$colors = array();

		foreach ( $raw as $type => $color ) {
			$type  = trim( (string) $type );
			$color = self::normalize_hex_color( (string) $color );

			if ( '' === $type || '' === $color || ! isset( $allowed_colors[ $color ] ) ) {
				continue;
			}

			$colors[ $type ] = $color;
		}

		return $colors;
	}

	/**
	 * @return array<string,string>
	 */
	public static function default_field_map(): array {
		return self::$default_field_map;
	}

	private static function normalize_hex_color( string $color ): string {
		$color = trim( strtolower( $color ) );

		if ( preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/', $color ) ) {
			return $color;
		}

		return '';
	}
}
