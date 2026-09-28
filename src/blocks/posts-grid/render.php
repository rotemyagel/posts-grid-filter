<?php
/**
 * Server-side render for pgf/posts-grid.
 *
 * The query comes only from URL params (?pgf_category=design,culture&
 * pgf_tag=news&pgf-page=2), so every filtered or paged view is a real URL that renders
 * without JavaScript. view.js then refreshes the list over REST on filter
 * changes.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner blocks (the pagination block).
 * @var WP_Block $block      Block instance.
 *
 * @package PostsGridFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$columns             = isset( $attributes['columns'] ) ? (int) $attributes['columns'] : 3;
$posts_per_page      = PGF_Blocks::sanitize_posts_per_page( $attributes['postsPerPage'] ?? 6 );
$current_page        = PGF_Blocks::get_requested_page();
$selected_categories = PGF_Blocks::get_requested_term_ids( PGF_Blocks::CATEGORY_PARAM );
$selected_tags       = PGF_Blocks::get_requested_term_ids( PGF_Blocks::TAG_PARAM );

if ( ! in_array( $columns, array( 2, 3, 4 ), true ) ) {
	$columns = 3;
}

$tax_query = PGF_Blocks::build_tax_query( $selected_categories, $selected_tags );

// Same helpers as pagination/render.php, so both agree on the clamped page.
$total_pages  = PGF_Blocks::get_total_pages( $posts_per_page, $tax_query );
$current_page = PGF_Blocks::clamp_page( $current_page, $total_pages );

$query_args = array(
	'post_type'      => PGF_Post_Type::POST_TYPE,
	'posts_per_page' => $posts_per_page,
	'paged'          => $current_page,
	'post_status'    => 'publish',
);
if ( $tax_query ) {
	$query_args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
}

$query = new WP_Query( $query_args );

wp_interactivity_state(
	'posts-grid-filter',
	array(
		'config'             => array(
			'postsPerPage'  => $posts_per_page,
			'restUrl'       => esc_url_raw( rest_url( 'wp/v2/' . PGF_Post_Type::POST_TYPE ) ),
			'pageParam'     => PGF_Blocks::PAGE_PARAM,
			'categoryParam' => PGF_Blocks::CATEGORY_PARAM,
			'tagParam'      => PGF_Blocks::TAG_PARAM,
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
		'termSlugs'          => PGF_Blocks::term_slug_map(),
	)
);

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'pgf-grid-block' ) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-interactive="posts-grid-filter">
	<div
		class="pgf-grid pgf-grid--cols-<?php echo esc_attr( $columns ); ?>"
		data-pgf-grid-list
		data-wp-class--is-loading="state.isLoading"
	>
		<?php if ( $query->have_posts() ) : ?>
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				?>
				<article class="pgf-grid__card">
					<?php if ( has_post_thumbnail() ) : ?>
						<a href="<?php the_permalink(); ?>" class="pgf-grid__thumb">
							<?php the_post_thumbnail( 'medium' ); ?>
						</a>
					<?php endif; ?>
					<h3 class="pgf-grid__title">
						<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					</h3>
					<div class="pgf-grid__excerpt"><?php the_excerpt(); ?></div>
				</article>
				<?php
			endwhile;
			wp_reset_postdata();
			?>
		<?php else : ?>
			<p class="pgf-grid__empty"><?php esc_html_e( 'No posts found.', 'wm-posts-grid-filter' ); ?></p>
		<?php endif; ?>
	</div>
	<p class="pgf-sr-only" aria-live="polite" data-wp-text="state.announcement"></p>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
