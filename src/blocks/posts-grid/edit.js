import './style.css';
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	Disabled,
	PanelBody,
	RangeControl,
	// Still only exported under the experimental name, up to WordPress 7.1.
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControl as ToggleGroupControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

const TEMPLATE = [ [ 'wmpgf/pagination', {} ] ];

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
					<ToggleGroupControl
						label={ __( 'Columns', 'wm-posts-grid-filter' ) }
						value={ columns }
						onChange={ ( value ) =>
							setAttributes( { columns: Number( value ) } )
						}
						isBlock
					>
						{ [ 2, 3, 4 ].map( ( n ) => (
							<ToggleGroupControlOption
								key={ n }
								value={ n }
								label={ String( n ) }
							/>
						) ) }
					</ToggleGroupControl>
					<RangeControl
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
				{ /* The cards come from render.php, the same template the site uses. */ }
				<Disabled>
					<ServerSideRender
						block="wmpgf/posts-grid"
						attributes={ { columns, postsPerPage } }
						skipBlockSupportAttributes
					/>
				</Disabled>
				<div { ...innerBlocksProps } />
			</div>
		</>
	);
}
