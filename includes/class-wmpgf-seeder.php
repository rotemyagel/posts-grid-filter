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
	const DEMO_PAGE_SLUG   = 'posts-grid-filter-demo';

	/**
	 * What this seeder created, so uninstall.php deletes only that and never
	 * a site owner's own wmpgf_posts, images, or terms.
	 */
	const SEEDED_POST_IDS_OPTION       = 'wmpgf_seeded_post_ids';
	const SEEDED_ATTACHMENT_IDS_OPTION = 'wmpgf_seeded_attachment_ids';
	const SEEDED_TERM_IDS_OPTION       = 'wmpgf_seeded_term_ids';
	const DEMO_PAGE_OWNED_OPTION       = 'wmpgf_demo_page_owned';

	/**
	 * Version of the rules seeded content is checked against. Raise it when
	 * those rules change, so existing installs are checked again, once.
	 * 2: every demo post has a usable featured image and its terms (2.0.1).
	 */
	const VALIDATION_VERSION       = 2;
	const VALIDATED_VERSION_OPTION = 'wmpgf_seed_validated_version';

	/**
	 * One-off WP-Cron event that checks an install seeded by an earlier
	 * version, and the lock that keeps two runs (seeding, the check, or a
	 * requested repair) from working on the demo content at once.
	 */
	const VALIDATION_HOOK = 'wmpgf_validate_demo_content';
	const LOCK_OPTION     = 'wmpgf_seed_lock';
	const LOCK_TIMEOUT    = 600;
	const RETRY_DELAY     = 3600;

	/**
	 * Post meta naming which demo-content.php entry a seeded post is. Only
	 * read on posts in the "created" list, so a user's post can't claim it.
	 */
	const DEMO_KEY_META = '_wmpgf_demo_key';

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
	 * While true, nothing is written: find_owned_post() keeps the demo keys
	 * it would give 2.0.0 posts in $unsaved_keys instead of saving them.
	 * Set for a repair dry run.
	 *
	 * @var bool
	 */
	private $read_only = false;

	/**
	 * Demo keys a read-only run would have saved, by post ID.
	 *
	 * @var array<int, string>
	 */
	private $unsaved_keys = array();

	/**
	 * Runs on plugin activation, and from WP-Cron after an update.
	 *
	 * A new install is seeded until one run leaves complete demo content;
	 * see seed_and_validate(). An install seeded by an earlier version has
	 * its demo content checked once against the current rules, without
	 * changing it; see check_seeded(). Either way the run holds a lock, so
	 * two requests never work on the demo content at the same time.
	 *
	 * On multisite this seeds only the site it runs on; network activation
	 * seeds the main site. See the README's known limitations.
	 *
	 * @return bool Whether the demo content is complete and checked.
	 */
	public function seed() {
		$seeded = (bool) get_option( self::SEEDED_OPTION );
		if ( $seeded && ! self::needs_validation() ) {
			return true;
		}
		if ( ! self::lock() ) {
			return false;
		}

		try {
			return $seeded ? $this->check_seeded() : $this->seed_and_validate();
		} finally {
			self::unlock();
		}
	}

	/**
	 * Whether an install was seeded by a version whose checks were weaker
	 * than today's (2.0.0 could mark seeding complete with featured images
	 * or terms missing). Two autoloaded options: cheap on every request.
	 *
	 * @return bool
	 */
	public static function needs_validation() {
		return get_option( self::SEEDED_OPTION ) && (int) get_option( self::VALIDATED_VERSION_OPTION, 0 ) < self::VALIDATION_VERSION;
	}

	/**
	 * On init: when an update left an install needing a check, schedules it
	 * as a one-off WP-Cron event, so it runs in a background request rather
	 * than during a visitor's page load. Activation doesn't run on updates.
	 */
	public static function schedule_validation() {
		if ( self::needs_validation() && ! wp_next_scheduled( self::VALIDATION_HOOK ) ) {
			wp_schedule_single_event( time(), self::VALIDATION_HOOK );
		}
	}

	/**
	 * The WP-Cron event. A run that couldn't finish (another run holding
	 * the lock) is tried again an hour later, not on every request.
	 */
	public static function run_scheduled_validation() {
		$seeder = new self();
		if ( ! $seeder->seed() && self::needs_validation() && ! wp_next_scheduled( self::VALIDATION_HOOK ) ) {
			wp_schedule_single_event( time() + self::RETRY_DELAY, self::VALIDATION_HOOK );
		}
	}

	/**
	 * Seeds a new install. Each run first repairs: it creates what is
	 * missing and fixes the seeder's own posts and page (status, excerpt,
	 * cover, terms, blocks). Then it re-reads everything and checks it.
	 * Only if every check passes is seeding marked complete; otherwise the
	 * next activation tries again.
	 *
	 * Posts and pages are recognised as demo content only through the
	 * seeder's own ownership records, never by title or slug, so a site
	 * owner's content is never changed or counted as demo content.
	 *
	 * @return bool Whether seeding is complete.
	 */
	private function seed_and_validate() {
		$this->author_id = $this->default_author_id();
		$demo_posts      = require WMPGF_DIR . 'includes/demo-content.php';

		$category_ids = $this->create_terms( array_keys( $this->categories ), WMPGF_Post_Type::TAX_CATEGORY );
		$tag_ids      = $this->create_terms( $this->tags, WMPGF_Post_Type::TAX_TAG );

		foreach ( $demo_posts as $index => $demo_post ) {
			$this->repair_post( $demo_post, $index, $category_ids, $tag_ids );
		}
		$this->repair_demo_page();

		$problems = $this->problems( $demo_posts, $category_ids, $tag_ids );
		if ( $problems ) {
			foreach ( $problems as $problem ) {
				self::log( 'Seeding incomplete: ' . $problem );
			}
			self::log( 'Not marking seeding complete. The next activation will repair what is missing.' );
			return false;
		}

		update_option( self::SEEDED_OPTION, true );
		update_option( self::VALIDATED_VERSION_OPTION, self::VALIDATION_VERSION );
		wp_clear_scheduled_hook( self::VALIDATION_HOOK );
		return true;
	}

	/**
	 * Checks an install seeded by an earlier version against the current
	 * rules, and changes none of its content.
	 *
	 * 2.0.0 could mark seeding complete with a demo post's featured image or
	 * terms missing. From what's stored, that can't be told apart from a site
	 * owner removing them on purpose: delete_post_thumbnail() and
	 * wp_remove_object_terms() leave a post's dates as they were, and so do
	 * WP-CLI, the REST API and other plugins that edit terms or meta
	 * directly. So this check repairs nothing. It logs what's missing, with
	 * the command that restores it (repair_demo_content(), run on request
	 * through `wp wmpgf repair-demo-content`), and records that the check
	 * ran. The only write to demo posts is bookkeeping: a post seeded by
	 * 2.0.0 is given its demo key (see find_owned_post()).
	 *
	 * @return bool True: the check ran. Only a held lock stops it, and seed()
	 *              reports that; the scheduled run then tries again later.
	 */
	private function check_seeded() {
		$gaps = $this->gaps();
		if ( $gaps ) {
			foreach ( $gaps as $gap ) {
				self::log( 'Demo content check: ' . $gap['description'] );
			}
			self::log( 'Nothing was changed: a missing image or term can be a failed 2.0.0 seed or the site owner\'s own change. To restore them, run: wp wmpgf repair-demo-content' );
		}

		update_option( self::VALIDATED_VERSION_OPTION, self::VALIDATION_VERSION );
		// A retry scheduled by a run that found the lock held has nothing left to do.
		wp_clear_scheduled_hook( self::VALIDATION_HOOK );
		return true;
	}

	/**
	 * Restores what the seeder's own published demo posts are missing: a
	 * usable featured image, and categories and tags that still exist. Run
	 * only on request (`wp wmpgf repair-demo-content`), because it can undo
	 * an owner's deliberate removal; see check_seeded().
	 *
	 * Posts are recognised only through the ownership records. Nothing is
	 * created or republished: a deleted post or term stays deleted, and a
	 * drafted, private or trashed post stays as it is. Extra terms and edited
	 * text are kept. Safe to run again: a failed run changes what it could,
	 * reports the rest, and the next run picks it up.
	 *
	 * A dry run writes nothing at all: not the repairs, not the demo keys a
	 * real run gives 2.0.0 posts, and not the lock.
	 *
	 * @param bool $dry_run Only report what would change.
	 * @return array {
	 *     @type string   $status   'repaired', 'planned' (dry run), 'nothing',
	 *                              'failed', 'busy' (another run holds the
	 *                              lock; never for a dry run) or 'not-seeded'
	 *                              (activation hasn't finished seeding;
	 *                              reactivating does that).
	 *     @type string[] $changes  What was, or would be, restored.
	 *     @type string[] $skipped  What was left alone, and why.
	 *     @type string[] $problems What is still missing after the run.
	 * }
	 */
	public function repair_demo_content( $dry_run = false ) {
		$result = array(
			'status'   => 'nothing',
			'changes'  => array(),
			'skipped'  => array(),
			'problems' => array(),
		);
		if ( ! get_option( self::SEEDED_OPTION ) ) {
			$result['status'] = 'not-seeded';
			return $result;
		}
		if ( $dry_run ) {
			return $this->preview_repair( $result );
		}
		if ( ! self::lock() ) {
			$result['status'] = 'busy';
			return $result;
		}

		try {
			$this->author_id = $this->default_author_id();
			foreach ( $this->gaps() as $gap ) {
				if ( ! $gap['repairable'] ) {
					$result['skipped'][] = $gap['description'];
					continue;
				}
				$result['changes'][] = $gap['description'];

				$demo_post = $gap['demo_post'];
				$this->assign_terms( $gap['post_id'], $demo_post['title'], $demo_post['categories'], $gap['category_ids'], WMPGF_Post_Type::TAX_CATEGORY );
				$this->assign_terms( $gap['post_id'], $demo_post['title'], $demo_post['tags'], $gap['tag_ids'], WMPGF_Post_Type::TAX_TAG );
				if ( ! $this->has_valid_cover( $gap['post_id'] ) ) {
					$this->add_cover( $gap['post_id'], $demo_post, $gap['index'] );
				}
			}

			// Read back, rather than trusting what this run believes it did.
			foreach ( $this->gaps() as $gap ) {
				if ( $gap['repairable'] ) {
					$result['problems'][] = $gap['description'];
				}
			}
			if ( $result['problems'] ) {
				$result['status'] = 'failed';
			} elseif ( $result['changes'] ) {
				$result['status'] = 'repaired';
			}
			return $result;
		} finally {
			self::unlock();
		}
	}

	/**
	 * The dry run of repair_demo_content(): what it would restore and leave
	 * alone, found in read-only mode. It takes no lock, so it never waits
	 * for another run, and it may describe content another run is changing.
	 *
	 * @param array $result The empty result to fill.
	 * @return array Result, with status 'planned' or 'nothing'.
	 */
	private function preview_repair( array $result ) {
		$this->read_only    = true;
		$this->unsaved_keys = array();

		try {
			foreach ( $this->gaps() as $gap ) {
				$result[ $gap['repairable'] ? 'changes' : 'skipped' ][] = $gap['description'];
			}
		} finally {
			$this->read_only    = false;
			$this->unsaved_keys = array();
		}

		$result['status'] = $result['changes'] ? 'planned' : 'nothing';
		return $result;
	}

	/**
	 * What the seeder's own demo posts lack by the current rules: a usable
	 * featured image, or intended categories and tags that still exist.
	 * Read-only, apart from find_owned_post() giving a 2.0.0 post its demo
	 * key, which it doesn't save in read-only mode. A term that no longer exists is mentioned but never counts as
	 * missing: it can't be restored without recreating it.
	 *
	 * @return array[] One entry per post with something to restore: post_id,
	 *                 index, demo_post, category_ids and tag_ids (intended
	 *                 terms that exist), description, and repairable
	 *                 (whether repair_demo_content() may restore it: only
	 *                 published posts).
	 */
	private function gaps() {
		$demo_posts   = require WMPGF_DIR . 'includes/demo-content.php';
		$category_ids = $this->existing_terms( array_keys( $this->categories ), WMPGF_Post_Type::TAX_CATEGORY );
		$tag_ids      = $this->existing_terms( $this->tags, WMPGF_Post_Type::TAX_TAG );
		$gaps         = array();

		foreach ( $demo_posts as $index => $demo_post ) {
			$post_id = $this->find_owned_post( $demo_post );
			if ( ! $post_id ) {
				continue;
			}

			clean_post_cache( $post_id );
			$missing = array();
			$deleted = array();
			if ( ! $this->has_valid_cover( $post_id ) ) {
				$missing[] = 'a featured image';
			}
			foreach ( array(
				WMPGF_Post_Type::TAX_CATEGORY => array( $demo_post['categories'], $category_ids ),
				WMPGF_Post_Type::TAX_TAG      => array( $demo_post['tags'], $tag_ids ),
			) as $taxonomy => list( $names, $term_ids ) ) {
				$current = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
				$current = is_wp_error( $current ) ? array() : array_map( 'intval', $current );
				foreach ( $names as $name ) {
					if ( ! isset( $term_ids[ $name ] ) ) {
						$deleted[] = '"' . $name . '"';
					} elseif ( ! in_array( $term_ids[ $name ], $current, true ) ) {
						$missing[] = '"' . $name . '"';
					}
				}
			}
			if ( ! $missing ) {
				continue;
			}

			$status      = get_post_status( $post_id );
			$description = sprintf( 'post "%s" (ID %d) is missing %s', $demo_post['title'], $post_id, implode( ', ', $missing ) );
			if ( $deleted ) {
				$description .= sprintf( '; %s no longer exist and are not recreated', implode( ', ', $deleted ) );
			}
			if ( 'publish' !== $status ) {
				$description .= sprintf( '; it is %s, so it is left as it is', $status );
			}

			$gaps[] = array(
				'post_id'      => $post_id,
				'index'        => $index,
				'demo_post'    => $demo_post,
				'category_ids' => $category_ids,
				'tag_ids'      => $tag_ids,
				'description'  => $description . '.',
				'repairable'   => 'publish' === $status,
			);
		}

		return $gaps;
	}

	/**
	 * Term IDs by name, for terms that exist; none are created.
	 *
	 * @param string[] $names    Term names.
	 * @param string   $taxonomy Taxonomy.
	 * @return array<string, int>
	 */
	private function existing_terms( array $names, $taxonomy ) {
		$ids = array();
		foreach ( $names as $name ) {
			$term = term_exists( $name, $taxonomy );
			if ( $term ) {
				$ids[ $name ] = (int) $term['term_id'];
			}
		}
		return $ids;
	}

	/**
	 * Takes the seeding lock. INSERT IGNORE adds the row only if no run
	 * holds it, so two requests can't both get it (the approach of
	 * WP_Upgrader::create_lock()). A lock older than LOCK_TIMEOUT belongs to
	 * a run that died, and is taken over.
	 *
	 * @return bool Whether this run holds the lock.
	 */
	private static function lock() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- An atomic lock can't go through the options cache.
		$taken = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO `{$wpdb->options}` ( `option_name`, `option_value`, `autoload` ) VALUES ( %s, %s, 'off' )",
				self::LOCK_OPTION,
				time()
			)
		);
		if ( $taken ) {
			return true;
		}

		$since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT `option_value` FROM `{$wpdb->options}` WHERE `option_name` = %s", self::LOCK_OPTION ) );
		if ( $since && $since < time() - self::LOCK_TIMEOUT ) {
			// Only one request can move the timestamp on from $since.
			return (bool) $wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$wpdb->options}` SET `option_value` = %s WHERE `option_name` = %s AND `option_value` = %s",
					time(),
					self::LOCK_OPTION,
					$since
				)
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return false;
	}

	/**
	 * Releases the seeding lock.
	 */
	private static function unlock() {
		delete_option( self::LOCK_OPTION );
	}

	/**
	 * Adds IDs to one of the "created by the seeder" lists. Called right
	 * after each insert, so a run that fails later still leaves a record,
	 * and merged rather than replaced, so earlier runs' records survive.
	 *
	 * @param string $option Option name.
	 * @param int[]  $ids    IDs just created.
	 */
	private static function remember( $option, array $ids ) {
		if ( ! $ids ) {
			return;
		}

		// Not autoloaded: only the seeder and uninstall.php read these.
		update_option( $option, array_values( array_unique( array_merge( self::owned( $option ), $ids ) ) ), false );
	}

	/**
	 * IDs on one of the "created by the seeder" lists.
	 *
	 * @param string $option Option name.
	 * @return int[]
	 */
	private static function owned( $option ) {
		$ids = get_option( $option, array() );

		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}

	/**
	 * Stable identifier of a demo-content.php entry.
	 *
	 * @param array $demo_post Entry.
	 * @return string
	 */
	private static function demo_key( array $demo_post ) {
		return sanitize_title( $demo_post['title'] );
	}

	/**
	 * Creates terms in one taxonomy, reusing any that already exist. Reused
	 * terms are used for assignment but not recorded as created, so
	 * uninstall leaves them alone.
	 *
	 * @param string[] $names    Term names.
	 * @param string   $taxonomy Taxonomy name.
	 * @return array<string, int> Name => term ID, for every usable term.
	 */
	private function create_terms( array $names, $taxonomy ) {
		$ids = array();

		foreach ( $names as $name ) {
			$existing = term_exists( $name, $taxonomy );
			$term     = $existing ? $existing : wp_insert_term( $name, $taxonomy );
			if ( is_wp_error( $term ) || ! $term ) {
				self::log( sprintf( 'Failed to create %s term "%s": %s', $taxonomy, $name, is_wp_error( $term ) ? $term->get_error_message() : 'no term returned' ) );
				continue;
			}

			$ids[ $name ] = (int) $term['term_id'];
			if ( ! $existing ) {
				self::remember( self::SEEDED_TERM_IDS_OPTION, array( (int) $term['term_id'] ) );
			}
		}

		return $ids;
	}

	/**
	 * The seeder's own post for a demo entry, in any status including the
	 * trash, or 0. Only posts on the "created" list are considered.
	 *
	 * Posts seeded by 2.0.0 have no demo key yet; one of those whose title
	 * matches is adopted as the entry's post and given the key. In
	 * read-only mode the key is only remembered for this run, so the post
	 * is still found, and not adopted twice, but nothing is saved.
	 *
	 * @param array $demo_post Entry from demo-content.php.
	 * @return int Post ID, or 0.
	 */
	private function find_owned_post( array $demo_post ) {
		$owned = self::owned( self::SEEDED_POST_IDS_OPTION );
		if ( ! $owned ) {
			return 0;
		}

		$posts = get_posts(
			array(
				'post_type'        => WMPGF_Post_Type::POST_TYPE,
				'post__in'         => $owned,
				'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private', 'trash' ),
				'numberposts'      => -1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);

		$key    = self::demo_key( $demo_post );
		$stored = array();
		foreach ( $posts as $post ) {
			$stored[ $post->ID ] = get_post_meta( $post->ID, self::DEMO_KEY_META, true );
			if ( '' === $stored[ $post->ID ] && isset( $this->unsaved_keys[ $post->ID ] ) ) {
				$stored[ $post->ID ] = $this->unsaved_keys[ $post->ID ];
			}
			if ( $stored[ $post->ID ] === $key ) {
				return $post->ID;
			}
		}
		foreach ( $posts as $post ) {
			if ( '' === $stored[ $post->ID ] && $post->post_title === $demo_post['title'] ) {
				if ( $this->read_only ) {
					$this->unsaved_keys[ $post->ID ] = $key;
				} else {
					update_post_meta( $post->ID, self::DEMO_KEY_META, $key );
				}
				return $post->ID;
			}
		}

		return 0;
	}

	/**
	 * Makes one demo post complete: creates it if the seeder has no post
	 * for the entry, otherwise restores its status and excerpt, then makes
	 * sure it has its terms and a cover. Each failure is logged; the
	 * validation in problems() decides whether the run is complete.
	 *
	 * @param array $demo_post    Entry from demo-content.php.
	 * @param int   $index        Entry index, seeds the cover pattern.
	 * @param array $category_ids Category name => term ID.
	 * @param array $tag_ids      Tag name => term ID.
	 */
	private function repair_post( array $demo_post, $index, array $category_ids, array $tag_ids ) {
		$title   = $demo_post['title'];
		$post_id = $this->find_owned_post( $demo_post );

		if ( ! $post_id ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => WMPGF_Post_Type::POST_TYPE,
					'post_title'   => $title,
					'post_status'  => 'publish',
					'post_author'  => $this->author_id,
					'post_excerpt' => $demo_post['excerpt'],
					'post_content' => $this->content_for( $demo_post['body'] ),
					'meta_input'   => array( self::DEMO_KEY_META => self::demo_key( $demo_post ) ),
				),
				true
			);
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				self::log( sprintf( 'Failed to create seed post "%s": %s', $title, is_wp_error( $post_id ) ? $post_id->get_error_message() : 'no post ID returned' ) );
				return;
			}
			self::remember( self::SEEDED_POST_IDS_OPTION, array( $post_id ) );
		} else {
			$this->restore_post_fields( $post_id, $demo_post );
		}

		$this->assign_terms( $post_id, $title, $demo_post['categories'], $category_ids, WMPGF_Post_Type::TAX_CATEGORY );
		$this->assign_terms( $post_id, $title, $demo_post['tags'], $tag_ids, WMPGF_Post_Type::TAX_TAG );

		if ( ! $this->has_valid_cover( $post_id ) ) {
			$this->add_cover( $post_id, $demo_post, $index );
		}
	}

	/**
	 * Puts an existing seeded post back in the trash-free, published state
	 * with an excerpt. Other fields, including a title or content the site
	 * owner edited, are left as they are.
	 *
	 * @param int   $post_id   Seeded post ID.
	 * @param array $demo_post Entry from demo-content.php.
	 */
	private function restore_post_fields( $post_id, array $demo_post ) {
		if ( 'trash' === get_post_status( $post_id ) && ! wp_untrash_post( $post_id ) ) {
			self::log( sprintf( 'Could not restore seed post %d from the trash.', $post_id ) );
			return;
		}

		$post    = get_post( $post_id );
		$changes = array();
		if ( 'publish' !== $post->post_status ) {
			$changes['post_status'] = 'publish';
		}
		if ( '' === trim( $post->post_excerpt ) ) {
			$changes['post_excerpt'] = $demo_post['excerpt'];
		}
		if ( ! $changes ) {
			return;
		}

		$result = wp_update_post( array( 'ID' => $post_id ) + $changes, true );
		if ( is_wp_error( $result ) || ! $result ) {
			self::log( sprintf( 'Could not repair seed post %d: %s', $post_id, is_wp_error( $result ) ? $result->get_error_message() : 'update failed' ) );
		}
	}

	/**
	 * Gives a post its intended terms, keeping any extra terms it has. The
	 * intended ones come first, so the primary category stays first.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $title    Post title, for the log.
	 * @param array  $names    Intended term names.
	 * @param array  $term_ids Name => term ID for the terms that exist.
	 * @param string $taxonomy Taxonomy.
	 */
	private function assign_terms( $post_id, $title, array $names, array $term_ids, $taxonomy ) {
		$intended = $this->intended_term_ids( $names, $term_ids );
		$current  = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
		$current  = is_wp_error( $current ) ? array() : array_map( 'intval', $current );

		if ( ! array_diff( $intended, $current ) ) {
			return;
		}

		$result = wp_set_object_terms( $post_id, array_values( array_unique( array_merge( $intended, $current ) ) ), $taxonomy );
		if ( is_wp_error( $result ) ) {
			self::log( sprintf( 'Failed to assign %s terms to seed post "%s" (post ID %d): %s', $taxonomy, $title, $post_id, $result->get_error_message() ) );
		}
	}

	/**
	 * Term IDs for the names that exist.
	 *
	 * @param string[] $names    Term names.
	 * @param array    $term_ids Name => term ID.
	 * @return int[]
	 */
	private function intended_term_ids( array $names, array $term_ids ) {
		$ids = array();
		foreach ( $names as $name ) {
			if ( isset( $term_ids[ $name ] ) ) {
				$ids[] = $term_ids[ $name ];
			}
		}
		return $ids;
	}

	/**
	 * Whether a post's featured image is an image attachment whose file
	 * exists. Any valid image counts, including one the site owner chose.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function has_valid_cover( $post_id ) {
		$attachment_id = (int) get_post_thumbnail_id( $post_id );
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			return false;
		}
		if ( 0 !== strpos( (string) get_post_mime_type( $attachment_id ), 'image/' ) ) {
			return false;
		}

		$file = get_attached_file( $attachment_id );
		return $file && file_exists( $file );
	}

	/**
	 * Generates a cover and sets it as the featured image. If it can't be
	 * set, the new attachment is deleted again rather than left unused.
	 *
	 * @param int   $post_id   Post ID.
	 * @param array $demo_post Entry from demo-content.php.
	 * @param int   $index     Entry index, seeds the pattern.
	 */
	private function add_cover( $post_id, array $demo_post, $index ) {
		$attachment_id = $this->create_cover_image( $post_id, $demo_post['categories'][0], $index );
		if ( ! $attachment_id ) {
			self::log( sprintf( 'No cover image for seed post "%s" (post ID %d); see the line above.', $demo_post['title'], $post_id ) );
			return;
		}
		self::remember( self::SEEDED_ATTACHMENT_IDS_OPTION, array( $attachment_id ) );

		if ( ! set_post_thumbnail( $post_id, $attachment_id ) ) {
			self::log( sprintf( 'Could not set the cover image of seed post %d.', $post_id ) );
			wp_delete_attachment( $attachment_id, true );
			return;
		}
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_strip_all_tags( $demo_post['title'] ) );
	}

	/**
	 * Checks the demo content from scratch, reading everything back from
	 * the database rather than trusting what this run believes it did.
	 *
	 * @param array[] $demo_posts   Entries from demo-content.php.
	 * @param array   $category_ids Category name => term ID.
	 * @param array   $tag_ids      Tag name => term ID.
	 * @return string[] One line per problem; empty when complete.
	 */
	private function problems( array $demo_posts, array $category_ids, array $tag_ids ) {
		$problems = array();

		foreach ( array_diff( array_keys( $this->categories ), array_keys( $category_ids ) ) as $name ) {
			$problems[] = sprintf( 'category "%s" is missing.', $name );
		}
		foreach ( array_diff( $this->tags, array_keys( $tag_ids ) ) as $name ) {
			$problems[] = sprintf( 'tag "%s" is missing.', $name );
		}

		foreach ( $demo_posts as $demo_post ) {
			$post_id = $this->find_owned_post( $demo_post );
			if ( ! $post_id ) {
				$problems[] = sprintf( 'post "%s" does not exist.', $demo_post['title'] );
				continue;
			}

			clean_post_cache( $post_id );
			$post = get_post( $post_id );
			$name = sprintf( 'post "%s" (ID %d)', $demo_post['title'], $post_id );
			if ( 'publish' !== $post->post_status ) {
				$problems[] = $name . ' is not published.';
			}
			if ( '' === trim( $post->post_excerpt ) ) {
				$problems[] = $name . ' has no excerpt.';
			}
			if ( ! $this->has_valid_cover( $post_id ) ) {
				$problems[] = $name . ' has no valid cover image.';
			}
			foreach ( array(
				WMPGF_Post_Type::TAX_CATEGORY => array( $demo_post['categories'], $category_ids ),
				WMPGF_Post_Type::TAX_TAG      => array( $demo_post['tags'], $tag_ids ),
			) as $taxonomy => list( $names, $term_ids ) ) {
				$current = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
				$missing = is_wp_error( $current )
					? $names
					: array_diff( $this->intended_term_ids( $names, $term_ids ), array_map( 'intval', $current ) );
				if ( $missing || count( $this->intended_term_ids( $names, $term_ids ) ) < count( $names ) ) {
					$problems[] = sprintf( '%s is missing %s terms.', $name, $taxonomy );
				}
			}
		}

		$page_problem = $this->demo_page_problem();
		if ( $page_problem ) {
			$problems[] = $page_problem;
		}

		return $problems;
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
	 * Writes the post's cover SVG and registers it as an attachment. A file
	 * that was written but couldn't be registered is deleted again.
	 *
	 * @param int    $post_id  Parent post ID.
	 * @param string $category Primary category name, used to pick the colours.
	 * @param int    $index    Post index, seeds the pattern.
	 * @return int Attachment ID, or 0 on failure.
	 */
	private function create_cover_image( $post_id, $category, $index ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			self::log( sprintf( 'Uploads folder unavailable for the cover of post %d: %s', $post_id, $upload_dir['error'] ) );
			return 0;
		}

		$width  = 1200;
		$height = 800;
		$svg    = $this->cover_svg(
			isset( $this->categories[ $category ] ) ? $this->categories[ $category ] : array( 100, 100, 100 ),
			$index,
			$width,
			$height
		);

		// A unique name, so a retry never overwrites a file an older
		// attachment still points at.
		$filename  = wp_unique_filename( $upload_dir['path'], 'wmpgf-cover-' . $post_id . '.svg' );
		$file_path = trailingslashit( $upload_dir['path'] ) . $filename;

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
		$attachment_id = wp_insert_attachment( $attachment, $file_path, $post_id, true );
		remove_filter( 'upload_mimes', $allow_svg_mime );

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			self::log( sprintf( 'wp_insert_attachment() failed for post %d: %s', $post_id, is_wp_error( $attachment_id ) ? $attachment_id->get_error_message() : 'no ID returned' ) );
			wp_delete_file( $file_path );
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
	 * The seeder's own demo page, or null. A page 2.0.0 recorded but merely
	 * adopted (one already at the slug) is not the seeder's.
	 *
	 * @return WP_Post|null
	 */
	private function owned_demo_page() {
		$page_id = (int) get_option( self::DEMO_PAGE_OPTION );
		if ( ! $page_id || ! get_option( self::DEMO_PAGE_OWNED_OPTION ) ) {
			return null;
		}

		clean_post_cache( $page_id );
		$page = get_post( $page_id );

		return $page && 'page' === $page->post_type ? $page : null;
	}

	/**
	 * Creates the demo page, or repairs the seeder's own page (status and
	 * blocks). A page someone else put at the slug is left untouched; the
	 * seeder's page then gets the next free slug.
	 */
	private function repair_demo_page() {
		$page = $this->owned_demo_page();

		if ( ! $page ) {
			$page_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_title'   => __( 'Posts Grid + Filter Demo', 'wm-posts-grid-filter' ),
					'post_name'    => self::DEMO_PAGE_SLUG,
					'post_status'  => 'publish',
					'post_author'  => $this->author_id,
					'post_content' => self::demo_page_content(),
				),
				true
			);
			if ( is_wp_error( $page_id ) || ! $page_id ) {
				self::log( 'Failed to create the demo page: ' . ( is_wp_error( $page_id ) ? $page_id->get_error_message() : 'no page ID returned' ) );
				return;
			}
			update_option( self::DEMO_PAGE_OPTION, $page_id );
			update_option( self::DEMO_PAGE_OWNED_OPTION, true );
			return;
		}

		if ( 'trash' === $page->post_status && ! wp_untrash_post( $page->ID ) ) {
			self::log( sprintf( 'Could not restore the demo page (ID %d) from the trash.', $page->ID ) );
			return;
		}

		$changes = array();
		if ( 'publish' !== get_post_status( $page->ID ) ) {
			$changes['post_status'] = 'publish';
		}
		if ( ! self::has_demo_blocks( $page->post_content ) ) {
			$changes['post_content'] = self::demo_page_content();
		}
		if ( ! $changes ) {
			return;
		}

		$result = wp_update_post( array( 'ID' => $page->ID ) + $changes, true );
		if ( is_wp_error( $result ) || ! $result ) {
			self::log( sprintf( 'Could not repair the demo page (ID %d): %s', $page->ID, is_wp_error( $result ) ? $result->get_error_message() : 'update failed' ) );
		}
	}

	/**
	 * What is wrong with the demo page, read back from the database.
	 *
	 * @return string Empty when the page is complete.
	 */
	private function demo_page_problem() {
		$page = $this->owned_demo_page();
		if ( ! $page ) {
			return 'the demo page does not exist.';
		}
		if ( 'publish' !== $page->post_status ) {
			return sprintf( 'the demo page (ID %d) is not published.', $page->ID );
		}
		if ( ! self::has_demo_blocks( $page->post_content ) ) {
			return sprintf( 'the demo page (ID %d) is missing the filter, the grid, or the pagination inside the grid.', $page->ID );
		}
		return '';
	}

	/**
	 * Block markup of the demo page.
	 *
	 * @return string
	 */
	private static function demo_page_content() {
		return "<!-- wp:wmpgf/posts-filter {\"align\":\"wide\"} /-->\n\n" .
			"<!-- wp:wmpgf/posts-grid {\"columns\":3,\"postsPerPage\":6,\"align\":\"wide\"} -->\n" .
			"<!-- wp:wmpgf/pagination /-->\n" .
			'<!-- /wp:wmpgf/posts-grid -->';
	}

	/**
	 * Whether content has the filter block and a grid block with the
	 * pagination block inside it, at any nesting depth.
	 *
	 * @param string $content Post content.
	 * @return bool
	 */
	private static function has_demo_blocks( $content ) {
		$blocks = parse_blocks( $content );
		if ( ! self::find_blocks( $blocks, 'wmpgf/posts-filter' ) ) {
			return false;
		}

		foreach ( self::find_blocks( $blocks, 'wmpgf/posts-grid' ) as $grid ) {
			if ( self::find_blocks( $grid['innerBlocks'], 'wmpgf/pagination' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Every block with a given name, searching inner blocks too.
	 *
	 * @param array[] $blocks Parsed blocks.
	 * @param string  $name   Block name.
	 * @return array[]
	 */
	private static function find_blocks( array $blocks, $name ) {
		$found = array();
		foreach ( $blocks as $block ) {
			if ( $name === $block['blockName'] ) {
				$found[] = $block;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = array_merge( $found, self::find_blocks( $block['innerBlocks'], $name ) );
			}
		}
		return $found;
	}
}
