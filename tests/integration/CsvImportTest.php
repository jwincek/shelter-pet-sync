<?php
/**
 * CSV import — the third way a pet can enter the site.
 *
 * Before this there were exactly two: the provider sync, and the block editor
 * one pet at a time. A shelter with eighty animals and no management platform —
 * the audience that most needs this plugin — had eighty manual entries ahead of
 * it.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

use Petsync\Core\Pet_Hydrator;
use Petsync\Export\Exporter;
use Petsync\Export\Schema;
use Petsync\Import\Column_Mapper;
use Petsync\Import\Importer;
use ReflectionClass;

final class CsvImportTest extends PetTestCase {

	/**
	 * A sheet as a shelter would actually save one: human headers, mixed
	 * spellings of yes/no, a quoted description containing a comma, and a
	 * trailing blank line.
	 */
	private const REAL_WORLD_CSV = <<<CSV
	Name,Species,Breed,Sex,"Good with dogs?","Good with cats?",Microchip #,Weight,Spayed/Neutered,Bio
	Luna,Dog,Collie,Female,Y,N,985141000111111,42 lb,yes,"Quiet, gentle, and good on a lead"
	Pepper,Cat,Domestic Shorthair,Male,unknown,yes,985141000222222,9 lb,TRUE,"Loves a sunny windowsill"
	CSV;

	private function csv(): string {
		// The heredoc above is indented for readability; strip that back off.
		return implode( "\n", array_map( 'trim', explode( "\n", self::REAL_WORLD_CSV ) ) ) . "\n\n";
	}

	// ─── Header mapping ─────────────────────────────────────────────────────

	public function test_it_matches_human_headers_to_fields(): void {
		$this->assertSame( 'ok_with_dogs', Column_Mapper::resolve( 'Good with dogs?' ) );
		$this->assertSame( 'microchip_id', Column_Mapper::resolve( 'Microchip #' ) );
		$this->assertSame( 'spayed_neutered', Column_Mapper::resolve( 'Spayed/Neutered' ) );
		$this->assertSame( 'name', Column_Mapper::resolve( 'PET NAME' ) );
		$this->assertSame( 'animal', Column_Mapper::resolve( 'Species' ) );
		$this->assertSame( 'color', Column_Mapper::resolve( 'Colour' ) );
	}

	/**
	 * Excel writes a BOM on the first header of a UTF-8 CSV. Left in, column one
	 * matches nothing — and column one is almost always the name, so the file
	 * looks fine except every row fails as nameless.
	 */
	public function test_a_byte_order_mark_does_not_break_the_first_column(): void {
		$this->assertSame( 'name', Column_Mapper::resolve( "\xEF\xBB\xBFName" ) );
	}

	/**
	 * A literal column name must never be rerouted by a synonym.
	 */
	public function test_an_exact_column_name_wins_over_an_alias(): void {
		$this->assertSame( 'age', Column_Mapper::resolve( 'age' ) );
		$this->assertSame( 'coat', Column_Mapper::resolve( 'coat' ) );
	}

	/**
	 * Lookup happens on the normalised header, so an alias key containing a
	 * space, slash or hash could never match. One did.
	 */
	public function test_every_alias_key_is_itself_normalised(): void {
		$aliases = ( new ReflectionClass( Column_Mapper::class ) )->getConstant( 'ALIASES' );
		$this->assertNotEmpty( $aliases );

		foreach ( array_keys( (array) $aliases ) as $key ) {
			$this->assertSame(
				$key,
				Column_Mapper::normalise( (string) $key ),
				"alias key '$key' is not normalised, so it can never be matched"
			);
		}
	}

	public function test_every_alias_target_is_a_real_importable_column(): void {
		$aliases = ( new ReflectionClass( Column_Mapper::class ) )->getConstant( 'ALIASES' );

		foreach ( (array) $aliases as $key => $target ) {
			$this->assertTrue(
				Schema::is_importable( (string) $target ),
				"alias '$key' points at '$target', which is not an importable column"
			);
		}
	}

	public function test_unmatched_columns_are_reported_rather_than_dropped(): void {
		$mapped = Column_Mapper::map( array( 'Name', 'Intake Officer', 'Kennel Number' ) );

		$this->assertSame( array( 0 => 'name' ), $mapped['mapping'] );
		$this->assertSame( array( 'Intake Officer', 'Kennel Number' ), array_values( $mapped['unmapped'] ) );
	}

	/**
	 * Two columns claiming one field would let the later silently overwrite the
	 * earlier.
	 */
	public function test_duplicate_columns_are_reported_and_the_first_wins(): void {
		$mapped = Column_Mapper::map( array( 'Name', 'Pet Name' ) );

		$this->assertSame( array( 0 => 'name' ), $mapped['mapping'] );
		$this->assertSame( array( 'Pet Name' ), array_values( $mapped['duplicates'] ) );
	}

	// ─── Dry run ────────────────────────────────────────────────────────────

	public function test_a_dry_run_writes_nothing(): void {
		$before = wp_count_posts( 'vcps_pet' )->publish;

		$report = Importer::run( $this->csv() );

		$this->assertFalse( $report['committed'] );
		$this->assertSame( 2, $report['created'] );
		$this->assertSame( 0, $report['failed'] );
		$this->assertSame( $before, wp_count_posts( 'vcps_pet' )->publish, 'a preview must not create anything' );
	}

	public function test_a_file_with_no_name_column_is_refused_outright(): void {
		$result = Importer::run( "Breed,Weight\nCollie,42 lb\n" );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'petsync_import_no_name', $result->get_error_code() );
	}

	public function test_an_empty_file_is_refused(): void {
		$this->assertInstanceOf( \WP_Error::class, Importer::run( '' ) );
	}

	/**
	 * One bad row must not cost the other seventy-nine.
	 */
	public function test_a_bad_row_is_reported_per_row_and_the_rest_still_import(): void {
		$csv = "Name,Good with dogs?\nLuna,yes\n,yes\nPepper,perhaps\n";

		$report = Importer::run( $csv, array( 'commit' => true ) );

		$this->assertSame( 1, $report['created'] );
		$this->assertSame( 2, $report['failed'] );

		$errors = array_column( $report['rows'], 'errors', 'line' );
		$this->assertNotEmpty( $errors[3], 'the nameless row must fail' );
		$this->assertNotEmpty( $errors[4], "'perhaps' is not a yes/no value" );
	}

	/**
	 * resolve_tristate() maps any unrecognised string to 'unknown', which is
	 * right for a provider feed and wrong for a spreadsheet — there, an
	 * unrecognised value is a typo or a mis-mapped column, and recording it as
	 * "asked, inconclusive" states something nobody said.
	 */
	public function test_an_unrecognised_tristate_is_an_error_not_a_silent_unknown(): void {
		$report = Importer::run( "Name,Good with cats?\nLuna,sometimes\n" );

		$this->assertSame( 1, $report['failed'] );
		$this->assertStringContainsString( 'yes/no', implode( ' ', $report['rows'][0]['errors'] ) );
	}

	// ─── Committing ─────────────────────────────────────────────────────────

	public function test_it_imports_a_real_world_sheet(): void {
		$report = Importer::run( $this->csv(), array( 'commit' => true ) );

		$this->assertSame( 2, $report['created'] );
		$this->assertSame( 0, $report['failed'] );

		$luna = get_page_by_path( 'luna', OBJECT, 'vcps_pet' ) ?? get_posts(
			array(
				'post_type'   => 'vcps_pet',
				'title'       => 'Luna',
				'numberposts' => 1,
			)
		)[0];

		$this->assertInstanceOf( \WP_Post::class, $luna );
		Pet_Hydrator::flush_cache();
		$hydrated = Pet_Hydrator::get( $luna->ID, 'full' );

		$this->assertSame( 'yes', $hydrated['ok_with_dogs'], "'Y' must normalise like every other yes" );
		$this->assertSame( 'no', $hydrated['ok_with_cats'], "'N' must normalise to no" );
		$this->assertSame( '42 lb', $hydrated['weight'] );
		$this->assertSame( '985141000111111', $hydrated['microchip_id'] );
		$this->assertStringContainsString( 'Quiet, gentle', $luna->post_content, 'a quoted field containing a comma must survive' );
	}

	public function test_taxonomy_terms_are_created_from_the_sheet(): void {
		Importer::run( $this->csv(), array( 'commit' => true ) );

		$pets = get_posts(
			array(
				'post_type'   => 'vcps_pet',
				'title'       => 'Pepper',
				'numberposts' => 1,
			)
		);
		$this->assertNotEmpty( $pets );

		$this->assertSame( array( 'Cat' ), wp_list_pluck( (array) get_the_terms( $pets[0]->ID, 'pet_animal' ), 'name' ) );
		$this->assertSame( array( 'Domestic Shorthair' ), wp_list_pluck( (array) get_the_terms( $pets[0]->ID, 'pet_breed' ), 'name' ) );
	}

	/**
	 * Attribute terms are derived from the hydrated entity, and the editor gets
	 * them from wp_after_insert_post — which fires before an import has written
	 * its meta. Without an explicit recompute, imported pets carry no
	 * pet_attribute terms and vanish from compatibility filtering while looking
	 * perfectly correct on their own page.
	 */
	public function test_imported_pets_get_their_attribute_terms(): void {
		Importer::run( "Name,Good with dogs?\nLuna,yes\n", array( 'commit' => true ) );

		$pets = get_posts(
			array(
				'post_type'   => 'vcps_pet',
				'title'       => 'Luna',
				'numberposts' => 1,
			)
		);

		$this->assertContains(
			'good-with-dogs',
			wp_list_pluck( (array) get_the_terms( $pets[0]->ID, 'pet_attribute' ), 'slug' ),
			'without this the pet is invisible to compatibility filtering'
		);
	}

	/**
	 * A row with a missing comma shifts every later value into the wrong field.
	 * Nothing downstream can detect it — the values are individually plausible,
	 * just attached to the wrong columns. Found in a hand-written fixture that
	 * only failed because a stray colour landed in a yes/no field; a stray "yes"
	 * would have imported silently and wrongly.
	 */
	public function test_a_row_with_the_wrong_number_of_cells_is_refused(): void {
		$csv = "Name,Sex,Good with dogs?,Weight\n" .
			"Luna,Female,yes,42 lb\n" .
			"Pepper,Male,yes\n";

		$report = Importer::run( $csv, array( 'commit' => true ) );

		$this->assertSame( 1, $report['created'] );
		$this->assertSame( 1, $report['failed'] );

		$errors = implode( ' ', $report['rows'][1]['errors'] );
		$this->assertStringContainsString( 'do not line up', $errors );
		$this->assertStringContainsString( '3 values', $errors );
		$this->assertStringContainsString( '4 columns', $errors );
	}

	/**
	 * The shift is only detectable at the row level, so prove the dangerous
	 * case specifically: every displaced value is individually valid.
	 */
	public function test_a_shifted_row_of_individually_valid_values_still_fails(): void {
		$csv = "Name,Good with dogs?,Good with cats?,Good with kids?\n" .
			"Luna,yes,yes\n";

		$report = Importer::run( $csv );

		$this->assertSame( 1, $report['failed'], 'every value here is a valid tristate; only the count betrays the shift' );
	}

	/**
	 * A multi-line description is quoted by the exporter and spans several
	 * physical lines. Splitting on newlines tears the record apart — which is
	 * how the export test was reading CSV until this feature was written.
	 */
	public function test_a_description_containing_newlines_survives(): void {
		$csv = "Name,Bio\nLuna,\"First line.\nSecond line.\"\n";

		$report = Importer::run( $csv, array( 'commit' => true ) );

		$this->assertSame( 1, $report['created'], 'a quoted newline must not end the record' );
		$this->assertSame( 0, $report['failed'] );

		$pets = get_posts(
			array(
				'post_type'   => 'vcps_pet',
				'numberposts' => -1,
			)
		);
		$this->assertStringContainsString( 'Second line.', $pets[0]->post_content );
	}

	// ─── The dangerous one ──────────────────────────────────────────────────

	/**
	 * THE test this feature must not ship without.
	 *
	 * remove_stale_pets() is provider-scoped: it drafts pets whose _pet_provider
	 * matches the syncing provider and whose id is absent from the feed. An
	 * imported pet is not in any provider's feed, so stamping it with a provider
	 * would make the very next sync draft every animal the shelter imported.
	 */
	public function test_imported_pets_have_no_provider_and_survive_a_sync(): void {
		Importer::run( $this->csv(), array( 'commit' => true ) );

		$pets = get_posts(
			array(
				'post_type'   => 'vcps_pet',
				'numberposts' => -1,
				'post_status' => 'publish',
			)
		);
		$this->assertCount( 2, $pets );

		foreach ( $pets as $pet ) {
			$this->assertSame( '', get_post_meta( $pet->ID, $this->prefix . 'provider', true ), 'an imported pet must have no provider' );
		}

		// A Petstablished sync whose feed contains neither of them.
		$method = new \ReflectionMethod( \Petsync_Sync::class, 'remove_stale_pets' );
		$method->invoke( new \Petsync_Sync(), array( array( 'id' => 5 ) ) );

		foreach ( $pets as $pet ) {
			$this->assertSame(
				'publish',
				get_post_status( $pet->ID ),
				'a sync must never draft an imported pet — this is the failure that would silently unpublish an entire shelter'
			);
		}
	}

	// ─── Re-import ──────────────────────────────────────────────────────────

	public function test_re_importing_the_same_file_updates_rather_than_duplicates(): void {
		Importer::run( $this->csv(), array( 'commit' => true ) );
		$report = Importer::run( $this->csv(), array( 'commit' => true ) );

		$this->assertSame( 0, $report['created'] );
		$this->assertSame( 2, $report['updated'] );
		$this->assertSame( 2, (int) wp_count_posts( 'vcps_pet' )->publish );
	}

	public function test_a_corrected_sheet_updates_the_existing_pet(): void {
		Importer::run( "Name,Microchip #,Weight\nLuna,985141000111111,42 lb\n", array( 'commit' => true ) );
		Importer::run( "Name,Microchip #,Weight\nLuna,985141000111111,44 lb\n", array( 'commit' => true ) );

		$pets = get_posts(
			array(
				'post_type'   => 'vcps_pet',
				'numberposts' => -1,
			)
		);
		$this->assertCount( 1, $pets );

		Pet_Hydrator::flush_cache();
		$this->assertSame( '44 lb', Pet_Hydrator::get( $pets[0]->ID, 'full' )['weight'] );
	}

	public function test_skip_leaves_the_existing_pet_untouched(): void {
		Importer::run( "Name,Microchip #,Weight\nLuna,985141000111111,42 lb\n", array( 'commit' => true ) );
		$report = Importer::run(
			"Name,Microchip #,Weight\nLuna,985141000111111,44 lb\n",
			array(
				'commit'   => true,
				'on_match' => Importer::ON_MATCH_SKIP,
			)
		);

		$this->assertSame( 1, $report['skipped'] );

		$pets = get_posts(
			array(
				'post_type'   => 'vcps_pet',
				'numberposts' => -1,
			)
		);
		Pet_Hydrator::flush_cache();
		$this->assertSame( '42 lb', Pet_Hydrator::get( $pets[0]->ID, 'full' )['weight'] );
	}

	/**
	 * A row with no microchip cannot be matched, so it is always an addition.
	 * Shelters do have unchipped animals.
	 */
	public function test_rows_without_a_microchip_are_always_added(): void {
		Importer::run( "Name,Weight\nLuna,42 lb\n", array( 'commit' => true ) );
		$report = Importer::run( "Name,Weight\nLuna,42 lb\n", array( 'commit' => true ) );

		$this->assertSame( 1, $report['created'] );
		$this->assertSame( 2, (int) wp_count_posts( 'vcps_pet' )->publish );
	}

	// ─── The round trip ─────────────────────────────────────────────────────

	/**
	 * The property the issue pairs export and import on: a file that comes out
	 * of the exporter must go back into the importer unchanged. One schema, so
	 * this is true by construction rather than by discipline — but only a test
	 * proves the construction held.
	 */
	public function test_an_exported_file_re_imports_unchanged(): void {
		$pet = $this->make_manual_pet( array( 'post_title' => 'Marigold' ) );
		update_post_meta( $pet, $this->prefix . 'weight', '11 lb' );
		update_post_meta( $pet, $this->prefix . 'microchip_id', '985141000999999' );
		update_post_meta( $pet, $this->prefix . 'ok_with_cats', 'no' );
		update_post_meta( $pet, $this->prefix . 'housebroken', 'unknown' );
		wp_set_object_terms( $pet, 'Cat', 'pet_animal' );
		Pet_Hydrator::flush_cache();

		$csv = Exporter::to_csv( array( $pet ), Schema::PORTABLE );

		// Every exported header must be one the importer recognises.
		$headers = str_getcsv( strtok( $csv, "\n" ), ',', '"', '' );
		foreach ( $headers as $header ) {
			$this->assertNotNull(
				Column_Mapper::resolve( (string) $header ),
				"the exporter emits '$header' but the importer cannot place it"
			);
		}

		$report = Importer::run( $csv, array( 'commit' => true ) );
		$this->assertSame( 0, $report['failed'], 'the exporter must not emit values the importer rejects' );
		$this->assertSame( 1, $report['updated'], 'matching on microchip must recognise the pet it came from' );

		Pet_Hydrator::flush_cache();
		$after = Pet_Hydrator::get( $pet, 'full' );

		$this->assertSame( '11 lb', $after['weight'] );
		$this->assertSame( 'no', $after['ok_with_cats'] );
		$this->assertSame( 'unknown', $after['housebroken'], "'unknown' must survive the round trip as itself" );
		$this->assertSame( array( 'Cat' ), wp_list_pluck( (array) get_the_terms( $pet, 'pet_animal' ), 'name' ) );
	}

	/**
	 * Columns derive from config, so a field added to entities.json becomes an
	 * importable column with no importer change.
	 */
	public function test_columns_derive_from_config_not_from_a_hand_list(): void {
		$entity   = \Petsync\Core\Config::get_path( 'entities', 'entities.vcps_pet', array() );
		$editable = array_keys( (array) ( $entity['editable_fields'] ?? array() ) );

		$this->assertNotEmpty( $editable );

		foreach ( $editable as $field ) {
			$this->assertTrue(
				Schema::is_importable( $field ),
				"editable field '$field' is not an importable column — the schema has drifted from the entity"
			);
		}
	}

	/**
	 * The acceptance criterion, literally: the shelter with eighty animals and
	 * no management platform, which is the whole reason this feature exists.
	 */
	public function test_eighty_pets_import_in_one_pass(): void {
		$csv = "Name,Species,Good with dogs?,Microchip #\n";
		for ( $i = 1; $i <= 80; $i++ ) {
			$csv .= sprintf( "Pet %d,Dog,yes,98514100%05d\n", $i, $i );
		}

		$report = Importer::run( $csv, array( 'commit' => true ) );

		$this->assertSame( 80, $report['created'] );
		$this->assertSame( 0, $report['failed'] );
		$this->assertSame( 80, (int) wp_count_posts( 'vcps_pet' )->publish );

		// And re-uploading the same sheet updates all eighty rather than
		// producing a hundred and sixty.
		$again = Importer::run( $csv, array( 'commit' => true ) );
		$this->assertSame( 80, $again['updated'] );
		$this->assertSame( 80, (int) wp_count_posts( 'vcps_pet' )->publish );
	}

	public function test_an_oversized_file_is_refused_rather_than_half_imported(): void {
		$csv = "Name\n" . str_repeat( "Luna\n", 5001 );

		$result = Importer::run( $csv );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'petsync_import_too_large', $result->get_error_code() );
	}
}
