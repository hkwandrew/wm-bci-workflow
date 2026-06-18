<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\Tests\Approval;

use PHPUnit\Framework\TestCase;
use WatersMeet\BciWorkflow\Approval\EntrySeeder;
use WatersMeet\BciWorkflow\Config;

final class EntrySeederTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wm_bci_test_options']        = array();
		$GLOBALS['wm_bci_test_entry_meta']     = array();
		$GLOBALS['wm_bci_updated_entry_fields'] = array();
		\GFAPI::$wm_bci_entries                = array();
		\GFAPI::$wm_bci_notes                  = array();
	}

	public function test_seed_approval_auto_approves_allowlisted_submitter_and_suppresses_review_notification(): void {
		$GLOBALS['wm_bci_test_options']['wm_bci_workflow'] = array(
			'auto_approved_user_ids' => array( 12 ),
		);

		$entry = array(
			'id'         => 301,
			'form_id'    => 4,
			'created_by' => 12,
			'22'         => '',
			'4'          => 'Community Workshop',
		);
		$form  = array(
			'id' => 4,
		);

		\GFAPI::$wm_bci_entries[301] = $entry;

		$seeder = new EntrySeeder( new Config() );
		$seeded = $seeder->seed_approval( $entry, $form );

		$this->assertSame( 'Approved', $seeded['22'] );
		$this->assertSame( 'Approved', \GFAPI::$wm_bci_entries[301]['22'] );
		$this->assertNotEmpty( $GLOBALS['wm_bci_test_entry_meta'][301][ Config::APPROVED_AT_META_KEY ] ?? '' );
		$this->assertCount( 1, \GFAPI::$wm_bci_notes );
		$this->assertStringContainsString( 'Approved automatically', \GFAPI::$wm_bci_notes[0]['note'] );
		$this->assertTrue(
			$seeder->disable_review_notification(
				false,
				array( 'name' => 'Admin Notification' ),
				$form,
				array( 'id' => 301 )
			)
		);
		$this->assertFalse(
			$seeder->disable_review_notification(
				false,
				array( 'name' => 'Different Notification' ),
				$form,
				array( 'id' => 301 )
			)
		);
	}

	public function test_seed_approval_keeps_non_allowlisted_submitter_pending_and_syncs_grant_date(): void {
		$entry = array(
			'id'         => 302,
			'form_id'    => 4,
			'created_by' => 77,
			'1'          => 'Grant / RFP',
			'6'          => '',
			'9'          => '2026-07-15',
			'22'         => '',
		);
		$form  = array(
			'id' => 4,
		);

		\GFAPI::$wm_bci_entries[302] = $entry;

		$seeder = new EntrySeeder( new Config() );
		$seeded = $seeder->seed_approval( $entry, $form );

		$this->assertSame( 'Pending', $seeded['22'] );
		$this->assertSame( '2026-07-15', $seeded['6'] );
		$this->assertSame( 'Pending', \GFAPI::$wm_bci_entries[302]['22'] );
		$this->assertSame( '2026-07-15', \GFAPI::$wm_bci_entries[302]['6'] );
		$this->assertArrayNotHasKey( 302, $GLOBALS['wm_bci_test_entry_meta'] );
		$this->assertFalse(
			$seeder->disable_review_notification(
				false,
				array( 'name' => 'Admin Notification' ),
				$form,
				array( 'id' => 302 )
			)
		);
	}
}
