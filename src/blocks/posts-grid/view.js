/**
 * Owns the `posts-grid-filter` Interactivity API store's fetch/render logic.
 * Other blocks (pagination, posts-filter) call `store('posts-grid-filter')`
 * with their own partial state/actions; WordPress merges every caller's
 * definition into one shared store by namespace, which is how the grid and
 * filter blocks stay in sync without being nested inside each other.
 */
import { store } from '@wordpress/interactivity';

/**
 * Picks the same "medium" image size the server-rendered first paint uses
 * (via the_post_thumbnail('medium')) rather than the full-resolution
 * original, and carries its real width/height so the <img> can reserve
 * layout space before it loads — without this, every filtered re-render
 * would cause a layout shift (a Core Web Vitals CLS regression) as each
 * image's true aspect ratio is discovered.
 *
 * @param {Object} post REST API post resource.
 * @return {?{url: string, width?: number, height?: number}} Thumbnail info, or null.
 */
const getThumbnail = ( post ) => {
	const media = post._embedded?.[ 'wp:featuredmedia' ]?.[ 0 ];
	if ( ! media ) {
		return null;
	}
	const medium = media.media_details?.sizes?.medium;
	if ( medium ) {
		return {
			url: medium.source_url,
			width: medium.width,
			height: medium.height,
		};
	}
	if ( media.source_url ) {
		return {
			url: media.source_url,
			width: media.media_details?.width,
			height: media.media_details?.height,
		};
	}
	return null;
};

/**
 * `title.rendered` from the REST API is WordPress-sanitized (titles can't
 * contain real tags) but can still contain HTML entities (an em dash as
 * `&#8211;`, an ampersand as `&amp;`), which need decoding for correct
 * display or a screen reader would read the literal entity code. Decoding
 * via a detached element's innerHTML/textContent round-trip is safe here
 * specifically because the input is already tag-free -- this is not a
 * general-purpose HTML sanitizer.
 *
 * @param {string} html Tag-free string that may contain HTML entities.
 * @return {string} Decoded plain text.
 */
const decodeEntities = ( html ) => {
	const el = document.createElement( 'textarea' );
	el.innerHTML = html;
	return el.textContent;
};

/**
 * Builds one card as real DOM nodes rather than an HTML string. Post titles
 * and links are admin/editor-controlled, not visitor input, but building
 * markup by interpolating raw values into an HTML string still means any
 * character with attribute-breaking significance (a `"` in a title, say)
 * lands in the page unescaped -- the original `.replace(/<[^>]+>/g, '')`
 * stripped tags but never attribute-escaped anything. Using DOM
 * properties/attributes instead (`textContent`, element properties like
 * `.href`/`.src`/`.alt`) gets correct escaping for free from the browser,
 * the same way `esc_attr()`/`esc_html()` do server-side. `excerpt.rendered`
 * is the one deliberate exception: it's WordPress-generated HTML (a `<p>`
 * wrapper) that's meant to render as markup, so it goes through
 * `innerHTML`, consistent with how the server-rendered excerpt
 * (`the_excerpt()`) already outputs real HTML on first paint.
 *
 * @param {Object} post REST API post resource.
 * @return {HTMLElement} A `.pgf-grid__card` <article> element.
 */
const renderPostCard = ( post ) => {
	const thumb = getThumbnail( post );
	const title = decodeEntities(
		( post.title?.rendered || '' ).replace( /<[^>]+>/g, '' )
	);
	const excerpt = post.excerpt?.rendered || '';

	const article = document.createElement( 'article' );
	article.className = 'pgf-grid__card';

	if ( thumb ) {
		const thumbLink = document.createElement( 'a' );
		thumbLink.href = post.link;
		thumbLink.className = 'pgf-grid__thumb';

		const img = document.createElement( 'img' );
		img.src = thumb.url;
		img.alt = title;
		if ( thumb.width && thumb.height ) {
			img.width = thumb.width;
			img.height = thumb.height;
		}
		img.loading = 'lazy';
		img.decoding = 'async';

		thumbLink.appendChild( img );
		article.appendChild( thumbLink );
	}

	const heading = document.createElement( 'h3' );
	heading.className = 'pgf-grid__title';
	const titleLink = document.createElement( 'a' );
	titleLink.href = post.link;
	titleLink.textContent = title;
	heading.appendChild( titleLink );
	article.appendChild( heading );

	const excerptEl = document.createElement( 'div' );
	excerptEl.className = 'pgf-grid__excerpt';
	excerptEl.innerHTML = excerpt; // phpcs equivalent: this is WordPress-rendered HTML, not visitor input.
	article.appendChild( excerptEl );

	return article;
};

// Bumped on every refresh() call; a response only applies if it's still the
// most recent request when it resolves. Without this, rapid filter changes
// can fire overlapping requests that resolve out of order, and whichever
// happens to finish LAST wins even if it was fired first -- silently
// reverting the grid to a stale selection's results while the filter UI
// itself already shows the new (correct) one checked.
let latestRequestId = 0;

const { state } = store( 'posts-grid-filter', {
	// `page`, `totalPages`, `selectedCategories`, and `selectedTags` are
	// deliberately NOT declared here with a literal default (e.g. `page: 1`):
	// all four are always server-seeded via wp_interactivity_state() (grid's
	// render.php seeds `page`/`selectedCategories`/`selectedTags` from the
	// URL, pagination seeds `totalPages`), and depending on script module
	// evaluation order, a client-declared "initial" value can clobber the
	// already-hydrated server value back to its default. Only state that is
	// genuinely client-only (never server-seeded) belongs here.
	state: {
		isLoading: false,
	},
	actions: {
		/**
		 * Re-fetches pgf_post from the REST API using the current shared
		 * filter state and patches the grid's post list in place. Called by
		 * the filter block on every checkbox change (which always resets
		 * `state.page` to 1 first -- see posts-filter/view.js -- since a new
		 * filter selection should start from page one). Pagination itself is
		 * plain full-page-reload navigation (see pagination/render.php), not
		 * an AJAX action, so it never calls this.
		 */
		async refresh() {
			const requestId = ++latestRequestId;
			state.isLoading = true;

			const restUrl = state.config?.restUrl;
			const list = document.querySelector( '[data-pgf-grid-list]' );
			if ( list ) {
				list.setAttribute( 'aria-busy', 'true' );
			}

			if ( ! restUrl ) {
				state.isLoading = false;
				return;
			}

			/*
			 * Building the URL this way -- rather than `${restUrl}?${params}`
			 * -- matters specifically for the "plain" permalink structure,
			 * where rest_url() already returns something like
			 * `/?rest_route=/wp/v2/pgf_post` (its own `?` already used).
			 * Naively appending another `?` would put every filter param
			 * inside the value of `rest_route` itself, producing a request
			 * for a nonexistent route. `URL` + `searchParams` merges into
			 * whatever query string is already there, correctly, for both
			 * pretty and plain permalinks.
			 */
			const url = new URL( restUrl, window.location.origin );
			url.searchParams.set( 'per_page', state.config?.postsPerPage || 6 );
			url.searchParams.set( 'page', state.page );
			// Scoped embed: only pull in the featured-media relation, not
			// author/terms/replies too — smaller response, faster parse.
			url.searchParams.set( '_embed', 'wp:featuredmedia' );
			// Only the fields the card template actually reads — cuts out
			// guid, modified, template, class_list, meta, etc.
			url.searchParams.set(
				'_fields',
				'id,link,title,excerpt,_links,_embedded'
			);
			state.selectedCategories.forEach( ( id ) =>
				url.searchParams.append( 'pgf_category[]', id )
			);
			state.selectedTags.forEach( ( id ) =>
				url.searchParams.append( 'pgf_tag[]', id )
			);

			let result;
			try {
				const response = await fetch( url.toString() );
				result = {
					ok: response.ok,
					posts: response.ok ? await response.json() : null,
					totalPages: Math.max(
						1,
						parseInt(
							response.headers.get( 'X-WP-TotalPages' ) || '1',
							10
						)
					),
				};
			} catch ( error ) {
				result = { ok: false, posts: null, totalPages: null };
			}

			// A newer refresh() started (and possibly already resolved) while
			// this one was in flight -- its result is stale, so it must not
			// overwrite whatever the newer request already rendered.
			if ( requestId !== latestRequestId ) {
				return;
			}

			state.isLoading = false;
			if ( list ) {
				list.removeAttribute( 'aria-busy' );
			}

			if ( ! result.ok || ! result.posts ) {
				// A failed request (HTTP error or network exception) is
				// reported as an error, not silently rendered as "no
				// results" -- those mean different things to a visitor, and
				// conflating them hides a real failure behind what looks
				// like a legitimate empty filter combination.
				if ( list ) {
					list.replaceChildren();
					const notice = document.createElement( 'p' );
					notice.className = 'pgf-grid__error';
					notice.setAttribute( 'role', 'alert' );
					notice.textContent =
						state.config?.i18n?.loadError ||
						'Could not load posts. Please try again.';
					list.appendChild( notice );
				}
				return;
			}

			state.totalPages = result.totalPages;

			if ( list ) {
				if ( result.posts.length ) {
					list.replaceChildren(
						...result.posts.map( renderPostCard )
					);
				} else {
					const empty = document.createElement( 'p' );
					empty.className = 'pgf-grid__empty';
					empty.textContent =
						state.config?.i18n?.noResults || 'No posts found.';
					list.replaceChildren( empty );
				}
			}
		},
	},
} );
