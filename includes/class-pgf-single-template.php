<?php
/**
 * Single-post template for pgf_post, used only when the active theme has
 * none (many themes render just the_content() for an unknown post type).
 *
 * @package PostsGridFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PGF_Single_Template
 */
class PGF_Single_Template {

	/**
	 * Hooks registration into WordPress.
	 */
	public function init() {
		add_filter( 'single_template', array( $this, 'template' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Points single pgf_post requests at the plugin's own template, unless
	 * the active theme already provides a more specific one.
	 *
	 * @param string $template Template path WordPress would otherwise use.
	 * @return string
	 */
	public function template( $template ) {
		if ( ! is_singular( PGF_Post_Type::POST_TYPE ) ) {
			return $template;
		}

		$theme_template = locate_template( array( 'single-' . PGF_Post_Type::POST_TYPE . '.php' ) );
		if ( $theme_template ) {
			return $theme_template;
		}

		return PGF_DIR . 'templates/single-pgf_post.php';
	}

	/**
	 * Enqueues the single-post stylesheet, only on the pages that use it.
	 */
	public function enqueue_assets() {
		if ( ! is_singular( PGF_Post_Type::POST_TYPE ) ) {
			return;
		}

		$path = PGF_DIR . 'assets/css/single.css';

		wp_enqueue_style(
			'pgf-single',
			PGF_URL . 'assets/css/single.css',
			array(),
			file_exists( $path ) ? filemtime( $path ) : PGF_VERSION
		);
	}
}
