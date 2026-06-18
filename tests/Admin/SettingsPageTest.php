<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\Tests\Admin;

use PHPUnit\Framework\TestCase;
use WatersMeet\BciWorkflow\Admin\SettingsPage;
use WatersMeet\BciWorkflow\Config;

final class SettingsPageTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wm_bci_test_options']    = array();
		$GLOBALS['wm_bci_settings_errors'] = array();
		$GLOBALS['wm_bci_enqueued_styles'] = array();
		$GLOBALS['wm_bci_test_users']      = array();
		$GLOBALS['wm_bci_test_settings_fields'] = array();
		$GLOBALS['wp_settings_sections']   = array();
		$GLOBALS['wp_settings_fields']     = array();
		\GFAPI::$wm_bci_test_form         = array();
	}

	public function test_sanitize_normalizes_recipient_list_and_records_warning_for_invalid_emails(): void {
		$page  = new SettingsPage( new Config() );
		$clean = $page->sanitize(
			array(
				'approval_notification_recipients' => "first@example.com\ninvalid-email,\nsecond@example.com,\nFIRST@example.com",
			)
		);

		$this->assertSame( 'first@example.com, second@example.com', $clean['approval_notification_recipients'] );
		$this->assertCount( 1, $GLOBALS['wm_bci_settings_errors'] );
		$this->assertSame( 'warning', $GLOBALS['wm_bci_settings_errors'][0]['type'] );
		$this->assertStringContainsString( 'invalid approval recipient email', $GLOBALS['wm_bci_settings_errors'][0]['message'] );
	}

	public function test_sanitize_accepts_empty_recipient_list_without_errors(): void {
		$page  = new SettingsPage( new Config() );
		$clean = $page->sanitize(
			array(
				'approval_notification_recipients' => '',
			)
		);

		$this->assertSame( '', $clean['approval_notification_recipients'] );
		$this->assertSame( array(), $GLOBALS['wm_bci_settings_errors'] );
	}

	public function test_sanitize_keeps_only_existing_auto_approved_user_ids(): void {
		$GLOBALS['wm_bci_test_users'] = array(
			(object) array(
				'ID'           => 12,
				'display_name' => 'Avery Smith',
				'user_login'   => 'asmith',
				'user_email'   => 'avery@example.com',
			),
			(object) array(
				'ID'           => 18,
				'display_name' => 'Casey Jones',
				'user_login'   => 'cjones',
				'user_email'   => 'casey@example.com',
			),
		);

		$page  = new SettingsPage( new Config() );
		$clean = $page->sanitize(
			array(
				'auto_approved_user_ids'         => array( '18', '999', '12', '18' ),
				'auto_approved_user_ids_present' => '1',
			)
		);

		$this->assertSame( array( 18, 12 ), $clean['auto_approved_user_ids'] );
	}

	public function test_sanitize_clears_auto_approved_user_ids_when_none_selected(): void {
		$GLOBALS['wm_bci_test_options']['wm_bci_workflow'] = array(
			'auto_approved_user_ids' => array( 12, 18 ),
		);

		$page  = new SettingsPage( new Config() );
		$clean = $page->sanitize(
			array(
				'auto_approved_user_ids_present' => '1',
			)
		);

		$this->assertSame( array(), $clean['auto_approved_user_ids'] );
	}

	public function test_sanitize_keeps_only_palette_calendar_event_colors(): void {
		$page  = new SettingsPage( new Config() );
		$clean = $page->sanitize(
			array(
				'calendar_event_colors' => array(
					'Event'       => '#004966',
					'Grant / RFP' => 'invalid',
					'Resources'   => '#5c6e7a',
					'Workshop'    => '#336699',
				),
			)
		);

		$this->assertSame(
			array(
				'Event'     => '#004966',
				'Resources' => '#5c6e7a',
			),
			$clean['calendar_event_colors']
		);
	}

	public function test_sanitize_clears_existing_calendar_event_color_when_no_color_is_selected(): void {
		$GLOBALS['wm_bci_test_options']['wm_bci_workflow'] = array(
			'calendar_event_colors' => array(
				'Event' => '#004966',
			),
		);

		$page  = new SettingsPage( new Config() );
		$clean = $page->sanitize(
			array(
				'calendar_event_colors_present' => '1',
			)
		);

		$this->assertSame( array(), $clean['calendar_event_colors'] );
	}

	public function test_sanitize_preserves_existing_calendar_event_colors_when_field_is_not_submitted(): void {
		$GLOBALS['wm_bci_test_options']['wm_bci_workflow'] = array(
			'calendar_event_colors' => array(
				'Event' => '#004966',
			),
		);

		$page  = new SettingsPage( new Config() );
		$clean = $page->sanitize(
			array(
				'calendar_page_slug' => 'bci-resources',
			)
		);

		$this->assertSame(
			array(
				'Event' => '#004966',
			),
			$clean['calendar_event_colors']
		);
	}

	public function test_render_calendar_event_colors_section_outputs_fixed_palette_matrix(): void {
		\GFAPI::$wm_bci_test_form = array(
			'fields' => array(
				array(
					'id'      => '1',
					'choices' => array(
						array(
							'text'  => 'Event',
							'value' => 'Event',
						),
					),
				),
			),
		);

		$page = new SettingsPage( new Config() );

		ob_start();
		$page->render_calendar_event_colors_section();
		$output = (string) ob_get_clean();

		$this->assertStringNotContainsString( '<style>', $output );
		$this->assertStringNotContainsString( 'type="color"', $output );
		$this->assertStringNotContainsString( 'Use GravityCalendar Default', $output );
		$this->assertStringNotContainsString( '<code>', $output );
		$this->assertStringNotContainsString( '<fieldset', $output );
		$this->assertStringNotContainsString( 'rowspan=', $output );
		$this->assertStringContainsString( 'colspan="8"', $output );
		$this->assertStringNotContainsString( 'wm-bci-calendar-palette-swatch--default', $output );
		$this->assertStringContainsString( 'calendar_event_colors_present', $output );
		$this->assertSame( 1, substr_count( $output, '<table' ) );
		$this->assertSame( 8, substr_count( $output, 'type="radio"' ) );

		foreach ( array_keys( Config::calendar_event_palette() ) as $color ) {
			$this->assertStringContainsString( sprintf( 'value="%s"', $color ), $output );
		}
	}

	public function test_render_calendar_event_colors_section_adds_other_row_when_field_supports_other_choice(): void {
		\GFAPI::$wm_bci_test_form = array(
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
							'text'          => 'Other',
							'value'         => 'gf_other_choice',
							'isOtherChoice' => true,
						),
					),
				),
			),
		);

		$page = new SettingsPage( new Config() );

		ob_start();
		$page->render_calendar_event_colors_section();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'calendar_event_colors][Other]', $output );
		$this->assertStringNotContainsString( 'calendar_event_colors][gf_other_choice]', $output );
		$this->assertStringContainsString( '>Other</span>', $output );
		$this->assertSame( 16, substr_count( $output, 'type="radio"' ) );
	}

	public function test_enqueue_assets_loads_settings_stylesheet_only_on_bci_settings_page(): void {
		$page = new SettingsPage( new Config() );

		$page->enqueue_assets( 'settings_page_wm-bci-workflow' );

		$this->assertCount( 1, $GLOBALS['wm_bci_enqueued_styles'] );
		$this->assertSame( 'wm-bci-admin-settings', $GLOBALS['wm_bci_enqueued_styles'][0]['handle'] );
		$this->assertStringContainsString( 'assets/css/bci-calendar-settings.css', $GLOBALS['wm_bci_enqueued_styles'][0]['src'] );
	}

	public function test_enqueue_assets_skips_unrelated_admin_pages(): void {
		$page = new SettingsPage( new Config() );

		$page->enqueue_assets( 'settings_page_other-plugin' );

		$this->assertSame( array(), $GLOBALS['wm_bci_enqueued_styles'] );
	}

	public function test_render_auto_approved_users_field_outputs_multiselect_with_selected_users(): void {
		$GLOBALS['wm_bci_test_options']['wm_bci_workflow'] = array(
			'auto_approved_user_ids' => array( 18 ),
		);
		$GLOBALS['wm_bci_test_users'] = array(
			(object) array(
				'ID'           => 12,
				'display_name' => 'Avery Smith',
				'user_login'   => 'asmith',
				'user_email'   => 'avery@example.com',
			),
			(object) array(
				'ID'           => 18,
				'display_name' => 'Casey Jones',
				'user_login'   => 'cjones',
				'user_email'   => 'casey@example.com',
			),
		);

		$page = new SettingsPage( new Config() );

		ob_start();
		$page->render_auto_approved_users_field();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'auto_approved_user_ids_present', $output );
		$this->assertStringContainsString( 'multiple="multiple"', $output );
		$this->assertStringContainsString( 'value="18" selected="selected"', $output );
		$this->assertStringContainsString( 'Casey Jones (cjones, casey@example.com)', $output );
		$this->assertStringContainsString( 'future BCI submissions should be approved automatically', $output );
	}

	public function test_render_registered_section_wraps_field_mapping_in_closed_details(): void {
		$page = new SettingsPage( new Config() );
		$page->register_settings();

		$method = new \ReflectionMethod( $page, 'render_registered_section' );
		$method->setAccessible( true );

		ob_start();
		$method->invoke( $page, 'wm_bci_field_map' );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '<details class="wm-bci-settings-collapsible">', $output );
		$this->assertStringContainsString( '<summary class="wm-bci-settings-collapsible__summary">', $output );
		$this->assertStringContainsString( 'Field Mapping', $output );
		$this->assertStringNotContainsString( '<details class="wm-bci-settings-collapsible" open>', $output );
		$this->assertStringContainsString( 'wm-bci-settings-section wm-bci-settings-section--advanced', $output );
		$this->assertStringContainsString( 'wm-bci-settings-card wm-bci-settings-card--compact', $output );
	}

	public function test_render_outputs_settings_page_shell_and_grouped_sections(): void {
		$page = new SettingsPage( new Config() );
		$page->register_settings();

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'class="wrap wm-bci-settings-page"', $output );
		$this->assertStringContainsString( 'wm-bci-settings-page__hero', $output );
		$this->assertStringContainsString( 'wm-bci-settings-grid', $output );
		$this->assertStringContainsString( 'wm-bci-settings-section--form-config', $output );
		$this->assertStringContainsString( 'wm-bci-settings-subsection', $output );
		$this->assertStringContainsString( 'Workflow Setup', $output );
		$this->assertStringContainsString( 'Approvals', $output );
		$this->assertStringContainsString( 'Publishing', $output );
		$this->assertStringContainsString( 'wm-bci-settings-submit', $output );
		$this->assertSame( array( 'wm-bci-workflow' ), $GLOBALS['wm_bci_test_settings_fields'] );
	}
}
