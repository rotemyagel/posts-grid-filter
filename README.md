# Posts Grid + Filter

Two Gutenberg blocks: a posts grid, and a filter that can sit anywhere on the same page. Picking categories or tags updates the grid without a page reload, and every filtered view is also a real URL that works without JavaScript. Activating the plugin creates demo posts and a demo page, so there is something to look at straight away.

I built it for the WalkMe WordPress technical assessment.

## Requirements and install

WordPress 6.7 or later, PHP 7.4 or later. 6.7 is the first release whose Interactivity API has `getServerState()`, which the filter uses to stay in sync after each navigation. CI tests 6.7 and the latest release.

**From the release zip.** Download [wm-posts-grid-filter.zip](https://github.com/rotemyagel/wm-posts-grid-filter/releases/latest/download/wm-posts-grid-filter.zip) and upload it under Plugins > Add New > Upload Plugin, or:

```bash
wp plugin install wm-posts-grid-filter.zip --activate
```

Activation creates everything; there is nothing to set up. Use the release zip rather than GitHub's "Download ZIP" button, which unpacks to `wm-posts-grid-filter-main`, the wrong folder name.

**With wp-env** (needs Docker):

```bash
npm install
npm run env start
```

This starts WordPress at http://localhost:8888 with the plugin active and pretty permalinks. The demo page is http://localhost:8888/posts-grid-filter-demo/; log in with `admin` / `password`. The environment serves minified scripts like a live site, and PHP notices go to `wp-content/debug.log` (`npm run env run cli -- cat wp-content/debug.log`), never into the page.

**From source.** `build/` is committed, so a checkout works as is. After changing anything in `src/`, run `npm install` and `npm run build`.

## Using the blocks

The blocks are under "WM Widgets" in the inserter. The demo page already has both.

- **Posts Grid** (`wmpgf/posts-grid`). In the sidebar: columns (2, 3 or 4), posts per page, a fallback image for posts without one, and the card titles' heading level. Columns step down when the block itself gets narrow.
- **Pagination** (`wmpgf/pagination`). An inner block of the grid, locked in place: Prev and Next, "Page 2 of 3", and a posts-per-page select for visitors.
- **Posts Filter** (`wmpgf/posts-filter`). Category and tag pills, a search field, the result count, "Clear filters" and a light/dark switch. Place it anywhere on the page, independently of the grid. In the sidebar: an optional heading, and whether to show search and the switch.

Filter rules: several categories match any of them (OR), and the same goes for tags. Categories, tags and search combine with AND.

## The assessment requirements

| Requirement | Where it's met |
|---|---|
| Two blocks; demo content seeded on activation; no manual setup | `WMPGF_Seeder`, run from the activation hook |
| Grid loads posts dynamically: title, featured image, excerpt | `posts-grid/render.php`, a server-rendered dynamic block |
| Inspector controls for columns (2, 3 or 4) and posts per page | `posts-grid/edit.js` |
| Pagination as an inner block of the grid | `wmpgf/pagination`, `parent` of the grid, locked by the grid's template |
| Category and tag filters, multiple selections each | `posts-filter/render.php` (checkboxes) |
| OR within a type, AND across types | `WMPGF_Query` (`tax_query` with `IN` per taxonomy, `AND` between) |
| Filter updates the grid; blocks placed independently, not nested | A shared Interactivity API store and router region; see below |
| Unique prefix for slug, post type and taxonomies | `wm-posts-grid-filter`; `wmpgf_post`, `wmpgf_category`, `wmpgf_tag` |
| At least 10 posts, 3 categories, multiple tags | 12 posts, 4 categories, 6 tags; several posts in two categories |
| Every post has a featured image, excerpt, category and tag | Seeding reads everything back and checks it before it's marked complete |
| A demo page with both blocks | `/posts-grid-filter-demo/`: the filter above the grid, with pagination inside |
| A README with setup, approach, tradeoffs and limitations | This file |

## Beyond the brief

Optional additions, none of which the requirements depend on:

- **Filtering:** search, a result count, "Clear filters", and a posts-per-page select for visitors.
- **Navigation:** Back and Forward, and a form that works without JavaScript.
- **Presentation:**
  - a fallback image or placeholder for posts without one;
  - a title heading level setting;
  - a light/dark switch for the plugin's blocks;
  - a self-hosted font with design tokens.
- **Loading:** skeletons during slow loads, Suspense previews in the editor, tuned lazy loading, and a phone layout. See [docs/performance.md](docs/performance.md).
- **Single post pages:**
  - a block template on block themes, and a PHP template on classic themes;
  - a meta description when nothing else prints one.
- **Maintenance:** careful uninstall; an upgrade check for installs seeded by 2.0.0, with an explicit repair command.

## How the blocks talk to each other

The filter and the grid share one Interactivity API store, `wmpgf`. Each block's `view.js` calls `store( 'wmpgf', … )`, and WordPress merges them by namespace, so neither block needs to know where the other is on the page. The URL is the single source of truth: the filter writes it, and the server reads it.

A filter click:

1. **Click.** The checkbox's change event bubbles up to its group's fieldset, whose `data-wp-on-async--change` runs `toggleCategory`. It reads the term's slug from the checkbox's `value` and updates `state.selectedCategories`.
2. **URL.** The action builds the new address with `urlWith()`, for example `?wmpgf-category=design,culture`, and resets the page to 1.
3. **Router.** `navigate()` from `@wordpress/interactivity-router` requests that URL like any page load and adds it to the browser history.
4. **Server.** `WMPGF_Request` reads the parameters, `WMPGF_Query` runs the query, and the grid's `render.php` prints the cards and pagination.
5. **Swap.** The router replaces the grid's router region with the new HTML and updates the store's server state. The filter's `syncFromServer` callback updates the count and checkboxes from it.

Back and Forward go through the same steps. Pagination links carry the current filters in their URL, so paging keeps them. Without JavaScript, the filter is a plain GET form that loads the same URL.

Alternatives I considered:

- **REST and cards built in JavaScript.** The first version did this, and the card then existed three times (PHP, hand-built DOM, editor JSX) and drifted apart. With the router, `posts-grid/render.php` is the only frontend template. The editor preview is a React component (`posts-grid/grid-preview.js`) fed by `useSuspenseSelect` and `@wordpress/core-data`, rather than `ServerSideRender`, which WordPress documents as a fallback. A Jest test fails if the preview and `render.php` stop using the same classes.
- **REST plus `data-wp-each` templates.** The card would still be written twice, and pagination and no-JS support would each need their own code path.
- **Nesting the filter in the grid, or custom DOM events.** Nesting limits where the filter can go, and custom events duplicate what the store already does.

## Decisions and tradeoffs

- **Own post type and taxonomies.** `wmpgf_post`, `wmpgf_category` and `wmpgf_tag` keep demo content separate from a site's posts. Everything uses the prefix `wmpgf`, the initials of the slug `wm-posts-grid-filter`. The slug itself can't be the prefix: post type keys are limited to 20 characters, and hyphens aren't valid in PHP names.
- **Readable URLs.** Links are built by hand (`src/shared/url.js`, mirrored by `WMPGF_Request::url()`), so slugs stay readable (`design,culture`, not `design%2Cculture`). The server also accepts `wmpgf-category[]=design`, which the no-JS form sends. Unknown slugs are ignored.
- **Posts per page for visitors sits in the pagination block, not the filter.** It receives the grid's own default through block context, and it's inside the region the router re-renders, so its value is always current. The editor's posts-per-page setting, which the brief asks for, is on the grid.
- **Server-rendered state instead of server directive processing.** `render.php` prints the checked boxes, the count and the "Clear filters" visibility from the same `WMPGF_Request::filters()` that drives the query. Server processing would need the JavaScript getters rewritten as PHP closures, so the logic would exist twice either way.
- **`style`, not `viewStyle`.** The editor preview uses the frontend's markup and classes, so it needs the same CSS. On classic themes that don't load block styles separately, the block CSS (about 11 KB) loads on every page.
- **Single posts use the theme's layout.** Block themes get a block template of core blocks (the theme's header and footer, featured image, title, terms, content), registered as `single-wmpgf-post` because WordPress 6.7 allows no underscore in a template's name. A theme's own `single-wmpgf_post` template wins. Classic themes get `templates/single-wmpgf_post.php`.
- **Single posts live at `/grid-post/{slug}/`.** An earlier `/{category}/{slug}/` rule had no fixed prefix, so it matched every two-segment URL and broke archives, feeds and nested pages. A test covers those URLs.
- **Light and dark mode for the plugin's blocks only.** A first visit follows the device; the switch saves a choice in `localStorage`; an inline script applies it before the blocks paint. The theme's own page is never restyled.
- **Card titles are `h2` by default,** under the page's `h1`; the level is a setting (h2 to h4, checked on the server).
- **A meta description on single Grid Posts only,** from the excerpt, and only when nothing else on the page printed one. Other pages are left to the theme and SEO plugins.

Performance decisions, with measurements (hydration, lazy loading, fonts, the phone layout, what Lighthouse still reports), are in [docs/performance.md](docs/performance.md).

## Demo content, reactivation and uninstall

**Seeding checks its own work, and only touches what the plugin created.**
- **Ownership records.** The seeder records the ID of everything it creates (posts, cover images, terms, the demo page) as it creates it, and tags each demo post with its demo entry. Content counts as demo content only through those records, never by title or slug.
- **Terms are the exception.** An existing term with the same name is reused, but it isn't recorded as created.
- **Checked before it's marked complete.** Every demo post must be published, with an excerpt, a featured image whose file exists, and its categories and tags. The demo page must contain the filter and a grid with the pagination inside. Failures are logged (with `WP_DEBUG_LOG`) and leave seeding open.
- **Reactivation.** Before seeding is complete, the next activation repairs the plugin's own posts and page in place, without duplicates. Once seeding is complete, reactivating creates nothing.
- **No external requirements.** Covers are generated as SVG text, so activation needs no network access and no PHP image extension.

**Uninstall** deletes the demo page and demo posts, then each seeded image and term only if nothing left on the site uses it:
- a term stays while a post of any status still has it;
- an image stays while it's a featured image, the site icon or logo, or its file name appears in post content, post meta, options, term meta or user meta.

**Upgrading from 2.0.0.** Version 2.0.0 could mark seeding complete even when a demo post's featured image or terms had failed.
- **The check.** Those installs are checked once: on the next activation, or, after an in-place update (which doesn't run activation), from a one-off WP-Cron event.
- **Why it repairs nothing.** A missing image or term can be a failed seed or the site owner's own change. Nothing stored tells the two apart: removing a featured image or a term directly (through WP-CLI, the REST API, another plugin or code) doesn't change a post's dates.
- **What it does instead.** It logs which demo posts are missing what, and records that the check ran.
- **Retries.** If another run holds the lock, the check is tried again an hour later.

To restore them on purpose:

```bash
wp wmpgf repair-demo-content --dry-run   # list what would change
wp wmpgf repair-demo-content
```

The command restores a missing featured image, and missing categories and tags that still exist:
- it only touches published demo posts on the ownership records;
- it recreates nothing, and leaves drafted or trashed posts as they are;
- it keeps extra terms and edited text;
- if a run fails, the command reports what is still missing and can be run again.

## Tests and tooling

```bash
npm run lint:js      # ESLint (@wordpress/scripts)
npm run lint:css     # Stylelint, including the design token rules
npm run lint:php     # WordPress Coding Standards (run composer install first)
npm run test:js      # Jest: the URL builder, the editor preview and skeleton
npm run test:php     # PHPUnit in wp-env's tests container (start wp-env first)
npm run test:e2e     # Playwright against wp-env (start wp-env first)
npm run plugin-zip   # Builds wm-posts-grid-filter.zip
```

- **PHPUnit:**
  - the filter logic (OR within a taxonomy, AND across, search, unknown slugs, both URL forms, clamping) and the URL builder;
  - the rewrite regression;
  - seeding: complete content, failures leaving seeding open, repair without duplicates, ownership records, users' look-alike content left alone;
  - the 2.0.0 upgrade check and the repair command, including direct term and thumbnail edits;
  - uninstall;
  - the single post template on block and classic themes, the meta description, and block output.
- **Playwright (a real browser):**
  - the OR/AND logic, Back and Forward, pagination and page size, search, keyboard focus, the no-JS form;
  - light and dark mode, loading states, image loading;
  - the editor preview and Inspector controls;
  - the single post page;
  - the phone layout at 375 × 812;
  - axe accessibility checks in both colour modes.

  It finds the demo page through the REST API, so it runs under any permalink setting. To run it against another disposable site, set `WP_BASE_URL`, `WP_USERNAME` and `WP_PASSWORD`.
- **Without Docker:** for PHPUnit, run `composer install` and set `WP_PHPUNIT__TESTS_CONFIG` to a `wp-tests-config.php` for an empty database.
- **GitHub Actions** runs all of this on every push, on WordPress 6.7 and the latest release. It also:
  - fails if the committed `build/` doesn't match the source;
  - checks the demo page under pretty and plain permalinks.

## Known limitations

- **One grid and one filter per page.** The store holds one selection and the router region has a fixed name.
- **Multisite.** Demo content is created only on the site where the plugin is activated (the main site on network activation), on purpose.
- **Upgrading from 1.x.** 2.0 renamed the prefix, including the post type and block names, so 1.x content doesn't carry over. Delete 1.x first, then install 2.x.
- **Installs seeded by 2.0.0** keep any missing demo images or terms until someone runs `wp wmpgf repair-demo-content`, because the plugin can't tell a failed seed from a deliberate removal. The command needs WP-CLI. 2.3.1 and 2.4.0 repaired these automatically on published posts whose dates were unchanged, which could undo a removal made directly.
- **Search** is WordPress's built-in search on title, excerpt and content, with no relevance ranking beyond that.
- **Every filter combination can be indexed.** Each is a legitimate page; on a site with thousands of terms I would limit indexing based on traffic.
- **Lighthouse findings from the server, core or theme** (cache headers, core's navigation markup and stylesheet, the theme's font) are listed in [docs/performance.md](docs/performance.md).

## Project layout

```
wm-posts-grid-filter.php     Bootstrap and constants
uninstall.php                Removes only what the seeder created and nothing still uses
includes/
  class-wmpgf-plugin.php            Hooks, activation, rewrite flush per version
  class-wmpgf-post-type.php         Post type and taxonomies
  class-wmpgf-request.php           The only code that reads $_GET; builds URLs
  class-wmpgf-query.php             Queries, cached per request
  class-wmpgf-blocks.php            Block and style registration
  class-wmpgf-image-loading.php     Eager, low-priority and lazy card images
  class-wmpgf-seeder.php            Demo content, the 2.0.0 upgrade check, the repair
  class-wmpgf-cli.php               wp wmpgf repair-demo-content
  class-wmpgf-single-template.php   Single post: block template, or PHP on classic themes
  class-wmpgf-meta-description.php  Meta description on single Grid Posts
  demo-content.php                  The 12 demo posts
templates/single-wmpgf_post.php  Single post template for classic themes
src/blocks/*/                    block.json, edit.js, render.php, view.js, style.css
src/shared/                      URL builder, router call, withSyncEvent fallback
src/tokens.css                   Design tokens and the font (built to build/tokens.css)
assets/fonts/                    The font and its licence
build/                           Compiled from src/, committed
docs/performance.md              Performance, loading and mobile notes
tests/php/, tests/e2e/           PHPUnit and Playwright
```

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
