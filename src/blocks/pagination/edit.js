import './style.css';
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

export default function Edit() {
	const blockProps = useBlockProps( { className: 'pgf-pagination' } );

	return (
		<div { ...blockProps }>
			<button type="button" disabled>
				{ __( '‹ Prev', 'wm-posts-grid-filter' ) }
			</button>
			<span>{ __( 'Page 1 of 1', 'wm-posts-grid-filter' ) }</span>
			<button type="button" disabled>
				{ __( 'Next ›', 'wm-posts-grid-filter' ) }
			</button>
		</div>
	);
}
