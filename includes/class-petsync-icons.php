<?php
/**
 * Shelter Pets Icons - Centralized SVG Icon Library
 *
 * Provides a single source of truth for all SVG icons used across blocks.
 * Supports customizable size, stroke width, and additional attributes.
 *
 * @package Shelter_Pets
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Petsync_Icons {

	/**
	 * Default icon attributes.
	 */
	private const DEFAULTS = array(
		'width'        => 20,
		'height'       => 20,
		'fill'         => 'none',
		'stroke'       => 'currentColor',
		'stroke-width' => 2,
		'aria-hidden'  => 'true',
	);

	/**
	 * Icon definitions.
	 *
	 * Each icon is defined as a viewBox and path data.
	 * The viewBox is typically '0 0 24 24' for most icons.
	 */
	private const ICONS = array(

		// === Animals ===

		'dog'               => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M10 5.172C10 3.782 8.423 2.679 6.5 3c-2.823.47-4.113 6.006-4 7 .08.703 1.725 1.722 3.656 1 1.261-.472 1.96-1.45 2.344-2.5M14.267 5.172c0-1.39 1.577-2.493 3.5-2.172 2.823.47 4.113 6.006 4 7-.08.703-1.725 1.722-3.656 1-1.261-.472-1.855-1.45-2.239-2.5',
				'M8 14v.5M16 14v.5M11.25 16.25h1.5L12 17l-.75-.75z',
				'M4.42 11.247A13.152 13.152 0 0 0 4 14.556C4 18.728 7.582 21 12 21s8-2.272 8-6.444a11.702 11.702 0 0 0-.493-3.309',
			),
		),

		'cat'               => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M12 5c.67 0 1.35.09 2 .26 1.78-2 5.03-2.84 6.42-2.26 1.4.58-.42 7-.42 7 .57 1.07 1 2.24 1 3.44C21 17.9 16.97 21 12 21s-9-3.1-9-7.56c0-1.25.5-2.4 1-3.44 0 0-1.89-6.42-.5-7 1.39-.58 4.72.23 6.5 2.23A9.04 9.04 0 0 1 12 5z',
				'M8 14v.5M16 14v.5M11.25 16.25h1.5L12 17l-.75-.75z',
			),
		),

		// Seated toddler — large head, small body sitting down.
		// Universally recognized as "child" from signage (changing rooms, car seats).
		'child'             => array(
			'viewBox' => '0 0 24 24',
			'circles' => array(
				array(
					'cx' => 12,
					'cy' => 4,
					'r'  => 3,
				), // head — proportionally large
			),
			'paths'   => array(
				'M12 9c-2 0-3.5 1.5-3.5 3.5V15h7v-2.5C15.5 10.5 14 9 12 9z', // torso sitting
				'M8.5 15v4.5a1.5 1.5 0 0 0 3 0V17', // left leg tucked
				'M12.5 17v2.5a1.5 1.5 0 0 0 3 0V15', // right leg extended
				'M8 11.5l-2.5 2M16 11.5l2.5 2', // arms reaching out
			),
		),

		// Pet paw placeholder (for missing images).
		'paw'               => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M10 5.172C10 3.782 8.423 2.679 6.5 3c-2.823.47-4.113 6.006-4 7 .08.703 1.725 1.722 3.656 1 1.261-.472 1.96-1.45 2.344-2.5M14 5.172c0-1.39 1.577-2.493 3.5-2.172 2.823.47 4.113 6.006 4 7-.08.703-1.725 1.722-3.656 1-1.261-.472-1.96-1.45-2.344-2.5',
				'M8 14v.5M16 14v.5M11.25 16.25h1.5L12 17l-.75-.75Z',
				'M4.42 11.247A13.152 13.152 0 0 0 4 14.556C4 18.728 7.582 21 12 21s8-2.272 8-6.444c0-1.061-.162-2.2-.493-3.309',
			),
		),

		// === Actions ===

		'heart'             => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z',
			),
		),

		'heart-special'     => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7 7-7z',
			),
		),

		'share'             => array(
			'viewBox' => '0 0 24 24',
			'circles' => array(
				array(
					'cx'   => 18,
					'cy'   => 5,
					'r'    => 3,
					'fill' => 'currentColor',
				),
				array(
					'cx'   => 6,
					'cy'   => 12,
					'r'    => 3,
					'fill' => 'currentColor',
				),
				array(
					'cx'   => 18,
					'cy'   => 19,
					'r'    => 3,
					'fill' => 'currentColor',
				),
			),
			'lines'   => array(
				array(
					'x1' => '8.59',
					'y1' => '13.51',
					'x2' => '15.42',
					'y2' => '17.49',
				),
				array(
					'x1' => '15.41',
					'y1' => '6.51',
					'x2' => '8.59',
					'y2' => '10.49',
				),
			),
		),

		'compare'           => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M16 3h5v5M8 3H3v5M3 16v5h5M21 16v5h-5',
			),
		),

		'compare-grid'      => array(
			'viewBox' => '0 0 24 24',
			'rects'   => array(
				array(
					'x'      => 3,
					'y'      => 3,
					'width'  => 7,
					'height' => 7,
				),
				array(
					'x'      => 14,
					'y'      => 3,
					'width'  => 7,
					'height' => 7,
				),
				array(
					'x'      => 3,
					'y'      => 14,
					'width'  => 7,
					'height' => 7,
				),
				array(
					'x'      => 14,
					'y'      => 14,
					'width'  => 7,
					'height' => 7,
				),
			),
		),

		'external-link'     => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6',
				'M15 3h6v6', // polyline
			),
			'lines'   => array(
				array(
					'x1' => '10',
					'y1' => '14',
					'x2' => '21',
					'y2' => '3',
				),
			),
		),

		'download'          => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4',
				'M7 10l5 5 5-5', // polyline
				'M12 15V3',      // line
			),
		),

		'trash'             => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M3 6h18', // polyline points="3 6 5 6 21 6"
				'M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2',
			),
		),

		// === Navigation ===

		'chevron-left'      => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M15 18l-6-6 6-6',
			),
		),

		'chevron-right'     => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M9 18l6-6-6-6',
			),
		),

		'chevron-down'      => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M6 9l6 6 6-6',
			),
		),

		'chevron-up'        => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M18 15l-6-6-6 6',
			),
		),

		'arrow-left'        => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M19 12H5', // line
				'M12 19l-7-7 7-7', // polyline
			),
		),

		'arrow-right'       => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M5 12h14',
				'M12 5l7 7-7 7',
			),
		),

		'back'              => array(
			'viewBox'   => '0 0 24 24',
			'paths'     => array(
				'M19 12H5',
			),
			'polylines' => array(
				'12 19 5 12 12 5',
			),
		),

		// === Status Indicators ===

		'check'             => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M20 6L9 17l-5-5', // polyline
			),
		),

		'check-circle'      => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M22 11.08V12a10 10 0 1 1-5.93-9.14',
				'M22 4L12 14.01l-3-3', // polyline
			),
		),

		'x'                 => array(
			'viewBox' => '0 0 24 24',
			'lines'   => array(
				array(
					'x1' => '18',
					'y1' => '6',
					'x2' => '6',
					'y2' => '18',
				),
				array(
					'x1' => '6',
					'y1' => '6',
					'x2' => '18',
					'y2' => '18',
				),
			),
		),

		'x-circle'          => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20z', // circle centered at 12,12 r=10
			),
			'lines'   => array(
				array(
					'x1' => '15',
					'y1' => '9',
					'x2' => '9',
					'y2' => '15',
				),
				array(
					'x1' => '9',
					'y1' => '9',
					'x2' => '15',
					'y2' => '15',
				),
			),
		),

		'minus'             => array(
			'viewBox' => '0 0 24 24',
			'lines'   => array(
				array(
					'x1' => '5',
					'y1' => '12',
					'x2' => '19',
					'y2' => '12',
				),
			),
		),

		'info'              => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20z', // circle centered at 12,12 r=10
			),
			'lines'   => array(
				array(
					'x1' => '12',
					'y1' => '16',
					'x2' => '12',
					'y2' => '12',
				),
				array(
					'x1' => '12',
					'y1' => '8',
					'x2' => '12.01',
					'y2' => '8',
				),
			),
		),

		'help-circle'       => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20z', // circle centered at 12,12 r=10
				'M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3', // question mark curve
			),
			'lines'   => array(
				array(
					'x1' => '12',
					'y1' => '17',
					'x2' => '12.01',
					'y2' => '17',
				), // dot
			),
		),

		// === UI Elements ===

		'expand'            => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M15 3h6v6', // polyline
				'M9 21H3v-6', // polyline
			),
			'lines'   => array(
				array(
					'x1' => '21',
					'y1' => '3',
					'x2' => '14',
					'y2' => '10',
				),
				array(
					'x1' => '3',
					'y1' => '21',
					'x2' => '10',
					'y2' => '14',
				),
			),
		),

		'search'            => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M21 21l-5.2-5.2', // handle
				'M10 17a7 7 0 1 0 0-14 7 7 0 0 0 0 14z', // lens
			),
		),

		'image-placeholder' => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M3 3h18a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z', // rect
				'M8.5 10a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z', // circle
				'M21 15l-5-5L5 21', // polyline
			),
		),

		// === Health & Compatibility ===

		'syringe'           => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'm18 2 4 4M7.5 21.5 3 17l10-10 4 4-10 10zM15 6l-2-2m-4 4-2-2m-4 4-2-2',
			),
		),

		// Shield with checkmark — health/vaccination protection.
		'shield-check'      => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z', // shield
				'M9 12l2 2 4-4', // checkmark
			),
		),

		'house'             => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z',
				'M9 22V12h6v10', // polyline
			),
		),

		// Social / utility icons for share dropdown.
		'facebook'          => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z',
			),
		),

		'x-twitter'         => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M4 4l7.2 10.2L4 20h2l5.6-4.5L16 20h4l-7.6-10.7L19.2 4H17.2l-5.2 4.2L8 4z',
			),
		),

		'mail'              => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z',
				'M22 6l-10 7L2 6',
			),
		),

		'link'              => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71',
				'M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71',
			),
		),

		// === Pet Attribute Icons ===

		'clock'             => array(
			'viewBox' => '0 0 24 24',
			'circles' => array(
				array(
					'cx' => 12,
					'cy' => 12,
					'r'  => 10,
				),
			),
			'paths'   => array(
				'M12 6v6l4 2',
			),
		),

		'user'              => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2',
			),
			'circles' => array(
				array(
					'cx' => 12,
					'cy' => 7,
					'r'  => 4,
				),
			),
		),

		'maximize'          => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7',
			),
		),

		'droplet'           => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z',
			),
		),

		'wind'              => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M17.7 7.7a2.5 2.5 0 1 1 1.8 4.3H2',
				'M9.6 4.6A2 2 0 1 1 11 8H2',
				'M12.6 19.4A2 2 0 1 0 14 16H2',
			),
		),

		'layers'            => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M12 2L2 7l10 5 10-5-10-5z',
				'M2 17l10 5 10-5',
				'M2 12l10 5 10-5',
			),
		),

		'scale'             => array(
			'viewBox' => '0 0 24 24',
			'paths'   => array(
				'M16 16l3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1z',
				'M2 16l3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1z',
				'M7 21h10M12 3v18M12 3a8 8 0 0 0-4 1M12 3a8 8 0 0 1 4 1',
			),
		),

	);

	/**
	 * Render an icon as HTML.
	 *
	 * @param string $name  Icon name.
	 * @param array  $attrs Optional. Override default attributes.
	 * @return void
	 */
	public static function render( string $name, array $attrs = array() ): void {
		echo self::get( $name, $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Get an icon as HTML string.
	 *
	 * @param string $name  Icon name.
	 * @param array  $attrs Optional. Override default attributes.
	 * @return string SVG HTML or empty string if icon not found.
	 */
	public static function get( string $name, array $attrs = array() ): string {
		if ( ! isset( self::ICONS[ $name ] ) ) {
			return '';
		}

		$icon      = self::ICONS[ $name ];
		$svg_attrs = self::build_attributes( $icon['viewBox'], $attrs );

		$inner_html = '';

		// Render paths.
		if ( ! empty( $icon['paths'] ) ) {
			foreach ( $icon['paths'] as $d ) {
				$inner_html .= '<path d="' . esc_attr( $d ) . '"/>';
			}
		}

		// Render circles.
		if ( ! empty( $icon['circles'] ) ) {
			foreach ( $icon['circles'] as $circle ) {
				$fill        = isset( $circle['fill'] ) ? esc_attr( $circle['fill'] ) : null;
				$inner_html .= sprintf(
					'<circle cx="%s" cy="%s" r="%s"%s/>',
					esc_attr( $circle['cx'] ),
					esc_attr( $circle['cy'] ),
					esc_attr( $circle['r'] ),
					$fill ? ' fill="' . $fill . '"' : ''
				);
			}
		}

		// Render lines.
		if ( ! empty( $icon['lines'] ) ) {
			foreach ( $icon['lines'] as $line ) {
				$inner_html .= sprintf(
					'<line x1="%s" y1="%s" x2="%s" y2="%s"/>',
					esc_attr( $line['x1'] ),
					esc_attr( $line['y1'] ),
					esc_attr( $line['x2'] ),
					esc_attr( $line['y2'] )
				);
			}
		}

		// Render rects.
		if ( ! empty( $icon['rects'] ) ) {
			foreach ( $icon['rects'] as $rect ) {
				$inner_html .= sprintf(
					'<rect x="%s" y="%s" width="%s" height="%s"/>',
					esc_attr( $rect['x'] ),
					esc_attr( $rect['y'] ),
					esc_attr( $rect['width'] ),
					esc_attr( $rect['height'] )
				);
			}
		}

		// Render polylines.
		if ( ! empty( $icon['polylines'] ) ) {
			foreach ( $icon['polylines'] as $points ) {
				$inner_html .= '<polyline points="' . esc_attr( $points ) . '"/>';
			}
		}

		return '<svg ' . $svg_attrs . '>' . $inner_html . '</svg>';
	}

	/**
	 * Get a heart icon with dynamic fill binding for Interactivity API.
	 *
	 * This is a special case for the favorite button where the fill
	 * changes based on state.
	 *
	 * @param array  $attrs      Optional. SVG attributes.
	 * @param string $fill_bind  Optional. The data-wp-bind--fill directive value.
	 * @return string SVG HTML.
	 */
	public static function get_heart_interactive( array $attrs = array(), string $fill_bind = "state.isFavorited ? 'currentColor' : 'none'", string $initial_fill = 'none' ): string {
		$svg_attrs = self::build_attributes( '0 0 24 24', $attrs );

		$path_attrs = sprintf(
			'fill="%s" stroke="currentColor" stroke-width="2" data-wp-bind--fill="%s"',
			esc_attr( $initial_fill ),
			esc_attr( $fill_bind )
		);

		$d = self::ICONS['heart']['paths'][0];

		return '<svg ' . $svg_attrs . '><path ' . $path_attrs . ' d="' . esc_attr( $d ) . '"/></svg>';
	}

	/**
	 * Build SVG attribute string.
	 *
	 * @param string $view_box Icon viewBox.
	 * @param array  $attrs   Custom attributes to merge with defaults.
	 * @return string Attribute string.
	 */
	private static function build_attributes( string $view_box, array $attrs ): string {
		$merged            = array_merge( self::DEFAULTS, $attrs );
		$merged['viewBox'] = $view_box;

		// Handle class merging.
		if ( isset( $attrs['class'] ) && ! empty( $attrs['class'] ) ) {
			$merged['class'] = $attrs['class'];
		}

		$attr_str = '';
		foreach ( $merged as $key => $value ) {
			if ( null === $value || '' === $value ) {
				continue;
			}
			$attr_str .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
		}

		return trim( $attr_str );
	}

	/**
	 * Get all available icon names.
	 *
	 * Useful for debugging or building icon pickers.
	 *
	 * @return array List of icon names.
	 */
	public static function get_available_icons(): array {
		return array_keys( self::ICONS );
	}

	/**
	 * Check if an icon exists.
	 *
	 * @param string $name Icon name.
	 * @return bool
	 */
	public static function has_icon( string $name ): bool {
		return isset( self::ICONS[ $name ] );
	}
}
