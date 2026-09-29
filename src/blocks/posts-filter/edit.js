import './style.css';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	ToggleControl,
	Spinner,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

// Same terms render.php lists: every non-empty term, ordered by name.
const TERMS_QUERY = { per_page: -1, hide_empty: true, _fields: 'id,name' };
const COUNT_QUERY = { per_page: 1, _fields: 'id' };

function TermGroup( { legend, terms } ) {
	if ( ! terms?.length ) {
		return null;
	}

	return (
		<fieldset className="wmpgf-filter__group">
			<legend>{ legend }</legend>
			<div className="wmpgf-filter__options">
				{ /* Display-only pills: selecting happens on the published page. */ }
				{ terms.map( ( term ) => (
					<span className="wmpgf-filter__option" key={ term.id }>
						{ term.name }
					</span>
				) ) }
			</div>
		</fieldset>
	);
}

export default function Edit( { attributes, setAttributes } ) {
	const { heading, showSearch, showColorSchemeToggle } = attributes;
	const blockProps = useBlockProps( { className: 'wmpgf-filter' } );

	const { categories, tags, total } = useSelect( ( select ) => {
		const { getEntityRecords, getEntityRecordsTotalItems } =
			select( coreStore );
		// The total selector is filled in by this records request.
		getEntityRecords( 'postType', 'wmpgf_post', COUNT_QUERY );
		return {
			categories: getEntityRecords(
				'taxonomy',
				'wmpgf_category',
				TERMS_QUERY
			),
			tags: getEntityRecords( 'taxonomy', 'wmpgf_tag', TERMS_QUERY ),
			total: getEntityRecordsTotalItems(
				'postType',
				'wmpgf_post',
				COUNT_QUERY
			),
		};
	}, [] );

	const isLoading = categories === null || tags === null;
	const isEmpty = ! isLoading && ! categories?.length && ! tags?.length;

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Filter settings', 'wm-posts-grid-filter' ) }
				>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Heading', 'wm-posts-grid-filter' ) }
						help={ __(
							'Optional. Leave empty for no heading.',
							'wm-posts-grid-filter'
						) }
						value={ heading }
						onChange={ ( value ) =>
							setAttributes( { heading: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show search', 'wm-posts-grid-filter' ) }
						checked={ showSearch }
						onChange={ ( value ) =>
							setAttributes( { showSearch: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show light/dark switch',
							'wm-posts-grid-filter'
						) }
						help={ __(
							'Without a choice, the blocks follow the visitor’s device setting.',
							'wm-posts-grid-filter'
						) }
						checked={ showColorSchemeToggle }
						onChange={ ( value ) =>
							setAttributes( { showColorSchemeToggle: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ heading && (
					<h3 className="wmpgf-filter__heading">{ heading }</h3>
				) }
				<div className="wmpgf-filter__form">
					<div className="wmpgf-filter__bar">
						{ showSearch && (
							<input
								type="search"
								className="wmpgf-filter__search"
								placeholder={ __(
									'Search posts',
									'wm-posts-grid-filter'
								) }
								aria-label={ __(
									'Search posts',
									'wm-posts-grid-filter'
								) }
								disabled
							/>
						) }
						{ total !== null && (
							<p className="wmpgf-filter__count">
								{ sprintf(
									/* translators: %s: number of posts. */
									_n(
										'%s post',
										'%s posts',
										total,
										'wm-posts-grid-filter'
									),
									total
								) }
							</p>
						) }
						{ showColorSchemeToggle && (
							// Display-only: the switch works on the published page.
							<span
								className="wmpgf-filter__scheme"
								aria-hidden="true"
							>
								<svg
									className="wmpgf-filter__scheme-icon"
									viewBox="0 0 24 24"
									width="18"
									height="18"
								>
									<path
										d="M20.5 14.6A8.5 8.5 0 0 1 9.4 3.5a8.5 8.5 0 1 0 11.1 11.1z"
										fill="none"
										stroke="currentColor"
										strokeWidth="1.8"
										strokeLinejoin="round"
									/>
								</svg>
							</span>
						) }
					</div>
					{ isLoading && <Spinner /> }
					{ isEmpty && (
						<p>
							{ __(
								'No Grid Categories or Grid Tags with posts yet.',
								'wm-posts-grid-filter'
							) }
						</p>
					) }
					<div className="wmpgf-filter__groups">
						<TermGroup
							legend={ __(
								'Categories',
								'wm-posts-grid-filter'
							) }
							terms={ categories }
						/>
						<TermGroup
							legend={ __( 'Tags', 'wm-posts-grid-filter' ) }
							terms={ tags }
						/>
					</div>
				</div>
			</div>
		</>
	);
}
