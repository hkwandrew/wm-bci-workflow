<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\Entry;

use WatersMeet\BciWorkflow\Config;

/**
 * Centralized field-value helpers for BCI entries.
 *
 * Every method reads field IDs from Config instead of hard-coding them.
 */
final class FieldAccessor {

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	public function title( array $entry ): string {
		$title = trim( (string) rgar( $entry, $this->config->field( 'title' ) ) );
		return '' !== $title ? $title : sprintf( 'BCI Submission #%d', (int) rgar( $entry, 'id' ) );
	}

	public function submitter_name( array $entry ): string {
		$field = $this->config->field( 'submitter_name' );
		$parts = array_filter(
			array(
				trim( (string) rgar( $entry, $field . '.3' ) ),
				trim( (string) rgar( $entry, $field . '.6' ) ),
			)
		);
		return trim( implode( ' ', $parts ) );
	}

	/**
	 * @return array{0: string, 1: string}
	 */
	public function split_name( string $name ): array {
		$name = trim( $name );

		if ( '' === $name ) {
			return array( '', '' );
		}

		$parts = preg_split( '/\s+/', $name );

		if ( false === $parts || empty( $parts ) ) {
			return array( $name, '' );
		}

		$first = array_shift( $parts );
		$last  = implode( ' ', $parts );

		return array( $first, $last );
	}

	public function opportunity_type( array $entry ): string {
		return trim( (string) rgar( $entry, $this->config->field( 'opportunity_type' ) ) );
	}

	public function legacy_opportunity_type( string $type ): string {
		$type = trim( $type );

		switch ( $type ) {
			case 'Grant / RFP':
				return 'Grant/RFP';
			case 'Event':
				return 'Events';
			case 'Workshops, trainings, or other learning opportunities':
				return 'Learning (any workshops or trainings or other learning opportunities)';
			case 'Resources':
				return 'Resources';
			default:
				return $type;
		}
	}

	public function form_choice_from_legacy_type( string $type ): string {
		$type = trim( $type );

		switch ( $type ) {
			case 'Grant/RFP':
			case 'Grant / RFP':
				return 'Grant / RFP';
			case 'Events':
			case 'Event':
				return 'Event';
			case 'Learning (any workshops or trainings or other learning opportunities)':
			case 'Workshops, trainings, or other learning opportunities':
				return 'Workshops, trainings, or other learning opportunities';
			case 'Resources':
				return 'Resources';
			default:
				return '';
		}
	}

	public function organization( array $entry ): string {
		return trim( (string) rgar( $entry, $this->config->field( 'organization' ) ) );
	}

	public function primary_date_value( array $entry ): string {
		$type = $this->opportunity_type( $entry );

		if ( 'Grant / RFP' === $type ) {
			return trim( (string) rgar( $entry, $this->config->field( 'grant_deadline' ) ) );
		}

		return trim( (string) rgar( $entry, $this->config->field( 'start_date' ) ) );
	}

	public function time_range( array $entry ): string {
		$start = trim( (string) rgar( $entry, $this->config->field( 'start_time' ) ) );
		$end   = trim( (string) rgar( $entry, $this->config->field( 'end_time' ) ) );

		if ( '' !== $start && '' !== $end ) {
			return $start . ' - ' . $end;
		}

		if ( '' !== $start ) {
			return $start;
		}

		return $end;
	}

	public function address( array $entry ): string {
		$field = $this->config->field( 'address' );
		$parts_without_country = array_filter(
			array(
				trim( (string) rgar( $entry, $field . '.1' ) ),
				trim( (string) rgar( $entry, $field . '.2' ) ),
				trim( (string) rgar( $entry, $field . '.3' ) ),
				trim( (string) rgar( $entry, $field . '.4' ) ),
				trim( (string) rgar( $entry, $field . '.5' ) ),
			)
		);

		if ( empty( $parts_without_country ) ) {
			return '';
		}

		$parts   = $parts_without_country;
		$country = trim( (string) rgar( $entry, $field . '.6' ) );

		if ( '' !== $country ) {
			$parts[] = $country;
		}

		return implode( ', ', $parts );
	}

	public function file_upload( array $entry ): string {
		$file_value = rgar( $entry, $this->config->field( 'file_upload' ) );

		if ( is_array( $file_value ) ) {
			return implode( ', ', array_map( 'esc_url_raw', $file_value ) );
		}

		return esc_url_raw( trim( (string) $file_value ) );
	}

	public function description( array $entry ): string {
		return trim( (string) rgar( $entry, $this->config->field( 'description' ) ) );
	}

	public function info_url( array $entry ): string {
		return esc_url( trim( (string) rgar( $entry, $this->config->field( 'info_url' ) ) ) );
	}

	public function cost( array $entry ): string {
		return trim( (string) rgar( $entry, $this->config->field( 'cost' ) ) );
	}

	public function end_date( array $entry ): string {
		return trim( (string) rgar( $entry, $this->config->field( 'end_date' ) ) );
	}

	public function timestamp( array $entry ): string {
		$date_created = trim( (string) rgar( $entry, 'date_created' ) );

		if ( '' === $date_created ) {
			return '';
		}

		return gmdate( 'c', strtotime( $date_created . ' UTC' ) );
	}

	public function approval_status( array $entry ): string {
		$value    = strtolower( trim( (string) rgar( $entry, $this->config->approval_field_id() ) ) );
		$statuses = $this->config->approval_statuses();

		return $statuses[ $value ] ?? '';
	}
}
