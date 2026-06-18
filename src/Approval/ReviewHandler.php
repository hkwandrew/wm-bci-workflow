<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\Approval;

use WatersMeet\BciWorkflow\Config;
use WatersMeet\BciWorkflow\Entry\FieldAccessor;
use WatersMeet\BciWorkflow\GoogleSync\SyncManager;
use GFAPI;

/**
 * Handles email approve/reject action links.
 */
final class ReviewHandler {

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	public function register(): void {
		add_action( 'admin_post_wm_bci_review', array( $this, 'handle' ) );
		add_action( 'admin_post_nopriv_wm_bci_review', array( $this, 'handle' ) );
	}

	public function handle(): void {
		$entry_id = isset( $_GET['entry'] ) ? absint( wp_unslash( $_GET['entry'] ) ) : 0;
		$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$expires  = isset( $_GET['expires'] ) ? absint( wp_unslash( $_GET['expires'] ) ) : 0;
		$provided = isset( $_GET['signature'] ) ? sanitize_text_field( wp_unslash( $_GET['signature'] ) ) : '';

		if ( ! $entry_id || ! $status || ! $expires || ! $provided ) {
			$this->respond( 400, 'Invalid review link.', 'This approval link is missing required information.' );
		}

		if ( time() > $expires ) {
			$this->respond( 410, 'Review link expired.', 'This approval link has expired. Open the entry in WordPress to review it manually.' );
		}

		$normalized_status = $this->config->status_label( $status );
		$review_url        = new ReviewUrl( $this->config );

		if ( '' === $normalized_status || ! $review_url->verify( $entry_id, $status, $expires, $provided ) ) {
			$this->respond( 403, 'Review link invalid.', 'This approval link could not be verified.' );
		}

		$entry = GFAPI::get_entry( $entry_id );

		if ( is_wp_error( $entry ) || $this->config->form_id() !== (int) rgar( $entry, 'form_id' ) ) {
			$this->respond( 404, 'Entry not found.', 'The requested BCI submission could not be found.' );
		}

		$fields   = new FieldAccessor( $this->config );
		$current  = $fields->approval_status( $entry );

		if ( $current !== $normalized_status ) {
			$update_result = GFAPI::update_entry_field( $entry_id, $this->config->approval_field_id(), $normalized_status );

			if ( is_wp_error( $update_result ) || false === $update_result ) {
				$this->respond( 500, 'Status update failed.', 'The entry status could not be updated. Please review it in WordPress.' );
			}

			$this->maybe_set_approved_at( $entry_id, $normalized_status );

			GFAPI::add_note(
				$entry_id,
				0,
				'WM BCI Approval Link',
				sprintf(
					'Calendar approval changed from %1$s to %2$s via secure review link.',
					$current ? $current : 'Unset',
					$normalized_status
				)
			);

			if ( 'Approved' === $normalized_status ) {
				$sync = new SyncManager( $this->config );
				$sync->sync_entry( $entry_id );
			}
		}

		$this->respond(
			200,
			sprintf( 'Submission %s.', strtolower( $normalized_status ) ),
			sprintf(
				'The BCI submission "%1$s" is now marked %2$s.',
				$fields->title( $entry ),
				$normalized_status
			),
			array(
				array(
					'label' => 'View entry',
					'url'   => $this->entry_admin_url( $entry_id ),
				),
				array(
					'label' => 'View BCI resources page',
					'url'   => home_url( '/' . $this->config->calendar_page_slug() . '/' ),
				),
			)
		);
	}

	private function maybe_set_approved_at( int $entry_id, string $current ): void {
		$meta_key = Config::APPROVED_AT_META_KEY;
		$existing = gform_get_meta( $entry_id, $meta_key );

		if ( 'Approved' !== $current || $existing ) {
			return;
		}

		gform_update_meta( $entry_id, $meta_key, gmdate( 'c' ), $this->config->form_id() );
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

	/**
	 * @param array<int, array{label: string, url: string}> $links
	 */
	private function respond( int $status_code, string $title, string $message, array $links = array() ): void {
		$link_markup = '';

		if ( ! empty( $links ) ) {
			$items = array();
			foreach ( $links as $link ) {
				if ( empty( $link['label'] ) || empty( $link['url'] ) ) {
					continue;
				}
				$items[] = sprintf(
					'<li><a href="%1$s">%2$s</a></li>',
					esc_url( $link['url'] ),
					esc_html( $link['label'] )
				);
			}
			if ( ! empty( $items ) ) {
				$link_markup = '<ul>' . implode( '', $items ) . '</ul>';
			}
		}

		status_header( $status_code );
		nocache_headers();

		echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<title>' . esc_html( $title ) . '</title>';
		echo '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f1f1f1;color:#222;margin:0;padding:32px;}main{max-width:720px;margin:0 auto;background:#fff;border:1px solid #dcdcde;padding:32px;box-shadow:0 1px 2px rgba(0,0,0,.04);}h1{margin:0 0 16px;font-size:28px;}p{line-height:1.6;}ul{margin:16px 0 0 20px;}a{color:#2271b1;}</style>';
		echo '</head><body><main>';
		echo '<h1>' . esc_html( $title ) . '</h1>';
		echo '<p>' . esc_html( $message ) . '</p>';
		echo wp_kses_post( $link_markup );
		echo '</main></body></html>';
		exit;
	}
}
