import './style.css';
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
} from '@wordpress/block-editor';
import { Disabled, PanelBody, RangeControl } from '@wordpress/components';
import { useSuspenseSelect } from '@wordpress/data';
import { Suspense, useMemo } from '@wordpress/element';
import { store as coreStore } from '@wordpress/core-data';
import { decodeEntities } from '@wordpress/html-entities';
import GridPreview from './grid-preview';
import GridSkeleton from './grid-skeleton';

const TEMPLATE = [ [ 'wmpgf/pagination', {} ] ];
const CATEGORY_QUERY = { per_page: -1, _fields: 'id,name' };

/**
 * Plain text of a rendered excerpt, which the REST API returns as HTML.
 *
 * @param {string} html Rendered excerpt.
 * @return {string} Text.
 */
const toText = ( html ) =>
	new window.DOMParser()
		.parseFromString( html, 'text/html' )
		.body.textContent.trim();

/**
 * The preview's posts, read with useSuspenseSelect: until the posts, their
 * categories and their images have all loaded, it suspends, and the
 * nearest <Suspense> shows its fallback instead.
 *
 * @param {Object} props
 * @param {number} props.columns      2, 3 or 4.
 * @param {number} props.postsPerPage Page size.
 * @return {Element} Grid preview.
 */
function PreviewCards( { columns, postsPerPage } ) {
	// The first page of the unfiltered grid, as a visitor first sees it.
	// Filters and pages only exist on the frontend, in the URL. Only store
	// values are returned here, so the result is stable between calls.
	const { posts, categories, images } = useSuspenseSelect(
		( select ) => {
			const { getEntityRecords } = select( coreStore );
			const records = getEntityRecords( 'postType', 'wmpgf_post', {
				per_page: postsPerPage,
				_fields: 'id,link,title,excerpt,featured_media,wmpgf_category',
			} );
			const imageIds = ( records ?? [] )
				.map( ( post ) => post.featured_media )
				.filter( Boolean );

			return {
				posts: records,
				categories: getEntityRecords(
					'taxonomy',
					'wmpgf_category',
					CATEGORY_QUERY
				),
				images: imageIds.length
					? getEntityRecords( 'postType', 'attachment', {
							include: imageIds,
							per_page: imageIds.length,
							_fields: 'id,source_url,media_details',
							context: 'view',
					  } )
					: null,
			};
		},
		[ postsPerPage ]
	);

	const cards = useMemo( () => {
		const find = ( list, id ) => list?.find( ( item ) => item.id === id );

		return posts.map( ( post ) => {
			const image = find( images, post.featured_media );
			return {
				id: post.id,
				link: post.link,
				title: decodeEntities( post.title.rendered ),
				excerpt: toText( post.excerpt.rendered ),
				imageUrl:
					image?.media_details?.sizes?.medium?.source_url ??
					image?.source_url ??
					'',
				// Categories come back in the order they were assigned, so
				// the first is the primary one, as on the frontend.
				category:
					find( categories, post.wmpgf_category?.[ 0 ] )?.name ?? '',
			};
		} );
	}, [ posts, categories, images ] );

	return <GridPreview cards={ cards } columns={ columns } />;
}

export default function Edit( { attributes, setAttributes } ) {
	const { columns, postsPerPage } = attributes;

	const blockProps = useBlockProps();
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'wmpgf-grid__pagination-slot' },
		{ template: TEMPLATE, templateLock: 'all' }
	);

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Grid settings', 'wm-posts-grid-filter' ) }
				>
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Columns', 'wm-posts-grid-filter' ) }
						min={ 2 }
						max={ 4 }
						value={ columns }
						onChange={ ( value ) =>
							setAttributes( { columns: value } )
						}
					/>
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Posts per page', 'wm-posts-grid-filter' ) }
						min={ 1 }
						max={ 24 }
						value={ postsPerPage }
						onChange={ ( value ) =>
							setAttributes( { postsPerPage: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ /* Disabled: a click on a card selects the block instead of
				   following the link. */ }
				<Disabled>
					<Suspense
						fallback={
							<GridSkeleton
								columns={ columns }
								count={ postsPerPage }
							/>
						}
					>
						<PreviewCards
							columns={ columns }
							postsPerPage={ postsPerPage }
						/>
					</Suspense>
				</Disabled>
				<div { ...innerBlocksProps } />
			</div>
		</>
	);
}
