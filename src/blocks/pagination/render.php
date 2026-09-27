<?php
/**
 * Server-side render for pgf/pagination.
 *
 * Computes its own total-page count from a lightweight query rather than
 * receiving it from the parent grid block, since `providesContext` only
 * carries real block attributes and total pages is a runtime WP_Query
 * result, not an attribute. This keeps the two blocks loosely coupled:
 * pagination only depends on `postsPerPage` (a real attribute passed via
 * context), and applies the same ?pgf_category=/?pgf_tag= URL filters as
 * posts-grid's own query so its count (and therefore "Page X of Y") is
 * correct for a filtered, shared/bookmarked URL too.
 *
 * Prev/Next are plain <a href> navigation -- a full page reload, deliberately
 * not intercepted by JavaScript. That is what makes page 2+ (filtered or
 * not) a real, crawlable, no-JS-required URL rather than content that only
 * ever exists after a client-side fetch, which is the specific pattern
 * "SEO-friendly infinite scroll" guidance warns against. `add_query_arg()`
 * (called with no base URL) preserves whatever other query parameters --
 * including ?pgf_category[]=/?pgf_tag[]= -- are already on the current
 * request, so a filtered view's selection travels along automatically when
 * a visitor pages through it. The one place that still needs JavaScript is
 * keeping these hrefs in sync with a filter change made via the Posts
 * Filter block's own AJAX interaction *before* any navigation happens --
 * handled by the state.prevHref/nextHref getters in pagination/view.js.
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

$posts_per_page      = PGF_Blocks::sanitize_posts_per_page( $block->context['posts-grid-filter/postsPerPage'] ?? 6 );
$current_page        = PGF_Blocks::get_requested_page();
$selected_categories = PGF_Blocks::get_requested_term_ids( PGF_Blocks::CATEGORY_PARAM );
$selected_tags       = PGF_Blocks::get_requested_term_ids( PGF_Blocks::TAG_PARAM );

$count_query_args = array(
	'post_type'      => PGF_Post_Type::POST_TYPE,
	'posts_per_page' => $posts_per_page,
	'post_status'    => 'publish',
	'fields'         => 'ids',
);

$tax_query = PGF_Blocks::build_tax_query( $selected_categories, $selected_tags );
if ( $tax_query ) {
	$count_query_args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
}

$count_query = new WP_Query( $count_query_args );
$total_pages = max( 1, (int) $count_query->max_num_pages );

wp_interactivity_state(
	'posts-grid-filter',
	array(
		'totalPages' => $total_pages,
	)
);

$is_first_page = $current_page <= 1;
$is_last_page  = $current_page >= $total_pages;

$prev_href = $is_first_page ? '' : esc_url( add_query_arg( PGF_Blocks::PAGE_PARAM, $current_page - 1 ) );
$next_href = $is_last_page ? '' : esc_url( add_query_arg( PGF_Blocks::PAGE_PARAM, $current_page + 1 ) );

/*
 * data-wp-bind--aria-disabled and data-wp-class--is-disabled only take
 * effect once client JS hydrates. Mirroring their computed value directly
 * in the initial markup (the same pattern already used for href and the
 * filter checkboxes' checked state) means a no-JS visitor or a screen
 * reader reading the page before hydration still sees a correctly
 * disabled, correctly styled control -- not a bare link with no
 * destination and no indication why.
 */
$prev_class = 'pgf-pagination__prev' . ( $is_first_page ? ' is-disabled' : '' );
$next_class = 'pgf-pagination__next' . ( $is_last_page ? ' is-disabled' : '' );

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'pgf-pagination' ) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-interactive="posts-grid-filter">
	<a
		class="<?php echo esc_attr( $prev_class ); ?>"
		rel="prev"
		<?php echo $prev_href ? 'href="' . esc_url( $prev_href ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php echo $is_first_page ? 'aria-disabled="true"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
		class="<?php echo esc_attr( $next_class ); ?>"
		rel="next"
		<?php echo $next_href ? 'href="' . esc_url( $next_href ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php echo $is_last_page ? 'aria-disabled="true"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		data-wp-bind--href="state.nextHref"
		data-wp-bind--aria-disabled="state.isLastPage"
		data-wp-class--is-disabled="state.isLastPage"
	>
		<?php esc_html_e( 'Next ›', 'posts-grid-filter' ); ?>
	</a>
</div>
