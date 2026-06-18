<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\Export;

use WatersMeet\BciWorkflow\Config;

/**
 * Handles CSV export of approved BCI opportunities.
 */
final class CsvExporter {

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	public function register(): void {
		add_action( 'admin_post_wm_bci_export_approved_csv', array( $this, 'handle' ) );
	}

	public function handle(): void {
		if ( ! $this->current_user_can_export() ) {
			wp_die(
				esc_html__( 'You are not allowed to export Gravity Forms entries.', 'wm-bci-workflow' ),
				esc_html__( 'Forbidden', 'wm-bci-workflow' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( 'wm_bci_export_approved_csv' );

		$row_mapper = new RowMapper( $this->config );
		$headers    = $row_mapper->headers();
		$rows       = $row_mapper->export_rows();
		$stream     = fopen( 'php://output', 'w' );

		if ( false === $stream ) {
			wp_die(
				esc_html__( 'The CSV export could not be generated.', 'wm-bci-workflow' ),
				esc_html__( 'Export Failed', 'wm-bci-workflow' ),
				array( 'response' => 500 )
			);
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="bci-approved-opportunities-' . gmdate( 'Y-m-d' ) . '.csv"' );

		echo "\xEF\xBB\xBF";

		fputcsv( $stream, $headers );

		foreach ( $rows as $row ) {
			$line = array();
			foreach ( $headers as $header_label ) {
				$line[] = $row[ $header_label ] ?? '';
			}
			fputcsv( $stream, $line );
		}

		fclose( $stream );
		exit;
	}

	private function current_user_can_export(): bool {
		return class_exists( 'GFCommon' ) && \GFCommon::current_user_can_any( 'gravityforms_export_entries' );
	}
}
