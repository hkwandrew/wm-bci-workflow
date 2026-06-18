<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\Tests\GoogleSync;

use GFAPI;
use PHPUnit\Framework\TestCase;
use WatersMeet\BciWorkflow\Config;
use WatersMeet\BciWorkflow\Export\RowMapper;
use WatersMeet\BciWorkflow\GoogleSync\CsvImporter;

final class CsvImporterTest extends TestCase {

	/** @var array<int,string> */
	private array $files = array();

	protected function setUp(): void {
		$GLOBALS['wm_bci_test_options']    = array();
		$GLOBALS['wm_bci_test_entry_meta'] = array();
		GFAPI::$wm_bci_entries             = array();
		GFAPI::$wm_bci_added_entries       = array();
		GFAPI::$wm_bci_notes               = array();
		GFAPI::$wm_bci_next_entry_id       = 1000;
		$this->files                       = array();
	}

	protected function tearDown(): void {
		foreach ( $this->files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}
	}

	public function test_import_creates_approved_calendar_entry_from_csv_row(): void {
		$config = new Config();
		$file   = $this->write_csv(
			array(
				array(
					'2026-04-27T13:45:00Z',
					'Events',
					'Jane Doe',
					'Park Cleanup',
					'Neighborhood Group',
					'2026-05-02',
					'2026-05-03',
					'9 AM - 11 AM',
					'123 Main St',
					'Free',
					'Bring gloves.',
					'https://example.com/details',
					'https://example.com/file.pdf',
					'',
					'Coordinator note',
					'Extra sheet-only column',
				),
			),
			array( 'Extra sheet-only column' )
		);

		$result = ( new CsvImporter( $config ) )->import_file( $file );

		$this->assertSame(
			array(
				'created' => 1,
				'skipped' => 0,
				'failed'  => 0,
			),
			$result
		);

		$entry = GFAPI::$wm_bci_entries[1000];

		$this->assertSame( 4, $entry['form_id'] );
		$this->assertSame( 'active', $entry['status'] );
		$this->assertSame( '2026-04-27 13:45:00', $entry['date_created'] );
		$this->assertSame( 'Event', $entry['1'] );
		$this->assertSame( 'Jane', $entry['3.3'] );
		$this->assertSame( 'Doe', $entry['3.6'] );
		$this->assertSame( 'Park Cleanup', $entry['4'] );
		$this->assertSame( 'Neighborhood Group', $entry['5'] );
		$this->assertSame( '2026-05-02', $entry['6'] );
		$this->assertSame( '2026-05-03', $entry['10'] );
		$this->assertSame( '9 AM - 11 AM', $entry['12'] );
		$this->assertSame( '123 Main St', $entry['15.1'] );
		$this->assertSame( 'Free', $entry['14'] );
		$this->assertSame( 'Bring gloves.', $entry['17'] );
		$this->assertSame( 'https://example.com/details', $entry['18'] );
		$this->assertSame( 'https://example.com/file.pdf', $entry['19'] );
		$this->assertSame( 'Approved', $entry['22'] );

		$this->assertSame( 'success', $GLOBALS['wm_bci_test_entry_meta'][1000][ Config::GOOGLE_SYNC_STATUS_META_KEY ] );
		$this->assertNotEmpty( $GLOBALS['wm_bci_test_entry_meta'][1000][ Config::CSV_IMPORT_HASH_META_KEY ] );
		$this->assertSame( 'WM BCI CSV Import', GFAPI::$wm_bci_notes[0]['user_name'] );
	}

	public function test_import_sets_grant_deadline_as_calendar_start_date(): void {
		$config = new Config();
		$file   = $this->write_csv(
			array(
				array(
					'2026-04-27T13:45:00Z',
					'Grant/RFP',
					'Jane Doe',
					'Neighborhood Grant',
					'Neighborhood Group',
					'2026-06-15',
					'',
					'',
					'',
					'',
					'Funding details.',
					'https://example.com/grant',
					'',
					'',
					'',
				),
			)
		);

		$result = ( new CsvImporter( $config ) )->import_file( $file );

		$this->assertSame( 1, $result['created'] );

		$entry = GFAPI::$wm_bci_entries[1000];

		$this->assertSame( 'Grant / RFP', $entry['1'] );
		$this->assertSame( '2026-06-15', $entry['6'] );
		$this->assertSame( '2026-06-15', $entry['9'] );
	}

	public function test_import_skips_rows_imported_by_an_earlier_run(): void {
		$config = new Config();
		$file   = $this->write_csv(
			array(
				array(
					'2026-04-27T13:45:00Z',
					'Resources',
					'Jane Doe',
					'Resource Drop',
					'Neighborhood Group',
					'2026-05-02',
					'',
					'',
					'',
					'',
					'Resource details.',
					'https://example.com/resource',
					'',
					'',
					'',
				),
			)
		);
		$importer = new CsvImporter( $config );

		$first_result  = $importer->import_file( $file );
		$second_result = $importer->import_file( $file );

		$this->assertSame( 1, $first_result['created'] );
		$this->assertSame(
			array(
				'created' => 0,
				'skipped' => 1,
				'failed'  => 0,
			),
			$second_result
		);
		$this->assertCount( 1, GFAPI::$wm_bci_entries );
	}

	public function test_import_rejects_csv_with_mismatched_headers(): void {
		$config = new Config();
		$file   = $this->write_csv(
			array(
				array( '2026-04-27T13:45:00Z', 'Events' ),
			),
			array(),
			array( 'Timestamp', 'Wrong header' )
		);

		$result = ( new CsvImporter( $config ) )->import_file( $file );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wm_bci_csv_import_header_mismatch', $result->get_error_code() );
		$this->assertSame( array(), GFAPI::$wm_bci_entries );
	}

	/**
	 * @param array<int,array<int,string>> $rows
	 * @param array<int,string>            $extra_headers
	 * @param array<int,string>|null       $override_headers
	 */
	private function write_csv( array $rows, array $extra_headers = array(), ?array $override_headers = null ): string {
		$config  = new Config();
		$headers = null === $override_headers ? ( new RowMapper( $config ) )->headers() : $override_headers;
		$file    = tempnam( sys_get_temp_dir(), 'wm-bci-csv-' );

		$this->assertIsString( $file );
		$this->files[] = $file;

		$handle = fopen( $file, 'wb' );
		$this->assertIsResource( $handle );

		fputcsv( $handle, array_merge( $headers, $extra_headers ), ',', '"', '' );

		foreach ( $rows as $row ) {
			fputcsv( $handle, $row, ',', '"', '' );
		}

		fclose( $handle );

		return $file;
	}
}
