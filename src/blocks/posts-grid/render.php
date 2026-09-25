<?php
/**
 * Server-side render for pgf/posts-grid.
 *
 * First paint is a plain WP_Query so the grid works with JS disabled and is
 * crawlable; the Interactivity API store (view.js) takes over from there,
 * re-fetching `data-pgf-grid-list` via the REST API whenever the Posts
 * Filter block (anywhere else on the page) or the pagination controls
 * change the shared client-side state.
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

$columns        = isset( $attributes['columns'] ) ? (int) $attributes['columns'] : 3;
$posts_per_page = isset( $attributes['postsPerPage'] ) ? (int) $attributes['postsPerPage'] : 6;

if ( ! in_array( $columns, array( 2, 3, 4 ), true ) ) {
	$columns = 3;
}

$query = new WP_Query(
	array(
		'post_type'      => PGF_Post_Type::POST_TYPE,
		'posts_per_page' => $posts_per_page,
		'paged'          => 1,
		'post_status'    => 'publish',
	)
);

wp_interactivity_state(
	'posts-grid-filter',
	array(
		'config' => array(
			'postsPerPage' => $posts_per_page,
			'restUrl'      => esc_url_raw( rest_url( 'wp/v2/' . PGF_Post_Type::POST_TYPE ) ),
		),
		'page'   => 1,
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
