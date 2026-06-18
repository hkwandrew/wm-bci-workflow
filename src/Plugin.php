<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow;

use WatersMeet\BciWorkflow\Approval\EntrySeeder;
use WatersMeet\BciWorkflow\Approval\ReviewHandler;
use WatersMeet\BciWorkflow\Calendar\EventFilter;
use WatersMeet\BciWorkflow\Calendar\EventCustomizer;
use WatersMeet\BciWorkflow\Calendar\TooltipOptions;
use WatersMeet\BciWorkflow\Calendar\Styles;
use WatersMeet\BciWorkflow\Notification\ApprovalEmail;
use WatersMeet\BciWorkflow\GoogleSync\SyncTrigger;
use WatersMeet\BciWorkflow\GoogleSync\RetryHandler;
use WatersMeet\BciWorkflow\GoogleSync\CsvImportHandler;
use WatersMeet\BciWorkflow\Export\CsvExporter;
use WatersMeet\BciWorkflow\Export\ExportButton;
use WatersMeet\BciWorkflow\Dashboard\Widget;
use WatersMeet\BciWorkflow\Dashboard\AdminNotice;
use WatersMeet\BciWorkflow\Admin\SettingsPage;

/**
 * Plugin orchestrator — creates Config and registers all modules.
 */
final class Plugin {

	public function boot(): void {
		$config = new Config();

		$modules = array(
			new EntrySeeder( $config ),
			new ReviewHandler( $config ),
			new EventFilter( $config ),
			new EventCustomizer( $config ),
			new TooltipOptions( $config ),
			new Styles( $config ),
			new ApprovalEmail( $config ),
			new SyncTrigger( $config ),
			new RetryHandler( $config ),
			new CsvImportHandler( $config ),
			new CsvExporter( $config ),
			new ExportButton( $config ),
			new Widget( $config ),
			new AdminNotice( $config ),
			new SettingsPage( $config ),
		);

		foreach ( $modules as $module ) {
			$module->register();
		}
	}
}
