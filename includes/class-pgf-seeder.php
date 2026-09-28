<?php
/**
 * Seeds demo posts, terms, featured images, and a demo page on activation.
 *
 * Images are generated as SVG text, so seeding needs no network access,
 * no bundled binaries, and no PHP image extension.
 *
 * @package PostsGridFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PGF_Seeder
 */
class PGF_Seeder {

	const SEEDED_OPTION    = 'pgf_seeded';
	const DEMO_PAGE_OPTION = 'pgf_demo_page_id';

	/**
	 * What this seeder created, so uninstall.php deletes only that and never
	 * a site owner's own pgf_posts, images, or terms.
	 */
	const SEEDED_POST_IDS_OPTION       = 'pgf_seeded_post_ids';
	const SEEDED_ATTACHMENT_IDS_OPTION = 'pgf_seeded_attachment_ids';
	const SEEDED_TERM_IDS_OPTION       = 'pgf_seeded_term_ids';
	const DEMO_PAGE_OWNED_OPTION       = 'pgf_demo_page_owned';

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
	 * Runs once on plugin activation. Idempotent: a second activation
	 * (e.g. deactivate/reactivate) will not create duplicate content.
	 */
	public function seed() {
		if ( get_option( self::SEEDED_OPTION ) ) {
			return;
		}

		$categories   = $this->create_terms();
		$tags         = $this->create_tags();
		$category_ids = $categories['ids'];
		$tag_ids      = $tags['ids'];

		// Without terms, create_posts() would divide by zero. Not marking the
		// run as seeded lets the next activation retry.
		if ( ! $category_ids || ! $tag_ids ) {
			PGF_Blocks::log( 'Seeding aborted: term creation produced no usable categories or tags, so no demo posts were created. Not marking seeding complete -- a later activation will retry.' );
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
			PGF_Blocks::log(
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
	 * Creates the pgf_category terms, reusing any that already exist.
	 *
	 * @return array{ids: array<string, int>, created_ids: int[]} `ids` maps
	 *              name => term_id for every usable term; `created_ids` holds
	 *              only the ones inserted by this call.
	 */
	private function create_terms() {
		$ids         = array();
		$created_ids = array();

		foreach ( array_keys( $this->categories ) as $name ) {
			$existing = term_exists( $name, PGF_Post_Type::TAX_CATEGORY );
			$term     = $existing;
			if ( ! $term ) {
				$term = wp_insert_term( $name, PGF_Post_Type::TAX_CATEGORY );
			}
			if ( is_wp_error( $term ) ) {
				PGF_Blocks::log( sprintf( 'Failed to create category "%s": %s', $name, $term->get_error_message() ) );
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
	 * Creates the pgf_tag terms, reusing any that already exist.
	 *
	 * @return array{ids: array<string, int>, created_ids: int[]}
	 */
	private function create_tags() {
		$ids         = array();
		$created_ids = array();

		foreach ( $this->tags as $name ) {
			$existing = term_exists( $name, PGF_Post_Type::TAX_TAG );
			$term     = $existing;
			if ( ! $term ) {
				$term = wp_insert_term( $name, PGF_Post_Type::TAX_TAG );
			}
			if ( is_wp_error( $term ) ) {
				PGF_Blocks::log( sprintf( 'Failed to create tag "%s": %s', $name, $term->get_error_message() ) );
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
	 * Inserts the demo posts. Twelve posts across 4 categories, each with
	 * 1-2 categories and 2-3 tags so filter combinations produce different,
	 * meaningful result sets rather than every post matching everything.
	 *
	 * @param array $category_ids Category name => term_id.
	 * @param array $tag_ids      Tag name => term_id.
	 * @return array{post_ids: int[], created_post_ids: int[], attachment_ids: int[]}
	 *              `post_ids` includes adopted posts; the other two hold only
	 *              what this call inserted.
	 */
	private function create_posts( $category_ids, $tag_ids ) {
		$category_names = array_keys( $category_ids );
		$tag_names      = array_keys( $tag_ids );

		$titles = array(
			'The Future of Headless WordPress',
			'A Designer\'s Guide to Design Tokens',
			'Scaling a Small Team Without Losing Culture',
			'What the Interactivity API Changes for Block Authors',
			'Notes on Building a Sustainable Freelance Business',
			'The Quiet Return of Skeuomorphism',
			'Why Editorial Calendars Fail (and What Works Instead)',
			'An Interview with a Block Theme Maintainer',
			'Performance Budgets for Content Teams',
			'Color Systems That Survive a Rebrand',
			'The Economics of Open Source Plugins',
			'Reading the Room: Culture Shifts in Remote Teams',
		);

		$post_ids         = array();
		$created_post_ids = array();
		$attachment_ids   = array();

		foreach ( $titles as $index => $title ) {
			// WP_Query 'title' replaces get_page_by_title(), deprecated in 6.2.
			$existing = new WP_Query(
				array(
					'post_type'              => PGF_Post_Type::POST_TYPE,
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

			$primary_category    = $category_names[ $index % count( $category_names ) ];
			$secondary_index     = ( $index + 1 ) % count( $category_names );
			$assigned_categories = array( $category_ids[ $primary_category ] );
			if ( 0 === $index % 3 ) {
				$assigned_categories[] = $category_ids[ $category_names[ $secondary_index ] ];
			}

			$assigned_tags = array(
				$tag_ids[ $tag_names[ $index % count( $tag_names ) ] ],
				$tag_ids[ $tag_names[ ( $index + 2 ) % count( $tag_names ) ] ],
			);
			if ( 0 === $index % 2 ) {
				$assigned_tags[] = $tag_ids[ $tag_names[ ( $index + 4 ) % count( $tag_names ) ] ];
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => PGF_Post_Type::POST_TYPE,
					'post_title'   => $title,
					'post_status'  => 'publish',
					'post_excerpt' => $this->excerpt_for( $title ),
					'post_content' => $this->content_for( $title ),
				)
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				$reason = is_wp_error( $post_id ) ? $post_id->get_error_message() : 'wp_insert_post() returned no ID';
				PGF_Blocks::log( sprintf( 'Failed to create seed post "%s": %s', $title, $reason ) );
				continue;
			}

			$category_result = wp_set_object_terms( $post_id, $assigned_categories, PGF_Post_Type::TAX_CATEGORY );
			if ( is_wp_error( $category_result ) ) {
				PGF_Blocks::log( sprintf( 'Failed to assign categories to seed post "%s" (post ID %d): %s', $title, $post_id, $category_result->get_error_message() ) );
			}

			$tag_result = wp_set_object_terms( $post_id, $assigned_tags, PGF_Post_Type::TAX_TAG );
			if ( is_wp_error( $tag_result ) ) {
				PGF_Blocks::log( sprintf( 'Failed to assign tags to seed post "%s" (post ID %d): %s', $title, $post_id, $tag_result->get_error_message() ) );
			}

			$attachment_id = $this->create_placeholder_image( $post_id, $primary_category, $index );
			if ( $attachment_id ) {
				set_post_thumbnail( $post_id, $attachment_id );
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_strip_all_tags( $title ) );
				$attachment_ids[] = $attachment_id;
			} else {
				PGF_Blocks::log( sprintf( 'No featured image generated for seed post "%s" (post ID %d) -- see prior log line for the reason.', $title, $post_id ) );
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
	 * Builds a short excerpt from the title.
	 *
	 * @param string $title Post title.
	 * @return string
	 */
	private function excerpt_for( $title ) {
		return sprintf(
			/* translators: %s: post title */
			__( 'A closer look at %s and what it means for teams working with WordPress today.', 'wm-posts-grid-filter' ),
			lcfirst( $title )
		);
	}

	/**
	 * Builds simple demo body content.
	 *
	 * @param string $title Post title.
	 * @return string
	 */
	private function content_for( $title ) {
		return '<!-- wp:paragraph --><p>' . esc_html( $this->excerpt_for( $title ) ) . '</p><!-- /wp:paragraph -->';
	}

	/**
	 * Writes a solid-color placeholder SVG and registers it as an attachment.
	 *
	 * @param int    $post_id  Parent post ID.
	 * @param string $category Category name, used to pick a color.
	 * @param int    $index    Post index, mixed into the label.
	 * @return int Attachment ID, or 0 on failure.
	 */
	private function create_placeholder_image( $post_id, $category, $index ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		list( $r, $g, $b ) = isset( $this->categories[ $category ] ) ? $this->categories[ $category ] : array( 100, 100, 100 );

		$width  = 1200;
		$height = 800;
		$label  = esc_html( $category . ' #' . ( $index + 1 ) );

		$svg = sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d" role="img" aria-hidden="true"><rect width="100%%" height="100%%" fill="rgb(%3$d,%4$d,%5$d)"/><text x="24" y="%6$d" font-family="sans-serif" font-size="36" fill="#ffffff">%7$s</text></svg>',
			$width,
			$height,
			$r,
			$g,
			$b,
			$height - 32,
			$label
		);

		$upload_dir = wp_upload_dir();
		$filename   = 'pgf-cover-' . $post_id . '.svg';
		$file_path  = trailingslashit( $upload_dir['path'] ) . $filename;

		if ( false === file_put_contents( $file_path, $svg ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			PGF_Blocks::log( sprintf( 'Could not write placeholder SVG to "%s".', $file_path ) );
			return 0;
		}

		$attachment = array(
			'post_mime_type' => 'image/svg+xml',
			'post_title'     => $category . ' cover image',
			'post_status'    => 'inherit',
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
			PGF_Blocks::log( sprintf( 'wp_insert_attachment() failed for post %d: %s', $post_id, $attachment_id->get_error_message() ) );
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

		$content = '<!-- wp:heading --><h2>' . esc_html__( 'Browse the grid', 'wm-posts-grid-filter' ) . "</h2><!-- /wp:heading -->\n\n" .
			"<!-- wp:pgf/posts-filter /-->\n\n" .
			"<!-- wp:pgf/posts-grid {\"columns\":3,\"postsPerPage\":6} -->\n" .
			"<!-- wp:pgf/pagination /-->\n" .
			'<!-- /wp:pgf/posts-grid -->';

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_title'   => __( 'Posts Grid + Filter Demo', 'wm-posts-grid-filter' ),
				'post_name'    => 'posts-grid-filter-demo',
				'post_status'  => 'publish',
				'post_content' => $content,
			)
		);

		if ( ! is_wp_error( $page_id ) && $page_id ) {
			update_option( self::DEMO_PAGE_OPTION, $page_id );
			update_option( self::DEMO_PAGE_OWNED_OPTION, true );
		} else {
			$reason = is_wp_error( $page_id ) ? $page_id->get_error_message() : 'wp_insert_post() returned no ID';
			PGF_Blocks::log( 'Failed to create the demo page: ' . $reason );
		}
	}
}
