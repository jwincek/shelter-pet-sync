<?php
/**
 * A toggle the server honours must be one the editor can set.
 *
 * render.php gated six fields on attributes, but the editor registration
 * declared only three of them. The other three had no control and — because an
 * attribute the editor does not declare is one it cannot serialise — could not
 * have been given one without the declaration either. Nothing failed: the block
 * rendered, the attributes had defaults, and the fields were simply on forever,
 * which reads as a design decision rather than an omission.
 *
 * validate-config check 13 holds the general rule. These tests hold the
 * behaviour, because a control that exists and does not work looks identical to
 * one that does until someone prints a card.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

final class PetHealthControlsTest extends PetTestCase {

	/**
	 * A pet with every health field answered, so each toggle has something to
	 * hide. Left as 'no' where a 'yes' would be suppressed as cat-only.
	 */
	private function make_pet_with_health(): int {
		$id = $this->make_manual_pet();
		wp_set_object_terms( $id, 'Cat', 'pet_animal' );

		foreach ( array(
			'spayed_neutered'   => 'yes',
			'shots_current'     => 'yes',
			'housebroken'       => 'no',
			'has_special_needs' => 'no',
			'hypoallergenic'    => 'no',
			'declawed'          => 'yes',
		) as $key => $value ) {
			update_post_meta( $id, $this->prefix . $key, $value );
		}

		\Petsync\Core\Pet_Hydrator::flush_cache();

		return $id;
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private function render( int $pet, array $attributes = array() ): string {
		global $post;

		$previous = $post;
		$post     = get_post( $pet ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
		setup_postdata( $post );

		$json = $attributes ? ' ' . wp_json_encode( $attributes ) . ' ' : ' ';
		$html = do_blocks( "<!-- wp:petsync/pet-health{$json}/-->" );

		$post = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring.
		wp_reset_postdata();

		return $html;
	}

	// ── The three toggles that had no control ───────────────────────────────

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function unreachable_toggles(): array {
		return array(
			'special needs'  => array( 'showSpecialNeeds', 'Special Needs' ),
			'hypoallergenic' => array( 'showHypoallergenic', 'Hypoallergenic' ),
			'declawed'       => array( 'showDeclawed', 'Declawed' ),
		);
	}

	/**
	 * @dataProvider unreachable_toggles
	 */
	public function test_a_field_can_be_switched_off( string $attribute, string $label ): void {
		$pet = $this->make_pet_with_health();

		$this->assertStringContainsString(
			$label,
			$this->render( $pet ),
			"$label should be shown by default"
		);

		$this->assertStringNotContainsString(
			$label,
			$this->render( $pet, array( $attribute => false ) ),
			"$attribute is honoured by render.php but setting it false changed nothing"
		);
	}

	// ── The Yes/No wording ──────────────────────────────────────────────────

	public function test_the_status_wording_can_be_turned_off(): void {
		$pet = $this->make_pet_with_health();

		$this->assertStringContainsString( 'pet-health__status', $this->render( $pet ) );
		$this->assertStringNotContainsString(
			'pet-health__status',
			$this->render( $pet, array( 'showStatusText' => false ) )
		);
	}

	/**
	 * The reason hiding the wording is acceptable at all: the value is on the
	 * <li> as an aria-label, and both the icon and the word are aria-hidden. If
	 * that ever stops being true, turning the wording off silently removes the
	 * value from assistive technology rather than just from the page.
	 */
	public function test_hiding_the_wording_does_not_remove_it_from_assistive_technology(): void {
		$pet = $this->make_pet_with_health();

		$with    = $this->render( $pet );
		$without = $this->render( $pet, array( 'showStatusText' => false ) );

		$this->assertSame(
			substr_count( $with, 'aria-label=' ),
			substr_count( $without, 'aria-label=' ),
			'hiding the wording must not reduce the number of labelled items'
		);
		$this->assertGreaterThan( 0, substr_count( $without, 'aria-label=' ) );
		$this->assertMatchesRegularExpression(
			'/aria-label="[^"]*:\s*(Yes|No)"/',
			$without,
			'the value must still reach a screen reader when the word is hidden'
		);
	}

	public function test_the_items_themselves_survive(): void {
		$pet = $this->make_pet_with_health();

		$this->assertSame(
			substr_count( $this->render( $pet ), 'pet-health__item ' ),
			substr_count( $this->render( $pet, array( 'showStatusText' => false ) ), 'pet-health__item ' ),
			'hiding the wording must hide only the wording'
		);
	}

	// ── The declared attributes must match what the server reads ────────────

	/**
	 * block.json is the contract the editor registration has to match. This is
	 * the half that was wrong, and it fails silently in the direction that looks
	 * like success.
	 */
	public function test_every_attribute_the_render_reads_is_declared_in_block_json(): void {
		$json = json_decode( (string) file_get_contents( PETSYNC_DIR . 'blocks/pet-health/block.json' ), true );
		$src  = (string) file_get_contents( PETSYNC_DIR . 'blocks/pet-health/render.php' );

		$declared = array_keys( (array) ( $json['attributes'] ?? array() ) );
		$this->assertNotEmpty( $declared );

		if ( preg_match_all( "/'toggle'\s*(?:=>|:)\s*'(\w+)'/", $src, $m ) ) {
			foreach ( array_unique( $m[1] ) as $toggle ) {
				$this->assertContains(
					$toggle,
					$declared,
					"render.php gates a field on '$toggle', which block.json does not declare"
				);
			}
		}
	}
}
