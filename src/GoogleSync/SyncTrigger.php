<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\GoogleSync;

use WatersMeet\BciWorkflow\Config;
use WatersMeet\BciWorkflow\Entry\FieldAccessor;
use GFAPI;

/**
 * Hooks that trigger Google Sheets sync when approval status changes.
 */
final class SyncTrigger {

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	public function register(): void {
		$form_id = $this->config->form_id();
		add_action( "gform_post_update_entry_{$form_id}", array( $this, 'after_update' ), 10, 2 );
		add_action( "gform_after_update_entry_{$form_id}", array( $this, 'after_manual_update' ), 10, 3 );
	}

	/**
	 * @param array $entry          Updated entry data.
	 * @param array $original_entry Original entry data.
	 */
	public function after_update( array $entry, array $original_entry ): void {
		if ( $this->config->form_id() !== (int) rgar( $entry, 'form_id' ) ) {
			return;
		}

		$fields          = new FieldAccessor( $this->config );
		$entry_id        = absint( rgar( $entry, 'id' ) );
		$previous_status = $fields->approval_status( $original_entry );
		$current_status  = $fields->approval_status( $entry );

		$this->maybe_set_approved_at( $entry_id, $current_status );

		if ( 'Approved' !== $current_status || $previous_status === $current_status ) {
			return;
		}

		$sync = new SyncManager( $this->config );
		$sync->sync_entry( $entry_id, $entry );
	}

	/**
	 * @param array $form           Form data.
	 * @param int   $entry_id       Entry ID.
	 * @param array $original_entry Original entry data.
	 */
	public function after_manual_update( array $form, $entry_id, array $original_entry ): void {
		if ( $this->config->form_id() !== (int) rgar( $form, 'id' ) ) {
			return;
		}

		$entry = GFAPI::get_entry( $entry_id );

		if ( is_wp_error( $entry ) ) {
			return;
		}

		$this->after_update( $entry, $original_entry );
	}

	private function maybe_set_approved_at( int $entry_id, string $current ): void {
		$meta_key = Config::APPROVED_AT_META_KEY;
		$existing = gform_get_meta( $entry_id, $meta_key );

		if ( 'Approved' !== $current || $existing ) {
			return;
		}

		gform_update_meta( $entry_id, $meta_key, gmdate( 'c' ), $this->config->form_id() );
	}
}
