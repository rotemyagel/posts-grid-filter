# Posts Grid + Filter

A WordPress plugin providing two Gutenberg blocks — **Posts Grid** and **Posts Filter** — with demo content seeded automatically on activation. Built for the WordPress Web Development Technical Assessment.

## Requirements

- WordPress 6.5+ (uses the Interactivity API and Script Modules)
- PHP 7.4+
- Node.js (only if rebuilding from source — see below)

## Setup

**From the packaged zip / a checked-out repo with `build/` already present:**

1. Copy the `posts-grid-filter` folder into `wp-content/plugins/`.
2. Activate **Posts Grid + Filter** from the Plugins screen (or `wp plugin activate posts-grid-filter`).
3. That's it. Activation seeds 12 demo posts across 4 categories and 6 tags (each with a featured image and excerpt), and publishes a **Posts Grid + Filter Demo** page with both blocks already placed. Visit that page to see it working.

No manual content creation, no configuration screen, no environment variables.

**Building from source** (only needed if `build/` isn't present, or after editing anything in `src/`):

```bash
npm install
npm run build
```

The build step needs `WP_EXPERIMENTAL_MODULES=1` to make `@wordpress/scripts` compile the blocks' `viewScriptModule` (Interactivity API) files — this is already wired into the `build`/`start` npm scripts via `cross-env`, so a plain `npm run build` is enough.

## What gets built

| Block | Purpose |
|---|---|
| `pgf/posts-grid` | Dynamic block rendering Grid Posts in a 2/3/4-column grid. Editable columns and posts-per-page via Inspector Controls. |
| `pgf/pagination` | Inner block of Posts Grid (locked in place via `templateLock: "all"` and `parent`). Prev/Next controls. |
| `pgf/posts-filter` | Category and tag checkboxes (multi-select). Can be placed anywhere on the page — not nested inside the grid. |

## Architecture decisions

### A dedicated post type and taxonomies, not core post/category/tag

The plugin registers `pgf_post` (custom post type) with `pgf_category` and `pgf_tag` (custom taxonomies), rather than reusing WordPress's built-in `post`/`category`/`post_tag`. This keeps all demo content fully isolated and identifiable — reactivating or uninstalling never touches real site content — and satisfies the brief's requirement that the plugin slug, post type, and taxonomy names all carry a unique prefix (`pgf_`).

### Inter-block communication: the Interactivity API, with a REST API doing the actual filtering

The brief asks for the grid and filter blocks to stay in sync without being nested inside each other. Three approaches were considered:

- **Custom DOM events / a hand-rolled global JS object.** Works, but reinvents state management and directive binding that WordPress already ships as a supported API.
- **URL query parameters + full page reloads.** Simplest to reason about, but a poor UX (loses scroll position, flashes the whole page) for what's fundamentally a client-side filter interaction.
- **The Interactivity API with a shared store namespace (chosen).** Both blocks call `store( 'posts-grid-filter', { state, actions } )` from their own `view.js`. WordPress **merges every caller's partial state/actions into one shared store by namespace** — this is the documented, intended pattern for exactly this situation. Neither block needs to know the other exists; they just agree on a namespace and a small state shape (`selectedCategories`, `selectedTags`, `page`, `totalPages`). This is why the two blocks can be placed anywhere on the page, on their own, and still work together.

Filtering itself is **not** reimplemented in JS or in a custom REST controller. Any taxonomy registered with `show_in_rest => true` automatically gets a REST collection parameter (named after its `rest_base`) on `/wp/v2/pgf_post`, and WordPress's own `tax_query` behavior is: `IN` (OR) for multiple terms within one taxonomy parameter, `AND` between different taxonomy parameters. That is *exactly* the "OR within a filter type, AND across filter types" logic the brief asks for — so `pgf/posts-grid`'s `view.js` just calls `fetch('${restUrl}?pgf_category[]=..&pgf_tag[]=..')` and WordPress does the rest. This was verified directly against the REST API during development: filtering by one category returned that category's post count; filtering by two categories returned their *union*; adding a tag on top returned the *intersection* — confirmed against `wp term list` counts before shipping.

### Declarative directives for simple UI, one imperative patch for the post list

Checkbox checked-state (`data-wp-bind--checked`) and pagination's prev/next/label (`data-wp-bind--disabled`, `data-wp-text`) are driven entirely by declarative Interactivity API directives bound to derived state getters — when `state.page` or `state.totalPages` change, those controls update themselves with no extra code.

The post grid itself is the one exception: after a `fetch()`, `pgf/posts-grid`'s `refresh()` action replaces `[data-pgf-grid-list]`'s `innerHTML` directly, rather than using the Interactivity API's `data-wp-each` list-rendering directive. This was a deliberate scope call — `data-wp-each` is the more "pure" declarative approach, but the imperative patch is simpler to get right and verify within the assessment's timeframe, and produces identical markup to the server-rendered first paint. Documented here as the one place the implementation trades a small amount of idiomatic purity for straightforwardness.

### Scope: one grid + one filter per page

State lives in one shared store, not namespaced per block instance. This matches the brief (grid and filter, placed anywhere on one page) and the demo page it seeds. Supporting multiple independent grid/filter pairs on the same page would need per-instance context propagation and wasn't built, since nothing in the brief calls for it.

### Featured images generated with GD, not bundled files

Demo images are generated on activation with PHP's GD extension (a solid color per category, similar to a placeholder-image service) rather than shipped as binary files in the plugin or fetched from a remote placeholder API. This keeps activation fully offline and the plugin's zip free of binary assets, at the cost of the images being flat colors rather than real photography — acceptable for demo content whose only job is to prove the "featured image" requirement end-to-end.

### Pagination scope

Pagination is Prev / "Page X of Y" / Next, not a numbered page list with ellipses. This satisfies "pagination must be implemented as an inner block" without the extra complexity of rebuilding a variable-length button list on every filter change (which would still need to happen declaratively or imperatively regardless of style).

### Pagination is real, full-reload navigation — not AJAX, not infinite scroll

Pagination started as a pure client-side interaction — clicking "Next" fetched new posts via the REST API with no URL change at all. That's the same pattern SEO guidance on infinite scroll / "load more" widgets specifically warns against: with JavaScript disabled (the test a crawler effectively runs), page 2+ never existed as a reachable URL, so it could never be indexed. An intermediate version added `history.pushState()` on top so the address bar stayed correct without a reload — better, but still not what "real pagination" means, and it added real complexity (see the hydration bug below, which that pushState layer directly caused).

The final design has no JavaScript in the pagination click path at all. Prev/Next (`pagination/render.php`) are plain `<a href="?pgf-page=2">` links with `rel="next"`/`rel="prev"`. Clicking one is a normal browser navigation — a full page reload — and `pgf/posts-grid`'s `render.php` reads that same `?pgf-page=` parameter (`PGF_Blocks::get_requested_page()`) to server-render the matching page directly. A crawler, a JS-disabled visitor, or someone who just bookmarks the link all get identical, correct content.

Category/tag filtering is a separate concern and keeps its instant, no-reload AJAX behavior through the Interactivity API (see the section above) — that part of the brief's requirement (blocks stay in sync without nesting) is unrelated to how pagination navigates. The one thing connecting them: if a visitor has filters selected via the AJAX filter UI and then clicks Next, that full-page reload needs to carry the current filter selection forward, or it would silently reset to the unfiltered grid. `pagination/view.js` handles only this — reactive `prevHref`/`nextHref` getters that rebuild the link's `href` (via `data-wp-bind--href`) to include `state.selectedCategories`/`selectedTags` whenever they change, so the *link itself* always points somewhere correct, without intercepting the click or touching the visible URL during filtering. `posts-grid/render.php`, `pagination/render.php` (its count query), and `posts-filter/render.php` (to pre-check the right boxes) all independently read the same `?pgf_category[]=`/`?pgf_tag[]=` parameters server-side, so a shared/bookmarked filtered-and-paginated link reconstructs the exact right posts and the exact right checkbox state on a fresh, no-JS load.

Verified end to end: `curl` (no JS, no cookies) against `?pgf-page=1`, a single-category filter, and a two-category filter spanning two pages each return correct, independently server-rendered content, with the rendered Next link correctly carrying the active filters forward and the correct checkboxes pre-checked. In a real browser: filtering two categories via AJAX (no URL change), then clicking Next, produces a genuine navigation to `?pgf-page=2&pgf_category[]=8&pgf_category[]=10` — confirmed by reading the post-navigation `document.readyState` and the fresh page's own checkbox state, not assumed from the click alone.

One thing intentionally *not* built: `<link rel="next"/"prev">` tags in `<head>` and per-page self-referential canonical tags. Google itself deprecated using `rel=next`/`prev` as a crawling/indexing signal in 2019, and computing an accurate canonical/total-page-count from `<head>` (rendered before any block's `render.php` runs) would mean parsing arbitrary post content for the block's attributes — real complexity for a signal with diminishing value. The anchors still carry `rel="next"/"prev"` directly, which is enough for the tools that still read it, without that extra machinery.

### A hydration bug worth documenting: don't re-declare server-seeded state on the client

While building the pushState-based version of pagination (since superseded by the full-reload design above, but the lesson still applies to `selectedCategories`/`selectedTags`, which are server-seeded the same way), `pgf/posts-grid`'s `view.js` originally declared `page: 1, totalPages: 1` as part of its client-side `store()` call's "initial" state — alongside genuinely client-only defaults like `isLoading: false`. Both were also always seeded server-side via `wp_interactivity_state()`, and depending on script-module evaluation timing, the client's literal `1` could win over the already-correct server-hydrated value, silently resetting a direct `?pgf-page=2` load's live pagination state back to page 1 after hydration (server-rendered HTML was always correct; only the post-hydration DOM was wrong, and only intermittently, which made it easy to miss). Caught by comparing the raw hydration JSON (`{"page":2,"totalPages":2,...}`, correct) against the live DOM after JS ran (showing page 1, wrong) in the same request. Fixed by simply not declaring server-seeded keys on the client at all — `posts-grid/view.js`'s only client-declared state today is `isLoading`; `page`, `totalPages`, `selectedCategories`, and `selectedTags` are only ever meant to come from the server, so there's nothing for the client to clobber them with.

### Performance and Core Web Vitals

- **Layout shift (CLS):** the server-rendered first paint already gets `width`/`height`, `loading`, and `decoding` attributes on images for free from `the_post_thumbnail()`. The client-side re-render path (after a filter/page change) didn't — a real regression on every filtered update, fixed by reading the REST response's `media_details.sizes.medium` (the same size the SSR path uses) and carrying its width/height, `loading="lazy"`, and `decoding="async"` through to the client-rendered markup, plus a CSS `aspect-ratio` backstop.
- **Payload size:** the filtered fetch scopes `_embed` to `wp:featuredmedia` only (not author/terms/replies) and trims response fields via `_fields` to just what the card template reads — measured roughly a 52% reduction (46.8KB → 22.5KB for 6 posts) versus the unscoped defaults.
- **Accessibility/SEO:** seeded images now get real alt text (`_wp_attachment_image_alt`, set to the post title) — previously unset, so even the server-rendered path had empty `alt=""` on every image.

### Responsive layout

The grid steps down from 3/4 columns to 2 at tablet widths before collapsing to a single column on phones, rather than jumping straight from N columns to 1. Filter checkboxes and pagination buttons have a 44px-minimum tap target for touch devices.

### Validation and audit logging

`columns` was already validated against a `[2, 3, 4]` allow-list, but `postsPerPage` was only cast to `(int)` with no range check, in both `posts-grid/render.php` (the main query) and `pagination/render.php` (its own count query). Block attributes live in the post's `post_content` and can be edited directly — via the REST API, a hand-edited import, anything with `edit_posts` — without ever touching the editor's RangeControl, and an untrusted value flows straight into `WP_Query`'s `posts_per_page`, where `-1` means "return every post." Added `PGF_Blocks::sanitize_posts_per_page()`, clamped to `[1, 24]` (matching the RangeControl's own bounds) and used by both render.php files so they can't disagree; verified directly by setting a tampered post's `postsPerPage` to `-1` and to `999999` and confirming the rendered page count stayed correctly bounded both times.

Every failure path in the seeder (a term, post, image, or the demo page that couldn't be created) previously failed via a silent `continue`, with no way to tell afterward why an install ended up with, say, 8 posts instead of 12. Added `PGF_Blocks::log()`, which writes via `error_log()` only when `WP_DEBUG_LOG` is enabled — the standard WordPress convention, silent by default on any site that hasn't opted into debug logging — called from every failure branch, plus a one-line summary at the end of `seed()` if the final counts came up short.

### An accessibility gap in the pagination controls, and a note on `supports.interactivity`

Every directive that affects *initial* markup elsewhere in this plugin has a matching manually-computed PHP value: `href` is present or absent based on `$current_page`, filter checkboxes get `checked()` based on the URL. Pagination's `data-wp-bind--aria-disabled` and `data-wp-class--is-disabled`, though, were left to resolve purely client-side — meaning on page 1, before JavaScript hydrates (or with JS disabled entirely), the Prev control was a link with no `href` but no `aria-disabled` and no disabled styling either: functionally inert, but with nothing telling a screen reader or a sighted no-JS visitor why. Fixed by computing `$is_first_page`/`$is_last_page` once in PHP and echoing `aria-disabled="true"` and the `is-disabled` class directly, the same pattern already used everywhere else. Verified via `curl` (no JS): page 1's Prev link now renders `class="...is-disabled"` and `aria-disabled="true"` with no `href`, entirely server-side.

This plugin doesn't set `"supports": { "interactivity": true }` in any block.json, which is what triggers WordPress's own automatic server-side directive resolution (`wp_interactivity_process_directives()`) using closures registered via `wp_interactivity_state()`. That's a legitimate, framework-supported way to get correct initial HTML without hand-computing it — but it would mean defining `isFirstPage`/`isLastPage`/`prevHref`/`nextHref`/etc. as PHP closures *in addition to* the JS getters that already exist, which duplicates the same logic in two languages rather than avoiding duplication. Manually computing the handful of values that affect initial markup, as done throughout this plugin, reaches the same "correct on first paint, no layout shift" goal the flag exists for, without that second copy. Worth revisiting if the number of derived values grows enough that keeping the two in sync by hand becomes error-prone.

## Known limitations

- **REST URL is not permalink-structure-agnostic beyond the standard case.** The frontend fetch uses `rest_url()` (server-provided, not hardcoded), so it works with any permalink structure WordPress itself is configured with — but it hasn't been tested against a site running the "plain" `?rest_route=` fallback.
- **No `data-wp-each` for the post list** (see above) — the tradeoff is documented, not hidden.
- **One grid + one filter per page** is the supported scope, not multiple independent pairs.
- **GD is required** for demo image generation on activation; on a PHP build without GD, posts still seed correctly but without featured images.
- **Pagination is a full page reload, not instant.** A deliberate tradeoff for the previous section's reasons — real, crawlable, no-JS-required navigation, at the cost of losing the AJAX version's instant feel and scroll position on page change. Filtering itself is unaffected and stays instant.
- **Filtered *and* paginated views are now both crawlable** (`?pgf_category[]=`/`?pgf_tag[]=` combined with `?pgf-page=`), which is a deliberate change from an earlier version of this plugin that only made the unfiltered grid crawlable. Whether every filter combination *should* be indexable is a separate, genuinely debatable SEO question (faceted navigation can create thin/duplicate-content pages at scale) — nothing here adds `noindex` or excludes any combination from indexing, which would be the next thing to consider for a larger, real-world catalog.
- **No `<link rel="next"/"prev">` or per-page canonical tags in `<head>`** — see the pagination section above for why.

## Verification performed

- Fresh activation on a real WordPress 7.1 / PHP 8.3 install: 12 posts, 4 categories, 6 tags, all with images/excerpts, demo page created — zero manual steps.
- Reactivation is idempotent (no duplicate content).
- REST API filtering tested directly (`curl`) confirming OR-within/AND-across taxonomy semantics against known term counts.
- Live browser testing: checking a category filters the grid in place (no reload); adding a tag narrows results further (AND); unchecking restores the full set; pagination recalculates and disables Prev/Next correctly at the boundaries; the filter and grid stay in sync from their independent positions on the page.
- Pagination crawlability: `curl` (no JS, no cookies) against `?pgf-page=1`, a single-category filter, and a two-category filter spanning two pages each return correct, independently server-rendered content — including the rendered Next link correctly carrying the active filters forward and the right checkboxes pre-checked.
- Filter-then-paginate in a real browser: selecting two categories via AJAX (confirmed no URL change while filtering), then clicking Next, produces a genuine full-page navigation to `?pgf-page=2&pgf_category[]=8&pgf_category[]=10` — confirmed via `document.readyState` and the fresh page's own checkbox state after reload, not assumed from the click alone.
- A real hydration bug (client-declared initial state clobbering server-seeded state — see above) was caught by comparing the raw hydrated JSON against the live post-JS DOM, not just eyeballing the page, and fixed before shipping.
- Client-rendered images (post-filter-fetch) confirmed via DOM inspection to carry the same `width`/`height`/`loading`/`decoding`/`alt` attributes as the server-rendered first paint.
- Responsive layout checked at 375px (phone) and standard desktop widths.
- Block editor: Inspector Controls (columns, posts per page) update the editor preview live; the grid's editor preview renders real data via `core-data`.
- Cross-checked against WordPress's own official Interactivity API guidance ([github.com/WordPress/agent-skills](https://github.com/WordPress/agent-skills)), which caught the `aria-disabled`/`is-disabled` gap above — confirmed via `curl` (no JS) that pagination's disabled state now renders correctly server-side, not just client-side.
