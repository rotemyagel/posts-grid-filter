/**
 * The seeded demo page's ID and address. Read through ?rest_route=, which
 * works under both pretty and plain permalinks, so the tests don't assume
 * either.
 *
 * @param {import('@playwright/test').APIRequestContext} request Request context.
 * @return {Promise<{id: number, link: string}>} The demo page.
 */
export async function demoPage( request ) {
	const response = await request.get(
		'?rest_route=/wp/v2/pages&slug=posts-grid-filter-demo&_fields=id,link'
	);
	const [ page ] = await response.json();
	if ( ! page ) {
		throw new Error( 'The demo page was not found. Is the plugin active?' );
	}
	return page;
}

/**
 * The address of one seeded Grid Post.
 *
 * @param {import('@playwright/test').APIRequestContext} request Request context.
 * @return {Promise<string>} Its permalink.
 */
export async function gridPost( request ) {
	const response = await request.get(
		'?rest_route=/wp/v2/wmpgf_post&per_page=1&_fields=link'
	);
	const [ post ] = await response.json();
	if ( ! post ) {
		throw new Error( 'No Grid Post was found. Is the plugin active?' );
	}
	return post.link;
}
