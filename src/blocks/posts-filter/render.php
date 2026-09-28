<?php
/**
 * Server-side render for pgf/posts-filter.
 *
 * Renders one checkbox per pgf_category and pgf_tag term. Each label
 * carries the term ID via data-wp-context so the shared actions know which
 * checkbox changed; selection state itself lives in the shared
 * posts-grid-filter store (selectedCategories/selectedTags), not locally,
 * so it survives this block being placed anywhere on the page relative to
 * the grid.
 *
 * Checkboxes matching the current ?pgf_category=/?pgf_tag= URL parameters
 * are marked checked() here on the server, so a shared/bookmarked filtered
 * link (reached via a pagination full-page reload -- see
 * pagination/render.php) shows the correct selection immediately, before
 * any JavaScript has run.
 *
 * The "Clear filters" link is shown/hidden the same dual way: PHP decides
 * whether to render it visible on first paint (based on whether the current
 * URL has any filter selected), and a matching client-side getter
 * (state.hideClearFilters in view.js) keeps it correct as checkboxes are
 * toggled afterward without a page reload.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner content (none, no children).
 * @var WP_Block $block      Block instance.
 *
 * @package PostsGridFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$heading = isset( $attributes['heading'] ) && '' !== $attributes['heading']
	? $attributes['heading']
	: __( 'Filter posts', 'rotem-posts-grid-filter' );

$selected_categories = PGF_Blocks::get_requested_term_ids( PGF_Blocks::CATEGORY_PARAM );
$selected_tags        = PGF_Blocks::get_requested_term_ids( PGF_Blocks::TAG_PARAM );
$has_active_filters   = $selected_categories || $selected_tags;

/*
 * A real, crawlable link to the unfiltered page -- not just a JS-only
 * reset -- built the same way pagination's Prev/Next hrefs are (see its
 * render.php), so a no-JS visitor or a shared/bookmarked filtered link
 * still gets a working way back to the unfiltered grid. The click is
 * additionally intercepted client-side (see actions.clearFilters in
 * view.js) so a JS-enabled visitor gets the same instant, no-reload
 * behavior as toggling a checkbox, instead of a full page reload.
 */
$clear_url = remove_query_arg( array( PGF_Blocks::CATEGORY_PARAM, PGF_Blocks::TAG_PARAM, PGF_Blocks::PAGE_PARAM ) );

$categories = get_terms(
	array(
		'taxonomy'   => PGF_Post_Type::TAX_CATEGORY,
		'hide_empty' => true,
	)
);

$tags = get_terms(
	array(
		'taxonomy'   => PGF_Post_Type::TAX_TAG,
		'hide_empty' => true,
	)
);

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'pgf-filter' ) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-interactive="posts-grid-filter">
	<div class="pgf-filter__head">
		<h3 class="pgf-filter__heading"><?php echo esc_html( $heading ); ?></h3>
		<a
			href="<?php echo esc_url( $clear_url ); ?>"
			class="pgf-filter__clear"
			<?php echo $has_active_filters ? '' : 'hidden'; ?>
			data-wp-bind--hidden="state.hideClearFilters"
			data-wp-on--click="actions.clearFilters"
		>
			<?php esc_html_e( 'Clear filters', 'rotem-posts-grid-filter' ); ?>
		</a>
	</div>

	<?php if ( ! is_wp_error( $categories ) && $categories ) : ?>
		<fieldset class="pgf-filter__group">
			<legend><?php esc_html_e( 'Categories', 'rotem-posts-grid-filter' ); ?></legend>
			<div class="pgf-filter__options">
				<?php foreach ( $categories as $term ) : ?>
					<label
						class="pgf-filter__option"
						data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'termId' => (int) $term->term_id ) ) ); ?>'
					>
						<input
							type="checkbox"
							<?php checked( in_array( (int) $term->term_id, $selected_categories, true ) ); ?>
							data-wp-on--change="actions.toggleCategory"
							data-wp-bind--checked="state.isCategoryChecked"
						/>
						<?php echo esc_html( $term->name ); ?>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>
	<?php endif; ?>

	<?php if ( ! is_wp_error( $tags ) && $tags ) : ?>
		<fieldset class="pgf-filter__group">
			<legend><?php esc_html_e( 'Tags', 'rotem-posts-grid-filter' ); ?></legend>
			<div class="pgf-filter__options">
				<?php foreach ( $tags as $term ) : ?>
					<label
						class="pgf-filter__option"
						data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'termId' => (int) $term->term_id ) ) ); ?>'
					>
						<input
							type="checkbox"
							<?php checked( in_array( (int) $term->term_id, $selected_tags, true ) ); ?>
							data-wp-on--change="actions.toggleTag"
							data-wp-bind--checked="state.isTagChecked"
						/>
						<?php echo esc_html( $term->name ); ?>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>
	<?php endif; ?>
</div>
