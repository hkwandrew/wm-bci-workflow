<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\Calendar;

use WatersMeet\BciWorkflow\Config;

/**
 * Configures tooltip placement and dayGrid display behavior for the BCI calendar.
 */
final class TooltipOptions {

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	public function register(): void {
		add_filter( 'gravityview/calendar/options', array( $this, 'customize_calendar_options' ), 10, 3 );
		add_filter( 'gravityview/calendar/extra_options', array( $this, 'customize_tooltip_options' ), 10, 3 );
	}

	/**
	 * @param array $calendar_options FullCalendar options.
	 * @param int   $form_id          Form ID.
	 * @param int   $feed_id          Feed ID.
	 * @return array
	 */
	public function customize_calendar_options( array $calendar_options, $form_id, $feed_id ): array {
		if ( $this->config->form_id() !== (int) $form_id ) {
			return $calendar_options;
		}

		// Prefer block rendering in dayGrid so month cells use bars instead of dot rows.
		$calendar_options['eventDisplay'] = 'block';

		return $calendar_options;
	}

	/**
	 * @param array $extra_options Extra calendar options.
	 * @param int   $form_id       Form ID.
	 * @param int   $feed_id       Feed ID.
	 * @return array
	 */
	public function customize_tooltip_options( array $extra_options, $form_id, $feed_id ): array {
		if ( $this->config->form_id() !== (int) $form_id ) {
			return $extra_options;
		}

		$tooltip_options = isset( $extra_options['tooltip_options'] ) && is_array( $extra_options['tooltip_options'] )
			? $extra_options['tooltip_options']
			: array();

		$tooltip_options = array_merge(
			$tooltip_options,
			array(
				'placement' => 'bottom-start',
				'maxWidth'  => 340,
				'offset'    => array( 0, 10 ),
			)
		);

		$tooltip_options['popperOptions'] = array_merge(
			isset( $tooltip_options['popperOptions'] ) && is_array( $tooltip_options['popperOptions'] )
				? $tooltip_options['popperOptions']
				: array(),
			array(
				'strategy'  => 'fixed',
				'modifiers' => array(
					array(
						'name'    => 'flip',
						'options' => array(
							'fallbackPlacements' => array( 'right-start', 'left-start', 'top-start' ),
						),
					),
					array(
						'name'    => 'preventOverflow',
						'options' => array(
							'altAxis' => true,
							'tether'  => false,
							'padding' => 16,
						),
					),
				),
			)
		);

		$extra_options['tooltip_options'] = $tooltip_options;

		return $extra_options;
	}
}
