<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\Tests\Calendar;

use PHPUnit\Framework\TestCase;
use WatersMeet\BciWorkflow\Calendar\EventCustomizer;
use WatersMeet\BciWorkflow\Config;

final class EventCustomizerTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wm_bci_test_options']    = array();
		$GLOBALS['wm_bci_settings_errors'] = array();
	}

	public function test_customize_applies_configured_colors_to_matching_events(): void {
		$GLOBALS['wm_bci_test_options']['wm_bci_workflow'] = array(
			'calendar_event_colors' => array(
				'Event' => '#004966',
			),
		);

		$customizer = new EventCustomizer( new Config() );
		$result     = $customizer->customize(
			array(
				array(
					'event_id' => 123,
					'title'    => 'Community Event',
					'start'    => '2026-04-20',
					'end'      => '2026-04-20',
					'url'      => '',
				),
			),
			array(
				'id' => 4,
			),
			array(),
			array(),
			array(
				array(
					'id' => 123,
					'1'  => 'Event',
					'4'  => 'Community Event',
				),
			)
		);

		$this->assertSame( '#004966', $result[0]['backgroundColor'] );
		$this->assertSame( '#004966', $result[0]['borderColor'] );
		$this->assertSame( '#ffffff', $result[0]['textColor'] );
	}

	public function test_customize_supports_legacy_stored_type_values_when_resolving_colors(): void {
		$GLOBALS['wm_bci_test_options']['wm_bci_workflow'] = array(
			'calendar_event_colors' => array(
				'Grant / RFP' => '#d9a242',
			),
		);

		$customizer = new EventCustomizer( new Config() );
		$result     = $customizer->customize(
			array(
				array(
					'event_id' => 321,
					'title'    => 'Grant Deadline',
					'start'    => '2026-05-01',
					'end'      => '2026-05-01',
					'url'      => '',
				),
			),
			array(
				'id' => 4,
			),
			array(),
			array(),
			array(
				array(
					'id' => 321,
					'1'  => 'Grant/RFP',
					'4'  => 'Grant Deadline',
				),
			)
		);

		$this->assertSame( '#d9a242', $result[0]['backgroundColor'] );
		$this->assertSame( '#d9a242', $result[0]['borderColor'] );
		$this->assertSame( '#1f1f1f', $result[0]['textColor'] );
	}

	public function test_customize_uses_other_color_for_unmatched_values_when_other_choice_is_enabled(): void {
		$GLOBALS['wm_bci_test_options']['wm_bci_workflow'] = array(
			'calendar_event_colors' => array(
				'Other' => '#418359',
			),
		);

		$customizer = new EventCustomizer( new Config() );
		$result     = $customizer->customize(
			array(
				array(
					'event_id' => 654,
					'title'    => 'Neighborhood Support',
					'start'    => '2026-05-02',
					'end'      => '2026-05-02',
					'url'      => '',
				),
			),
			array(
				'id'     => 4,
				'fields' => array(
					array(
						'id'                => '1',
						'enableOtherChoice' => true,
						'choices'           => array(
							array(
								'text'  => 'Event',
								'value' => 'Event',
							),
							array(
								'text'  => 'Resources',
								'value' => 'Resources',
							),
						),
					),
				),
			),
			array(),
			array(),
			array(
				array(
					'id' => 654,
					'1'  => 'Mutual aid exchange',
					'4'  => 'Neighborhood Support',
				),
			)
		);

		$this->assertSame( '#418359', $result[0]['backgroundColor'] );
		$this->assertSame( '#418359', $result[0]['borderColor'] );
		$this->assertSame( '#ffffff', $result[0]['textColor'] );
	}

	public function test_customize_uses_event_type_as_tooltip_eyebrow(): void {
		$customizer = new EventCustomizer( new Config() );
		$result     = $customizer->customize(
			array(
				array(
					'event_id' => 246,
					'title'    => 'Community Gathering',
					'start'    => '2026-05-04',
					'end'      => '2026-05-04',
					'url'      => '',
				),
			),
			array(
				'id' => 4,
			),
			array(),
			array(),
			array(
				array(
					'id' => 246,
					'1'  => 'Event',
					'4'  => 'Community Gathering',
				),
			)
		);

		$this->assertStringContainsString( '<span class="wm-bci-calendar-tooltip__eyebrow">Events</span>', $result[0]['description'] );
		$this->assertStringContainsString( '<li><strong>Type:</strong> Events</li>', $result[0]['description'] );
	}

	public function test_customize_keeps_default_tooltip_eyebrow_for_other_choice_values(): void {
		$customizer = new EventCustomizer( new Config() );
		$result     = $customizer->customize(
			array(
				array(
					'event_id' => 864,
					'title'    => 'Neighborhood Support',
					'start'    => '2026-05-06',
					'end'      => '2026-05-06',
					'url'      => '',
				),
			),
			array(
				'id'     => 4,
				'fields' => array(
					array(
						'id'                => '1',
						'enableOtherChoice' => true,
						'choices'           => array(
							array(
								'text'  => 'Event',
								'value' => 'Event',
							),
							array(
								'text'  => 'Resources',
								'value' => 'Resources',
							),
						),
					),
				),
			),
			array(),
			array(),
			array(
				array(
					'id' => 864,
					'1'  => 'Mutual aid exchange',
					'4'  => 'Neighborhood Support',
				),
			)
		);

		$this->assertStringContainsString( '<span class="wm-bci-calendar-tooltip__eyebrow">BCI Opportunity</span>', $result[0]['description'] );
		$this->assertStringNotContainsString( '<span class="wm-bci-calendar-tooltip__eyebrow">Mutual aid exchange</span>', $result[0]['description'] );
	}

	public function test_customize_leaves_event_colors_untouched_when_no_override_exists(): void {
		$customizer = new EventCustomizer( new Config() );
		$result     = $customizer->customize(
			array(
				array(
					'event_id' => 987,
					'title'    => 'Resource Drop',
					'start'    => '2026-04-21',
					'end'      => '2026-04-21',
					'url'      => '',
				),
			),
			array(
				'id' => 4,
			),
			array(),
			array(),
			array(
				array(
					'id' => 987,
					'1'  => 'Resources',
					'4'  => 'Resource Drop',
				),
			)
		);

		$this->assertArrayNotHasKey( 'backgroundColor', $result[0] );
		$this->assertArrayNotHasKey( 'borderColor', $result[0] );
		$this->assertArrayNotHasKey( 'textColor', $result[0] );
	}
}
