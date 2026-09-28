import './style.css';
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const { heading } = attributes;
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Filter settings', 'rotem-posts-grid-filter' ) }>
					<TextControl
						label={ __( 'Heading', 'rotem-posts-grid-filter' ) }
						value={ heading }
						onChange={ ( value ) => setAttributes( { heading: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<h3>{ heading }</h3>
				<p>
					<em>
						{ __(
							'Category and tag checkboxes render on the frontend, populated from your Grid Categories / Grid Tags.',
							'rotem-posts-grid-filter'
						) }
					</em>
				</p>
			</div>
		</>
	);
}
