<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\Tests\Calendar;

use PHPUnit\Framework\TestCase;
use WatersMeet\BciWorkflow\Calendar\TooltipOptions;
use WatersMeet\BciWorkflow\Config;

final class TooltipOptionsTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wm_bci_test_options']    = array();
		$GLOBALS['wm_bci_settings_errors'] = array();
	}

	public function test_customize_calendar_options_forces_block_display_for_bci_form(): void {
		$options  = array(
			'initialView' => 'dayGridMonth',
		);
		$tooltips = new TooltipOptions( new Config() );

		$result = $tooltips->customize_calendar_options( $options, 4, 12 );

		$this->assertSame( 'block', $result['eventDisplay'] );
	}

	public function test_customize_calendar_options_leaves_other_forms_untouched(): void {
		$options  = array(
			'initialView' => 'dayGridMonth',
		);
		$tooltips = new TooltipOptions( new Config() );

		$result = $tooltips->customize_calendar_options( $options, 999, 12 );

		$this->assertSame( $options, $result );
	}
}
