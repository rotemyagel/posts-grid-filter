<?php
/**
 * Seeds demo content on activation so the plugin is usable with zero
 * manual setup: posts, terms, featured images, and a demo page carrying
 * both blocks.
 *
 * Featured images are generated on the fly as SVGs -- a solid rect plus a
 * text label, written directly with file_put_contents() -- rather than
 * bundled as binary assets or generated with an image library. This needs
 * no network access, no extra files to ship with the plugin, and (unlike
 * an earlier GD-based version of this file) no PHP image extension either:
 * SVG is plain XML text, so there is nothing to fall back to or skip.
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
	 * Generates a solid-color placeholder SVG, uploads it through the normal
	 * attachment pipeline, and returns the attachment ID. SVG needs no PHP
	 * image extension at all -- it's XML text written with
	 * file_put_contents() -- unlike the GD-based JPEG this replaced, which
	 * silently produced no featured images at all on a PHP build without
	 * the GD extension.
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

		/*
		 * WordPress blocks SVG uploads by default -- a real XSS risk for
		 * user-supplied files, since an SVG can embed <script>. Allowed only
		 * for this one insert, for a file this method just wrote itself
		 * (a rect and a text label, nothing else), then immediately removed
		 * again: the site's own upload restrictions for real user uploads
		 * are never weakened.
		 */
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

		/*
		 * wp_generate_attachment_metadata() runs the file through
		 * wp_get_image_editor() (GD/Imagick), which doesn't know how to
		 * introspect an SVG's dimensions -- the true width/height are set
		 * directly instead, for the REST API and for is/has-thumbnail checks.
		 *
		 * No 'sizes' entries: tried adding an explicit 'medium' entry
		 * pointing at this same file (there's no separate crop to generate;
		 * this SVG scales losslessly), expecting the_post_thumbnail('medium')
		 * to then report this file's real 1200x800 the same way the REST API
		 * does -- but core's image_downsize() always re-constrains a *named*
		 * size's reported width/height to that size's registered bounding
		 * box (image_constrain_size_for_editor()) regardless of what's
		 * stored in metadata, so the_post_thumbnail('medium') reports
		 * 300x200 (medium's configured box) either way. This is harmless:
		 * a 1200x800 source is 3:2, medium's box-constrained 300x200 is
		 * also 3:2, so the two render paths report different absolute
		 * numbers for the same image but an identical aspect ratio -- and
		 * aspect ratio, not the absolute pixel values, is what a browser
		 * actually needs from width/height to reserve correct layout space
		 * and avoid a shift.
		 */
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
