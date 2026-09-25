/**
 * Owns the `posts-grid-filter` Interactivity API store's fetch/render logic.
 * Other blocks (pagination, posts-filter) call `store('posts-grid-filter')`
 * with their own partial state/actions; WordPress merges every caller's
 * definition into one shared store by namespace, which is how the grid and
 * filter blocks stay in sync without being nested inside each other.
 */
import { store } from '@wordpress/interactivity';

function renderPostCard( post ) {
	const image = post._embedded?.[ 'wp:featuredmedia' ]?.[ 0 ]?.source_url;
	const title = post.title?.rendered || '';
	const excerpt = post.excerpt?.rendered || '';

	return (
		'<article class="pgf-grid__card">' +
		( image
			? `<a href="${ post.link }" class="pgf-grid__thumb"><img src="${ image }" alt="" /></a>`
			: '' ) +
		`<h3 class="pgf-grid__title"><a href="${ post.link }">${ title }</a></h3>` +
		`<div class="pgf-grid__excerpt">${ excerpt }</div>` +
		'</article>'
	);
}

const { state, actions } = store( 'posts-grid-filter', {
	state: {
		isLoading: false,
		page: 1,
		totalPages: 1,
		selectedCategories: [],
		selectedTags: [],
	},
	actions: {
		/**
		 * Re-fetches pgf_post from the REST API using the current shared
		 * filter/page state and patches the grid's post list in place.
		 * Called by the filter block on every checkbox change and by the
		 * pagination block on prev/next.
		 */
		async refresh() {
			state.isLoading = true;

			const restUrl = state.config?.restUrl;
			if ( ! restUrl ) {
				state.isLoading = false;
				return;
			}

			const params = new URLSearchParams();
			params.set( 'per_page', state.config?.postsPerPage || 6 );
			params.set( 'page', state.page );
			params.set( '_embed', '1' );
			state.selectedCategories.forEach( ( id ) =>
				params.append( 'pgf_category[]', id )
			);
			state.selectedTags.forEach( ( id ) => params.append( 'pgf_tag[]', id ) );

			try {
				const response = await fetch( `${ restUrl }?${ params.toString() }` );
				const posts = response.ok ? await response.json() : [];
				const totalPagesHeader = response.headers.get( 'X-WP-TotalPages' );

				state.totalPages = Math.max( 1, parseInt( totalPagesHeader || '1', 10 ) );
				if ( state.page > state.totalPages ) {
					state.page = state.totalPages;
					await actions.refresh();
					return;
				}

				const list = document.querySelector( '[data-pgf-grid-list]' );
				if ( list ) {
					list.innerHTML = posts.length
						? posts.map( renderPostCard ).join( '' )
						: '<p class="pgf-grid__empty">No posts found.</p>';
				}
			} catch ( error ) {
				// Network error: leave the last known results on screen.
			} finally {
				state.isLoading = false;
			}
		},
	},
} );
