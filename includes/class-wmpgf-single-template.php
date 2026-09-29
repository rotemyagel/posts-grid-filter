<?php
/**
 * The single Grid Post page. Block themes get a block template built from
 * core blocks, so the page has the theme's own header and footer; classic
 * themes without their own template get templates/single-wmpgf_post.php.
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
	 * The block template's slug and name (the plugin's namespace, then the
	 * slug). Not single-wmpgf_post, the slug the hierarchy looks for by
	 * itself: WordPress 6.7 accepts only letters, digits and hyphens in a
	 * registered template's name, and the post type key has an underscore.
	 * template_hierarchy() adds this slug to the hierarchy instead.
	 */
	const BLOCK_TEMPLATE_SLUG = 'single-wmpgf-post';
	const BLOCK_TEMPLATE      = 'wm-posts-grid-filter//' . self::BLOCK_TEMPLATE_SLUG;

	/**
	 * Hooks registration into WordPress.
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_block_template' ) );
		add_filter( 'single_template_hierarchy', array( $this, 'template_hierarchy' ) );
		add_filter( 'single_template', array( $this, 'template' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * On a block theme, registers the single Grid Post template. A theme's
	 * own single-wmpgf_post.html, or a copy edited in the Site Editor, takes
	 * precedence.
	 */
	public function register_block_template() {
		if ( ! wp_is_block_theme() ) {
			return;
		}

		register_block_template(
			self::BLOCK_TEMPLATE,
			array(
				'title'       => __( 'Single Grid Post', 'wm-posts-grid-filter' ),
				'description' => __( 'Displays a single Grid Post: its cover, title, categories, content and tags.', 'wm-posts-grid-filter' ),
				'content'     => self::block_template_content(),
			)
		);
	}

	/**
	 * Core blocks only, laid out like Twenty Twenty-Five's single post, so
	 * the page takes the theme's styles.
	 *
	 * @return string Block markup.
	 */
	private static function block_template_content() {
		return '<!-- wp:template-part {"slug":"header"} /-->

<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
	<!-- wp:post-featured-image {"aspectRatio":"3/2"} /-->
	<!-- wp:post-title {"level":1} /-->
	<!-- wp:post-terms {"term":"' . WMPGF_Post_Type::TAX_CATEGORY . '"} /-->
	<!-- wp:post-content {"layout":{"type":"constrained"}} /-->
	<!-- wp:post-terms {"term":"' . WMPGF_Post_Type::TAX_TAG . '"} /-->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer"} /-->';
	}

	/**
	 * On a block theme, puts the plugin's template in a single Grid Post's
	 * template hierarchy, right after the theme's own single-wmpgf_post, so
	 * a theme that has one still wins.
	 *
	 * @param string[] $templates Template file names, most specific first.
	 * @return string[]
	 */
	public function template_hierarchy( $templates ) {
		if ( ! is_singular( WMPGF_Post_Type::POST_TYPE ) || ! wp_is_block_theme() ) {
			return $templates;
		}

		$theme_own = array_search( 'single-' . WMPGF_Post_Type::POST_TYPE . '.php', $templates, true );
		array_splice( $templates, false === $theme_own ? 0 : $theme_own + 1, 0, array( self::BLOCK_TEMPLATE_SLUG . '.php' ) );
		return $templates;
	}

	/**
	 * On a classic theme, points single wmpgf_post requests at the plugin's
	 * own PHP template, unless the theme provides a more specific one.
	 * Block themes are left to the block template above: they have no
	 * header.php, so the PHP template's get_header() would fall back to a
	 * deprecated file and print a page without a doctype.
	 *
	 * @param string $template Template path WordPress would otherwise use.
	 * @return string
	 */
	public function template( $template ) {
		if ( ! is_singular( WMPGF_Post_Type::POST_TYPE ) || wp_is_block_theme() ) {
			return $template;
		}

		$theme_template = locate_template( array( 'single-' . WMPGF_Post_Type::POST_TYPE . '.php' ) );
		if ( $theme_template ) {
			return $theme_template;
		}

		return WMPGF_DIR . 'templates/single-wmpgf_post.php';
	}

	/**
	 * Enqueues the PHP template's stylesheet, only on the pages that use it.
	 */
	public function enqueue_assets() {
		if ( ! is_singular( WMPGF_Post_Type::POST_TYPE ) || wp_is_block_theme() ) {
			return;
		}

		$path = WMPGF_DIR . 'assets/css/single.css';

		wp_enqueue_style(
			'wmpgf-single',
			WMPGF_URL . 'assets/css/single.css',
			array( 'wmpgf-tokens' ),
			file_exists( $path ) ? filemtime( $path ) : WMPGF_VERSION
		);
	}
}
