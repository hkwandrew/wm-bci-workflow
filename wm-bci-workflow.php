<?php
/**
 * Plugin Name: WM BCI Workflow
 * Description: Gravity Forms submission → Approval → Calendar display → Google Sheets sync for BCI community opportunities.
 * Version:     1.1.0
 * Author:      HKW <andrew@hkw.io>
 * Author URI:  https://hkw.io
 * Text Domain: wm-bci-workflow
 * Requires PHP: 8.2
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WM_BCI_WORKFLOW_VERSION', '1.1.0' );
define( 'WM_BCI_WORKFLOW_FILE', __FILE__ );
define( 'WM_BCI_WORKFLOW_DIR', plugin_dir_path( __FILE__ ) );
define( 'WM_BCI_WORKFLOW_ACTIVE', true );

/**
 * PSR-4 autoloader for the WatersMeet\BciWorkflow namespace.
 */
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'WatersMeet\\BciWorkflow\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$file     = WM_BCI_WORKFLOW_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * Verify Gravity Forms is available before booting.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( 'GFAPI' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="notice notice-error"><p>';
					esc_html_e( 'WM BCI Workflow requires Gravity Forms to be installed and activated.', 'wm-bci-workflow' );
					echo '</p></div>';
				}
			);
			return;
		}

		(new WatersMeet\BciWorkflow\Plugin())->boot();
	},
	5
);

register_activation_hook(
	__FILE__,
	static function (): void {
		if ( false === get_option( 'wm_bci_workflow' ) ) {
			add_option( 'wm_bci_workflow', array() );
		}
	}
);
