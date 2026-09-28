<?php
/**
 * Server-side render for wmpgf/posts-grid.
 *
 * The query comes only from URL params (?wmpgf-category=design,culture&
 * wmpgf-tag=news&wmpgf-page=2), so every filtered or paged view is a real URL that renders
 * without JavaScript. view.js then refreshes the list over REST on filter
 * changes.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner blocks (the pagination block).
 * @var WP_Block $block      Block instance.
 *
 * @package WMPGF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$columns             = isset( $attributes['columns'] ) ? (int) $attributes['columns'] : 3;
$posts_per_page      = WMPGF_Query::sanitize_posts_per_page( $attributes['postsPerPage'] ?? 6 );
$current_page        = WMPGF_Request::page();
$selected_categories = WMPGF_Request::term_ids( WMPGF_Request::CATEGORY_PARAM );
$selected_tags       = WMPGF_Request::term_ids( WMPGF_Request::TAG_PARAM );

if ( ! in_array( $columns, array( 2, 3, 4 ), true ) ) {
	$columns = 3;
}

$tax_query = WMPGF_Query::tax_query( $selected_categories, $selected_tags );

// Same helpers as pagination/render.php, so both agree on the clamped page.
$total_pages  = WMPGF_Query::total_pages( $posts_per_page, $tax_query );
$current_page = WMPGF_Query::clamp_page( $current_page, $total_pages );

$query_args = array(
	'post_type'      => WMPGF_Post_Type::POST_TYPE,
	'posts_per_page' => $posts_per_page,
	'paged'          => $current_page,
	'post_status'    => 'publish',
);
if ( $tax_query ) {
	$query_args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
}

$query = new WP_Query( $query_args );

wp_interactivity_state(
	'wmpgf',
	array(
		'config'             => array(
			'postsPerPage'  => $posts_per_page,
			'restUrl'       => esc_url_raw( rest_url( 'wp/v2/' . WMPGF_Post_Type::POST_TYPE ) ),
			'pageParam'     => WMPGF_Request::PAGE_PARAM,
			'categoryParam' => WMPGF_Request::CATEGORY_PARAM,
			'tagParam'      => WMPGF_Request::TAG_PARAM,
			'i18n'          => array(
				'noResults' => __( 'No posts found.', 'wm-posts-grid-filter' ),
				/* translators: {count} is replaced in the browser with the number of matching posts. */
				'results'   => __( 'Posts found: {count}', 'wm-posts-grid-filter' ),
				'loadError' => __( 'Could not load posts. Please try again.', 'wm-posts-grid-filter' ),
			),
		),
		'page'               => $current_page,
		'selectedCategories' => $selected_categories,
		'selectedTags'       => $selected_tags,
		'termSlugs'          => WMPGF_Request::term_slug_map(),
	)
);

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'wmpgf-grid-block' ) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-interactive="wmpgf">
	<div
		class="wmpgf-grid wmpgf-grid--cols-<?php echo esc_attr( $columns ); ?>"
		data-wmpgf-grid-list
		data-wp-class--is-loading="state.isLoading"
	>
		<?php if ( $query->have_posts() ) : ?>
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				?>
				<article class="wmpgf-grid__card">
					<?php if ( has_post_thumbnail() ) : ?>
						<a href="<?php the_permalink(); ?>" class="wmpgf-grid__thumb">
							<?php the_post_thumbnail( 'medium' ); ?>
						</a>
					<?php endif; ?>
					<h3 class="wmpgf-grid__title">
						<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					</h3>
					<div class="wmpgf-grid__excerpt"><?php the_excerpt(); ?></div>
				</article>
				<?php
			endwhile;
			wp_reset_postdata();
			?>
		<?php else : ?>
			<p class="wmpgf-grid__empty"><?php esc_html_e( 'No posts found.', 'wm-posts-grid-filter' ); ?></p>
		<?php endif; ?>
	</div>
	<p class="wmpgf-sr-only" aria-live="polite" data-wp-text="state.announcement"></p>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
