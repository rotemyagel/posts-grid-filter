<?php
/**
 * Seeds demo posts, terms, featured images, and a demo page on activation.
 *
 * Images are generated as SVG text, so seeding needs no network access,
 * no bundled binaries, and no PHP image extension.
 *
 * @package WMPGF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WMPGF_Seeder
 */
class WMPGF_Seeder {

	const SEEDED_OPTION    = 'wmpgf_seeded';
	const DEMO_PAGE_OPTION = 'wmpgf_demo_page_id';

	/**
	 * What this seeder created, so uninstall.php deletes only that and never
	 * a site owner's own wmpgf_posts, images, or terms.
	 */
	const SEEDED_POST_IDS_OPTION       = 'wmpgf_seeded_post_ids';
	const SEEDED_ATTACHMENT_IDS_OPTION = 'wmpgf_seeded_attachment_ids';
	const SEEDED_TERM_IDS_OPTION       = 'wmpgf_seeded_term_ids';
	const DEMO_PAGE_OWNED_OPTION       = 'wmpgf_demo_page_owned';

	/**
	 * Category => color map used both as term data and as the seeded
	 * placeholder image background, so each category is visually distinct.
	 *
	 * @var array
	 */
	private $categories = array(
		'Technology' => array( 79, 70, 229 ),
		'Design'     => array( 219, 39, 119 ),
		'Business'   => array( 5, 150, 105 ),
		'Culture'    => array( 217, 119, 6 ),
	);

	/**
	 * Flat tag list assigned across the seeded posts.
	 *
	 * @var string[]
	 */
	private $tags = array( 'Guide', 'Opinion', 'News', 'Interview', 'Deep Dive', 'Trends' );

	/**
	 * Author for everything seeded.
	 *
	 * @var int
	 */
	private $author_id = 0;

	/**
	 * Runs once on plugin activation. Idempotent: a second activation
	 * (e.g. deactivate/reactivate) will not create duplicate content.
	 */
	public function seed() {
		if ( get_option( self::SEEDED_OPTION ) ) {
			return;
		}

		$this->author_id = $this->default_author_id();

		$categories   = $this->create_terms();
		$tags         = $this->create_tags();
		$category_ids = $categories['ids'];
		$tag_ids      = $tags['ids'];

		// Without terms, create_posts() would divide by zero. Not marking the
		// run as seeded lets the next activation retry.
		if ( ! $category_ids || ! $tag_ids ) {
			self::log( 'Seeding aborted: term creation produced no usable categories or tags, so no demo posts were created. Not marking seeding complete -- a later activation will retry.' );
			return;
		}

		$result         = $this->create_posts( $category_ids, $tag_ids );
		$post_ids       = $result['post_ids'];
		$attachment_ids = $result['attachment_ids'];

		$this->create_demo_page();

		update_option( self::SEEDED_OPTION, true );

		// Created IDs only: adopted pre-existing posts/terms aren't ours to delete.
		update_option( self::SEEDED_POST_IDS_OPTION, $result['created_post_ids'] );
		update_option( self::SEEDED_ATTACHMENT_IDS_OPTION, $attachment_ids );
		update_option(
			self::SEEDED_TERM_IDS_OPTION,
			array_merge( $categories['created_ids'], $tags['created_ids'] )
		);

		if ( count( $category_ids ) < count( $this->categories )
			|| count( $tag_ids ) < count( $this->tags )
			|| count( $post_ids ) < 12
		) {
			self::log(
				sprintf(
					'Seeding finished with fewer items than expected: %d/%d categories, %d/%d tags, %d/12 posts. See prior log lines for individual failures.',
					count( $category_ids ),
					count( $this->categories ),
					count( $tag_ids ),
					count( $this->tags ),
					count( $post_ids )
				)
			);
		}
	}

	/**
	 * Creates the wmpgf_category terms, reusing any that already exist.
	 *
	 * @return array{ids: array<string, int>, created_ids: int[]} `ids` maps
	 *              name => term_id for every usable term; `created_ids` holds
	 *              only the ones inserted by this call.
	 */
	private function create_terms() {
		$ids         = array();
		$created_ids = array();

		foreach ( array_keys( $this->categories ) as $name ) {
			$existing = term_exists( $name, WMPGF_Post_Type::TAX_CATEGORY );
			$term     = $existing;
			if ( ! $term ) {
				$term = wp_insert_term( $name, WMPGF_Post_Type::TAX_CATEGORY );
			}
			if ( is_wp_error( $term ) ) {
				self::log( sprintf( 'Failed to create category "%s": %s', $name, $term->get_error_message() ) );
				continue;
			}
			$ids[ $name ] = (int) $term['term_id'];
			if ( ! $existing ) {
				$created_ids[] = (int) $term['term_id'];
			}
		}

		return array(
			'ids'         => $ids,
			'created_ids' => $created_ids,
		);
	}

	/**
	 * Creates the wmpgf_tag terms, reusing any that already exist.
	 *
	 * @return array{ids: array<string, int>, created_ids: int[]}
	 */
	private function create_tags() {
		$ids         = array();
		$created_ids = array();

		foreach ( $this->tags as $name ) {
			$existing = term_exists( $name, WMPGF_Post_Type::TAX_TAG );
			$term     = $existing;
			if ( ! $term ) {
				$term = wp_insert_term( $name, WMPGF_Post_Type::TAX_TAG );
			}
			if ( is_wp_error( $term ) ) {
				self::log( sprintf( 'Failed to create tag "%s": %s', $name, $term->get_error_message() ) );
				continue;
			}
			$ids[ $name ] = (int) $term['term_id'];
			if ( ! $existing ) {
				$created_ids[] = (int) $term['term_id'];
			}
		}

		return array(
			'ids'         => $ids,
			'created_ids' => $created_ids,
		);
	}

	/**
	 * Inserts the demo posts from includes/demo-content.php.
	 *
	 * @param array $category_ids Category name => term_id.
	 * @param array $tag_ids      Tag name => term_id.
	 * @return array{post_ids: int[], created_post_ids: int[], attachment_ids: int[]}
	 *              `post_ids` includes adopted posts; the other two hold only
	 *              what this call inserted.
	 */
	private function create_posts( $category_ids, $tag_ids ) {
		$demo_posts = require WMPGF_DIR . 'includes/demo-content.php';

		$post_ids         = array();
		$created_post_ids = array();
		$attachment_ids   = array();

		foreach ( $demo_posts as $index => $demo_post ) {
			$title = $demo_post['title'];

			// WP_Query 'title' replaces get_page_by_title(), deprecated in 6.2.
			$existing = new WP_Query(
				array(
					'post_type'              => WMPGF_Post_Type::POST_TYPE,
					'title'                  => $title,
					'post_status'            => 'any',
					'posts_per_page'         => 1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			if ( $existing->have_posts() ) {
				$post_ids[] = (int) $existing->posts[0];
				continue;
			}

			// In the listed order, primary category first; the category
			// taxonomy is sorted, so WordPress keeps that order.
			$assigned_categories = array();
			foreach ( $demo_post['categories'] as $name ) {
				if ( isset( $category_ids[ $name ] ) ) {
					$assigned_categories[] = $category_ids[ $name ];
				}
			}
			$assigned_tags = array();
			foreach ( $demo_post['tags'] as $name ) {
				if ( isset( $tag_ids[ $name ] ) ) {
					$assigned_tags[] = $tag_ids[ $name ];
				}
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => WMPGF_Post_Type::POST_TYPE,
					'post_title'   => $title,
					'post_status'  => 'publish',
					'post_author'  => $this->author_id,
					'post_excerpt' => $demo_post['excerpt'],
					'post_content' => $this->content_for( $demo_post['body'] ),
				)
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				$reason = is_wp_error( $post_id ) ? $post_id->get_error_message() : 'wp_insert_post() returned no ID';
				self::log( sprintf( 'Failed to create seed post "%s": %s', $title, $reason ) );
				continue;
			}

			$category_result = wp_set_object_terms( $post_id, $assigned_categories, WMPGF_Post_Type::TAX_CATEGORY );
			if ( is_wp_error( $category_result ) ) {
				self::log( sprintf( 'Failed to assign categories to seed post "%s" (post ID %d): %s', $title, $post_id, $category_result->get_error_message() ) );
			}

			$tag_result = wp_set_object_terms( $post_id, $assigned_tags, WMPGF_Post_Type::TAX_TAG );
			if ( is_wp_error( $tag_result ) ) {
				self::log( sprintf( 'Failed to assign tags to seed post "%s" (post ID %d): %s', $title, $post_id, $tag_result->get_error_message() ) );
			}

			$attachment_id = $this->create_cover_image( $post_id, $demo_post['categories'][0], $index );
			if ( $attachment_id ) {
				set_post_thumbnail( $post_id, $attachment_id );
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_strip_all_tags( $title ) );
				$attachment_ids[] = $attachment_id;
			} else {
				self::log( sprintf( 'No featured image generated for seed post "%s" (post ID %d) -- see prior log line for the reason.', $title, $post_id ) );
			}

			$post_ids[]         = $post_id;
			$created_post_ids[] = $post_id;
		}

		return array(
			'post_ids'         => $post_ids,
			'created_post_ids' => $created_post_ids,
			'attachment_ids'   => $attachment_ids,
		);
	}

	/**
	 * Logs seeding failures, only when WP_DEBUG_LOG is enabled.
	 *
	 * @param string $message Message to log.
	 */
	private static function log( $message ) {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[Posts Grid + Filter] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * The activating user, or the first administrator when there is none
	 * (e.g. activation via WP-CLI), so seeded content never has no author.
	 *
	 * @return int User ID, or 0 if the site has no administrator.
	 */
	private function default_author_id() {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			return $user_id;
		}

		$admins = get_users(
			array(
				'role'    => 'administrator',
				'orderby' => 'ID',
				'order'   => 'ASC',
				'number'  => 1,
				'fields'  => 'ID',
			)
		);

		return $admins ? (int) $admins[0] : 0;
	}

	/**
	 * Post content as paragraph blocks.
	 *
	 * @param string[] $paragraphs Plain-text paragraphs.
	 * @return string
	 */
	private function content_for( array $paragraphs ) {
		$blocks = array();
		foreach ( $paragraphs as $paragraph ) {
			$blocks[] = '<!-- wp:paragraph --><p>' . esc_html( $paragraph ) . '</p><!-- /wp:paragraph -->';
		}

		return implode( "\n\n", $blocks );
	}

	/**
	 * Writes the post's cover SVG and registers it as an attachment.
	 *
	 * @param int    $post_id  Parent post ID.
	 * @param string $category Primary category name, used to pick the colours.
	 * @param int    $index    Post index, seeds the pattern.
	 * @return int Attachment ID, or 0 on failure.
	 */
	private function create_cover_image( $post_id, $category, $index ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$width  = 1200;
		$height = 800;
		$svg    = $this->cover_svg(
			isset( $this->categories[ $category ] ) ? $this->categories[ $category ] : array( 100, 100, 100 ),
			$index,
			$width,
			$height
		);

		$upload_dir = wp_upload_dir();
		$filename   = 'wmpgf-cover-' . $post_id . '.svg';
		$file_path  = trailingslashit( $upload_dir['path'] ) . $filename;

		if ( false === file_put_contents( $file_path, $svg ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			self::log( sprintf( 'Could not write cover SVG to "%s".', $file_path ) );
			return 0;
		}

		$attachment = array(
			'post_mime_type' => 'image/svg+xml',
			'post_title'     => $category . ' cover image',
			'post_status'    => 'inherit',
			'post_author'    => $this->author_id,
		);

		// SVG is blocked by default (it can carry scripts). Allowed only for
		// this one insert of a file we just wrote, then removed again.
		$allow_svg_mime = static function ( $mimes ) {
			$mimes['svg'] = 'image/svg+xml';
			return $mimes;
		};
		add_filter( 'upload_mimes', $allow_svg_mime );
		$attachment_id = wp_insert_attachment( $attachment, $file_path, $post_id );
		remove_filter( 'upload_mimes', $allow_svg_mime );

		if ( is_wp_error( $attachment_id ) ) {
			self::log( sprintf( 'wp_insert_attachment() failed for post %d: %s', $post_id, $attachment_id->get_error_message() ) );
			return 0;
		}

		// Image editors can't read SVG dimensions, so they're set directly.
		// No 'sizes': the SVG scales, and core reports 'medium' at the same 3:2.
		wp_update_attachment_metadata(
			$attachment_id,
			array(
				'width'  => $width,
				'height' => $height,
				'file'   => _wp_relative_upload_path( $file_path ),
				'sizes'  => array(),
			)
		);

		return $attachment_id;
	}

	/**
	 * A geometric cover: a 6x4 grid of tiles, each with a quarter circle,
	 * half circle, circle, triangle or nothing, in tints of the category
	 * colour. A small seeded generator picks the shapes, so the same post
	 * always gets the same picture.
	 *
	 * @param int[] $rgb    Category colour.
	 * @param int   $seed   Post index.
	 * @param int   $width  Canvas width.
	 * @param int   $height Canvas height.
	 * @return string SVG markup.
	 */
	private function cover_svg( array $rgb, $seed, $width, $height ) {
		$state  = ( (int) $seed + 1 ) * 7919;
		$random = static function ( $max ) use ( &$state ) {
			$state = ( $state * 1103515245 + 12345 ) & 0x7fffffff;
			return $state % $max;
		};
		$mix    = static function ( array $target, $amount ) use ( $rgb ) {
			$channels = array();
			foreach ( $rgb as $i => $channel ) {
				$channels[] = (int) round( $channel + ( $target[ $i ] - $channel ) * $amount );
			}
			return vsprintf( 'rgb(%d,%d,%d)', $channels );
		};

		$palette = array(
			$mix( array( 255, 255, 255 ), 0.85 ),
			$mix( array( 255, 255, 255 ), 0.5 ),
			$mix( array( 255, 255, 255 ), 0 ),
			$mix( array( 0, 0, 0 ), 0.35 ),
		);
		$size    = 200;
		$shapes  = '';

		$rows = intdiv( $height, $size );
		$cols = intdiv( $width, $size );
		for ( $row = 0; $row < $rows; $row++ ) {
			for ( $col = 0; $col < $cols; $col++ ) {
				$x          = $col * $size;
				$y          = $row * $size;
				$background = $random( 4 );
				$fill       = $palette[ ( $background + 1 + $random( 3 ) ) % 4 ];
				$shapes    .= sprintf( '<rect x="%d" y="%d" width="%d" height="%d" fill="%s"/>', $x, $y, $size, $size, $palette[ $background ] );

				$r = $size / 2;
				$s = $size;
				switch ( $random( 6 ) ) {
					case 0: // Quarter circle, centred on one corner.
						$corners = array(
							"M$x $y L" . ( $x + $s ) . " $y A$s $s 0 0 1 $x " . ( $y + $s ) . 'Z',
							'M' . ( $x + $s ) . " $y L" . ( $x + $s ) . ' ' . ( $y + $s ) . " A$s $s 0 0 1 $x {$y}Z",
							'M' . ( $x + $s ) . ' ' . ( $y + $s ) . " L$x " . ( $y + $s ) . " A$s $s 0 0 1 " . ( $x + $s ) . " {$y}Z",
							"M$x " . ( $y + $s ) . " L$x $y A$s $s 0 0 1 " . ( $x + $s ) . ' ' . ( $y + $s ) . 'Z',
						);
						$shapes .= sprintf( '<path d="%s" fill="%s"/>', $corners[ $random( 4 ) ], $fill );
						break;
					case 1: // Half circle on one side, bulging inwards.
						$sides   = array(
							"M$x $y A$r $r 0 0 0 " . ( $x + $s ) . " {$y}Z",
							"M$x " . ( $y + $s ) . " A$r $r 0 0 1 " . ( $x + $s ) . ' ' . ( $y + $s ) . 'Z',
							"M$x $y A$r $r 0 0 1 $x " . ( $y + $s ) . 'Z',
							'M' . ( $x + $s ) . " $y A$r $r 0 0 0 " . ( $x + $s ) . ' ' . ( $y + $s ) . 'Z',
						);
						$shapes .= sprintf( '<path d="%s" fill="%s"/>', $sides[ $random( 4 ) ], $fill );
						break;
					case 2: // Large circle.
					case 3: // Small circle.
						$shapes .= sprintf( '<circle cx="%d" cy="%d" r="%d" fill="%s"/>', $x + $r, $y + $r, 2 === $random( 3 ) ? $s * 0.18 : $s * 0.38, $fill );
						break;
					case 4: // Triangle across one diagonal.
						$triangles = array(
							array( $x, $y, $x + $s, $y, $x, $y + $s ),
							array( $x + $s, $y, $x + $s, $y + $s, $x, $y ),
							array( $x + $s, $y + $s, $x, $y + $s, $x + $s, $y ),
							array( $x, $y + $s, $x, $y, $x + $s, $y + $s ),
						);
						$shapes   .= sprintf( '<polygon points="%s" fill="%s"/>', vsprintf( '%d,%d %d,%d %d,%d', $triangles[ $random( 4 ) ] ), $fill );
						break;
					default: // Leave the tile plain.
						break;
				}
			}
		}

		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d">%3$s</svg>',
			$width,
			$height,
			$shapes
		);
	}

	/**
	 * Creates the demo page with both blocks placed, or adopts an existing
	 * page at that slug. Only a page created here is flagged as owned, so
	 * uninstall.php never deletes an adopted one.
	 */
	private function create_demo_page() {
		if ( get_option( self::DEMO_PAGE_OPTION ) ) {
			return;
		}

		$existing = get_page_by_path( 'posts-grid-filter-demo' );
		if ( $existing ) {
			update_option( self::DEMO_PAGE_OPTION, $existing->ID );
			return;
		}

		$content = "<!-- wp:wmpgf/posts-filter {\"align\":\"wide\"} /-->\n\n" .
			"<!-- wp:wmpgf/posts-grid {\"columns\":3,\"postsPerPage\":6,\"align\":\"wide\"} -->\n" .
			"<!-- wp:wmpgf/pagination /-->\n" .
			'<!-- /wp:wmpgf/posts-grid -->';

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_title'   => __( 'Posts Grid + Filter Demo', 'wm-posts-grid-filter' ),
				'post_name'    => 'posts-grid-filter-demo',
				'post_status'  => 'publish',
				'post_author'  => $this->author_id,
				'post_content' => $content,
			)
		);

		if ( ! is_wp_error( $page_id ) && $page_id ) {
			update_option( self::DEMO_PAGE_OPTION, $page_id );
			update_option( self::DEMO_PAGE_OWNED_OPTION, true );
		} else {
			$reason = is_wp_error( $page_id ) ? $page_id->get_error_message() : 'wp_insert_post() returned no ID';
			self::log( 'Failed to create the demo page: ' . $reason );
		}
	}
}
