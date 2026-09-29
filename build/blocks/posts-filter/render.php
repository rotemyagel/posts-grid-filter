<?php
/**
 * Server-side render for wmpgf/posts-filter.
 *
 * One checkbox per term, pre-checked from the URL. The selection lives in
 * the shared wmpgf store, which is what lets this block sit anywhere on the
 * page: changing it builds a new URL and the Interactivity Router renders
 * the grid for that URL on the server. Without JavaScript the checkboxes
 * submit as a plain GET form via a <noscript> "Apply filters" button.
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

$heading     = $attributes['heading'] ?? '';
$show_search = $attributes['showSearch'] ?? true;
$show_scheme = $attributes['showColorSchemeToggle'] ?? true;
$filters     = WMPGF_Request::filters();
$count       = WMPGF_Query::count( $filters );
$count_label = sprintf(
	/* translators: %s: number of matching posts. */
	_n( '%s post', '%s posts', $count, 'wm-posts-grid-filter' ),
	number_format_i18n( $count )
);

// Initial values are printed by PHP below (for first paint and no-JS);
// the data-wp-* directives take over once the store hydrates.
wp_interactivity_config(
	'wmpgf',
	array(
		'params'      => WMPGF_Request::params(),
		'searchDelay' => 300,
		'colorScheme' => WMPGF_Blocks::color_scheme_config(),
	)
);
wp_interactivity_state(
	'wmpgf',
	array(
		'selectedCategories' => $filters['categories'],
		'selectedTags'       => $filters['tags'],
		'search'             => $filters['search'],
		'resultsLabel'       => $count_label,
		// Set in the browser, which alone knows the saved choice and the
		// device setting; empty keeps the switch hidden until then.
		'colorScheme'        => '',
	)
);

$groups = array(
	array(
		'legend'   => __( 'Categories', 'wm-posts-grid-filter' ),
		'taxonomy' => WMPGF_Post_Type::TAX_CATEGORY,
		'param'    => WMPGF_Request::CATEGORY_PARAM,
		'selected' => $filters['categories'],
		'action'   => 'actions.toggleCategory',
		'checked'  => 'state.isCategoryChecked',
	),
	array(
		'legend'   => __( 'Tags', 'wm-posts-grid-filter' ),
		'taxonomy' => WMPGF_Post_Type::TAX_TAG,
		'param'    => WMPGF_Request::TAG_PARAM,
		'selected' => $filters['tags'],
		'action'   => 'actions.toggleTag',
		'checked'  => 'state.isTagChecked',
	),
);

// The form sets these itself; with search hidden, a search already in the
// URL is kept as a hidden input instead.
$own_params = array( WMPGF_Request::PAGE_PARAM, WMPGF_Request::CATEGORY_PARAM, WMPGF_Request::TAG_PARAM );
if ( $show_search ) {
	$own_params[] = WMPGF_Request::SEARCH_PARAM;
}

$clear_url          = WMPGF_Request::url(
	array(
		'categories' => array(),
		'tags'       => array(),
		'search'     => '',
	)
);
$has_active_filters = $filters['categories'] || $filters['tags'] || '' !== $filters['search'];
$search_id          = wp_unique_id( 'wmpgf-search-' );

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'wmpgf-filter' ) );
?>
<div
	<?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wmpgf"
	data-wp-watch="callbacks.syncFromServer"
>
	<?php WMPGF_Blocks::print_color_scheme_script(); ?>
	<?php if ( '' !== $heading ) : ?>
		<h3 class="wmpgf-filter__heading"><?php echo esc_html( $heading ); ?></h3>
	<?php endif; ?>

	<form
		class="wmpgf-filter__form"
		method="get"
		action="<?php echo esc_url( strtok( $clear_url, '?' ) ); ?>"
		role="search"
		data-wp-on--submit="actions.submitSearch"
	>
		<?php WMPGF_Request::hidden_inputs( $own_params ); ?>

		<div class="wmpgf-filter__bar">
			<?php if ( $show_search ) : ?>
				<label class="wmpgf-sr-only" for="<?php echo esc_attr( $search_id ); ?>"><?php esc_html_e( 'Search posts', 'wm-posts-grid-filter' ); ?></label>
				<input
					type="search"
					id="<?php echo esc_attr( $search_id ); ?>"
					class="wmpgf-filter__search"
					name="<?php echo esc_attr( WMPGF_Request::SEARCH_PARAM ); ?>"
					value="<?php echo esc_attr( $filters['search'] ); ?>"
					placeholder="<?php esc_attr_e( 'Search posts', 'wm-posts-grid-filter' ); ?>"
					maxlength="<?php echo (int) WMPGF_Request::MAX_SEARCH_LENGTH; ?>"
					autocomplete="off"
					data-wp-on-async--input="actions.updateSearch"
					data-wp-bind--value="state.search"
				/>
			<?php endif; ?>
			<p class="wmpgf-filter__count" aria-live="polite" data-wp-text="state.resultsLabel"><?php echo esc_html( $count_label ); ?></p>
			<a
				href="<?php echo esc_url( $clear_url ); ?>"
				class="wmpgf-filter__clear"
				<?php echo $has_active_filters ? '' : 'hidden'; ?>
				data-wp-bind--hidden="state.hideClearFilters"
				data-wp-on--click="actions.clearFilters"
			><?php esc_html_e( 'Clear filters', 'wm-posts-grid-filter' ); ?></a>
			<?php if ( $show_scheme ) : ?>
				<?php // Hidden until the script runs: without it the switch can't work. ?>
				<button
					type="button"
					class="wmpgf-filter__scheme"
					aria-label="<?php esc_attr_e( 'Dark mode', 'wm-posts-grid-filter' ); ?>"
					title="<?php esc_attr_e( 'Dark mode', 'wm-posts-grid-filter' ); ?>"
					aria-pressed="false"
					hidden
					data-wp-init="callbacks.initColorScheme"
					data-wp-bind--hidden="!state.colorScheme"
					data-wp-bind--aria-pressed="state.isDark"
					data-wp-on-async--click="actions.toggleColorScheme"
				>
					<?php // Moon in light mode (click for dark), sun in dark mode (click for light). ?>
					<svg class="wmpgf-filter__scheme-icon wmpgf-filter__scheme-icon--moon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M20.5 14.6A8.5 8.5 0 0 1 9.4 3.5a8.5 8.5 0 1 0 11.1 11.1z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
					<svg class="wmpgf-filter__scheme-icon wmpgf-filter__scheme-icon--sun" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4.2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 2.5v2.2M12 19.3v2.2M2.5 12h2.2M19.3 12h2.2M5.3 5.3l1.6 1.6M17.1 17.1l1.6 1.6M5.3 18.7l1.6-1.6M17.1 6.9l1.6-1.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
				</button>
			<?php endif; ?>
		</div>

		<div class="wmpgf-filter__groups">
		<?php foreach ( $groups as $group ) : ?>
			<?php
			$group_terms = get_terms(
				array(
					'taxonomy'   => $group['taxonomy'],
					'hide_empty' => true,
				)
			);
			if ( is_wp_error( $group_terms ) || ! $group_terms ) {
				continue;
			}
			?>
			<?php // One change handler per group: a checkbox's change event bubbles up to it. ?>
			<fieldset class="wmpgf-filter__group" data-wp-on-async--change="<?php echo esc_attr( $group['action'] ); ?>">
				<legend><?php echo esc_html( $group['legend'] ); ?></legend>
				<div class="wmpgf-filter__options">
					<?php foreach ( $group_terms as $group_term ) : ?>
						<label class="wmpgf-filter__option">
							<input
								type="checkbox"
								name="<?php echo esc_attr( $group['param'] ); ?>[]"
								value="<?php echo esc_attr( $group_term->slug ); ?>"
								<?php checked( in_array( $group_term->slug, $group['selected'], true ) ); ?>
								data-wp-bind--checked="<?php echo esc_attr( $group['checked'] ); ?>"
							/>
							<?php echo esc_html( $group_term->name ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>
		<?php endforeach; ?>
		</div>

		<noscript>
			<button type="submit" class="wmpgf-filter__submit"><?php esc_html_e( 'Apply filters', 'wm-posts-grid-filter' ); ?></button>
		</noscript>
	</form>
</div>
