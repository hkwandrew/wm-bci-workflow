<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\GoogleSync;

use WatersMeet\BciWorkflow\Config;
use WP_Error;

/**
 * Admin action handler for the one-time Google Sheet CSV import.
 */
final class CsvImportHandler {

	private const ACTION_NAME = 'wm_bci_import_google_sheet_csv';
	private const FILE_FIELD  = 'wm_bci_google_sheet_csv';
	private const MAX_BYTES   = 2097152;

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION_NAME, array( $this, 'handle' ) );
	}

	public function handle(): void {
		if ( ! $this->current_user_can_export() ) {
			wp_die(
				esc_html__( 'You are not allowed to import Gravity Forms entries.', 'wm-bci-workflow' ),
				esc_html__( 'Forbidden', 'wm-bci-workflow' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::ACTION_NAME );

		$file = $this->validated_upload();

		if ( is_wp_error( $file ) ) {
			$this->redirect_failure( $file->get_error_message() );
		}

		$result = ( new CsvImporter( $this->config ) )->import_file( $file );

		if ( is_wp_error( $result ) ) {
			$this->redirect_failure( $result->get_error_message() );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'wm_bci_csv_import' => 'complete',
					'created'           => $result['created'],
					'failed'            => $result['failed'],
					'skipped'           => $result['skipped'],
				),
				admin_url( 'index.php' )
			)
		);
		exit;
	}

	/**
	 * @return string|WP_Error
	 */
	private function validated_upload() {
		if ( empty( $_FILES[ self::FILE_FIELD ] ) || ! is_array( $_FILES[ self::FILE_FIELD ] ) ) {
			return new WP_Error( 'wm_bci_csv_import_missing_file', 'Choose a CSV file exported from the Google Sheet.' );
		}

		$file  = $_FILES[ self::FILE_FIELD ];
		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_OK !== $error ) {
			return new WP_Error( 'wm_bci_csv_import_upload_failed', 'The CSV upload failed. Choose the file again and retry.' );
		}

		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;

		if ( $size <= 0 || $size > self::MAX_BYTES ) {
			return new WP_Error( 'wm_bci_csv_import_invalid_size', 'The CSV file must be larger than 0 bytes and no larger than 2 MB.' );
		}

		$name = isset( $file['name'] ) ? sanitize_file_name( (string) $file['name'] ) : '';

		if ( 'csv' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			return new WP_Error( 'wm_bci_csv_import_invalid_extension', 'Upload a .csv file exported from Google Sheets.' );
		}

		$tmp_name = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';

		if ( '' === $tmp_name || ! is_uploaded_file( $tmp_name ) || ! is_readable( $tmp_name ) ) {
			return new WP_Error( 'wm_bci_csv_import_unreadable', 'The uploaded CSV file could not be read.' );
		}

		return $tmp_name;
	}

	private function redirect_failure( string $message ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'wm_bci_csv_import' => 'failed',
					'message'           => $message,
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
