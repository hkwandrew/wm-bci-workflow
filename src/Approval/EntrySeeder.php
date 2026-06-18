<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\Approval;

use WatersMeet\BciWorkflow\Config;
use WatersMeet\BciWorkflow\GoogleSync\SyncManager;
use GFAPI;

/**
 * Seeds new BCI submissions to "Pending" and syncs Grant/RFP deadline → start date.
 */
final class EntrySeeder {

	private Config $config;
	/** @var array<int,bool> */
	private array $auto_approved_entry_ids = array();

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	public function register(): void {
		$form_id = $this->config->form_id();
		add_filter( "gform_entry_post_save_{$form_id}", array( $this, 'seed_approval' ), 10, 2 );
		add_filter( "gform_disable_notification_{$form_id}", array( $this, 'disable_review_notification' ), 10, 5 );
	}

	/**
	 * @param array $entry Entry data.
	 * @param array $form  Form data.
	 * @return array
	 */
	public function seed_approval( array $entry, array $form ): array {
		$approval_field_id = $this->config->approval_field_id();

		if ( $this->config->form_id() !== (int) rgar( $form, 'id' ) || ! empty( rgar( $entry, $approval_field_id ) ) ) {
			return $this->sync_dates( $entry );
		}

		$entry = $this->sync_dates( $entry );

		$approval_status             = $this->should_auto_approve( $entry ) ? 'Approved' : 'Pending';
		$entry[ $approval_field_id ] = $approval_status;

		$entry_id = absint( rgar( $entry, 'id' ) );
		GFAPI::update_entry_field( $entry_id, $approval_field_id, $approval_status );

		if ( 'Approved' === $approval_status && $entry_id ) {
			$this->auto_approved_entry_ids[ $entry_id ] = true;
			$this->maybe_set_approved_at( $entry_id );
			GFAPI::add_note(
				$entry_id,
				0,
				'WM BCI Workflow',
				'Calendar approval set to Approved automatically because the submitter is on the auto-approved user list.'
			);

			$sync = new SyncManager( $this->config );
			$sync->sync_entry( $entry_id, $entry );
		}

		return $entry;
	}

	/**
	 * @param array<string,mixed> $notification
	 * @param array<string,mixed> $form
	 * @param array<string,mixed> $entry
	 * @param array<string,mixed> $data
	 */
	public function disable_review_notification( bool $disabled, array $notification, array $form, array $entry, array $data = array() ): bool {
		if ( $disabled || $this->config->notification_name() !== (string) rgar( $notification, 'name' ) ) {
			return $disabled;
		}

		if ( $this->config->form_id() !== (int) rgar( $form, 'id' ) ) {
			return $disabled;
		}

		$entry_id = absint( rgar( $entry, 'id' ) );

		return $entry_id && isset( $this->auto_approved_entry_ids[ $entry_id ] );
	}

	/**
	 * Copy Grant/RFP deadline into the calendar start date field when needed.
	 */
	private function sync_dates( array $entry ): array {
		if ( $this->config->form_id() !== (int) rgar( $entry, 'form_id' ) ) {
			return $entry;
		}

		$type_field      = $this->config->field( 'opportunity_type' );
		$start_field     = $this->config->field( 'start_date' );
		$deadline_field  = $this->config->field( 'grant_deadline' );

		$type          = trim( (string) rgar( $entry, $type_field ) );
		$calendar_date = trim( (string) rgar( $entry, $start_field ) );
		$grant_date    = trim( (string) rgar( $entry, $deadline_field ) );
		$entry_id      = absint( rgar( $entry, 'id' ) );

		if ( 'Grant / RFP' !== $type || '' !== $calendar_date || '' === $grant_date ) {
			return $entry;
		}

		$entry[ $start_field ] = $grant_date;

		if ( $entry_id ) {
			GFAPI::update_entry_field( $entry_id, $start_field, $grant_date );
		}

		return $entry;
	}

	private function should_auto_approve( array $entry ): bool {
		$created_by = absint( rgar( $entry, 'created_by' ) );

		return $created_by && in_array( $created_by, $this->config->auto_approved_user_ids(), true );
	}

	private function maybe_set_approved_at( int $entry_id ): void {
		$meta_key = Config::APPROVED_AT_META_KEY;
		$existing = gform_get_meta( $entry_id, $meta_key );

		if ( $existing ) {
			return;
		}

		gform_update_meta( $entry_id, $meta_key, gmdate( 'c' ), $this->config->form_id() );
	}
}
