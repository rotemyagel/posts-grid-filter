import './style.css';
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

// Same terms render.php lists: every non-empty term, ordered by name.
const TERMS_QUERY = { per_page: -1, hide_empty: true, _fields: 'id,name' };

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
	const { heading } = attributes;
	const blockProps = useBlockProps( { className: 'wmpgf-filter' } );

	const { categories, tags } = useSelect( ( select ) => {
		const { getEntityRecords } = select( coreStore );
		return {
			categories: getEntityRecords(
				'taxonomy',
				'wmpgf_category',
				TERMS_QUERY
			),
			tags: getEntityRecords( 'taxonomy', 'wmpgf_tag', TERMS_QUERY ),
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
						label={ __( 'Heading', 'wm-posts-grid-filter' ) }
						value={ heading }
						onChange={ ( value ) =>
							setAttributes( { heading: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="wmpgf-filter__head">
					<h3 className="wmpgf-filter__heading">{ heading }</h3>
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
				<TermGroup
					legend={ __( 'Categories', 'wm-posts-grid-filter' ) }
					terms={ categories }
				/>
				<TermGroup
					legend={ __( 'Tags', 'wm-posts-grid-filter' ) }
					terms={ tags }
				/>
			</div>
		</>
	);
}
