<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\Calendar;

use WatersMeet\BciWorkflow\Config;

/**
 * Enqueues BCI calendar tooltip presentation assets.
 */
final class Styles {

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ), 20 );
		add_action( 'wp_footer', array( $this, 'maybe_print_tooltip_runtime_options' ), 21 );
	}

	public function maybe_enqueue(): void {
		if ( is_admin() || ! is_page( $this->config->calendar_page_slug() ) ) {
			return;
		}

		$css_file = WM_BCI_WORKFLOW_DIR . 'assets/css/bci-calendar-tooltip.css';

		wp_enqueue_style(
			'wm-bci-calendar-tooltip',
			plugins_url( 'assets/css/bci-calendar-tooltip.css', WM_BCI_WORKFLOW_FILE ),
			array(),
			file_exists( $css_file ) ? (string) filemtime( $css_file ) : WM_BCI_WORKFLOW_VERSION
		);
	}

	public function maybe_print_tooltip_runtime_options(): void {
		if ( is_admin() || ! is_page( $this->config->calendar_page_slug() ) ) {
			return;
		}

		echo '<script id="wm-bci-calendar-tooltip-runtime">(function(){if(window.gv_calendar_tippy&&window.gv_calendar_tippy.setDefaultProps){window.gv_calendar_tippy.setDefaultProps({appendTo:function(){return document.body;},zIndex:99999});}}());</script>' . "\n";
	}
}
