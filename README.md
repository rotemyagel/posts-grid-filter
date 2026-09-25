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

## Known limitations

- **REST URL is not permalink-structure-agnostic beyond the standard case.** The frontend fetch uses `rest_url()` (server-provided, not hardcoded), so it works with any permalink structure WordPress itself is configured with — but it hasn't been tested against a site running the "plain" `?rest_route=` fallback.
- **No `data-wp-each` for the post list** (see above) — the tradeoff is documented, not hidden.
- **One grid + one filter per page** is the supported scope, not multiple independent pairs.
- **GD is required** for demo image generation on activation; on a PHP build without GD, posts still seed correctly but without featured images.

## Verification performed

- Fresh activation on a real WordPress 7.1 / PHP 8.3 install: 12 posts, 4 categories, 6 tags, all with images/excerpts, demo page created — zero manual steps.
- Reactivation is idempotent (no duplicate content).
- REST API filtering tested directly (`curl`) confirming OR-within/AND-across taxonomy semantics against known term counts.
- Live browser testing: checking a category filters the grid in place (no reload); adding a tag narrows results further (AND); unchecking restores the full set; pagination recalculates and disables Prev/Next correctly at the boundaries; the filter and grid stay in sync from their independent positions on the page.
- Block editor: Inspector Controls (columns, posts per page) update the editor preview live; the grid's editor preview renders real data via `core-data`.
