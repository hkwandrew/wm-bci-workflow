<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\GoogleSync;

use WatersMeet\BciWorkflow\Config;
use WatersMeet\BciWorkflow\Entry\FieldAccessor;
use WatersMeet\BciWorkflow\Export\RowMapper;
use GFAPI;
use WP_Error;

/**
 * Core Google Sheets sync logic — builds payload, signs, sends, records result.
 */
final class SyncManager {

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * @return array{result: string, disposition: string}|WP_Error
	 */
	public function sync_entry( int $entry_id, ?array $entry = null ) {
		$entry_id = absint( $entry_id );

		if ( ! $entry_id ) {
			return new WP_Error( 'wm_bci_sync_missing_entry', 'Missing BCI entry ID.' );
		}

		if ( null === $entry ) {
			$entry = GFAPI::get_entry( $entry_id );
		}

		if ( is_wp_error( $entry ) ) {
			return $entry;
		}

		if ( $this->config->form_id() !== (int) rgar( $entry, 'form_id' ) ) {
			return new WP_Error( 'wm_bci_sync_wrong_form', 'This entry is not part of the BCI workflow.' );
		}

		$fields = new FieldAccessor( $this->config );

		if ( 'Approved' !== $fields->approval_status( $entry ) ) {
			return new WP_Error( 'wm_bci_sync_not_approved', 'Only approved BCI entries can be synced.' );
		}

		if ( 'success' === $this->get_sync_status( $entry_id ) ) {
			return array(
				'result'      => 'skipped',
				'disposition' => 'already_synced',
			);
		}

		if ( ! $this->config->is_google_sync_configured() ) {
			return new WP_Error(
				'wm_bci_sync_not_configured',
				'Automatic Google Sheet sync is not configured. Define WATERS_MEET_BCI_GOOGLE_SYNC_URL and WATERS_MEET_BCI_GOOGLE_SYNC_SECRET.'
			);
		}

		$approved_at = trim( (string) gform_get_meta( $entry_id, Config::APPROVED_AT_META_KEY ) );

		if ( '' === $approved_at ) {
			$approved_at = gmdate( 'c' );
			gform_update_meta( $entry_id, Config::APPROVED_AT_META_KEY, $approved_at, $this->config->form_id() );
		}

		$row_mapper = new RowMapper( $this->config );
		$headers    = $row_mapper->headers();
		$row_data   = $row_mapper->row_data( $entry );
		$row        = array();

		foreach ( $headers as $header_label ) {
			$row[] = $row_data[ $header_label ] ?? '';
		}

		$payload = array(
			'event'            => 'bci_entry_approved',
			'entryId'          => $entry_id,
			'approvedAt'       => $approved_at,
			'headers'          => $headers,
			'row'              => $row,
			'sourceEntryUrl'   => $this->entry_admin_url( $entry_id ),
			'sourceEntriesUrl' => $this->entries_admin_url(),
		);

		$body = wp_json_encode( $payload );

		if ( false === $body ) {
			return $this->mark_failure(
				$entry_id,
				sprintf( 'The Google sync payload could not be encoded: %s', function_exists( 'json_last_error_msg' ) ? json_last_error_msg() : 'Unknown JSON error' ),
				gmdate( 'c' )
			);
		}

		$attempted_at = gmdate( 'c' );
		$signature    = hash_hmac( 'sha256', $body, $this->config->google_sync_secret() );
		$sync_url     = add_query_arg( array( 'signature' => $signature ), $this->config->google_sync_url() );

		$response = HttpClient::request(
			$sync_url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'            => 'application/json; charset=utf-8',
					'X-Waters-Meet-Signature' => $signature,
				),
				'body'    => $body,
			),
			'POST'
		);

		if ( is_wp_error( $response ) ) {
			return $this->mark_failure( $entry_id, $response->get_error_message(), $attempted_at );
		}

		$response_code = (int) wp_remote_retrieve_response_code( $response );
		$response_body = (string) wp_remote_retrieve_body( $response );
		$response_data = json_decode( $response_body, true );

		if ( $response_code < 200 || $response_code >= 300 ) {
			$message = sprintf( 'The Google sync endpoint returned HTTP %d.', $response_code );
			if ( is_array( $response_data ) && ! empty( $response_data['error'] ) ) {
				$message .= ' ' . sanitize_text_field( (string) $response_data['error'] );
			}
			return $this->mark_failure( $entry_id, $message, $attempted_at );
		}

		if ( ! is_array( $response_data ) || empty( $response_data['ok'] ) ) {
			$message = 'The Google sync endpoint returned an unexpected response.';
			if ( '' !== $response_body ) {
				$body_excerpt = substr( trim( wp_strip_all_tags( $response_body ) ), 0, 180 );
				if ( '' !== $body_excerpt ) {
					$message .= ' ' . $body_excerpt;
				}
			}
			return $this->mark_failure( $entry_id, $message, $attempted_at );
		}

		$disposition = sanitize_key( isset( $response_data['disposition'] ) ? (string) $response_data['disposition'] : '' );

		if ( ! in_array( $disposition, array( 'appended', 'duplicate' ), true ) ) {
			return $this->mark_failure(
				$entry_id,
				'The Google sync endpoint did not return a recognized disposition.',
				$attempted_at
			);
		}

		return $this->mark_success( $entry_id, $disposition, $attempted_at );
	}

	public function get_sync_status( int $entry_id ): string {
		return trim( (string) gform_get_meta( $entry_id, Config::GOOGLE_SYNC_STATUS_META_KEY ) );
	}

	/**
	 * @return array{result: string, disposition: string}
	 */
	private function mark_success( int $entry_id, string $disposition, string $attempted_at ): array {
		$synced_at = gmdate( 'c' );
		$form_id   = $this->config->form_id();

		gform_update_meta( $entry_id, Config::GOOGLE_SYNC_STATUS_META_KEY, 'success', $form_id );
		gform_update_meta( $entry_id, Config::GOOGLE_SYNC_ATTEMPTED_AT_META_KEY, $attempted_at, $form_id );
		gform_update_meta( $entry_id, Config::GOOGLE_SYNC_SYNCED_AT_META_KEY, $synced_at, $form_id );
		gform_update_meta( $entry_id, Config::GOOGLE_SYNC_ERROR_META_KEY, '', $form_id );

		GFAPI::add_note(
			$entry_id,
			0,
			'WM BCI Google Sync',
			sprintf( 'Approved opportunity synced to the Google Sheet workflow (%s).', $disposition )
		);

		return array(
			'result'      => 'success',
			'disposition' => $disposition,
		);
	}

	private function mark_failure( int $entry_id, string $message, string $attempted_at ): WP_Error {
		$message = substr( trim( $message ), 0, 500 );
		$form_id = $this->config->form_id();

		gform_update_meta( $entry_id, Config::GOOGLE_SYNC_STATUS_META_KEY, 'failed', $form_id );
		gform_update_meta( $entry_id, Config::GOOGLE_SYNC_ATTEMPTED_AT_META_KEY, $attempted_at, $form_id );
		gform_update_meta( $entry_id, Config::GOOGLE_SYNC_ERROR_META_KEY, $message, $form_id );

		GFAPI::add_note(
			$entry_id,
			0,
			'WM BCI Google Sync',
			sprintf( 'Google Sheet sync failed: %s', $message )
		);

		return new WP_Error( 'wm_bci_sync_failed', $message );
	}

	private function entry_admin_url( int $entry_id ): string {
		return add_query_arg(
			array(
				'page' => 'gf_entries',
				'view' => 'entry',
				'id'   => $this->config->form_id(),
				'lid'  => $entry_id,
			),
			admin_url( 'admin.php' )
		);
	}

	private function entries_admin_url(): string {
		return add_query_arg(
			array(
				'page' => 'gf_entries',
				'id'   => $this->config->form_id(),
			),
			admin_url( 'admin.php' )
		);
	}
}
