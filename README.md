<div align="center">

# Posts Grid + Filter

### Two Gutenberg blocks, synced without nesting, with demo content seeded on activation.

A dynamic **Posts Grid** and a companion **Posts Filter** — kept in sync via a shared Interactivity API store, with pagination as a real inner block and zero manual setup after activation.

[![WordPress 6.5+](https://img.shields.io/badge/WordPress-6.5%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![Block API v3](https://img.shields.io/badge/Block%20API-v3-0073aa)](https://developer.wordpress.org/block-editor/reference-guides/block-api/)
[![Built with @wordpress/scripts](https://img.shields.io/badge/built%20with-%40wordpress%2Fscripts-21759b)](https://www.npmjs.com/package/@wordpress/scripts)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

Built for the WordPress Web Development Technical Assessment.

[**Rotem Yagel**](https://github.com/rotemyagel) &nbsp;·&nbsp; [GitHub](https://github.com/rotemyagel/wm-posts-grid-filter)

</div>

---

## ✨ Highlights

- **Dynamic Posts Grid** — server-rendered via `WP_Query`. Configurable columns (2 / 3 / 4) and posts-per-page via Inspector Controls.
- **Posts Filter** — category and tag checkboxes rendered as pill toggles, with a "Clear filters" link. Can be placed anywhere on the page, not nested inside the grid.
- **Pagination as a true inner block** — locked into the grid's template, registered as its own block rather than markup the grid just happens to render.
- **Zero-JS-in-the-click-path pagination** — Prev/Next are plain, crawlable `<a href>` links with a full page reload; filtering stays instant via AJAX. See [why](#pagination-is-real-full-reload-navigation) below.
- **Shared Interactivity API store, not nesting** — both blocks call `store('posts-grid-filter', {...})` from their own `view.js`; WordPress merges every caller's state into one store by namespace.
- **Category-prefixed permalinks** — `/product-news/some-post/`, via the same `%category%`-style mechanism WordPress core uses for the built-in `post` type (and WooCommerce uses for `%product_cat%`).
- **Idempotent, ownership-aware activation** — seeds 12 posts across 4 categories and 6 tags (each with a featured image and excerpt) plus a demo page. Reactivation never duplicates content, and `uninstall.php` only ever removes what the plugin itself created — never a site owner's own content, even if it shares a title or slug with seeded content.

---

## 🚀 Quick start

### Requirements

| Tool | Version | Notes |
|---|---|---|
| WordPress | 6.5+ | Uses the Interactivity API and Script Modules |
| PHP | 7.4+ | No image library (GD/Imagick) needed — demo images are generated as SVG |
| Node.js | any current LTS | Only needed if rebuilding from source |
| Composer | any current version | Only needed to run the PHP linter (`npm run lint:php`) — not a runtime dependency |

### From the packaged zip / a checked-out repo with `build/` present

```bash
# 1. Copy the wm-posts-grid-filter folder into wp-content/plugins/
# 2. Activate it
wp plugin activate wm-posts-grid-filter
```

That's it — no configuration screen, no environment variables. Activation seeds 12 demo posts, 4 categories, 6 tags, and publishes a **Posts Grid + Filter Demo** page with both blocks already placed.

### Building from source

Only needed if `build/` isn't present, or after editing anything in `src/`:

```bash
npm install
npm run build
```

`WP_EXPERIMENTAL_MODULES=1` (needed for `@wordpress/scripts` to compile the Interactivity API `viewScriptModule` files) is already wired into the `build`/`start` scripts via `cross-env` — a plain `npm run build` is enough.

### Disposable environment via wp-env

No existing WordPress install needed:

```bash
npm install
npm run build
npm run env start
```

`@wordpress/env` spins up a complete WordPress + MySQL stack in Docker with this plugin already active. The first start takes a few minutes (Docker pulls images); subsequent starts are seconds.

```bash
npm run start        # Webpack watch — auto-rebuild on src/ changes
npm run lint:js      # ESLint via @wordpress/scripts
npm run env stop     # Stop containers (preserves DB)
npm run env destroy  # Wipe containers + DB
```

PHP linting is Composer-managed, separately from the npm-based JS tooling above:

```bash
composer install      # once, installs PHPCS + WordPress Coding Standards
npm run lint:php       # checks; exits clean
npm run lint:php-fix   # auto-fixes what it can
```

---

## 📦 What's in the box

### Three blocks

| Block | Type | Responsibility |
|---|---|---|
| `pgf/posts-grid` | Dynamic | Grid of posts, configurable columns/posts-per-page. Server-rendered; refreshes in place via REST when filters change. |
| `pgf/pagination` | Dynamic, inner block | Prev / "Page X of Y" / Next. Locked into `pgf/posts-grid`'s template via `parent` + `templateLock: "all"`. |
| `pgf/posts-filter` | Dynamic | Category/tag pill toggles + Clear filters. Independent of the grid — no nesting required. |

### Seeded demo content

Created automatically on first activation, idempotently (reactivating never duplicates it):

- **4 categories** — Technology · Design · Business · Culture
- **6 tags** — Guide · Opinion · News · Interview · Deep Dive · Trends
- **12 posts** — deterministic but overlapping category/tag assignments (4 posts carry two categories, several carry three tags), so filter combinations produce meaningfully different result sets rather than every post matching everything
- **12 featured images** — generated locally as SVG (a solid color per category plus a text label), so activation needs no network access and no PHP image extension
- **1 demo page** — `/posts-grid-filter-demo/`, with both blocks already placed

---

## 🏛️ Architecture decisions

A short account of the calls made and why. The full round-by-round history — including two independent code-review passes and every bug they surfaced — is in [CHANGELOG.md](CHANGELOG.md).

### A dedicated post type and taxonomies

`pgf_post` with `pgf_category`/`pgf_tag`, not core `post`/`category`/`post_tag`. Keeps demo content fully isolated and identifiable — activation, reactivation, and uninstall never touch a site's real content — and satisfies the brief's requirement that the post type and taxonomy names carry a unique prefix.

### Inter-block sync: the Interactivity API, with REST doing the actual filtering

Both blocks call `store('posts-grid-filter', { state, actions })` from their own `view.js`. WordPress merges every caller's partial state/actions into one shared store by namespace — the documented pattern for exactly this situation, and why the blocks can sit anywhere on the page without either needing to know the other exists.

Filtering itself isn't reimplemented: any taxonomy registered with `show_in_rest => true` gets an automatic REST collection parameter, and WordPress's own `tax_query` behavior (`IN`/OR within one taxonomy, `AND` across taxonomies) is exactly the "OR within a filter type, AND across filter types" logic the brief asks for.

### Pagination is real, full-reload navigation

Prev/Next are plain `<a href="?pgf-page=2">` links with `rel="next"/"prev"` — a normal browser navigation, not AJAX or infinite scroll. A crawler, a JS-disabled visitor, or someone who bookmarks the link all reach identical, correctly server-rendered content. Filtering is a separate concern and keeps its instant AJAX behavior; the only connection is that a Next/Prev link's `href` needs to carry the current filter selection forward, handled by reactive getters in `pagination/view.js`.

### Category-prefixed permalinks

`register_post_type()` has no built-in equivalent of the `%category%` tag core gives the built-in `post` type. Replicated the same mechanism core uses internally — a custom `add_rewrite_tag('%pgf_category%', ...)` plus a `post_type_link` filter that fills in the post's actual category slug — the same technique WooCommerce uses for its own `%product_cat%/%postname%/` option.

### Ownership-aware seeding and uninstall

`pgf_post` is a public, registered post type — once seeded, a site owner creating one by hand is normal use, not a leftover. The seeder records exactly which posts, attachments, and taxonomy terms it *creates* (as opposed to *adopts*, when idempotency finds a pre-existing match), and `uninstall.php` deletes only those, never anything it can't prove it created — including the demo page itself, which is adopted rather than deleted if something already occupies that slug.

### Scope: one grid + one filter per page

State lives in one shared store, not namespaced per block instance — matching the brief (grid and filter, placed anywhere on one page) and the demo page it seeds. Multiple independent grid/filter pairs would need per-instance context propagation, and nothing in the brief calls for it.

### SVG-generated demo images, not bundled files or a GD dependency

Solid-color placeholders with a text label, written directly as SVG (plain XML text via `file_put_contents()`) rather than shipped as binary files, fetched from a remote API, or generated with an image library. Keeps activation fully offline, the plugin's zip free of binary assets, and needs no PHP image extension at all — an earlier GD-based version of this file produced no featured images whatsoever on a PHP build without GD; SVG has nothing to fall back to or skip.

---

## 🗂️ Project layout

```
wm-posts-grid-filter/
├── wm-posts-grid-filter.php       Plugin bootstrap, constants, hook registration
├── uninstall.php                  Ownership-aware demo cleanup on plugin delete
├── .wp-env.json                   wp-env (Docker) config
├── package.json                   Dev dependencies, npm scripts
├── composer.json                  Dev-only: PHPCS + WordPress Coding Standards
├── phpcs.xml.dist                 PHPCS ruleset for this plugin
├── README.md                      This file
├── CHANGELOG.md                   Full development history, both review passes
│
├── includes/
│   ├── class-pgf-plugin.php       Bootstrap: init/activate/deactivate, rewrite-flush guard
│   ├── class-pgf-post-type.php    pgf_post CPT + taxonomies, category-prefixed permalinks
│   ├── class-pgf-blocks.php       Block registration + shared query/param helpers
│   ├── class-pgf-seeder.php       Idempotent, ownership-tracked demo content seeding
│   └── class-pgf-single-template.php  Single-post template fallback for themes with none
│
├── templates/
│   └── single-pgf_post.php        Title/image/terms/content template for a single grid post
│
├── assets/css/
│   └── single.css                 Styling for the single-post template
│
├── src/
│   ├── posts-grid/
│   │   ├── block.json             Metadata, attributes, asset wiring
│   │   ├── edit.js                InspectorControls (columns, posts per page)
│   │   ├── render.php             Server render: query, pagination clamp, i18n config
│   │   ├── view.js                Frontend: REST refresh, request sequencing, card rendering
│   │   └── style.css
│   │
│   ├── pagination/
│   │   ├── block.json             parent: ["pgf/posts-grid"]
│   │   ├── render.php             Prev/Next hrefs, total-page count
│   │   ├── view.js                Reactive href/label getters
│   │   └── style.css
│   │
│   └── posts-filter/
│       ├── block.json
│       ├── render.php             Checkbox groups, Clear filters link
│       ├── view.js                Toggle/clear actions on the shared store
│       └── style.css
│
└── build/                         Generated by @wordpress/scripts (gitignored source, shipped in the zip)
```

---

## ✅ Coding standards

- **PHP** — checked with the actual [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards) ruleset via PHPCS (`phpcs.xml.dist`, `npm run lint:php`), not just followed by convention: tab indentation, `esc_*` on every output, prepared queries, `WP_Query`/`get_terms()` used over raw SQL throughout. One rule is disabled with a documented reason in `phpcs.xml.dist` — the naming check for `templates/single-pgf_post.php`, whose underscore is required by WordPress's own template-hierarchy convention (`single-{$post_type}.php`), not a style inconsistency.
- **JavaScript** — `@wordpress/scripts` ESLint config. Only `@wordpress/*` packages; no external React libraries.
- **i18n** — every user-facing string wrapped with `__()`/`_e()`/`esc_html__()`/`esc_html_e()` under the `wm-posts-grid-filter` text domain, including strings rendered client-side (passed through from PHP via `wp_interactivity_state()`, not hardcoded in JS).
- **Security** — output escaping on every dynamic value; client-rendered cards build real DOM nodes with element properties rather than string-interpolated HTML, so a title can't break out of an attribute.

---

## 🧪 Verification checklist

A smoke test you can run after activation:

1. **Plugin is active** — Plugins screen shows "Posts Grid + Filter" enabled.
2. **Seeding ran** — Posts screen (Grid Posts) lists 12 demo posts, each with a featured image, excerpt, at least one category, and at least one tag.
3. **Demo page exists** — Pages screen lists "Posts Grid + Filter Demo"; opening it shows the filter above the grid, both populated.
4. **Inspector controls work** — select the grid block, change Columns and Posts per page; the editor preview updates live.
5. **Frontend filtering** — check a category; the grid refreshes in place (no reload). Add a tag; results narrow further (AND). Uncheck everything; the full set returns.
6. **Clear filters** — appears once a filter is active, resets both instantly.
7. **Pagination** — click Next with filters active; a real page navigation occurs, filters are preserved in the URL, and the checkboxes on the new page are pre-checked to match.
8. **Single post** — click a card's title; the single-post page renders title, image, categories/tags, and content, at a `/category-slug/post-slug/` URL.
9. **Reactivation is idempotent** — deactivate, reactivate; no duplicate posts appear.
10. **Uninstall is ownership-safe** — create a Grid Post by hand, then delete the plugin via the Plugins screen: the 12 seeded posts/images/terms and the demo page are removed; your hand-created post survives.

### Programmatic checks

```bash
# OR within a taxonomy: two categories should return their union
curl -s "http://your-site.test/wp-json/wp/v2/pgf_post?pgf_category[]=8&pgf_category[]=10" | python3 -c "import json,sys; print(len(json.load(sys.stdin)))"

# AND across taxonomies: adding a tag should narrow the same request
curl -s "http://your-site.test/wp-json/wp/v2/pgf_post?pgf_category[]=8&pgf_category[]=10&pgf_tag[]=3" | python3 -c "import json,sys; print(len(json.load(sys.stdin)))"

# No-JS pagination + filtering, server-rendered
curl -s "http://your-site.test/posts-grid-filter-demo/?pgf-page=2&pgf_category[]=8" | grep -o 'Page [0-9]* of [0-9]*'
```

---

## ⚖️ Known limitations & tradeoffs

- **One grid + one filter per page** is the supported scope, not multiple independent pairs on the same page — nothing in the brief calls for it, and it would need per-instance context propagation the shared store doesn't do today.
- **No `data-wp-each` for the post list** — the client-rendered card list is patched imperatively (`replaceChildren()`) rather than via the Interactivity API's declarative list directive, a deliberate scope call documented in [CHANGELOG.md](CHANGELOG.md).

Two items previously listed here were fixed rather than left as limitations — see [CHANGELOG.md](CHANGELOG.md#fourth-pass-fixing-rather-than-documenting-two-limitations): demo images no longer need GD at all (generated as SVG instead), and `npm run lint:js` runs clean (a `typescript` version override, plus real formatting/unused-variable fixes it then caught).

Two more are deliberate design decisions, not bugs to fix:

- **Pagination is a full page reload, not instant.** This was built this way on purpose, specifically *instead of* an earlier AJAX version — full-reload navigation is what makes page 2+ a real, crawlable URL that works with JavaScript disabled, which instant pagination structurally cannot do. Reverting this would reintroduce the exact problem it was built to solve. Filtering is a separate concern and stays instant.
- **Filtered *and* paginated views are both crawlable** (`?pgf_category[]=` combined with `?pgf-page=`), with no `noindex` on any specific filter combination. At this plugin's demo scale (4 categories, 6 tags, 12 posts) every combination is a legitimate, meaningfully different page, so there's nothing to exclude. Faceted-navigation index control (excluding some combinations to avoid thin-content pages) is a real concern *at scale* — thousands of terms, not a handful — and would need real content/traffic data to decide sensibly, which a demo plugin doesn't have. Adding a speculative rule now, with nothing to base it on, would be guessing.

---

## 📄 License

GPL-2.0-or-later, matching WordPress core.
