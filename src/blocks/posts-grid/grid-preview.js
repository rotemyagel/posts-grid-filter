/**
 * The grid's markup in the editor. It mirrors the card in render.php
 * class for class, so style.css styles both; test/grid-preview.js fails
 * if the two templates stop using the same classes.
 */
import { __ } from '@wordpress/i18n';

/**
 * A card image. Until it paints, the thumbnail's background shows in its
 * place, as on the frontend. Decorative, as in render.php: the title says
 * the same. The first row is in view, so it loads right away; later rows
 * load lazily. Width and height give the browser the image's shape before
 * it arrives.
 *
 * @param {Object}  props
 * @param {string}  props.src    Image URL.
 * @param {number}  props.width  Intrinsic width, if known.
 * @param {number}  props.height Intrinsic height, if known.
 * @param {boolean} props.eager  Whether the image is in the first row.
 * @return {Element} Thumbnail.
 */
function Thumb( { src, width, height, eager } ) {
	return (
		<div className="wmpgf-grid__thumb">
			<img
				src={ src }
				alt=""
				width={ width || undefined }
				height={ height || undefined }
				loading={ eager ? 'eager' : 'lazy' }
				decoding="async"
			/>
		</div>
	);
}

/**
 * A post with no image: a neutral shape the size of a cover, as in
 * render.php, so the row still lines up.
 *
 * @return {Element} Placeholder.
 */
function Placeholder() {
	return (
		<div className="wmpgf-grid__thumb wmpgf-grid__thumb--placeholder">
			<svg viewBox="0 0 24 24" width="40" height="40" aria-hidden="true">
				<rect
					x="3"
					y="4.5"
					width="18"
					height="15"
					rx="2"
					fill="none"
					stroke="currentColor"
					strokeWidth="1.5"
				/>
				<circle cx="9" cy="9.5" r="1.75" fill="currentColor" />
				<path
					d="M3.75 17.5l5-5 3.5 3.5 2.5-2.5 5.5 5"
					fill="none"
					stroke="currentColor"
					strokeWidth="1.5"
					strokeLinejoin="round"
				/>
			</svg>
		</div>
	);
}

/**
 * @typedef {Object} PreviewCard
 * @property {number} id            Post ID.
 * @property {string} link          Permalink.
 * @property {string} title         Plain-text title.
 * @property {string} excerpt       Plain-text excerpt.
 * @property {string} imageUrl      Featured image URL, or '' for none.
 * @property {number} [imageWidth]  Its width, if known.
 * @property {number} [imageHeight] Its height, if known.
 * @property {string} category      Primary category name, or '' for none.
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
				cards.map( ( card, index ) => (
					<article key={ card.id } className="wmpgf-grid__card">
						{ card.imageUrl ? (
							<Thumb
								src={ card.imageUrl }
								width={ card.imageWidth }
								height={ card.imageHeight }
								eager={ index < columns }
							/>
						) : (
							<Placeholder />
						) }
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
