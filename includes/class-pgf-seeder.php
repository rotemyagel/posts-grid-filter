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

	const SEEDED_OPTION      = 'pgf_seeded';
	const DEMO_PAGE_OPTION   = 'pgf_demo_page_id';

	/**
	 * Options recording exactly which posts/attachments this seeder created,
	 * read by uninstall.php so it deletes only content the plugin actually
	 * owns -- not every pgf_post in the database. Without this, a real
	 * pgf_post a site owner created by hand after activation (a normal,
	 * expected use of the post type once seeded, not a demo-only leftover)
	 * would be deleted right along with the demo content on uninstall, and
	 * so would any existing media library image later chosen as its
	 * featured image.
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

		$categories = $this->create_terms();
		$tags       = $this->create_tags();
		$category_ids = $categories['ids'];
		$tag_ids      = $tags['ids'];

		/*
		 * create_posts() picks a category/tag for each post via `$index %
		 * count( $category_names )` -- if term creation fails completely
		 * (both arrays empty), that becomes a modulo by zero. Bailing here
		 * with nothing marked "seeded" means a later activation attempt
		 * (deactivate/reactivate) gets a genuine retry instead of a
		 * permanently half-seeded, permanently-skipped state.
		 */
		if ( ! $category_ids || ! $tag_ids ) {
			PGF_Blocks::log( 'Seeding aborted: term creation produced no usable categories or tags, so no demo posts were created. Not marking seeding complete -- a later activation will retry.' );
			return;
		}

		$result         = $this->create_posts( $category_ids, $tag_ids );
		$post_ids       = $result['post_ids'];
		$attachment_ids = $result['attachment_ids'];

		$this->create_demo_page();

		update_option( self::SEEDED_OPTION, true );
		/*
		 * Only genuinely-created IDs are persisted here, not every ID these
		 * calls returned -- an adopted pre-existing post/term (matched by
		 * title/name, kept for idempotency) is not something this plugin
		 * created, so uninstall.php must not delete it. See create_terms()
		 * and create_posts() for the created-vs-adopted distinction.
		 */
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
	 * Creates the pgf_category terms, keyed by name.
	 *
	 * `term_exists()` reuses a term that already has this name rather than
	 * erroring or duplicating it -- necessary for idempotency, but a term
	 * that already existed before this run was not created by the plugin
	 * and is deliberately excluded from `created_ids`, the same ownership
	 * distinction create_posts() below makes for adopted-vs-created posts.
	 * A term this plugin didn't create might belong to a site owner's own
	 * taxonomy usage (e.g. after an uninstall that cleared its own tracking
	 * options but, for whatever reason, left the term rows behind), and
	 * uninstall.php should never delete a term it didn't create.
	 *
	 * @return array{ids: array<string, int>, created_ids: int[]} `ids` is
	 *              name => term_id for every usable term (needed to build
	 *              posts below); `created_ids` is only the ones actually
	 *              inserted this call, for uninstall.php's ownership check.
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
	 * Creates the pgf_tag terms. See create_terms() above for why
	 * pre-existing (adopted, not created) terms are excluded from
	 * `created_ids`.
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
	 *              `post_ids` is every matched post (adopted or created),
	 *              used for the completeness count in seed(); `created_post_ids`
	 *              is only the ones actually inserted this call, for
	 *              uninstall.php's ownership-based cleanup (see create_terms()
	 *              for why an adopted pre-existing post is excluded);
	 *              `attachment_ids` only ever contains generated-this-call
	 *              images already, since create_placeholder_image() is only
	 *              reached for a post this call itself just inserted.
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

		$post_ids         = array();
		$created_post_ids = array();
		$attachment_ids   = array();

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
			'attachment_ids' => $attachment_ids,
		);
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
			PGF_Blocks::log( 'GD extension is not available; skipping featured image generation.' );
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
			PGF_Blocks::log( sprintf( 'imagejpeg() did not produce a file at "%s".', $file_path ) );
			return 0;
		}

		$attachment = array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => $category . ' cover image',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $file_path, $post_id );
		if ( is_wp_error( $attachment_id ) ) {
			PGF_Blocks::log( sprintf( 'wp_insert_attachment() failed for post %d: %s', $post_id, $attachment_id->get_error_message() ) );
			return 0;
		}

		$metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return $attachment_id;
	}

	/**
	 * Creates the demo page with both blocks already placed, so the
	 * assessment environment is ready to use immediately after activation.
	 *
	 * DEMO_PAGE_OPTION is set either way (adopted or created) so the
	 * single-post template's "back to grid" link always has somewhere to
	 * point, regardless of who the page belongs to. DEMO_PAGE_OWNED_OPTION
	 * is the separate, narrower flag uninstall.php actually checks before
	 * deleting it -- only true when this call is the one that created the
	 * page, never when it adopted a pre-existing one at that slug that this
	 * plugin didn't make.
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

		$content = "<!-- wp:heading --><h2>" . esc_html__( 'Browse the grid', 'wm-posts-grid-filter' ) . "</h2><!-- /wp:heading -->\n\n" .
			"<!-- wp:pgf/posts-filter /-->\n\n" .
			"<!-- wp:pgf/posts-grid {\"columns\":3,\"postsPerPage\":6} -->\n" .
			"<!-- wp:pgf/pagination /-->\n" .
			"<!-- /wp:pgf/posts-grid -->";

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
