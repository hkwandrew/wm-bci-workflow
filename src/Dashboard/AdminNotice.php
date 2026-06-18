<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\Dashboard;

use WatersMeet\BciWorkflow\Config;

/**
 * Renders admin notices after Google sync retry actions.
 */
final class AdminNotice {

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	public function render(): void {
		if ( ! $this->current_user_can_export() ) {
			return;
		}

		if ( ! empty( $_GET['wm_bci_csv_import'] ) ) {
			$this->render_csv_import_notice();
			return;
		}

		if ( empty( $_GET['wm_bci_sync_retry'] ) ) {
			return;
		}

		$status = sanitize_key( wp_unslash( $_GET['wm_bci_sync_retry'] ) );

		if ( 'config-missing' === $status ) {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'Automatic Google Sheet sync is not configured. Save the Google sync URL and shared secret in Settings > BCI Workflow before retrying.', 'wm-bci-workflow' )
				. '</p></div>';
			return;
		}

		if ( 'complete' !== $status ) {
			return;
		}

		$success = isset( $_GET['success'] ) ? absint( wp_unslash( $_GET['success'] ) ) : 0;
		$failed  = isset( $_GET['failed'] ) ? absint( wp_unslash( $_GET['failed'] ) ) : 0;
		$skipped = isset( $_GET['skipped'] ) ? absint( wp_unslash( $_GET['skipped'] ) ) : 0;
		$class   = $failed > 0 ? 'notice notice-warning' : 'notice notice-success';

		printf(
			'<div class="%1$s"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html(
				sprintf(
					'Google Sheet sync retry finished. Synced: %1$d. Failed: %2$d. Skipped: %3$d.',
					$success,
					$failed,
					$skipped
				)
			)
		);
	}

	private function render_csv_import_notice(): void {
		$status = sanitize_key( wp_unslash( $_GET['wm_bci_csv_import'] ) );

		if ( 'failed' === $status ) {
			$message = isset( $_GET['message'] )
				? sanitize_text_field( rawurldecode( wp_unslash( $_GET['message'] ) ) )
				: __( 'The Google Sheet import could not be completed.', 'wm-bci-workflow' );

			echo '<div class="notice notice-error"><p>'
				. esc_html( $message )
				. '</p></div>';
			return;
		}

		if ( 'complete' !== $status ) {
			return;
		}

		$created = isset( $_GET['created'] ) ? absint( wp_unslash( $_GET['created'] ) ) : 0;
		$failed  = isset( $_GET['failed'] ) ? absint( wp_unslash( $_GET['failed'] ) ) : 0;
		$skipped = isset( $_GET['skipped'] ) ? absint( wp_unslash( $_GET['skipped'] ) ) : 0;
		$class   = $failed > 0 ? 'notice notice-warning' : 'notice notice-success';

		printf(
			'<div class="%1$s"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html(
				sprintf(
					'Google Sheet import finished. Created: %1$d. Failed: %2$d. Skipped: %3$d.',
					$created,
					$failed,
					$skipped
				)
			)
		);
	}

	private function current_user_can_export(): bool {
		return class_exists( 'GFCommon' ) && \GFCommon::current_user_can_any( 'gravityforms_export_entries' );
	}
}
