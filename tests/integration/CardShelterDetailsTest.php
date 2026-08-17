<?php
/**
 * The kennel card's contact line is data, not text in a design.
 *
 * It used to be literal prose in parts/kennel-card.html — "Add your shelter's
 * phone, email and address here" — which meant the instruction printed on every
 * card until someone edited the design. On the live site nobody did, and four
 * real cards went out telling the reader to enter an address.
 *
 * The org name above it never had that problem, because it came from
 * wp:site-title. The difference was never the wording; it was that one was
 * bound and the other was typed.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

final class CardShelterDetailsTest extends PetTestCase {

	public function tear_down(): void {
		delete_option( \ShelterKit_Profile::OPTION );
		parent::tear_down();
	}

	private function binding( string $key ): string {
		return ( new \Petsync_Blocks() )->get_shelter_binding_value( array( 'key' => $key ) );
	}

	/**
	 * The regression this whole change exists for.
	 */
	public function test_the_shipped_card_carries_no_placeholder_prose(): void {
		$card = (string) file_get_contents( PETSYNC_DIR . 'parts/kennel-card.html' );

		$this->assertStringNotContainsString( 'Add your shelter', $card, 'the card must not instruct the public' );
		$this->assertStringContainsString(
			'"source":"petsync/shelter"',
			$card,
			'the contact line must be bound, not typed'
		);
	}

	/**
	 * A shelter that has filled nothing in gets a clean card, not a prompt aimed
	 * at someone who will never see it.
	 */
	public function test_an_unfilled_profile_prints_nothing(): void {
		delete_option( \ShelterKit_Profile::OPTION );

		$this->assertSame( '', $this->binding( 'contact' ) );
		$this->assertSame( '', $this->binding( 'address' ) );
	}

	public function test_a_filled_profile_composes_one_line(): void {
		\ShelterKit_Profile::save(
			array(
				'street_address' => '286 South Main Street',
				'locality'       => 'Seneca',
				'region'         => 'PA',
				'postal_code'    => '16346',
				'phone'          => '814-677-4040',
			)
		);

		$this->assertSame(
			'286 South Main Street, Seneca, PA 16346 · 814-677-4040',
			$this->binding( 'contact' )
		);
	}

	/**
	 * A phone but no address must not print a leading separator.
	 */
	public function test_a_partial_profile_does_not_print_a_stray_separator(): void {
		\ShelterKit_Profile::save( array( 'phone' => '814-677-4040' ) );

		$this->assertSame( '814-677-4040', $this->binding( 'contact' ) );
	}

	/**
	 * The name falls back to the site title, so it is never blank — which is
	 * why has_contact_details() must not count it, or an empty profile would
	 * still render a contact line.
	 */
	public function test_the_name_alone_does_not_produce_a_contact_line(): void {
		\ShelterKit_Profile::save( array( 'name' => 'Venango County Humane Society' ) );

		$this->assertSame( 'Venango County Humane Society', $this->binding( 'name' ) );
		$this->assertSame( '', $this->binding( 'contact' ) );
	}

	/**
	 * The binding must survive the profile class being absent — the shared file
	 * is loaded by version negotiation, and a sibling plugin could in principle
	 * win with a copy that has not loaded yet.
	 */
	public function test_an_unknown_key_returns_a_string_not_a_notice(): void {
		$this->assertSame( '', $this->binding( 'no_such_field' ) );
		$this->assertSame( '', ( new \Petsync_Blocks() )->get_shelter_binding_value( array() ) );
	}

	/**
	 * The prompt moved to where the person who can act on it is standing.
	 */
	public function test_the_admin_screen_prompts_when_details_are_missing(): void {
		$src = (string) file_get_contents( PETSYNC_DIR . 'includes/class-petsync-kennel-cards.php' );

		$this->assertStringContainsString( 'render_shelter_details_notice', $src );
		$this->assertStringContainsString( 'has_contact_details', $src, 'the prompt must be conditional, not permanent' );
	}
}
