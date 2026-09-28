<?php
/**
 * Server-side render for wmpgf/pagination.
 *
 * Prev/Next are real links, so every page is a crawlable URL that keeps
 * the current filters. view.js intercepts the click and loads the page
 * through the Interactivity Router instead of a full reload. This block
 * sits inside the grid's router region, so it is re-rendered here on the
 * server after every navigation.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner content (none).
 * @var WP_Block $block      Block instance.
 *
 * @package WMPGF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Same cached query as the grid, so both agree on the clamped page.
$result = WMPGF_Query::page(
	WMPGF_Request::filters(),
	$block->context['wmpgf/postsPerPage'] ?? 6,
	WMPGF_Request::page()
);

if ( $result['total_pages'] <= 1 ) {
	return;
}

$current_page = $result['page'];
$page_links   = array(
	'prev' => array(
		'page'  => $current_page - 1,
		'label' => __( '‹ Prev', 'wm-posts-grid-filter' ),
		'rel'   => 'prev',
	),
	'next' => array(
		'page'  => $current_page + 1,
		'label' => __( 'Next ›', 'wm-posts-grid-filter' ),
		'rel'   => 'next',
	),
);

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'wmpgf-pagination' ) );
?>
<nav
	<?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	aria-label="<?php esc_attr_e( 'Posts pagination', 'wm-posts-grid-filter' ); ?>"
	data-wp-interactive="wmpgf"
>
	<?php foreach ( $page_links as $key => $page_link ) : ?>
		<?php if ( 'next' === $key ) : ?>
			<span class="wmpgf-pagination__status">
				<?php
				printf(
					/* translators: 1: current page, 2: total pages */
					esc_html__( 'Page %1$d of %2$d', 'wm-posts-grid-filter' ),
					(int) $current_page,
					(int) $result['total_pages']
				);
				?>
			</span>
		<?php endif; ?>
		<?php if ( $page_link['page'] >= 1 && $page_link['page'] <= $result['total_pages'] ) : ?>
			<a
				class="wmpgf-pagination__link wmpgf-pagination__<?php echo esc_attr( $key ); ?>"
				href="<?php echo esc_url( WMPGF_Request::url( array( 'page' => $page_link['page'] ) ) ); ?>"
				rel="<?php echo esc_attr( $page_link['rel'] ); ?>"
				data-wp-on--click="actions.goToPage"
			><?php echo esc_html( $page_link['label'] ); ?></a>
		<?php else : ?>
			<span class="wmpgf-pagination__link wmpgf-pagination__<?php echo esc_attr( $key ); ?> is-disabled" aria-disabled="true"><?php echo esc_html( $page_link['label'] ); ?></span>
		<?php endif; ?>
	<?php endforeach; ?>
</nav>
