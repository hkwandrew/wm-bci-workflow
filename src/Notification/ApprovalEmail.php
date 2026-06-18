<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\Notification;

use WatersMeet\BciWorkflow\Config;
use WatersMeet\BciWorkflow\Entry\FieldAccessor;
use WatersMeet\BciWorkflow\Approval\ReviewUrl;

/**
 * Customizes the admin notification email with entry summary and action links.
 */
final class ApprovalEmail {

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	public function register(): void {
		$form_id = $this->config->form_id();
		add_filter( "gform_notification_{$form_id}", array( $this, 'customize' ), 10, 3 );
	}

	/**
	 * @param array $notification Notification configuration.
	 * @param array $form         Form data.
	 * @param array $entry        Entry data.
	 * @return array
	 */
	public function customize( array $notification, array $form, array $entry ): array {
		if (
			$this->config->form_id() !== (int) rgar( $form, 'id' )
			|| $this->config->notification_name() !== (string) rgar( $notification, 'name' )
		) {
			return $notification;
		}

		$fields = new FieldAccessor( $this->config );
		$to     = $this->config->approval_notification_recipients();

		if ( '' !== $to ) {
			$notification['toType']  = 'email';
			$notification['to']      = $to;
			$notification['toField'] = '';
			$notification['routing'] = null;
		}

		$notification['subject']           = sprintf( 'Review needed: %s', $fields->title( $entry ) );
		$notification['message']           = $this->build_message( $entry, $fields );
		$notification['message_format']    = 'html';
		$notification['disableAutoformat'] = true;

		return $notification;
	}

	private function build_message( array $entry, FieldAccessor $fields ): string {
		$summary_rows = array(
			'Opportunity'  => $fields->title( $entry ),
			'Type'         => $fields->legacy_opportunity_type( $fields->opportunity_type( $entry ) ),
			'Submitter'    => $fields->submitter_name( $entry ),
			'Organization' => $fields->organization( $entry ),
			'Date'         => $fields->primary_date_value( $entry ),
			'Time'         => $fields->time_range( $entry ),
			'Location'     => $fields->address( $entry ),
			'Cost'         => $fields->cost( $entry ),
		);

		$list_items = array();
		foreach ( $summary_rows as $label => $value ) {
			if ( '' === trim( (string) $value ) ) {
				continue;
			}
			$list_items[] = sprintf(
				'<li><strong>%1$s:</strong> %2$s</li>',
				esc_html( $label ),
				esc_html( $value )
			);
		}

		$description = $fields->description( $entry );
		$info_url    = $fields->info_url( $entry );
		$file_url    = $fields->file_upload( $entry );
		$review_url  = new ReviewUrl( $this->config );

		$message  = '<p>A new BCI community opportunity submission needs review.</p>';
		$message .= '<ul>' . implode( '', $list_items ) . '</ul>';

		if ( '' !== $description ) {
			$message .= '<p><strong>Description</strong><br>' . nl2br( esc_html( $description ) ) . '</p>';
		}

		if ( '' !== $info_url ) {
			$message .= sprintf(
				'<p><strong>More information:</strong> <a href="%1$s">%1$s</a></p>',
				esc_url( $info_url )
			);
		}

		if ( '' !== $file_url ) {
			$message .= sprintf(
				'<p><strong>Attachment:</strong> <a href="%1$s">%1$s</a></p>',
				esc_url( $file_url )
			);
		}

		$entry_id = (int) rgar( $entry, 'id' );

		$message .= '<p><strong>Review actions</strong></p>';
		$message .= '<ul>';
		$message .= sprintf(
			'<li><a href="%1$s">Approve submission</a></li>',
			esc_url( $review_url->generate( $entry_id, 'approved' ) )
		);
		$message .= sprintf(
			'<li><a href="%1$s">Reject submission</a></li>',
			esc_url( $review_url->generate( $entry_id, 'rejected' ) )
		);
		$message .= sprintf(
			'<li><a href="%1$s">View entry in Gravity Forms</a></li>',
			esc_url(
				add_query_arg(
					array(
						'page' => 'gf_entries',
						'view' => 'entry',
						'id'   => $this->config->form_id(),
						'lid'  => $entry_id,
					),
					admin_url( 'admin.php' )
				)
			)
		);
		$message .= '</ul>';
		$message .= '<p>Review links expire in 7 days.</p>';

		return $message;
	}
}
