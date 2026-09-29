# Changelog

## Unreleased

- New: loading skeletons. On the frontend, a filter or page change slower than 200 ms turns the cards into placeholder shapes; card images shimmer until they load, then fade in.
- New: the editor previews use React Suspense (`useSuspenseSelect`) with skeleton fallbacks in the block's final layout, instead of a spinner. The first row of preview images loads right away and later rows lazily, with `width` and `height`.
- Improved: a 4-column grid's whole first row loads eagerly when it's near the top of the page. WordPress core alone makes the 4th image lazy though it's in view.
- Fixed: scroll snapping could scroll the filter's pill row so the "Categories" label was out of view, for example on phones. Each group is now a snap point.

## 2.1.0 (2026-09-29)

- New: a light/dark switch in the filter bar (moon and sun icons) for the plugin's blocks. It follows the device until clicked, remembers the choice, applies it before the blocks paint, and can be hidden per filter block. The filter and grid now sit on their own background in both modes, and the search field shrinks at phone widths so the bar still fits.

## 2.0.3 (2026-09-29)

- Uninstall also keeps seeded images referenced by file name in options (widgets, theme mods), term meta or user meta. Transients don't count.
- Added a Playwright browser suite covering the filter, Back and Forward, pagination, search, focus, the no-JavaScript form and the editor. CI runs it on WordPress 6.7 and latest.
- README: the block CSS is about 11 KB.

## 2.0.2 (2026-09-29)

- Fixed: the stated minimum was WordPress 6.6, but the filter's script needs `getServerState()`, added in 6.7; on 6.6 it failed to load and the filter did nothing. The minimum is now 6.7, and CI tests 6.7 and the latest release.
- The grid's editor preview is a React component using the block editor's data store, instead of `ServerSideRender`. A test keeps its classes identical to the frontend template.
- The REST API returns a post's categories in the order they were assigned, so the first is the primary category everywhere.
- The Columns setting uses a stable `RangeControl` instead of an experimental component, and the inspector controls no longer log deprecation warnings.
- CI loads a filtered demo page under plain permalinks.

## 2.0.1 (2026-09-29)

- Fixed: seeding could be marked complete with missing covers or terms. Every demo post and the demo page are now checked after each run, and seeding is marked complete only when all checks pass.
- Fixed: a retry skipped existing demo posts. It now repairs the plugin's own posts and page (status, excerpt, cover, terms, blocks) without creating duplicates.
- Fixed: posts and pages with a matching title or slug were adopted as demo content. Only content the seeder recorded as created is treated as demo content. An existing page at the demo address is left alone, and the demo page uses the next free address.
- Fixed: uninstall deleted seeded terms and images that surviving content still used. They are now kept, and kept whenever that can't be checked.

## 2.0.0 (2026-09-29)

Breaking: the prefix changed from `pgf` to `wmpgf` everywhere, including the post type, block names, options and URL parameters. 1.x content doesn't carry over; delete 1.x before installing.

- Fixed: the post type's `/{category}/{slug}/` permalinks matched every two-segment URL, so author archives, date archives, feeds, `/page/2/` and nested pages returned 404. Single posts now live at `/grid-post/{slug}/`.
- Filtering and pagination now go through the Interactivity API router. The server renders every card, so the card template exists once, and Back and Forward step through filter changes. The REST fetch and client-side card rendering are gone.
- New: a search field in the filter, and a posts-per-page select (6, 12, 24) under the grid. Both work without JavaScript.
- New design:
  - Three text sizes, two weights, colour tokens that follow the theme, and the self-hosted Bricolage Grotesque font.
  - Container queries for the grid columns.
  - Wide alignment and spacing support.
  - A two-row filter bar.
- New demo content: written excerpts and body text, and geometric cover images.
- Split the blocks class into request, query and registration classes. Queries are cached per request (29 queries per render down to 16).
- Keyboard focus is kept after pagination and page-size changes. The result count is visible, not only announced.
- The seeder retries until a full run succeeds, and never loses track of what it created.
- Added PHPUnit and Jest tests, a GitHub Actions workflow and `npm run plugin-zip`. wp-env now switches on pretty permalinks.
- Fixed: a negative `wmpgf-page` value served a later page instead of page 1.

## 1.3.2 (2026-09-28)

- Seeded content is authored by the first administrator when activation has no current user (WP-CLI).

## 1.3.1 (2026-09-28)

- Filter URLs use term slugs (`?pgf_category=design,culture`) instead of IDs.

## 1.3.0 (2026-09-28)

- The filter works without JavaScript as a GET form, and the selection is mirrored into the address bar.
- Accessibility fixes and live editor previews for both blocks.

## 1.2.0 to 1.2.5 (2026-09-28)

- Renamed the plugin slug to `wm-posts-grid-filter`.
- Uninstall tracks exactly which posts, images and terms it created.
- Demo images are generated as SVG, so there is no GD dependency.
- WordPress Coding Standards are enforced with PHPCS, and `npm run lint:js` passes.
- Blocks are grouped under a "WM Widgets" inserter category.

## 1.1.0 and 1.1.1 (2026-09-27)

- Added category-prefixed single-post permalinks. This caused the rewrite bug fixed in 2.0.0.
- Fixed bugs found in an independent review.

## 1.0.0 (2026-09-25)

- First version:
  - Posts Grid, Pagination and Posts Filter blocks, synced through an Interactivity API store.
  - Pagination with real, crawlable links.
  - Demo content seeded on activation, and removed on uninstall.
  - A single-post template.
