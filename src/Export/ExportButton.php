<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\Export;

use WatersMeet\BciWorkflow\Config;

/**
 * Renders the CSV export button on the Gravity Forms entry list page.
 */
final class ExportButton {

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	public function register(): void {
		add_action( 'gform_pre_entry_list', array( $this, 'render' ) );
	}

	/**
	 * @param int $form_id Current form ID.
	 */
	public function render( $form_id ): void {
		if ( $this->config->form_id() !== (int) $form_id || ! $this->current_user_can_export() ) {
			return;
		}

		printf(
			'<div class="notice notice-info inline"><p><a class="button button-primary" href="%1$s">%2$s</a></p></div>',
			esc_url( $this->export_url() ),
			esc_html__( 'Download Approved Opportunities CSV', 'wm-bci-workflow' )
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

	private function current_user_can_export(): bool {
		return class_exists( 'GFCommon' ) && \GFCommon::current_user_can_any( 'gravityforms_export_entries' );
	}
}
