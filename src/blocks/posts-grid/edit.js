import './style.css';
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, RangeControl, ButtonGroup, Button } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

const TEMPLATE = [ [ 'pgf/pagination', {} ] ];

export default function Edit( { attributes, setAttributes } ) {
	const { columns, postsPerPage } = attributes;

	const blockProps = useBlockProps();
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'pgf-grid__pagination-slot' },
		{ template: TEMPLATE, templateLock: 'all' }
	);

	const posts = useSelect(
		( select ) =>
			select( coreStore ).getEntityRecords( 'postType', 'pgf_post', {
				per_page: postsPerPage,
				_embed: true,
			} ),
		[ postsPerPage ]
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Grid settings', 'posts-grid-filter' ) }>
					<p className="pgf-columns-label">{ __( 'Columns', 'posts-grid-filter' ) }</p>
					<ButtonGroup>
						{ [ 2, 3, 4 ].map( ( n ) => (
							<Button
								key={ n }
								variant={ columns === n ? 'primary' : 'secondary' }
								onClick={ () => setAttributes( { columns: n } ) }
							>
								{ n }
							</Button>
						) ) }
					</ButtonGroup>
					<RangeControl
						label={ __( 'Posts per page', 'posts-grid-filter' ) }
						min={ 1 }
						max={ 24 }
						value={ postsPerPage }
						onChange={ ( value ) => setAttributes( { postsPerPage: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className={ `pgf-grid pgf-grid--cols-${ columns }` }>
					{ ! posts && <p>{ __( 'Loading…', 'posts-grid-filter' ) }</p> }
					{ posts && posts.length === 0 && (
						<p>{ __( 'No grid posts yet.', 'posts-grid-filter' ) }</p>
					) }
					{ posts &&
						posts.map( ( post ) => {
							const image =
								post._embedded?.[ 'wp:featuredmedia' ]?.[ 0 ]?.source_url;
							return (
								<div className="pgf-grid__card" key={ post.id }>
									{ image && <img src={ image } alt="" /> }
									<h3
										dangerouslySetInnerHTML={ {
											__html: post.title?.rendered,
										} }
									/>
									<div
										dangerouslySetInnerHTML={ {
											__html: post.excerpt?.rendered,
										} }
									/>
								</div>
							);
						} ) }
				</div>
				<div { ...innerBlocksProps } />
			</div>
		</>
	);
}
