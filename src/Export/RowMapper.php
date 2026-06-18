<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\Export;

use WatersMeet\BciWorkflow\Config;
use WatersMeet\BciWorkflow\Entry\FieldAccessor;
use GFAPI;

/**
 * Maps BCI entries to export row arrays — shared by CsvExporter and SyncManager.
 */
final class RowMapper {

	private const PRIMARY_DATE_HEADER = "When is your opportunity happening? We need this to have this with at least a week's notice. The newsletter goes out on Thursdays, so anything happening before Thursday of the current week should not be included.";

	private Config $config;
	private FieldAccessor $fields;

	public function __construct( Config $config ) {
		$this->config = $config;
		$this->fields = new FieldAccessor( $config );
	}

	/**
	 * @return array<int,string>
	 */
	public function headers(): array {
		return array(
			'Timestamp',
			'What kind of opportunity is this?',
			'Your name:',
			'What is the title of your community opportunity?',
			'What is the name of your organization?',
			self::PRIMARY_DATE_HEADER,
			'If your opportunity has a date range, what is the end date?',
			'For events and learning opportunities, what time of day is it happening?',
			'For opportunities with a physical location, what is the address?',
			'Is there any cost?',
			'Provide a short description of this opportunity',
			'Provide a link for additional information:',
			'Please upload any relevant files here:',
			'Has this been in a newsletter?',
			'Additional Info , Instructions, and Commentary',
		);
	}

	/**
	 * @return array<string,string>
	 */
	public function row_data( array $entry ): array {
		return array(
			'Timestamp'                                                    => $this->fields->timestamp( $entry ),
			'What kind of opportunity is this?'                            => $this->fields->legacy_opportunity_type( $this->fields->opportunity_type( $entry ) ),
			'Your name:'                                                   => $this->fields->submitter_name( $entry ),
			'What is the title of your community opportunity?'             => $this->fields->title( $entry ),
			'What is the name of your organization?'                       => $this->fields->organization( $entry ),
			self::PRIMARY_DATE_HEADER                                       => $this->fields->primary_date_value( $entry ),
			'If your opportunity has a date range, what is the end date?'  => $this->fields->end_date( $entry ),
			'For events and learning opportunities, what time of day is it happening?' => $this->fields->time_range( $entry ),
			'For opportunities with a physical location, what is the address?' => $this->fields->address( $entry ),
			'Is there any cost?'                                           => $this->fields->cost( $entry ),
			'Provide a short description of this opportunity'              => $this->fields->description( $entry ),
			'Provide a link for additional information:'                   => esc_url_raw( trim( (string) rgar( $entry, $this->config->field( 'info_url' ) ) ) ),
			'Please upload any relevant files here:'                       => $this->fields->file_upload( $entry ),
			'Has this been in a newsletter?'                               => '',
			'Additional Info , Instructions, and Commentary'               => '',
		);
	}

	/**
	 * @return array<int,array<string,string>>
	 */
	public function export_rows(): array {
		$rows = array();

		foreach ( $this->approved_entries() as $entry ) {
			$rows[] = $this->row_data( $entry );
		}

		usort( $rows, array( $this, 'compare_rows' ) );

		return $rows;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function approved_entries(): array {
		$entries         = array();
		$total_count     = 0;
		$offset          = 0;
		$page_size       = 200;
		$search_criteria = array(
			'status'        => 'active',
			'field_filters' => array(
				array(
					'key'      => $this->config->approval_field_id(),
					'operator' => 'is',
					'value'    => 'Approved',
				),
			),
		);
		$sorting = array(
			'key'       => 'date_created',
			'direction' => 'ASC',
		);

		do {
			$paging       = array(
				'offset'    => $offset,
				'page_size' => $page_size,
			);
			$page_entries = GFAPI::get_entries( $this->config->form_id(), $search_criteria, $sorting, $paging, $total_count );

			if ( is_wp_error( $page_entries ) || empty( $page_entries ) ) {
				break;
			}

			$entries = array_merge( $entries, $page_entries );
			$offset += count( $page_entries );
		} while ( $offset < $total_count );

		return $entries;
	}

	private function compare_rows( array $left, array $right ): int {
		$left_timestamp  = strtotime( (string) $left[ self::PRIMARY_DATE_HEADER ] );
		$right_timestamp = strtotime( (string) $right[ self::PRIMARY_DATE_HEADER ] );

		if ( $left_timestamp !== $right_timestamp ) {
			return $left_timestamp <=> $right_timestamp;
		}

		return strcmp(
			(string) $left['What is the title of your community opportunity?'],
			(string) $right['What is the title of your community opportunity?']
		);
	}
}
