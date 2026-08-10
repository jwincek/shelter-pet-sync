<?php
/**
 * Matching a shelter's spreadsheet headers to our column names.
 *
 * A real sheet says "Name", "Good with dogs?", "Microchip #" — not
 * `ok_with_dogs`. This resolves those to canonical columns, and reports what it
 * could not resolve so the UI can ask rather than guess.
 *
 * Deliberately separate from the importer: header matching is the part with
 * judgement in it, and it is the part worth testing on its own against strings
 * nobody would think to write by hand.
 *
 * @package ShelterKit_Pets
 * @since   1.1.0
 */

declare( strict_types = 1 );

namespace Petsync\Import;

use Petsync\Export\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Column_Mapper {

	/**
	 * Extra spellings a shelter is likely to use, beyond what normalisation
	 * already catches.
	 *
	 * Kept deliberately small and obvious. Every entry is a phrase a person
	 * would actually type, not a permutation — normalisation handles case,
	 * spacing, punctuation and the ? already, so this file only holds genuine
	 * SYNONYMS. A long alias list is a sign the field names are wrong.
	 *
	 * Keys must ALREADY be normalised — lookup happens on the normalised header,
	 * so an alias key containing a space or slash can never match anything.
	 * Asserted by test_every_alias_key_is_itself_normalised.
	 *
	 * @var array<string, string> normalised alias => canonical column
	 */
	private const ALIASES = array(
		'petname'          => 'name',
		'animalname'       => 'name',
		'title'            => 'name',
		'species'          => 'animal',
		'type'             => 'animal',
		'animaltype'       => 'animal',
		'gender'           => 'sex',
		'primarybreed'     => 'breed',
		'coatlength'       => 'coat',
		'hair'             => 'coat',
		'bio'              => 'description',
		'notes'            => 'description',
		'about'            => 'description',
		'story'            => 'description',
		'goodwithdogs'     => 'ok_with_dogs',
		'goodwithcats'     => 'ok_with_cats',
		'goodwithkids'     => 'ok_with_kids',
		'goodwithchildren' => 'ok_with_kids',
		'okwithchildren'   => 'ok_with_kids',
		'housetrained'     => 'housebroken',
		'housebroke'       => 'housebroken',
		'altered'          => 'spayed_neutered',
		'fixed'            => 'spayed_neutered',
		'spayed'           => 'spayed_neutered',
		'neutered'         => 'spayed_neutered',
		'microchip'        => 'microchip_id',
		'microchipnumber'  => 'microchip_id',
		'chip'             => 'microchip_id',
		'chipnumber'       => 'microchip_id',
		'vaccinated'       => 'shots_current',
		'shots'            => 'shots_current',
		'uptodateonshots'  => 'shots_current',
		'age'              => 'age',
		'ageyears'         => 'numerical_age',
		'agenumber'        => 'numerical_age',
		'fee'              => 'adoption_fee',
		'price'            => 'adoption_fee',
		'specialneeds'     => 'has_special_needs',
		// Non-US spellings. A shelter in the UK, Canada, Australia or Ireland
		// writes "Colour", and a column of empty cells is a poor welcome.
		'colour'           => 'color',
		'primarycolour'    => 'color',
		'primarycolor'     => 'color',
		'coatcolour'       => 'color',
		'neuteredspayed'   => 'spayed_neutered',
		'desexed'          => 'spayed_neutered',
		'microchipped'     => 'microchip_id',
	);

	/**
	 * Reduce a header to its comparable form.
	 *
	 * Case, spaces, underscores, hyphens and punctuation all go, because a
	 * shelter's "Good with dogs?" and our ok_with_dogs differ in every one of
	 * them. A BOM is stripped too: Excel writes one on the first header of a
	 * UTF-8 CSV, and it makes column one match nothing at all — a failure that
	 * looks like the file is fine except the name column is empty.
	 *
	 * @param string $header Raw header cell.
	 */
	public static function normalise( string $header ): string {
		$header = preg_replace( '/^\xEF\xBB\xBF/', '', $header ) ?? $header;
		$header = strtolower( trim( $header ) );

		return (string) preg_replace( '/[^a-z0-9]/', '', $header );
	}

	/**
	 * Resolve one header to a canonical column, or null.
	 *
	 * @param string $header Raw header cell.
	 */
	public static function resolve( string $header ): ?string {
		$key = self::normalise( $header );
		if ( '' === $key ) {
			return null;
		}

		// An exact match on a real column always wins over an alias, so a sheet
		// whose header is literally 'age' is never rerouted by a synonym.
		foreach ( Schema::columns( Schema::PORTABLE ) as $column ) {
			if ( self::normalise( $column ) === $key ) {
				return $column;
			}
		}

		$aliased = self::ALIASES[ $key ] ?? null;

		return ( null !== $aliased && Schema::is_importable( $aliased ) ) ? $aliased : null;
	}

	/**
	 * Map a header row.
	 *
	 * Returns the resolved mapping plus everything it could not place, so the
	 * caller can show the mapping for confirmation instead of silently dropping
	 * a column the shelter cared about.
	 *
	 * @param string[] $headers Raw header cells, in file order.
	 * @return array{mapping: array<int, string>, unmapped: array<int, string>, duplicates: array<int, string>}
	 */
	public static function map( array $headers ): array {
		$mapping    = array();
		$unmapped   = array();
		$duplicates = array();

		foreach ( array_values( $headers ) as $index => $header ) {
			$column = self::resolve( (string) $header );

			if ( null === $column ) {
				if ( '' !== trim( (string) $header ) ) {
					$unmapped[ $index ] = (string) $header;
				}
				continue;
			}

			// Two columns claiming one field would make the later one win
			// silently. Report it and keep the first.
			if ( in_array( $column, $mapping, true ) ) {
				$duplicates[ $index ] = (string) $header;
				continue;
			}

			$mapping[ $index ] = $column;
		}

		return array(
			'mapping'    => $mapping,
			'unmapped'   => $unmapped,
			'duplicates' => $duplicates,
		);
	}

	/**
	 * Every column an import can write, for the mapping UI's override dropdown.
	 *
	 * @return string[]
	 */
	public static function available_columns(): array {
		return Schema::columns( Schema::PORTABLE );
	}
}
