# Build Tasks: Posts Grid + Posts Filter (WP Technical Assessment)

Source brief: `WordPress Web Development - Technical Assessment.pdf`
Deadline: 1 week from receipt. Submit to moshe.buaron@walkme.com / dorin.weil@walkme.com.

## Architecture decisions (locked in before coding starts)

- **Plugin slug / prefix**: `posts-grid-filter`, PHP prefix `PGF_` / `pgf_`, text domain `posts-grid-filter`.
- **Content model**: custom post type `pgf_post` (not core `post`) with custom taxonomies `pgf_category` (hierarchical) and `pgf_tag` (flat), both `show_in_rest`. Keeps demo content fully isolated from the real site already running in this install and satisfies the brief's "unique prefix for plugin slug, post type, and taxonomy names" requirement directly.
- **Build tooling**: `@wordpress/scripts` (wp-scripts), standard `src/` → `build/` block layout, one `block.json` per block.
- **Blocks**: `pgf/posts-grid` (dynamic, server-rendered, InnerBlocks-restricted to `pgf/pagination`), `pgf/pagination` (inner block), `pgf/posts-filter` (dynamic, server-rendered checkboxes for categories/tags).
- **Inter-block sync (no nesting)**: WordPress **Interactivity API** with a single shared store namespace (`posts-grid-filter`). The Filter block writes selected term IDs into shared client-side state; the Grid block reads that state and re-queries the core REST endpoint for `pgf_post` (`/wp/v2/pgf_post?pgf_category[]=..&pgf_tag[]=..`), re-rendering results without a page reload. WordPress's own `tax_query` semantics (`relation: OR` within one taxonomy, `AND` across taxonomies) give the exact "OR within type, AND across types" logic for free — no custom filtering endpoint needed. This works because the store is global, not a parent/child prop chain, so Filter and Grid can sit anywhere on the page independently. Documented in the README along with alternatives considered (custom events, `postMessage`, URL-param-only).
- **Demo images**: 3–4 small royalty-free JPGs bundled in `assets/seed-images/`, sideloaded via `wp_insert_attachment` + `wp_generate_attachment_metadata` on activation (no external network calls, so the assessment environment works offline).
- **Seeding idempotency**: activation hook checks a `pgf_seeded` option before inserting anything, so reactivating the plugin never duplicates content.

## Foundation
- [ ] **Plugin scaffold & lifecycle**: Main plugin file with header, `includes/` structure, `wp-scripts` build config, `package.json`, activation/deactivation/uninstall hooks (uninstall removes seeded CPT posts, terms, media, and options). _New._
- [ ] **CPT + taxonomies**: Register `pgf_post`, `pgf_category`, `pgf_tag` with correct `show_in_rest`, `rest_base`, and REST query var support so taxonomy filtering works through the core REST API. _New._

## Core Build
- [ ] **Demo content seeder**: Activation-time routine inserting ≥10 `pgf_post` entries across ≥3 categories and multiple tags (multi-term assignments so filter combinations are meaningful), each with excerpt + sideloaded featured image. Creates one demo `page` with both blocks pre-placed in its content. Idempotent via `pgf_seeded` option. _New._
- [ ] **Posts Grid block — editor UX**: `pgf/posts-grid` block.json + edit.js with InspectorControls for column count (2/3/4) and posts-per-page, editor preview via `core-data` selectors, InnerBlocks template auto-inserting `pgf/pagination`, `allowedBlocks` restricted to it. _New._
- [ ] **Posts Grid block — dynamic render + pagination**: `render.php` running `WP_Query` against `pgf_post` for first paint (title, featured image, excerpt, column count from attributes), wired with `data-wp-interactive`/`data-wp-context` for the shared store. `pgf/pagination` inner block renders prev/next + page numbers and updates the store's page index without reloading. _Depends on: Posts Grid editor UX, CPT + taxonomies._
- [ ] **Posts Filter block**: `pgf/posts-filter` block.json + render.php outputting a checkbox group per taxonomy (multi-select), bound via Interactivity API directives; minimal edit.js (heading/label control only, since the meaningful UI is on the frontend). _Depends on: CPT + taxonomies._
- [ ] **Shared Interactivity store + REST sync**: Central store module (`view.js`) holding selected category/tag IDs and current page; Grid subscribes and fetches `/wp/v2/pgf_post` with `pgf_category[]`/`pgf_tag[]`/`page` params on any state change, replacing the rendered post list and pagination in place. Covers loading and "no results" states. _Depends on: Posts Grid dynamic render, Posts Filter block._

## Verification
- [ ] **Cross-placement test**: On the demo page, move Filter above/below/away from Grid (including on a second page) and confirm sync still works; test single-category, multi-category, category+tag, and zero-match combinations.
- [ ] **Coding standards pass**: `phpcs --standard=WordPress`, proper escaping/sanitization on all output and REST query args, i18n on all user-facing strings, keyboard-accessible filter checkboxes.
- [ ] **Fresh-install smoke test**: Deactivate/delete plugin, reinstall from the packaged zip on a clean WP install (or reset the CPT/options locally), confirm zero manual setup is needed after activation.

## Documentation & Submission
- [ ] **README**: Setup instructions, architecture explanation (why Interactivity API + REST over alternatives), known limitations/tradeoffs (no-JS fallback, placeholder images, query performance at scale).
- [ ] **Package for submission**: Clean `.gitignore` (exclude `node_modules`, keep `build/`), tag a release or produce a zip, final activation smoke test before sending.
