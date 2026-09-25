<?php
/**
 * Server-side render for pgf/pagination.
 *
 * Computes its own total-page count from a lightweight query rather than
 * receiving it from the parent grid block, since `providesContext` only
 * carries real block attributes and total pages is a runtime WP_Query
 * result, not an attribute. This keeps the two blocks loosely coupled:
 * pagination only depends on `postsPerPage` (a real attribute passed via
 * context), and after first paint the Interactivity store keeps both
 * blocks' numbers in sync regardless of how each was computed server-side.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner content (none, this block has no children).
 * @var WP_Block $block      Block instance.
 *
 * @package PostsGridFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$posts_per_page = isset( $block->context['posts-grid-filter/postsPerPage'] )
	? (int) $block->context['posts-grid-filter/postsPerPage']
	: 6;

$count_query = new WP_Query(
	array(
		'post_type'      => PGF_Post_Type::POST_TYPE,
		'posts_per_page' => $posts_per_page,
		'post_status'    => 'publish',
		'fields'         => 'ids',
	)
);

$total_pages = max( 1, (int) $count_query->max_num_pages );

wp_interactivity_state(
	'posts-grid-filter',
	array(
		'totalPages' => $total_pages,
	)
);

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'pgf-pagination' ) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-interactive="posts-grid-filter">
	<button
		type="button"
		class="pgf-pagination__prev"
		data-wp-on--click="actions.goToPreviousPage"
		data-wp-bind--disabled="state.isFirstPage"
	>
		<?php esc_html_e( '‹ Prev', 'posts-grid-filter' ); ?>
	</button>
	<span class="pgf-pagination__status" data-wp-text="state.paginationLabel">
		<?php
		printf(
			/* translators: 1: current page, 2: total pages */
			esc_html__( 'Page %1$d of %2$d', 'posts-grid-filter' ),
			1,
			$total_pages
		);
		?>
	</span>
	<button
		type="button"
		class="pgf-pagination__next"
		data-wp-on--click="actions.goToNextPage"
		data-wp-bind--disabled="state.isLastPage"
	>
		<?php esc_html_e( 'Next ›', 'posts-grid-filter' ); ?>
	</button>
</div>
