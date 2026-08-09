<?php
/**
 * Tristate normalisation and the compatibility summary built from it.
 *
 * These two are tested together because the bug that motivated the suite lived
 * in the seam between them: resolve_tristate() correctly returned the STRING
 * 'no', and compute_compatibility() then tested it with ! empty(), which is
 * true for any non-empty string. Pets recorded as not good with dogs, cats or
 * children were advertised as good with exactly those — 22 of 93 published
 * pets on the site where it was found.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Petsync\Core\Pet_Hydrator;
use ReflectionMethod;

final class TristateTest extends TestCase {

	/**
	 * @dataProvider tristate_values
	 */
	public function test_resolve_tristate_normalises( mixed $input, string $expected ): void {
		$this->assertSame( $expected, Pet_Hydrator::resolve_tristate( $input ) );
	}

	/**
	 * @return array<string, array{0: mixed, 1: string}>
	 */
	public static function tristate_values(): array {
		return array(
			'empty string is no data'  => array( '', '' ),
			'null is no data'          => array( null, '' ),
			'yes'                      => array( 'yes', 'yes' ),
			'Yes is case-insensitive'  => array( 'Yes', 'yes' ),
			'true string'              => array( 'true', 'yes' ),
			'numeric one'              => array( '1', 'yes' ),
			'boolean true'             => array( true, 'yes' ),
			'no'                       => array( 'no', 'no' ),
			'No is case-insensitive'   => array( 'No', 'no' ),
			'false string'             => array( 'false', 'no' ),
			'numeric zero'             => array( '0', 'no' ),
			'boolean false'            => array( false, 'no' ),
			'Not Sure is inconclusive' => array( 'Not Sure', 'unknown' ),
			'anything else is unknown' => array( 'maybe', 'unknown' ),
			'whitespace is trimmed'    => array( '  yes  ', 'yes' ),
		);
	}

	/**
	 * Only an explicit 'yes' may appear in the summary.
	 *
	 * @dataProvider compatibility_cases
	 *
	 * @param array<string, string> $entity   Tristate values.
	 * @param string                $expected Summary text.
	 */
	public function test_compatibility_counts_only_yes( array $entity, string $expected ): void {
		$this->assertSame( $expected, self::compatibility( $entity ) );
	}

	/**
	 * @return array<string, array{0: array<string, string>, 1: string}>
	 */
	public static function compatibility_cases(): array {
		return array(
			'all yes'                    => array(
				array(
					'ok_with_dogs' => 'yes',
					'ok_with_cats' => 'yes',
					'ok_with_kids' => 'yes',
				),
				'Good with dogs, cats, kids',
			),
			// The regression: 'no' is a non-empty string.
			'all no claims nothing'      => array(
				array(
					'ok_with_dogs' => 'no',
					'ok_with_cats' => 'no',
					'ok_with_kids' => 'no',
				),
				'',
			),
			// So is 'unknown'.
			'unknown claims nothing'     => array(
				array(
					'ok_with_dogs' => 'unknown',
					'ok_with_cats' => 'unknown',
					'ok_with_kids' => 'unknown',
				),
				'',
			),
			'mixed lists only the yeses' => array(
				array(
					'ok_with_dogs' => 'no',
					'ok_with_cats' => 'yes',
					'ok_with_kids' => 'unknown',
				),
				'Good with cats',
			),
			'no data claims nothing'     => array(
				array(
					'ok_with_dogs' => '',
					'ok_with_cats' => '',
					'ok_with_kids' => '',
				),
				'',
			),
			'missing keys claim nothing' => array( array(), '' ),
			'uppercase YES still counts' => array(
				array(
					'ok_with_dogs' => 'YES',
					'ok_with_cats' => 'no',
					'ok_with_kids' => 'no',
				),
				'Good with dogs',
			),
		);
	}

	/**
	 * The real Spike: recorded as unsafe with everything, must claim nothing.
	 */
	public function test_a_pet_unsafe_with_everything_claims_nothing(): void {
		$spike = array(
			'ok_with_dogs' => Pet_Hydrator::resolve_tristate( 'No' ),
			'ok_with_cats' => Pet_Hydrator::resolve_tristate( 'No' ),
			'ok_with_kids' => Pet_Hydrator::resolve_tristate( 'No' ),
		);

		$this->assertSame( '', self::compatibility( $spike ) );
	}

	/**
	 * compute_compatibility() is private; it is pure, so reflection is fine.
	 *
	 * @param array<string, string> $entity Entity fragment.
	 */
	private static function compatibility( array $entity ): string {
		// No setAccessible(): it has been a no-op since PHP 8.1 and is
		// deprecated in 8.5.
		$method = new ReflectionMethod( Pet_Hydrator::class, 'compute_compatibility' );

		return $method->invoke( null, $entity );
	}
}
