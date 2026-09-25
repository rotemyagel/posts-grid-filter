<?php
/**
 * Seeds demo content on activation so the plugin is usable with zero
 * manual setup: posts, terms, featured images, and a demo page carrying
 * both blocks.
 *
 * Featured images are generated on the fly with GD rather than bundled as
 * binary assets, so seeding needs no network access and no extra files to
 * ship with the plugin.
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

		$category_ids = $this->create_terms();
		$tag_ids      = $this->create_tags();
		$post_ids     = $this->create_posts( $category_ids, $tag_ids );
		$this->create_demo_page();

		update_option( self::SEEDED_OPTION, true );

		unset( $post_ids );
	}

	/**
	 * Creates the pgf_category terms, keyed by name.
	 *
	 * @return array<string, int> Category name => term_id.
	 */
	private function create_terms() {
		$ids = array();

		foreach ( array_keys( $this->categories ) as $name ) {
			$term = term_exists( $name, PGF_Post_Type::TAX_CATEGORY );
			if ( ! $term ) {
				$term = wp_insert_term( $name, PGF_Post_Type::TAX_CATEGORY );
			}
			if ( ! is_wp_error( $term ) ) {
				$ids[ $name ] = (int) $term['term_id'];
			}
		}

		return $ids;
	}

	/**
	 * Creates the pgf_tag terms.
	 *
	 * @return array<string, int> Tag name => term_id.
	 */
	private function create_tags() {
		$ids = array();

		foreach ( $this->tags as $name ) {
			$term = term_exists( $name, PGF_Post_Type::TAX_TAG );
			if ( ! $term ) {
				$term = wp_insert_term( $name, PGF_Post_Type::TAX_TAG );
			}
			if ( ! is_wp_error( $term ) ) {
				$ids[ $name ] = (int) $term['term_id'];
			}
		}

		return $ids;
	}

	/**
	 * Inserts the demo posts. Twelve posts across 4 categories, each with
	 * 1-2 categories and 2-3 tags so filter combinations produce different,
	 * meaningful result sets rather than every post matching everything.
	 *
	 * @param array $category_ids Category name => term_id.
	 * @param array $tag_ids      Tag name => term_id.
	 * @return int[] Inserted post IDs.
	 */
	private function create_posts( $category_ids, $tag_ids ) {
		$category_names = array_keys( $category_ids );
		$tag_names       = array_keys( $tag_ids );

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

		$post_ids = array();

		foreach ( $titles as $index => $title ) {
			$existing = get_page_by_title( $title, OBJECT, PGF_Post_Type::POST_TYPE );
			if ( $existing ) {
				$post_ids[] = $existing->ID;
				continue;
			}

			$primary_category = $category_names[ $index % count( $category_names ) ];
			$secondary_index   = ( $index + 1 ) % count( $category_names );
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
				continue;
			}

			wp_set_object_terms( $post_id, $assigned_categories, PGF_Post_Type::TAX_CATEGORY );
			wp_set_object_terms( $post_id, $assigned_tags, PGF_Post_Type::TAX_TAG );

			$attachment_id = $this->create_placeholder_image( $post_id, $primary_category, $index );
			if ( $attachment_id ) {
				set_post_thumbnail( $post_id, $attachment_id );
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_strip_all_tags( $title ) );
			}

			$post_ids[] = $post_id;
		}

		return $post_ids;
	}

	/**
	 * Builds a short excerpt from the title so every seeded post has one,
	 * as required, without needing real editorial copy.
	 *
	 * @param string $title Post title.
	 * @return string
	 */
	private function excerpt_for( $title ) {
		return sprintf(
			/* translators: %s: post title */
			__( 'A closer look at %s and what it means for teams working with WordPress today.', 'posts-grid-filter' ),
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
	 * Generates a solid-color placeholder JPEG via GD, uploads it through
	 * the normal media pipeline, and returns the attachment ID. Using GD
	 * instead of bundled image files keeps activation fully offline.
	 *
	 * @param int    $post_id  Parent post ID.
	 * @param string $category Category name, used to pick a color.
	 * @param int    $index    Post index, mixed into the label.
	 * @return int Attachment ID, or 0 on failure.
	 */
	private function create_placeholder_image( $post_id, $category, $index ) {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		list( $r, $g, $b ) = isset( $this->categories[ $category ] ) ? $this->categories[ $category ] : array( 100, 100, 100 );

		$width  = 1200;
		$height = 800;
		$image  = imagecreatetruecolor( $width, $height );
		$bg     = imagecolorallocate( $image, $r, $g, $b );
		imagefilledrectangle( $image, 0, 0, $width, $height, $bg );

		$text       = $category . ' #' . ( $index + 1 );
		$text_color = imagecolorallocate( $image, 255, 255, 255 );
		imagestring( $image, 5, 24, $height - 40, $text, $text_color );

		$upload_dir = wp_upload_dir();
		$filename   = 'pgf-cover-' . $post_id . '.jpg';
		$file_path  = trailingslashit( $upload_dir['path'] ) . $filename;

		imagejpeg( $image, $file_path, 82 );
		imagedestroy( $image );

		if ( ! file_exists( $file_path ) ) {
			return 0;
		}

		$attachment = array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => $category . ' cover image',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $file_path, $post_id );
		if ( is_wp_error( $attachment_id ) ) {
			return 0;
		}

		$metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return $attachment_id;
	}

	/**
	 * Creates the demo page with both blocks already placed, so the
	 * assessment environment is ready to use immediately after activation.
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

		$content = "<!-- wp:heading --><h2>" . esc_html__( 'Browse the grid', 'posts-grid-filter' ) . "</h2><!-- /wp:heading -->\n\n" .
			"<!-- wp:pgf/posts-filter /-->\n\n" .
			"<!-- wp:pgf/posts-grid {\"columns\":3,\"postsPerPage\":6} -->\n" .
			"<!-- wp:pgf/pagination /-->\n" .
			"<!-- /wp:pgf/posts-grid -->";

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_title'   => __( 'Posts Grid + Filter Demo', 'posts-grid-filter' ),
				'post_name'    => 'posts-grid-filter-demo',
				'post_status'  => 'publish',
				'post_content' => $content,
			)
		);

		if ( ! is_wp_error( $page_id ) && $page_id ) {
			update_option( self::DEMO_PAGE_OPTION, $page_id );
		}
	}
}
