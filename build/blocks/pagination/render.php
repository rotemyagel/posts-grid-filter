<?php
/**
 * Server-side render for wmpgf/pagination.
 *
 * Counts pages itself, because block context can only pass attributes
 * (postsPerPage), not the grid's query result. Prev/Next are plain links
 * with a full reload, so every page is a crawlable URL that keeps the
 * current filters.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner content (none, this block has no children).
 * @var WP_Block $block      Block instance.
 *
 * @package WMPGF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$posts_per_page      = WMPGF_Query::sanitize_posts_per_page( $block->context['wmpgf/postsPerPage'] ?? 6 );
$selected_categories = WMPGF_Request::term_ids( WMPGF_Request::CATEGORY_PARAM );
$selected_tags       = WMPGF_Request::term_ids( WMPGF_Request::TAG_PARAM );
$tax_query           = WMPGF_Query::tax_query( $selected_categories, $selected_tags );

// Same helpers as posts-grid/render.php, so both agree on the clamped page.
$total_pages  = WMPGF_Query::total_pages( $posts_per_page, $tax_query );
$current_page = WMPGF_Query::clamp_page( WMPGF_Request::page(), $total_pages );

wp_interactivity_state(
	'wmpgf',
	array(
		'totalPages'            => $total_pages,
		// Translated template; view.js fills it in when the page count changes.
		'paginationLabelFormat' => __( 'Page {current} of {total}', 'wm-posts-grid-filter' ),
	)
);

$is_first_page = $current_page <= 1;
$is_last_page  = $current_page >= $total_pages;

$prev_href = $is_first_page ? '' : WMPGF_Request::page_url( $current_page - 1, $selected_categories, $selected_tags );
$next_href = $is_last_page ? '' : WMPGF_Request::page_url( $current_page + 1, $selected_categories, $selected_tags );

// Disabled state is also rendered server-side, for first paint and no-JS.
$prev_class = 'wmpgf-pagination__prev' . ( $is_first_page ? ' is-disabled' : '' );
$next_class = 'wmpgf-pagination__next' . ( $is_last_page ? ' is-disabled' : '' );

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'wmpgf-pagination' ) );
?>
<div
	<?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php echo $total_pages <= 1 ? 'hidden' : ''; ?>
	data-wp-interactive="wmpgf"
	data-wp-bind--hidden="state.isSinglePage"
>
	<a
		class="<?php echo esc_attr( $prev_class ); ?>"
		rel="prev"
		<?php echo $prev_href ? 'href="' . esc_url( $prev_href ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php echo $is_first_page ? 'aria-disabled="true"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		data-wp-bind--href="state.prevHref"
		data-wp-bind--aria-disabled="state.isFirstPage"
		data-wp-class--is-disabled="state.isFirstPage"
	>
		<?php esc_html_e( '‹ Prev', 'wm-posts-grid-filter' ); ?>
	</a>
	<span class="wmpgf-pagination__status" data-wp-text="state.paginationLabel">
		<?php
		printf(
			/* translators: 1: current page, 2: total pages */
			esc_html__( 'Page %1$d of %2$d', 'wm-posts-grid-filter' ),
			(int) $current_page,
			(int) $total_pages
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
		<?php esc_html_e( 'Next ›', 'wm-posts-grid-filter' ); ?>
	</a>
</div>
