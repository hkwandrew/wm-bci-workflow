<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\GoogleSync;

use WatersMeet\BciWorkflow\Config;
use WatersMeet\BciWorkflow\Export\RowMapper;

/**
 * Admin action handler for retrying failed Google Sheets syncs.
 */
final class RetryHandler {

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	public function register(): void {
		add_action( 'admin_post_wm_bci_retry_google_sync', array( $this, 'handle' ) );
	}

	public function handle(): void {
		if ( ! $this->current_user_can_export() ) {
			wp_die(
				esc_html__( 'You are not allowed to sync Gravity Forms entries.', 'wm-bci-workflow' ),
				esc_html__( 'Forbidden', 'wm-bci-workflow' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( 'wm_bci_retry_google_sync' );

		if ( ! $this->config->is_google_sync_configured() ) {
			wp_safe_redirect(
				add_query_arg(
					array( 'wm_bci_sync_retry' => 'config-missing' ),
					admin_url( 'index.php' )
				)
			);
			exit;
		}

		$results = array(
			'success' => 0,
			'failed'  => 0,
			'skipped' => 0,
		);

		$row_mapper = new RowMapper( $this->config );
		$sync       = new SyncManager( $this->config );

		foreach ( $row_mapper->approved_entries() as $entry ) {
			$entry_id = absint( rgar( $entry, 'id' ) );

			if ( 'success' === $sync->get_sync_status( $entry_id ) ) {
				++$results['skipped'];
				continue;
			}

			$result = $sync->sync_entry( $entry_id, $entry );

			if ( is_wp_error( $result ) ) {
				++$results['failed'];
				continue;
			}

			if ( 'success' === $result['result'] ) {
				++$results['success'];
				continue;
			}

			++$results['skipped'];
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'wm_bci_sync_retry' => 'complete',
					'success'           => $results['success'],
					'failed'            => $results['failed'],
					'skipped'           => $results['skipped'],
				),
				admin_url( 'index.php' )
			)
		);
		exit;
	}

	private function current_user_can_export(): bool {
		return class_exists( 'GFCommon' ) && \GFCommon::current_user_can_any( 'gravityforms_export_entries' );
	}
}
