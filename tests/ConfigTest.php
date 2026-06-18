<?php

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use WatersMeet\BciWorkflow\Config;

final class ConfigTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wm_bci_test_options']    = array();
		$GLOBALS['wm_bci_settings_errors'] = array();
	}

	public function test_normalize_recipient_list_filters_invalid_emails_and_dedupes(): void {
		$normalized = Config::normalize_recipient_list(
			"first@example.com,\ninvalid-email,\nFIRST@example.com,\nsecond@example.com"
		);

		$this->assertSame( 'first@example.com, second@example.com', $normalized );
	}

	public function test_accessor_returns_normalized_recipient_list(): void {
		$GLOBALS['wm_bci_test_options']['wm_bci_workflow'] = array(
			'approval_notification_recipients' => "first@example.com\nsecond@example.com",
		);

		$config = new Config();

		$this->assertSame( 'first@example.com, second@example.com', $config->approval_notification_recipients() );
	}

	public function test_normalize_user_ids_filters_invalid_values_and_dedupes(): void {
		$this->assertSame(
			array( 12, 9 ),
			Config::normalize_user_ids( array( '12', 0, 'abc', 12, -9, '9' ) )
		);
	}

	public function test_accessor_returns_normalized_auto_approved_user_ids(): void {
		$GLOBALS['wm_bci_test_options']['wm_bci_workflow'] = array(
			'auto_approved_user_ids' => array( '18', '18', '7', 0 ),
		);

		$config = new Config();

		$this->assertSame( array( 18, 7 ), $config->auto_approved_user_ids() );
	}

	public function test_accessor_returns_sanitized_calendar_event_colors(): void {
		$GLOBALS['wm_bci_test_options']['wm_bci_workflow'] = array(
			'calendar_event_colors' => array(
				'Event'       => '#004966',
				'Grant / RFP' => 'invalid',
				'Resources'   => '#ABC',
				'Workshop'    => '#336699',
			),
		);

		$config = new Config();

		$this->assertSame(
			array(
				'Event' => '#004966',
			),
			$config->calendar_event_colors()
		);
	}

	public function test_calendar_event_palette_matches_swatches(): void {
		$this->assertSame(
			array(
				'#004966' => 'Dark Blue',
				'#d9a242' => 'Gold',
				'#b34d34' => 'Rust',
				'#7e5f8e' => 'Plum',
				'#5c6e7a' => 'Slate',
				'#520066' => 'Purple',
				'#c2385a' => 'Rose',
				'#418359' => 'Green',
			),
			Config::calendar_event_palette()
		);
	}
}
