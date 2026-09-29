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
