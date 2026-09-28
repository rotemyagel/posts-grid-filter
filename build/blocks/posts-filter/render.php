<?php
/**
 * Server-side render for wmpgf/posts-filter.
 *
 * One checkbox per term, pre-checked from the URL. The selection lives in
 * the shared wmpgf store, which is what lets this block sit
 * anywhere on the page. Without JavaScript the checkboxes submit as a
 * plain GET form via a <noscript> "Apply filters" button.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner content (none, no children).
 * @var WP_Block $block      Block instance.
 *
 * @package WMPGF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$heading = isset( $attributes['heading'] ) && '' !== $attributes['heading']
	? $attributes['heading']
	: __( 'Filter posts', 'wm-posts-grid-filter' );

$selected_categories = WMPGF_Request::term_ids( WMPGF_Request::CATEGORY_PARAM );
$selected_tags       = WMPGF_Request::term_ids( WMPGF_Request::TAG_PARAM );
$has_active_filters  = $selected_categories || $selected_tags;

// For the address-bar sync in view.js, which works with term IDs.
wp_interactivity_state( 'wmpgf', array( 'termSlugs' => WMPGF_Request::term_slug_map() ) );

// A real link for no-JS; view.js intercepts it to clear without a reload.
$clear_url = WMPGF_Request::page_url( 1, array(), array() );

// A GET form replaces the whole query string, so other params (e.g.
// ?page_id= under plain permalinks) are carried over as hidden inputs.
$form_action     = strtok( $clear_url, '?' );
$preserved_query = array();
wp_parse_str( (string) wp_parse_url( $clear_url, PHP_URL_QUERY ), $preserved_query );

$categories = get_terms(
	array(
		'taxonomy'   => WMPGF_Post_Type::TAX_CATEGORY,
		'hide_empty' => true,
	)
);

$tags = get_terms(
	array(
		'taxonomy'   => WMPGF_Post_Type::TAX_TAG,
		'hide_empty' => true,
	)
);

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'wmpgf-filter' ) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-interactive="wmpgf">
	<div class="wmpgf-filter__head">
		<h3 class="wmpgf-filter__heading"><?php echo esc_html( $heading ); ?></h3>
		<a
			href="<?php echo esc_url( $clear_url ); ?>"
			class="wmpgf-filter__clear"
			<?php echo $has_active_filters ? '' : 'hidden'; ?>
			data-wp-bind--hidden="state.hideClearFilters"
			data-wp-on--click="actions.clearFilters"
		>
			<?php esc_html_e( 'Clear filters', 'wm-posts-grid-filter' ); ?>
		</a>
	</div>

	<form class="wmpgf-filter__form" method="get" action="<?php echo esc_url( $form_action ); ?>">
		<?php foreach ( $preserved_query as $query_key => $query_value ) : ?>
			<?php if ( is_scalar( $query_value ) ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $query_key ); ?>" value="<?php echo esc_attr( $query_value ); ?>" />
			<?php endif; ?>
		<?php endforeach; ?>

		<?php if ( ! is_wp_error( $categories ) && $categories ) : ?>
			<fieldset class="wmpgf-filter__group">
				<legend><?php esc_html_e( 'Categories', 'wm-posts-grid-filter' ); ?></legend>
				<div class="wmpgf-filter__options">
					<?php foreach ( $categories as $category_term ) : ?>
						<label
							class="wmpgf-filter__option"
							data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'termId' => (int) $category_term->term_id ) ) ); ?>'
						>
							<input
								type="checkbox"
								name="<?php echo esc_attr( WMPGF_Request::CATEGORY_PARAM ); ?>[]"
								value="<?php echo esc_attr( $category_term->slug ); ?>"
								<?php checked( in_array( (int) $category_term->term_id, $selected_categories, true ) ); ?>
								data-wp-on--change="actions.toggleCategory"
								data-wp-bind--checked="state.isCategoryChecked"
							/>
							<?php echo esc_html( $category_term->name ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>
		<?php endif; ?>

		<?php if ( ! is_wp_error( $tags ) && $tags ) : ?>
			<fieldset class="wmpgf-filter__group">
				<legend><?php esc_html_e( 'Tags', 'wm-posts-grid-filter' ); ?></legend>
				<div class="wmpgf-filter__options">
					<?php foreach ( $tags as $tag_term ) : ?>
						<label
							class="wmpgf-filter__option"
							data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'termId' => (int) $tag_term->term_id ) ) ); ?>'
						>
							<input
								type="checkbox"
								name="<?php echo esc_attr( WMPGF_Request::TAG_PARAM ); ?>[]"
								value="<?php echo esc_attr( $tag_term->slug ); ?>"
								<?php checked( in_array( (int) $tag_term->term_id, $selected_tags, true ) ); ?>
								data-wp-on--change="actions.toggleTag"
								data-wp-bind--checked="state.isTagChecked"
							/>
							<?php echo esc_html( $tag_term->name ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>
		<?php endif; ?>

		<noscript>
			<button type="submit" class="wmpgf-filter__submit"><?php esc_html_e( 'Apply filters', 'wm-posts-grid-filter' ); ?></button>
		</noscript>
	</form>
</div>
