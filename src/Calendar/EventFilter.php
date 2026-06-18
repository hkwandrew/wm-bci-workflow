<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\Calendar;

use WatersMeet\BciWorkflow\Config;

/**
 * Filters GravityCalendar events to only show approved entries.
 */
final class EventFilter {

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	public function register(): void {
		add_filter( 'gk/gravitycalendar/events/filters', array( $this, 'filter' ), 10, 4 );
	}

	/**
	 * @param array      $filters Feed filters.
	 * @param int|string $feed_id Feed ID.
	 * @return array
	 */
	public function filter( array $filters, $feed_id ): array {
		$approval_field_id = $this->config->approval_field_id();

		if ( ! $this->is_bci_feed( $feed_id ) ) {
			return $filters;
		}

		if ( empty( $filters['conditions'] ) || ! is_array( $filters['conditions'] ) ) {
			$filters['conditions'] = array();
		}

		// Avoid duplicating the condition if the theme already added it.
		foreach ( $filters['conditions'] as $condition ) {
			if (
				isset( $condition['key'], $condition['operator'], $condition['value'] )
				&& $approval_field_id === (string) $condition['key']
				&& 'is' === (string) $condition['operator']
				&& 'Approved' === (string) $condition['value']
			) {
				return $filters;
			}
		}

		$filters['conditions'][] = array(
			'key'      => $approval_field_id,
			'operator' => 'is',
			'value'    => 'Approved',
		);

		return $filters;
	}

	/**
	 * @param int|string $feed_id
	 */
	private function is_bci_feed( $feed_id ): bool {
		if ( ! class_exists( 'GV_Extension_Calendar_Feed' ) ) {
			return false;
		}

		$feed = \GV_Extension_Calendar_Feed::get_instance()->get_feed( $feed_id );

		if ( empty( $feed ) ) {
			return false;
		}

		return $this->config->form_id() === (int) rgar( $feed, 'form_id' )
			&& $this->config->calendar_feed_name() === (string) rgars( $feed, 'meta/feedName' );
	}
}
