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
 * The page-size select lives here rather than in the filter: this block
 * knows the grid's own postsPerPage through block context, and page size
 * is a property of the list, not of which posts match.
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

$block_default = WMPGF_Query::sanitize_posts_per_page( $block->context['wmpgf/postsPerPage'] ?? 6 );
$page_size     = WMPGF_Request::per_page( $block_default );
$show_per_page = $attributes['showPerPage'] ?? true;

// Same cached query as the grid, so both agree on the clamped page.
$result = WMPGF_Query::page( WMPGF_Request::filters(), $page_size, WMPGF_Request::page() );

if ( $result['total_pages'] <= 1 && ! $show_per_page ) {
	return;
}

wp_interactivity_config( 'wmpgf', array( 'params' => WMPGF_Request::params() ) );

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

$per_page_options = array_unique( array_merge( WMPGF_Request::PER_PAGE_OPTIONS, array( $block_default, $page_size ) ) );
sort( $per_page_options );
$per_page_id = wp_unique_id( 'wmpgf-per-page-' );

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'wmpgf-pagination' ) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-interactive="wmpgf">
	<?php if ( $result['total_pages'] > 1 ) : ?>
		<nav class="wmpgf-pagination__nav" aria-label="<?php esc_attr_e( 'Posts pagination', 'wm-posts-grid-filter' ); ?>">
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
	<?php endif; ?>

	<?php if ( $show_per_page ) : ?>
		<form class="wmpgf-pagination__per-page" method="get" action="<?php echo esc_url( strtok( WMPGF_Request::url( array() ), '?' ) ); ?>">
			<?php WMPGF_Request::hidden_inputs( array( WMPGF_Request::PER_PAGE_PARAM, WMPGF_Request::PAGE_PARAM ) ); ?>
			<label for="<?php echo esc_attr( $per_page_id ); ?>"><?php esc_html_e( 'Posts per page', 'wm-posts-grid-filter' ); ?></label>
			<select
				id="<?php echo esc_attr( $per_page_id ); ?>"
				name="<?php echo esc_attr( WMPGF_Request::PER_PAGE_PARAM ); ?>"
				data-wp-on-async--change="actions.changePerPage"
			>
				<?php foreach ( $per_page_options as $option ) : ?>
					<option value="<?php echo (int) $option; ?>" <?php selected( $option, $page_size ); ?>><?php echo (int) $option; ?></option>
				<?php endforeach; ?>
			</select>
			<noscript><button type="submit"><?php esc_html_e( 'Apply', 'wm-posts-grid-filter' ); ?></button></noscript>
		</form>
	<?php endif; ?>
</div>
