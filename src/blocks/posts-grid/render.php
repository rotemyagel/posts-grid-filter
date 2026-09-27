<?php
/**
 * Server-side render for pgf/posts-grid.
 *
 * The query is driven entirely by plain `?pgf-page=`, `?pgf_category[]=`
 * and `?pgf_tag[]=` URL parameters (PGF_Blocks::get_requested_page() /
 * get_requested_term_ids()), not by client-side state: page 2, a filtered
 * view, or both together are all real, crawlable URLs that render correct
 * content on their own with or without JavaScript -- there is no
 * client-only page or filtered view that only exists after a fetch.
 *
 * Filtering itself still updates instantly via the Posts Filter block's
 * Interactivity API store (no reload) for a fast interactive experience,
 * patching `data-pgf-grid-list` via the REST API. Pagination, however, is
 * plain `<a href>` navigation with a full page reload (see
 * pagination/render.php) -- once a visitor moves to another page, the
 * currently-selected filters travel with it as URL parameters rather than
 * living only in client memory, which is also what keeps a filtered
 * page 2 shareable/bookmarkable and correct on a fresh, no-JS load.
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

$query_args = array(
	'post_type'      => PGF_Post_Type::POST_TYPE,
	'posts_per_page' => $posts_per_page,
	'paged'          => $current_page,
	'post_status'    => 'publish',
);

$tax_query = PGF_Blocks::build_tax_query( $selected_categories, $selected_tags );
if ( $tax_query ) {
	$query_args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
}

$query = new WP_Query( $query_args );

wp_interactivity_state(
	'posts-grid-filter',
	array(
		'config'             => array(
			'postsPerPage' => $posts_per_page,
			'restUrl'      => esc_url_raw( rest_url( 'wp/v2/' . PGF_Post_Type::POST_TYPE ) ),
			'pageParam'    => PGF_Blocks::PAGE_PARAM,
			'categoryParam' => PGF_Blocks::CATEGORY_PARAM,
			'tagParam'      => PGF_Blocks::TAG_PARAM,
		),
		'page'               => $current_page,
		'selectedCategories' => $selected_categories,
		'selectedTags'       => $selected_tags,
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
			<p class="pgf-grid__empty"><?php esc_html_e( 'No posts found.', 'posts-grid-filter' ); ?></p>
		<?php endif; ?>
	</div>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
