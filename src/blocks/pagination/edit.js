import './style.css';
import { __, sprintf } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

export default function Edit( { attributes, setAttributes, context } ) {
	const { showPerPage } = attributes;
	const postsPerPage = context[ 'wmpgf/postsPerPage' ] || 6;

	const totalPages = useSelect(
		( select ) => {
			const query = { per_page: postsPerPage, _fields: 'id' };
			const { getEntityRecords, getEntityRecordsTotalPages } =
				select( coreStore );
			// The total-pages selector is filled in by this records request.
			getEntityRecords( 'postType', 'wmpgf_post', query );
			return getEntityRecordsTotalPages(
				'postType',
				'wmpgf_post',
				query
			);
		},
		[ postsPerPage ]
	);

	const isSinglePage = totalPages !== null && totalPages <= 1;
	const blockProps = useBlockProps( { className: 'wmpgf-pagination' } );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __(
						'Pagination settings',
						'wm-posts-grid-filter'
					) }
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show posts per page',
							'wm-posts-grid-filter'
						) }
						help={ __(
							'Lets visitors choose 6, 12 or 24 posts per page.',
							'wm-posts-grid-filter'
						) }
						checked={ showPerPage }
						onChange={ ( value ) =>
							setAttributes( { showPerPage: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="wmpgf-pagination__nav">
					<span className="wmpgf-pagination__link wmpgf-pagination__prev is-disabled">
						{ __( '‹ Prev', 'wm-posts-grid-filter' ) }
					</span>
					<span className="wmpgf-pagination__status">
						{ sprintf(
							/* translators: 1: current page, 2: total pages */
							__( 'Page %1$d of %2$d', 'wm-posts-grid-filter' ),
							1,
							totalPages || 1
						) }
					</span>
					<span
						className={
							'wmpgf-pagination__link wmpgf-pagination__next' +
							( isSinglePage ? ' is-disabled' : '' )
						}
					>
						{ __( 'Next ›', 'wm-posts-grid-filter' ) }
					</span>
				</div>
				{ showPerPage && (
					<div className="wmpgf-pagination__per-page">
						<label htmlFor="wmpgf-per-page-preview">
							{ __( 'Posts per page', 'wm-posts-grid-filter' ) }
						</label>
						<select id="wmpgf-per-page-preview" disabled>
							<option>{ postsPerPage }</option>
						</select>
					</div>
				) }
				{ isSinglePage && (
					<p className="wmpgf-pagination__note">
						{ __(
							'Prev/Next are hidden on the site while all posts fit on one page.',
							'wm-posts-grid-filter'
						) }
					</p>
				) }
			</div>
		</>
	);
}
