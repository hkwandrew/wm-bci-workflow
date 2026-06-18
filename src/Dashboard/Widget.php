<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\Dashboard;

use WatersMeet\BciWorkflow\Config;
use WatersMeet\BciWorkflow\Export\RowMapper;
use WatersMeet\BciWorkflow\GoogleSync\SyncManager;
use GFAPI;

/**
 * Dashboard widget showing BCI sync stats and quick actions.
 */
final class Widget {

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	public function register(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'setup' ) );
	}

	public function setup(): void {
		if ( ! $this->current_user_can_export() ) {
			return;
		}

		wp_add_dashboard_widget(
			'wm_bci_export_widget',
			esc_html__( 'BCI Newsletter Export', 'wm-bci-workflow' ),
			array( $this, 'render' )
		);
	}

	public function render(): void {
		$summary = $this->get_summary();

		echo '<p>' . esc_html__( 'Approved opportunities can sync automatically to the Google Sheet used for the weekly newsletter workflow.', 'wm-bci-workflow' ) . '</p>';

		if ( ! $summary['sync_configured'] ) {
			echo '<p><strong>' . esc_html__( 'Automatic sync is not configured.', 'wm-bci-workflow' ) . '</strong> '
				. esc_html__( 'Save the Google sync URL and shared secret in Settings > BCI Workflow to enable it.', 'wm-bci-workflow' ) . '</p>';
		} elseif ( 0 === (int) $summary['awaiting_sync'] && 0 === (int) $summary['sync_failed'] ) {
			echo '<p>' . esc_html__( 'Automatic Google Sheet sync is up to date.', 'wm-bci-workflow' ) . '</p>';
		}

		echo '<ul>';
		echo '<li><strong>' . esc_html__( 'Approved and synced:', 'wm-bci-workflow' ) . '</strong> ' . esc_html( (string) $summary['synced'] ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Approved awaiting sync:', 'wm-bci-workflow' ) . '</strong> ' . esc_html( (string) $summary['awaiting_sync'] ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Sync failed:', 'wm-bci-workflow' ) . '</strong> ' . esc_html( (string) $summary['sync_failed'] ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Pending review:', 'wm-bci-workflow' ) . '</strong> ' . esc_html( (string) $summary['pending'] ) . '</li>';
		echo '</ul>';

		if ( $summary['sync_configured'] && ( (int) $summary['awaiting_sync'] > 0 || (int) $summary['sync_failed'] > 0 ) ) {
			echo '<p><a class="button button-primary" href="' . esc_url( $this->retry_url() ) . '">'
				. esc_html__( 'Retry Google Sheet Sync', 'wm-bci-workflow' ) . '</a></p>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
		echo '<input type="hidden" name="action" value="wm_bci_import_google_sheet_csv" />';
		wp_nonce_field( 'wm_bci_import_google_sheet_csv' );
		echo '<p><label for="wm-bci-google-sheet-csv"><strong>' . esc_html__( 'Import Existing Google Sheet CSV', 'wm-bci-workflow' ) . '</strong></label></p>';
		echo '<p><input type="file" id="wm-bci-google-sheet-csv" name="wm_bci_google_sheet_csv" accept=".csv,text/csv" /></p>';
		submit_button( __( 'Import CSV Rows', 'wm-bci-workflow' ), 'secondary', 'submit', false );
		echo '<p class="description">' . esc_html__( 'Use once after downloading the connected Google Sheet tab as CSV. Imported rows are marked as already synced.', 'wm-bci-workflow' ) . '</p>';
		echo '</form>';

		echo '<p><a class="button" href="' . esc_url( $this->export_url() ) . '">'
			. esc_html__( 'Download Approved Opportunities CSV', 'wm-bci-workflow' ) . '</a></p>';

		echo '<p><a href="' . esc_url( $this->entries_url() ) . '">'
			. esc_html__( 'Open form entries', 'wm-bci-workflow' ) . '</a></p>';
	}

	/**
	 * @return array<string,int|bool>
	 */
	private function get_summary(): array {
		$row_mapper       = new RowMapper( $this->config );
		$sync             = new SyncManager( $this->config );
		$approved_entries = $row_mapper->approved_entries();
		$pending_count    = 0;
		$synced_count     = 0;
		$awaiting_sync    = 0;
		$sync_failed      = 0;

		foreach ( $approved_entries as $entry ) {
			$status = $sync->get_sync_status( absint( rgar( $entry, 'id' ) ) );

			if ( 'success' === $status ) {
				++$synced_count;
			} elseif ( 'failed' === $status ) {
				++$sync_failed;
			} else {
				++$awaiting_sync;
			}
		}

		$search_criteria = array(
			'status'        => 'active',
			'field_filters' => array(
				array(
					'key'      => $this->config->approval_field_id(),
					'operator' => 'is',
					'value'    => 'Pending',
				),
			),
		);

		GFAPI::get_entries(
			$this->config->form_id(),
			$search_criteria,
			null,
			array( 'offset' => 0, 'page_size' => 1 ),
			$pending_count
		);

		return array(
			'approved_exportable' => count( $approved_entries ),
			'pending'             => (int) $pending_count,
			'synced'              => $synced_count,
			'awaiting_sync'       => $awaiting_sync,
			'sync_failed'         => $sync_failed,
			'sync_configured'     => $this->config->is_google_sync_configured(),
		);
	}

	private function export_url(): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'wm_bci_export_approved_csv',
					'form_id' => $this->config->form_id(),
				),
				admin_url( 'admin-post.php' )
			),
			'wm_bci_export_approved_csv'
		);
	}

	private function retry_url(): string {
		return wp_nonce_url(
			add_query_arg(
				array( 'action' => 'wm_bci_retry_google_sync' ),
				admin_url( 'admin-post.php' )
			),
			'wm_bci_retry_google_sync'
		);
	}

	private function entries_url(): string {
		return add_query_arg(
			array(
				'page' => 'gf_entries',
				'id'   => $this->config->form_id(),
			),
			admin_url( 'admin.php' )
		);
	}

	private function current_user_can_export(): bool {
		return class_exists( 'GFCommon' ) && \GFCommon::current_user_can_any( 'gravityforms_export_entries' );
	}
}
