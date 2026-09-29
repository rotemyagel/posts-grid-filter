<?php
/**
 * How soon the grid's card images load, on top of the page's own image
 * optimisation.
 *
 * WordPress core, or a plugin that replaces its logic (Elementor's
 * optimised image loading, for one), decides each image in the page: the
 * first few eager, the first of them fetchpriority="high", the rest lazy.
 * That decision counts every image on the page, so the grid follows it for
 * its first image and adjusts only its own first two rows:
 *
 * - When the first image is eager (the grid starts near the top), the rest
 *   of the first row is eager too. Otherwise a 4-column row has its 4th
 *   image lazy though it's in view.
 * - The second row then starts loading right away, at low priority. It's
 *   in view on most laptop and desktop screens, and a lazy image can't
 *   start until the page's CSS has loaded (measured: 2.3s later on Fast
 *   3G). On phones it's below the fold but mostly within the distance
 *   Chrome loads lazy images from anyway, and low priority keeps it from
 *   delaying the first image.
 * - When the first image is lazy (the grid starts further down), nothing
 *   changes.
 *
 * @package WMPGF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WMPGF_Image_Loading
 */
class WMPGF_Image_Loading {

	/**
	 * Marks a card image with its place: first, first-row or second-row.
	 */
	const ATTRIBUTE = 'data-wmpgf-load';

	/**
	 * Whether the grid being processed starts near the top of the page, as
	 * decided for its first image. Images are processed in page order.
	 *
	 * @var bool
	 */
	private static $near_top = false;

	/**
	 * Hooks in after the page's optimiser: core adds its attributes before
	 * wp_content_img_tag runs, and plugins that replace it filter at 10.
	 */
	public function init() {
		add_filter( 'wp_content_img_tag', array( __CLASS__, 'filter_content_image' ), 20 );
		add_filter( 'wp_get_loading_optimization_attributes', array( __CLASS__, 'filter_attributes' ), 20, 3 );
	}

	/**
	 * The role of a card image, for its ATTRIBUTE.
	 *
	 * @param int $index   Card index on the page, from 0.
	 * @param int $columns Grid columns.
	 * @return string '' for images after the second row.
	 */
	public static function role( $index, $columns ) {
		if ( 0 === $index ) {
			return 'first';
		}
		if ( $index < $columns ) {
			return 'first-row';
		}
		return $index < 2 * $columns ? 'second-row' : '';
	}

	/**
	 * Images in post content: adjusted after the optimiser has added its
	 * attributes to the finished tag.
	 *
	 * @param string $image The img tag.
	 * @return string
	 */
	public static function filter_content_image( $image ) {
		if ( false === strpos( $image, self::ATTRIBUTE ) || ! preg_match( '/ ' . self::ATTRIBUTE . '="([a-z-]+)"/', $image, $role ) ) {
			return $image;
		}

		$lazy   = '/ loading=["\']lazy["\']/';
		$change = self::decide( $role[1], (bool) preg_match( $lazy, $image ) );
		if ( $change ) {
			$image = preg_replace( $lazy, '', $image );
			if ( 'low' === $change && ! preg_match( '/ fetchpriority=/', $image ) ) {
				$image = str_replace( '<img', '<img fetchpriority="low"', $image );
			}
		}
		return $image;
	}

	/**
	 * Images outside post content (a grid placed straight in a template):
	 * core decides these as the image is built. Inside post content it
	 * skips them and decides later, which filter_content_image() follows.
	 *
	 * @param array  $loading_attributes Attributes the optimiser chose.
	 * @param string $tag_name           Tag name.
	 * @param array  $attr               The image's attributes.
	 * @return array
	 */
	public static function filter_attributes( $loading_attributes, $tag_name, $attr ) {
		if (
			empty( $attr[ self::ATTRIBUTE ] ) ||
			doing_filter( 'the_content' ) ||
			doing_filter( 'widget_text_content' ) ||
			doing_filter( 'widget_block_content' )
		) {
			return $loading_attributes;
		}

		$lazy   = isset( $loading_attributes['loading'] ) && 'lazy' === $loading_attributes['loading'];
		$change = self::decide( $attr[ self::ATTRIBUTE ], $lazy );
		if ( $change ) {
			unset( $loading_attributes['loading'] );
			if ( 'low' === $change && empty( $attr['fetchpriority'] ) ) {
				$loading_attributes['fetchpriority'] = 'low';
			}
		}
		return $loading_attributes;
	}

	/**
	 * What to change for one image, given the optimiser's decision.
	 *
	 * @param string $role Image role.
	 * @param bool   $lazy Whether the optimiser made it lazy.
	 * @return string|null 'eager', 'low', or null to keep the decision.
	 */
	private static function decide( $role, $lazy ) {
		if ( 'first' === $role ) {
			self::$near_top = ! $lazy;
			return null;
		}
		if ( ! self::$near_top ) {
			return null;
		}
		if ( 'first-row' === $role ) {
			return $lazy ? 'eager' : null;
		}
		return 'second-row' === $role ? 'low' : null;
	}
}
