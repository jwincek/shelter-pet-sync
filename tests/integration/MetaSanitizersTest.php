<?php
/**
 * Registered meta sanitisation, exercised through the REST-facing path.
 *
 * The attachment_ids case is here because absint() was the obvious choice and
 * the wrong one: it takes the ABSOLUTE value, so -5 silently resolved to
 * attachment 5 — a real, unrelated image rather than a rejected input.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

final class MetaSanitizersTest extends PetTestCase {

	public function test_editable_fields_are_registered_as_meta(): void {
		$entity   = \Petsync\Core\Config::get_path( 'entities', 'entities.vcps_pet', array() );
		$editable = array_keys( $entity['editable_fields'] ?? array() );
		$keys     = get_registered_meta_keys( 'post', 'vcps_pet' );

		$this->assertNotEmpty( $editable );

		foreach ( $editable as $field ) {
			$this->assertArrayHasKey(
				$this->prefix . $field,
				$keys,
				"editable field {$field} must be registered or the editor writes into a void"
			);
		}
	}

	public function test_gallery_ids_is_registered_as_an_array_with_a_rest_schema(): void {
		$keys = get_registered_meta_keys( 'post', 'vcps_pet' );
		$meta = $keys[ $this->prefix . 'gallery_ids' ] ?? null;

		$this->assertNotNull( $meta );
		$this->assertSame( 'array', $meta['type'] );
		$this->assertIsArray(
			$meta['show_in_rest'],
			'array meta needs an explicit schema or REST rejects the value and the save fails silently'
		);
		$this->assertSame( 'integer', $meta['show_in_rest']['schema']['items']['type'] );
	}

	/**
	 * @dataProvider attachment_id_inputs
	 *
	 * @param mixed $input    Raw value.
	 * @param array $expected Stored value.
	 */
	public function test_attachment_ids_are_sanitised( mixed $input, array $expected ): void {
		$id = $this->make_manual_pet();

		update_post_meta( $id, $this->prefix . 'gallery_ids', $input );

		$this->assertSame( $expected, get_post_meta( $id, $this->prefix . 'gallery_ids', true ) );
	}

	/**
	 * @return array<string, array{0: mixed, 1: array}>
	 */
	public static function attachment_id_inputs(): array {
		return array(
			'positive ids survive in order' => array( array( 31, 12, 25 ), array( 31, 12, 25 ) ),
			'numeric strings are coerced'   => array( array( '31', '12' ), array( 31, 12 ) ),
			'zero is dropped'               => array( array( 31, 0 ), array( 31 ) ),
			'non-numeric is dropped'        => array( array( 31, 'not-an-id' ), array( 31 ) ),
			// absint(-5) is 5. intval keeps it negative so the filter drops it.
			'negatives are rejected'        => array( array( 31, -5 ), array( 31 ) ),
			'a bare scalar is not an array' => array( 'nope', array() ),
			'an empty array stays empty'    => array( array(), array() ),
		);
	}

	public function test_a_negative_id_never_becomes_a_different_attachment(): void {
		$id = $this->make_manual_pet();

		update_post_meta( $id, $this->prefix . 'gallery_ids', array( -5 ) );

		$stored = get_post_meta( $id, $this->prefix . 'gallery_ids', true );

		$this->assertNotContains( 5, $stored, 'absint would have turned -5 into attachment 5' );
		$this->assertSame( array(), $stored );
	}

	public function test_text_fields_are_sanitised(): void {
		$id = $this->make_manual_pet();

		update_post_meta( $id, $this->prefix . 'weight', '<script>alert(1)</script>40 lbs' );

		$this->assertSame( '40 lbs', get_post_meta( $id, $this->prefix . 'weight', true ) );
	}
	/**
	 * A negative id must not be flipped into a real attachment.
	 *
	 * absint() takes the ABSOLUTE value, so -5 would resolve to attachment 5 —
	 * a real, unrelated image. The sanitiser uses intval() with a > 0 filter.
	 */
	public function test_a_negative_attachment_id_is_rejected(): void {
		$pet = $this->make_manual_pet();

		update_post_meta( $pet, $this->prefix . 'gallery_ids', array( 31, -31 ) );

		$this->assertSame( array( 31 ), get_post_meta( $pet, $this->prefix . 'gallery_ids', true ) );
	}

	/**
	 * Non-images are handled at READ time, not by the sanitiser.
	 *
	 * #34 proposed moving the ability's wp_attachment_is_image() check into
	 * this sanitiser so both write paths matched. Testing it showed the premise
	 * was wrong: a non-image never renders as a broken image, because
	 * compute_manual_gallery() skips anything wp_get_attachment_image_url()
	 * cannot resolve. Putting the check on the write side would have coupled
	 * meta-writing to attachment-creation order and silently discarded ids an
	 * importer had not sideloaded yet — a real failure traded for one that does
	 * not happen.
	 *
	 * This pins the protection that actually exists, so it stops being
	 * incidental.
	 */
	public function test_non_images_are_dropped_when_the_gallery_is_read(): void {
		$image = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$pdf   = self::factory()->post->create(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'application/pdf',
			)
		);

		$pet = $this->make_manual_pet();
		update_post_meta( $pet, $this->prefix . 'gallery_ids', array( $image, $pdf, 999999 ) );

		$this->assertCount(
			3,
			get_post_meta( $pet, $this->prefix . 'gallery_ids', true ),
			'the sanitiser stores what it is given — existence is not its business'
		);

		\Petsync\Core\Pet_Hydrator::flush_cache();
		$gallery = \Petsync\Core\Pet_Hydrator::get( $pet, 'full' )['gallery'] ?? array();

		$this->assertCount( 1, $gallery, 'only the real image may reach the front end' );
	}
}
