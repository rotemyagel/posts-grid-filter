/**
 * The grid's markup in the editor. It mirrors the card in render.php
 * class for class, so style.css styles both; test/grid-preview.js fails
 * if the two templates stop using the same classes.
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';

/**
 * A card image that loads lazily and shimmers until it has loaded, like
 * the frontend's. Decorative, as in render.php: the title says the same.
 *
 * @param {Object} props
 * @param {string} props.src Image URL.
 * @return {Element} Thumbnail.
 */
function Thumb( { src } ) {
	const [ loaded, setLoaded ] = useState( false );
	const done = () => setLoaded( true );

	return (
		<div className={ `wmpgf-grid__thumb${ loaded ? '' : ' is-loading' }` }>
			<img
				src={ src }
				alt=""
				loading="lazy"
				decoding="async"
				onLoad={ done }
				onError={ done }
			/>
		</div>
	);
}

/**
 * @typedef {Object} PreviewCard
 * @property {number} id       Post ID.
 * @property {string} link     Permalink.
 * @property {string} title    Plain-text title.
 * @property {string} excerpt  Plain-text excerpt.
 * @property {string} imageUrl Featured image URL, or '' for none.
 * @property {string} category Primary category name, or '' for none.
 */

/**
 * @param {Object}        props
 * @param {PreviewCard[]} props.cards   Posts to show.
 * @param {number}        props.columns 2, 3 or 4.
 * @return {Element} Grid markup.
 */
export default function GridPreview( { cards, columns } ) {
	return (
		<div className={ `wmpgf-grid wmpgf-grid--cols-${ columns }` }>
			{ cards.length ? (
				cards.map( ( card ) => (
					<article key={ card.id } className="wmpgf-grid__card">
						{ card.imageUrl && <Thumb src={ card.imageUrl } /> }
						{ card.category && (
							<p className="wmpgf-grid__category">
								{ card.category }
							</p>
						) }
						<h3 className="wmpgf-grid__title">
							<a href={ card.link }>{ card.title }</a>
						</h3>
						<div className="wmpgf-grid__excerpt">
							<p>{ card.excerpt }</p>
						</div>
					</article>
				) )
			) : (
				<p className="wmpgf-grid__empty">
					{ __(
						'No Grid Posts to show yet.',
						'wm-posts-grid-filter'
					) }
				</p>
			) }
		</div>
	);
}
