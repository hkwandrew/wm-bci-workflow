<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\Tests\Admin;

use PHPUnit\Framework\TestCase;
use WatersMeet\BciWorkflow\Admin\SettingsPage;
use WatersMeet\BciWorkflow\Config;

final class SettingsPageTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wm_bci_test_options']    = array();
		$GLOBALS['wm_bci_settings_errors'] = array();
		$GLOBALS['wm_bci_test_users']      = array();
		\GFAPI::$wm_bci_test_form         = array();
	}

	public function test_sanitize_normalizes_recipient_list_and_records_warning_for_invalid_emails(): void {
		$page  = new SettingsPage( new Config() );
		$clean = $page->sanitize(
			array(
				'approval_notification_recipients' => "first@example.com\ninvalid-email,\nsecond@example.com,\nFIRST@example.com",
			)
		);

		$this->assertSame( 'first@example.com, second@example.com', $clean['approval_notification_recipients'] );
		$this->assertCount( 1, $GLOBALS['wm_bci_settings_errors'] );
		$this->assertSame( 'warning', $GLOBALS['wm_bci_settings_errors'][0]['type'] );
		$this->assertStringContainsString( 'invalid approval recipient email', $GLOBALS['wm_bci_settings_errors'][0]['message'] );
	}

	public function test_sanitize_accepts_empty_recipient_list_without_errors(): void {
		$page  = new SettingsPage( new Config() );
		$clean = $page->sanitize(
			array(
				'approval_notification_recipients' => '',
			)
		);

		$this->assertSame( '', $clean['approval_notification_recipients'] );
		$this->assertSame( array(), $GLOBALS['wm_bci_settings_errors'] );
	}

	public function test_sanitize_keeps_only_existing_auto_approved_user_ids(): void {
		$GLOBALS['wm_bci_test_users'] = array(
			(object) array(
				'ID'           => 12,
				'display_name' => 'Avery Smith',
				'user_login'   => 'asmith',
				'user_email'   => 'avery@example.com',
			),
			(object) array(
				'ID'           => 18,
				'display_name' => 'Casey Jones',
				'user_login'   => 'cjones',
				'user_email'   => 'casey@example.com',
			),
		);

		$page  = new SettingsPage( new Config() );
		$clean = $page->sanitize(
			array(
				'auto_approved_user_ids'         => array( '18', '999', '12', '18' ),
				'auto_approved_user_ids_present' => '1',
			)
		);

		$this->assertSame( array( 18, 12 ), $clean['auto_approved_user_ids'] );
	}

	public function test_sanitize_clears_auto_approved_user_ids_when_none_selected(): void {
		$GLOBALS['wm_bci_test_options']['wm_bci_workflow'] = array(
			'auto_approved_user_ids' => array( 12, 18 ),
		);

		$page  = new SettingsPage( new Config() );
		$clean = $page->sanitize(
			array(
				'auto_approved_user_ids_present' => '1',
			)
		);

		$this->assertSame( array(), $clean['auto_approved_user_ids'] );
	}

	public function test_sanitize_keeps_only_palette_calendar_event_colors(): void {
		$page  = new SettingsPage( new Config() );
		$clean = $page->sanitize(
			array(
				'calendar_event_colors' => array(
					'Event'       => '#004966',
					'Grant / RFP' => 'invalid',
					'Resources'   => '#5c6e7a',
					'Workshop'    => '#336699',
				),
			)
		);

		$this->assertSame(
			array(
				'Event'     => '#004966',
				'Resources' => '#5c6e7a',
			),
			$clean['calendar_event_colors']
		);
	}

	public function test_sanitize_clears_existing_calendar_event_color_when_no_color_is_selected(): void {
		$GLOBALS['wm_bci_test_options']['wm_bci_workflow'] = array(
			'calendar_event_colors' => array(
				'Event' => '#004966',
			),
		);

		$page  = new SettingsPage( new Config() );
		$clean = $page->sanitize(
			array(
				'calendar_event_colors_present' => '1',
			)
		);

		$this->assertSame( array(), $clean['calendar_event_colors'] );
	}

	public function test_sanitize_preserves_existing_calendar_event_colors_when_field_is_not_submitted(): void {
		$GLOBALS['wm_bci_test_options']['wm_bci_workflow'] = array(
			'calendar_event_colors' => array(
				'Event' => '#004966',
			),
		);

		$page  = new SettingsPage( new Config() );
		$clean = $page->sanitize(
			array(
				'calendar_page_slug' => 'bci-resources',
			)
		);

		$this->assertSame(
			array(
				'Event' => '#004966',
			),
			$clean['calendar_event_colors']
		);
	}

}
