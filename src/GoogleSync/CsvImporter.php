<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\GoogleSync;

use GFAPI;
use WatersMeet\BciWorkflow\Config;
use WatersMeet\BciWorkflow\Entry\FieldAccessor;
use WatersMeet\BciWorkflow\Export\RowMapper;
use WP_Error;

/**
 * Imports existing Google Sheet CSV rows into Gravity Forms entries.
 */
final class CsvImporter {

	private Config $config;

	/** @var array<int,string>|null */
	private ?array $headers = null;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * @return array{created: int, skipped: int, failed: int}|WP_Error
	 */
	public function import_file( string $file_path ) {
		$rows = $this->read_rows( $file_path );

		if ( is_wp_error( $rows ) ) {
			return $rows;
		}

		$existing_hashes = $this->existing_import_hashes();
		$results         = array(
			'created' => 0,
			'skipped' => 0,
			'failed'  => 0,
		);

		foreach ( $rows as $row ) {
			if ( $this->is_blank_row( $row ) ) {
				++$results['skipped'];
				continue;
			}

			$hash = $this->row_hash( $row );

			if ( isset( $existing_hashes[ $hash ] ) ) {
				++$results['skipped'];
				continue;
			}

			$entry_id = GFAPI::add_entry( $this->entry_from_row( $row ) );

			if ( is_wp_error( $entry_id ) || ! absint( $entry_id ) ) {
				++$results['failed'];
				continue;
			}

			$this->mark_imported( absint( $entry_id ), $hash );
			$existing_hashes[ $hash ] = true;
			++$results['created'];
		}

		return $results;
	}

	/**
	 * @return array<int,array<string,string>>|WP_Error
	 */
	private function read_rows( string $file_path ) {
		if ( '' === $file_path || ! is_readable( $file_path ) ) {
			return new WP_Error( 'wm_bci_csv_import_unreadable', 'The uploaded CSV file could not be read.' );
		}

		$handle = fopen( $file_path, 'rb' );

		if ( false === $handle ) {
			return new WP_Error( 'wm_bci_csv_import_unreadable', 'The uploaded CSV file could not be opened.' );
		}

		$raw_headers = fgetcsv( $handle, 0, ',', '"', '' );

		if ( false === $raw_headers || ! $this->headers_match( $raw_headers ) ) {
			fclose( $handle );
			return new WP_Error( 'wm_bci_csv_import_header_mismatch', 'The CSV header row does not match the BCI export columns.' );
		}

		$rows    = array();
		$headers = $this->headers();

		while ( false !== ( $raw_row = fgetcsv( $handle, 0, ',', '"', '' ) ) ) {
			$row = array();

			foreach ( $headers as $index => $header ) {
				$row[ $header ] = $this->normalize_cell( $raw_row[ $index ] ?? '' );
			}

			$rows[] = $row;
		}

		fclose( $handle );

		return $rows;
	}

	/**
	 * @param array<int,mixed> $raw_headers
	 */
	private function headers_match( array $raw_headers ): bool {
		$headers = $this->headers();

		foreach ( $headers as $index => $header ) {
			$raw = $this->normalize_cell( $raw_headers[ $index ] ?? '' );

			if ( 0 === $index ) {
				$raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw ) ?? $raw;
			}

			if ( $raw !== $header ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @return array<int,string>
	 */
	private function headers(): array {
		if ( null === $this->headers ) {
			$this->headers = ( new RowMapper( $this->config ) )->headers();
		}

		return $this->headers;
	}

	/**
	 * @return array<string,bool>
	 */
	private function existing_import_hashes(): array {
		$hashes      = array();
		$total_count = 0;
		$offset      = 0;
		$page_size   = 200;

		do {
			$entries = GFAPI::get_entries(
				$this->config->form_id(),
				array( 'status' => 'active' ),
				null,
				array(
					'offset'    => $offset,
					'page_size' => $page_size,
				),
				$total_count
			);

			if ( is_wp_error( $entries ) || empty( $entries ) ) {
				break;
			}

			foreach ( $entries as $entry ) {
				$entry_id = absint( rgar( $entry, 'id' ) );
				$hash     = trim( (string) gform_get_meta( $entry_id, Config::CSV_IMPORT_HASH_META_KEY ) );

				if ( '' !== $hash ) {
					$hashes[ $hash ] = true;
				}
			}

			$offset += count( $entries );
		} while ( $offset < $total_count );

		return $hashes;
	}

	/**
	 * @param array<string,string> $row
	 * @return array<string,mixed>
	 */
	private function entry_from_row( array $row ): array {
		$headers = $this->headers();
		$fields  = new FieldAccessor( $this->config );
		$type    = $this->normalize_opportunity_type( $this->row_value( $row, $headers[1] ) );
		$date    = $this->normalize_date( $this->row_value( $row, $headers[5] ) );
		$entry   = array(
			'form_id'      => $this->config->form_id(),
			'status'       => 'active',
			'is_read'      => 0,
			'is_starred'   => 0,
			'date_created' => $this->normalize_datetime( $this->row_value( $row, $headers[0] ) ),
		);

		$this->set_field( $entry, 'opportunity_type', $type );
		$this->set_name_field( $entry, $this->row_value( $row, $headers[2] ), $fields );
		$this->set_field( $entry, 'title', $this->row_value( $row, $headers[3] ) );
		$this->set_field( $entry, 'organization', $this->row_value( $row, $headers[4] ) );

		if ( 'Grant / RFP' === $type ) {
			$this->set_field( $entry, 'grant_deadline', $date );
		}

		$this->set_field( $entry, 'start_date', $date );
		$this->set_field( $entry, 'end_date', $this->normalize_date( $this->row_value( $row, $headers[6] ) ) );
		$this->set_field( $entry, 'start_time', $this->row_value( $row, $headers[7] ) );
		$this->set_address_field( $entry, $this->row_value( $row, $headers[8] ) );
		$this->set_field( $entry, 'cost', $this->row_value( $row, $headers[9] ) );
		$this->set_field( $entry, 'description', $this->row_value( $row, $headers[10] ) );
		$this->set_field( $entry, 'info_url', esc_url_raw( $this->row_value( $row, $headers[11] ) ) );
		$this->set_field( $entry, 'file_upload', $this->row_value( $row, $headers[12] ) );

		$approval_field = $this->config->approval_field_id();
		if ( '' !== $approval_field ) {
			$entry[ $approval_field ] = 'Approved';
		}

		return $entry;
	}

	/**
	 * @param array<string,mixed> $entry
	 */
	private function set_field( array &$entry, string $field_key, string $value ): void {
		$field_id = $this->config->field( $field_key );

		if ( '' === $field_id ) {
			return;
		}

		$entry[ $field_id ] = $value;
	}

	/**
	 * @param array<string,mixed> $entry
	 */
	private function set_name_field( array &$entry, string $name, FieldAccessor $fields ): void {
		$field_id = $this->config->field( 'submitter_name' );

		if ( '' === $field_id ) {
			return;
		}

		list( $first, $last ) = $fields->split_name( $name );

		$entry[ $field_id . '.3' ] = $first;
		$entry[ $field_id . '.6' ] = $last;
	}

	/**
	 * @param array<string,mixed> $entry
	 */
	private function set_address_field( array &$entry, string $address ): void {
		$field_id = $this->config->field( 'address' );

		if ( '' === $field_id ) {
			return;
		}

		$entry[ $field_id . '.1' ] = $address;
	}

	private function normalize_opportunity_type( string $type ): string {
		$fields     = new FieldAccessor( $this->config );
		$normalized = $fields->form_choice_from_legacy_type( $type );

		return '' !== $normalized ? $normalized : $type;
	}

	private function normalize_date( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		$timestamp = strtotime( $value );

		return false === $timestamp ? $value : gmdate( 'Y-m-d', $timestamp );
	}

	private function normalize_datetime( string $value ): string {
		$timestamp = '' === $value ? false : strtotime( $value );

		return false === $timestamp ? gmdate( 'Y-m-d H:i:s' ) : gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	private function normalize_cell( $value ): string {
		if ( null === $value ) {
			return '';
		}

		if ( is_scalar( $value ) ) {
			return trim( (string) $value );
		}

		$encoded = wp_json_encode( $value );
		return false === $encoded ? '' : trim( $encoded );
	}

	/**
	 * @param array<string,string> $row
	 */
	private function is_blank_row( array $row ): bool {
		foreach ( $row as $value ) {
			if ( '' !== trim( (string) $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<string,string> $row
	 */
	private function row_hash( array $row ): string {
		$ordered = array();

		foreach ( $this->headers() as $header ) {
			$ordered[ $header ] = $this->row_value( $row, $header );
		}

		return hash( 'sha256', (string) wp_json_encode( $ordered ) );
	}

	/**
	 * @param array<string,string> $row
	 */
	private function row_value( array $row, string $header ): string {
		return trim( (string) ( $row[ $header ] ?? '' ) );
	}

	private function mark_imported( int $entry_id, string $hash ): void {
		$now     = gmdate( 'c' );
		$form_id = $this->config->form_id();

		gform_update_meta( $entry_id, Config::CSV_IMPORT_HASH_META_KEY, $hash, $form_id );
		gform_update_meta( $entry_id, Config::CSV_IMPORT_IMPORTED_AT_META_KEY, $now, $form_id );
		gform_update_meta( $entry_id, Config::APPROVED_AT_META_KEY, $now, $form_id );
		gform_update_meta( $entry_id, Config::GOOGLE_SYNC_STATUS_META_KEY, 'success', $form_id );
		gform_update_meta( $entry_id, Config::GOOGLE_SYNC_ATTEMPTED_AT_META_KEY, $now, $form_id );
		gform_update_meta( $entry_id, Config::GOOGLE_SYNC_SYNCED_AT_META_KEY, $now, $form_id );
		gform_update_meta( $entry_id, Config::GOOGLE_SYNC_ERROR_META_KEY, '', $form_id );

		GFAPI::add_note(
			$entry_id,
			0,
			'WM BCI CSV Import',
			'Imported from a Google Sheet CSV upload and marked as already synced.'
		);
	}
}
