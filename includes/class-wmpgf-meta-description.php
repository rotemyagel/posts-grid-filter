<?php
/**
 * A meta description on single Grid Post pages, from the post's excerpt.
 *
 * @package WMPGF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WMPGF_Meta_Description
 *
 * Only on the plugin's own post type: what a site's other pages say in
 * <head> is the job of the theme or an SEO plugin. If anything else prints
 * a description on the page (an SEO plugin, the theme), this prints none,
 * so there is never a second one.
 */
class WMPGF_Meta_Description {

	/**
	 * Most search results show about this many characters.
	 */
	const MAX_LENGTH = 160;

	/**
	 * Output buffer level before this class's own buffer, while wp_head runs.
	 *
	 * @var int|null
	 */
	private $buffer_level;

	/**
	 * Hooks registration into WordPress.
	 */
	public function init() {
		// First and last on wp_head, so everything printed in between is seen.
		add_action( 'wp_head', array( $this, 'start' ), PHP_INT_MIN );
		add_action( 'wp_head', array( $this, 'finish' ), PHP_INT_MAX );
	}

	/**
	 * Starts collecting what wp_head prints, on single Grid Posts only.
	 */
	public function start() {
		if ( ! is_singular( WMPGF_Post_Type::POST_TYPE ) ) {
			return;
		}
		$this->buffer_level = ob_get_level();
		ob_start();
	}

	/**
	 * Prints what wp_head printed, then a description if it had none.
	 */
	public function finish() {
		if ( null === $this->buffer_level ) {
			return;
		}
		$level              = $this->buffer_level;
		$this->buffer_level = null;

		// Another plugin opened a buffer in wp_head and hasn't closed it:
		// closing ours now would take theirs. Leave both, add nothing.
		if ( ob_get_level() !== $level + 1 ) {
			return;
		}

		$head = ob_get_clean();
		echo $head; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_head's own output, unchanged.

		if ( preg_match( '/<meta\s[^>]*name\s*=\s*["\']?description["\'\s\/>]/i', $head ) ) {
			return;
		}
		$description = self::description( get_queried_object_id() );
		if ( '' !== $description ) {
			printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $description ) );
		}
	}

	/**
	 * The post's excerpt as plain text, or else the start of its content,
	 * cut at a word to at most MAX_LENGTH characters.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function description( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || post_password_required( $post ) ) {
			return '';
		}

		$text = '' !== trim( $post->post_excerpt ) ? $post->post_excerpt : excerpt_remove_blocks( strip_shortcodes( $post->post_content ) );
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) );

		if ( mb_strlen( $text ) > self::MAX_LENGTH ) {
			$cut = mb_substr( $text, 0, self::MAX_LENGTH - 1 );
			// Drop a word cut in half.
			if ( ' ' !== mb_substr( $text, self::MAX_LENGTH - 1, 1 ) ) {
				$cut = preg_replace( '/\s+\S*$/u', '', $cut );
			}
			$text = rtrim( $cut ) . '…';
		}
		return $text;
	}
}
