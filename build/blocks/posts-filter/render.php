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
	: __( 'Filter posts', 'posts-grid-filter' );

$selected_categories = PGF_Blocks::get_requested_term_ids( PGF_Blocks::CATEGORY_PARAM );
$selected_tags        = PGF_Blocks::get_requested_term_ids( PGF_Blocks::TAG_PARAM );

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
	<h3 class="pgf-filter__heading"><?php echo esc_html( $heading ); ?></h3>

	<?php if ( ! is_wp_error( $categories ) && $categories ) : ?>
		<fieldset class="pgf-filter__group">
			<legend><?php esc_html_e( 'Categories', 'posts-grid-filter' ); ?></legend>
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
		</fieldset>
	<?php endif; ?>

	<?php if ( ! is_wp_error( $tags ) && $tags ) : ?>
		<fieldset class="pgf-filter__group">
			<legend><?php esc_html_e( 'Tags', 'posts-grid-filter' ); ?></legend>
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
		</fieldset>
	<?php endif; ?>
</div>
