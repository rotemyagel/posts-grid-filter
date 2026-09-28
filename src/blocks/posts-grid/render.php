<?php
/**
 * Server-side render for wmpgf/posts-grid. The only post card template:
 * the frontend, filter and page changes (through the Interactivity
 * Router) and the editor preview (through ServerSideRender) all use it.
 *
 * The query comes only from URL params (?wmpgf-category=design,culture&
 * wmpgf-tag=news&wmpgf-page=2), so every filtered or paged view is a real
 * URL that renders without JavaScript.
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

$columns = isset( $attributes['columns'] ) ? (int) $attributes['columns'] : 3;
if ( ! in_array( $columns, array( 2, 3, 4 ), true ) ) {
	$columns = 3;
}

$filters = WMPGF_Request::filters();
$result  = WMPGF_Query::page(
	$filters,
	$attributes['postsPerPage'] ?? 6,
	WMPGF_Request::page()
);
$query   = $result['query'];

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'wmpgf-grid-block' ) );
?>
<div
	<?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wmpgf"
	data-wp-router-region="wmpgf/grid"
	data-wp-class--is-loading="state.isLoading"
	data-wp-bind--aria-busy="state.isLoading"
>
	<div class="wmpgf-grid wmpgf-grid--cols-<?php echo esc_attr( $columns ); ?>">
		<?php if ( $query->have_posts() ) : ?>
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				?>
				<article class="wmpgf-grid__card">
					<?php if ( has_post_thumbnail() ) : ?>
						<a href="<?php the_permalink(); ?>" class="wmpgf-grid__thumb" tabindex="-1" aria-hidden="true">
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
			<p class="wmpgf-grid__empty">
				<?php
				if ( '' !== $filters['search'] ) {
					/* translators: %s: the search term. */
					printf( esc_html__( 'No posts match "%s".', 'wm-posts-grid-filter' ), esc_html( $filters['search'] ) );
				} else {
					esc_html_e( 'No posts match these filters.', 'wm-posts-grid-filter' );
				}
				?>
			</p>
		<?php endif; ?>
	</div>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
