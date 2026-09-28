<?php
/**
 * Single-post template for wmpgf_post, used only when the active theme has
 * none (many themes render just the_content() for an unknown post type).
 *
 * @package WMPGF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WMPGF_Single_Template
 */
class WMPGF_Single_Template {

	/**
	 * Hooks registration into WordPress.
	 */
	public function init() {
		add_filter( 'single_template', array( $this, 'template' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Points single wmpgf_post requests at the plugin's own template, unless
	 * the active theme already provides a more specific one.
	 *
	 * @param string $template Template path WordPress would otherwise use.
	 * @return string
	 */
	public function template( $template ) {
		if ( ! is_singular( WMPGF_Post_Type::POST_TYPE ) ) {
			return $template;
		}

		$theme_template = locate_template( array( 'single-' . WMPGF_Post_Type::POST_TYPE . '.php' ) );
		if ( $theme_template ) {
			return $theme_template;
		}

		return WMPGF_DIR . 'templates/single-wmpgf_post.php';
	}

	/**
	 * Enqueues the single-post stylesheet, only on the pages that use it.
	 */
	public function enqueue_assets() {
		if ( ! is_singular( WMPGF_Post_Type::POST_TYPE ) ) {
			return;
		}

		$path = WMPGF_DIR . 'assets/css/single.css';

		wp_enqueue_style(
			'wmpgf-single',
			WMPGF_URL . 'assets/css/single.css',
			array(),
			file_exists( $path ) ? filemtime( $path ) : WMPGF_VERSION
		);
	}
}
