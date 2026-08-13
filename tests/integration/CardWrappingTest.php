<?php
/**
 * Kennel-card text must not break mid-word.
 *
 * Printed cards read "Spayed/Neutere / d" and "YE / S" on a narrow column.
 * The cause is inherited: several themes set `word-break: break-word`, which
 * collapses a label's min-content width to a SINGLE CHARACTER — measured at
 * 9.6px for "Spayed/Neutered" against 114.7px with the fix. A flex item is then
 * free to shrink to nothing, and the text shatters:
 *
 *   without: ["Sp","ay","ed","/N","eu","te","re","d"]
 *   with:    does not break; others break only at spaces
 *
 * `overflow-wrap: break-word` rather than `anywhere` is the load-bearing part —
 * it keeps a last-resort break without affecting intrinsic sizing, which is the
 * entire difference between the two.
 *
 * This asserts the declarations exist. It cannot prove the rendering, which was
 * verified in a real browser at the width that broke — but it does catch the
 * likely regression, which is someone tidying the rules away.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

final class CardWrappingTest extends PetTestCase {

	/**
	 * @return array<string, string[]>
	 */
	private function stylesheets(): array {
		return array(
			'blocks/pet-health/style.css'     => array( '.pet-health__label', '.pet-health__status' ),
			'blocks/pet-attributes/style.css' => array( '.pet-attributes__label', '.pet-attributes__value' ),
		);
	}

	public function test_the_card_text_blocks_assert_their_own_wrapping(): void {
		foreach ( $this->stylesheets() as $file => $selectors ) {
			$css = file_get_contents( PETSYNC_DIR . $file );
			$this->assertNotFalse( $css, "$file is missing" );

			foreach ( $selectors as $selector ) {
				$this->assertStringContainsString( $selector, $css, "$file no longer styles $selector" );
			}

			$this->assertMatchesRegularExpression(
				'/word-break:\s*normal/',
				$css,
				"$file must undo an inherited word-break, or labels break mid-word on a narrow card"
			);
			$this->assertMatchesRegularExpression(
				'/overflow-wrap:\s*break-word/',
				$css,
				"$file must use break-word, not anywhere — anywhere collapses min-content to one character"
			);
			$this->assertDoesNotMatchRegularExpression(
				'/overflow-wrap:\s*anywhere/',
				$css,
				"$file must not use overflow-wrap: anywhere, which reintroduces the mid-word break"
			);
		}
	}

	/**
	 * A three-letter status has no sensible break point at all.
	 */
	public function test_the_status_pill_never_wraps(): void {
		$css = (string) file_get_contents( PETSYNC_DIR . 'blocks/pet-health/style.css' );

		$this->assertMatchesRegularExpression( '/white-space:\s*nowrap/', $css );
	}

	/**
	 * The URL on the card is the one place breaking anywhere is correct — a
	 * permalink has no spaces and would otherwise overflow the card entirely.
	 */
	public function test_the_card_url_still_breaks_anywhere(): void {
		$css = (string) file_get_contents( PETSYNC_DIR . 'assets/css/kennel-cards.css' );

		$this->assertMatchesRegularExpression(
			'/\.kennel-card__url\s*\{[^}]*overflow-wrap:\s*anywhere/s',
			$css,
			'a permalink has no break opportunities and must be allowed to break anywhere'
		);
	}
}
