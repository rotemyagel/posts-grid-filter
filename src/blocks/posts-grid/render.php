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
	WMPGF_Request::per_page( $attributes['postsPerPage'] ?? 6 ),
	WMPGF_Request::page()
);
$query   = $result['query'];
$labels  = WMPGF_Query::primary_categories( wp_list_pluck( $query->posts, 'ID' ) );

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'wmpgf-grid-block' ) );

// Card position, for how soon its image loads (see WMPGF_Image_Loading).
$card_index  = 0;
$first_image = true;

// Shown for posts without a featured image; without one, a placeholder.
$fallback_image_id = (int) ( $attributes['fallbackImageId'] ?? 0 );
if ( $fallback_image_id && ! wp_attachment_is_image( $fallback_image_id ) ) {
	$fallback_image_id = 0;
}
?>
<div
	<?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wmpgf"
	data-wp-router-region="wmpgf/grid"
	data-wp-class--is-loading="state.isLoading"
	data-wp-class--is-skeleton="state.showSkeleton"
	data-wp-bind--aria-busy="state.isLoading"
>
	<?php WMPGF_Blocks::print_color_scheme_script(); ?>
	<div class="wmpgf-grid wmpgf-grid--cols-<?php echo esc_attr( $columns ); ?>">
		<?php if ( $query->have_posts() ) : ?>
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				?>
				<article class="wmpgf-grid__card">
					<?php
					// A featured image whose attachment was deleted counts as none.
					$has_thumbnail = has_post_thumbnail() && wp_attachment_is_image( get_post_thumbnail_id() );
					?>
					<?php if ( $has_thumbnail || $fallback_image_id ) : ?>
						<div
							class="wmpgf-grid__thumb"
							data-wp-context='{"imageLoading":false}'
							data-wp-class--is-loading="context.imageLoading"
						>
							<?php
							// Decorative: the title below says the same thing.
							$image_attributes = array(
								'alt'               => '',
								WMPGF_Image_Loading::ATTRIBUTE => WMPGF_Image_Loading::role( $card_index, $columns, $first_image ),
								'data-wp-init'      => 'callbacks.watchImage',
								'data-wp-on--load'  => 'actions.imageLoaded',
								'data-wp-on--error' => 'actions.imageLoaded',
							);
							if ( $has_thumbnail ) {
								the_post_thumbnail( 'medium', $image_attributes );
							} else {
								echo wp_get_attachment_image( $fallback_image_id, 'medium', false, $image_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped by core.
							}
							$first_image = false;
							?>
						</div>
					<?php else : ?>
						<?php // No image at all: a neutral placeholder the size of a cover, so the row still lines up. ?>
						<div class="wmpgf-grid__thumb wmpgf-grid__thumb--placeholder">
							<svg viewBox="0 0 24 24" width="40" height="40" aria-hidden="true" focusable="false"><rect x="3" y="4.5" width="18" height="15" rx="2" fill="none" stroke="currentColor" stroke-width="1.5"/><circle cx="9" cy="9.5" r="1.75" fill="currentColor"/><path d="M3.75 17.5l5-5 3.5 3.5 2.5-2.5 5.5 5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
						</div>
					<?php endif; ?>
					<?php if ( isset( $labels[ get_the_ID() ] ) ) : ?>
						<p class="wmpgf-grid__category"><?php echo esc_html( $labels[ get_the_ID() ]->name ); ?></p>
					<?php endif; ?>
					<h3 class="wmpgf-grid__title">
						<?php // Its ::after covers the card, so the whole card is one link. ?>
						<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					</h3>
					<div class="wmpgf-grid__excerpt"><?php the_excerpt(); ?></div>
				</article>
				<?php
				++$card_index;
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
