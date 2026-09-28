import './style.css';
import { __, sprintf } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

export default function Edit( { context } ) {
	const postsPerPage = context[ 'posts-grid-filter/postsPerPage' ] || 6;

	const totalPages = useSelect(
		( select ) => {
			const query = { per_page: postsPerPage, _fields: 'id' };
			const { getEntityRecords, getEntityRecordsTotalPages } =
				select( coreStore );
			// The total-pages selector is filled in by this records request.
			getEntityRecords( 'postType', 'pgf_post', query );
			return getEntityRecordsTotalPages( 'postType', 'pgf_post', query );
		},
		[ postsPerPage ]
	);

	const isSinglePage = totalPages !== null && totalPages <= 1;
	const blockProps = useBlockProps( { className: 'pgf-pagination' } );

	return (
		<div { ...blockProps }>
			<span className="pgf-pagination__prev is-disabled">
				{ __( '‹ Prev', 'wm-posts-grid-filter' ) }
			</span>
			<span className="pgf-pagination__status">
				{ sprintf(
					/* translators: 1: current page, 2: total pages */
					__( 'Page %1$d of %2$d', 'wm-posts-grid-filter' ),
					1,
					totalPages || 1
				) }
			</span>
			<span
				className={
					'pgf-pagination__next' +
					( isSinglePage ? ' is-disabled' : '' )
				}
			>
				{ __( 'Next ›', 'wm-posts-grid-filter' ) }
			</span>
			{ isSinglePage && (
				<p className="pgf-pagination__note">
					{ __(
						'Hidden on the site while all posts fit on one page.',
						'wm-posts-grid-filter'
					) }
				</p>
			) }
		</div>
	);
}
