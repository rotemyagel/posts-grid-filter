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

$posts_per_page = PGF_Blocks::sanitize_posts_per_page( $block->context['posts-grid-filter/postsPerPage'] ?? 6 );
$current_page   = PGF_Blocks::get_requested_page();

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

/*
 * Real, crawlable prev/next URLs, not just JS click handlers: with
 * JavaScript disabled these are plain links a browser or a crawler can
 * follow directly, and posts-grid's render.php reads the same ?pgf-page=
 * parameter to server-render the matching page. The Interactivity API
 * directives below layer an AJAX-driven, no-reload experience on top
 * (pagination/view.js intercepts the click and calls preventDefault()),
 * but the underlying href is what makes page 2+ reachable at all without
 * it -- the specific gap plain "load more"/infinite-scroll patterns are
 * usually criticized for.
 */
$prev_href = $current_page > 1 ? esc_url( add_query_arg( PGF_Blocks::PAGE_PARAM, $current_page - 1 ) ) : '';
$next_href = $current_page < $total_pages ? esc_url( add_query_arg( PGF_Blocks::PAGE_PARAM, $current_page + 1 ) ) : '';

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'pgf-pagination' ) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-interactive="posts-grid-filter">
	<a
		class="pgf-pagination__prev"
		rel="prev"
		<?php echo $prev_href ? 'href="' . esc_url( $prev_href ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		data-wp-on--click="actions.goToPreviousPage"
		data-wp-bind--href="state.prevHref"
		data-wp-bind--aria-disabled="state.isFirstPage"
		data-wp-class--is-disabled="state.isFirstPage"
	>
		<?php esc_html_e( '‹ Prev', 'posts-grid-filter' ); ?>
	</a>
	<span class="pgf-pagination__status" data-wp-text="state.paginationLabel">
		<?php
		printf(
			/* translators: 1: current page, 2: total pages */
			esc_html__( 'Page %1$d of %2$d', 'posts-grid-filter' ),
			$current_page,
			$total_pages
		);
		?>
	</span>
	<a
		class="pgf-pagination__next"
		rel="next"
		<?php echo $next_href ? 'href="' . esc_url( $next_href ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		data-wp-on--click="actions.goToNextPage"
		data-wp-bind--href="state.nextHref"
		data-wp-bind--aria-disabled="state.isLastPage"
		data-wp-class--is-disabled="state.isLastPage"
	>
		<?php esc_html_e( 'Next ›', 'posts-grid-filter' ); ?>
	</a>
</div>
