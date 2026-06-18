<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\Approval;

use WatersMeet\BciWorkflow\Config;

/**
 * Generates and verifies HMAC-signed review URLs for email approval links.
 */
final class ReviewUrl {

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	public function generate( int $entry_id, string $status ): string {
		$expires   = time() + WEEK_IN_SECONDS;
		$signature = $this->sign( $entry_id, $status, $expires );

		return add_query_arg(
			array(
				'action'    => 'wm_bci_review',
				'entry'     => absint( $entry_id ),
				'status'    => sanitize_key( $status ),
				'expires'   => $expires,
				'signature' => $signature,
			),
			admin_url( 'admin-post.php' )
		);
	}

	public function verify( int $entry_id, string $status, int $expires, string $provided ): bool {
		$expected = $this->sign( $entry_id, $status, $expires );
		return hash_equals( $expected, $provided );
	}

	private function sign( int $entry_id, string $status, int $expires ): string {
		$payload = implode(
			'|',
			array(
				'bci-review',
				$this->config->form_id(),
				absint( $entry_id ),
				sanitize_key( $status ),
				absint( $expires ),
			)
		);

		return hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
	}
}
