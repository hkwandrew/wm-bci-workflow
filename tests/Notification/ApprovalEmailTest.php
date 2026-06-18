<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\Tests\Notification;

use PHPUnit\Framework\TestCase;
use WatersMeet\BciWorkflow\Config;
use WatersMeet\BciWorkflow\Notification\ApprovalEmail;

final class ApprovalEmailTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wm_bci_test_options']    = array();
		$GLOBALS['wm_bci_settings_errors'] = array();
	}

	public function test_customize_overrides_notification_recipient_list_when_plugin_setting_is_present(): void {
		$GLOBALS['wm_bci_test_options']['wm_bci_workflow'] = array(
			'approval_notification_recipients' => "first@example.com\nsecond@example.com",
		);

		$email        = new ApprovalEmail( new Config() );
		$notification = $email->customize(
			array(
				'name'    => 'Admin Notification',
				'toType'  => 'routing',
				'to'      => '',
				'routing' => array(
					array(
						'email' => 'legacy@example.com',
					),
				),
			),
			array(
				'id' => 4,
			),
			array(
				'id'  => 42,
				'4'   => 'Community Workshop',
				'3.3' => 'Avery',
				'3.6' => 'Smith',
			)
		);

		$this->assertSame( 'email', $notification['toType'] );
		$this->assertSame( 'first@example.com, second@example.com', $notification['to'] );
		$this->assertSame( '', $notification['toField'] );
		$this->assertNull( $notification['routing'] );
		$this->assertSame( 'Review needed: Community Workshop', $notification['subject'] );
		$this->assertStringContainsString( 'Approve submission', $notification['message'] );
	}

	public function test_customize_leaves_existing_recipient_destination_when_plugin_setting_is_blank(): void {
		$email        = new ApprovalEmail( new Config() );
		$notification = $email->customize(
			array(
				'name'    => 'Admin Notification',
				'toType'  => 'routing',
				'to'      => '',
				'routing' => array(
					array(
						'email' => 'legacy@example.com',
					),
				),
			),
			array(
				'id' => 4,
			),
			array(
				'id' => 42,
				'4'  => 'Community Workshop',
			)
		);

		$this->assertSame( 'routing', $notification['toType'] );
		$this->assertSame(
			array(
				array(
					'email' => 'legacy@example.com',
				),
			),
			$notification['routing']
		);
	}
}
