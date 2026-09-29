# Posts Grid + Filter

Two Gutenberg blocks: a posts grid and a filter that can sit anywhere on the same page. Picking categories, tags or a search term updates the grid without a page reload, and every filtered view is also a real URL that works without JavaScript. Activating the plugin creates demo posts and a demo page, so there is something to look at straight away.

I built it for the WalkMe WordPress technical assessment.

## Install

Requires WordPress 6.7 and PHP 7.4 or later. 6.7 is the first release whose Interactivity API has `getServerState()`, which the filter uses to stay in sync after each navigation; CI tests 6.7 and the latest release.

**From the release zip.** Download [wm-posts-grid-filter.zip](https://github.com/rotemyagel/wm-posts-grid-filter/releases/latest/download/wm-posts-grid-filter.zip), then upload it under Plugins > Add New > Upload, or:

```bash
wp plugin install wm-posts-grid-filter.zip --activate
```

Use the release zip rather than GitHub's "Download ZIP" button. That one unpacks to `wm-posts-grid-filter-main`, which is the wrong folder name.

**With wp-env** (needs Docker):

```bash
npm install
npm run env start
```

This starts WordPress at http://localhost:8888 with the plugin active and pretty permalinks switched on. The demo page is at http://localhost:8888/posts-grid-filter-demo/. Log in with `admin` / `password`.

**From source.** `build/` is committed, so a checkout works as is. After changing anything in `src/`, run `npm install` and `npm run build`.

## What you get

Three blocks, grouped under "WM Widgets" in the inserter:

- **Posts Grid** (`wmpgf/posts-grid`). The grid shows 2, 3 or 4 columns, and the columns step down when the block itself gets narrow, not only the screen. In the sidebar you choose columns and posts per page. A post without a featured image shows the grid's fallback image, set in the sidebar; without one, a neutral placeholder the size of a cover keeps the row aligned. A featured image that isn't a usable image (a PDF, or an ID left behind by a database edit) counts as none.
- **Pagination** (`wmpgf/pagination`). This lives inside the grid and can't be used on its own. It has Prev and Next links, "Page 2 of 3", and a posts-per-page select for visitors (6, 12 or 24).
- **Posts Filter** (`wmpgf/posts-filter`). It has a search field, category and tag pills, a result count, "Clear filters" and a light/dark switch. It isn't nested in the grid; put it anywhere on the page.

On activation the plugin creates:
- 12 posts in its own post type, each with an excerpt, three paragraphs of body text and a generated cover image.
- 4 categories with 4 posts each; 4 of the posts have two categories.
- 6 tags.
- A demo page, `/posts-grid-filter-demo/`, with the filter above the grid. If a page already uses that address, it is left alone and the demo page gets the next free one (`/posts-grid-filter-demo-2/`).

Filter rules: several categories match any of them (OR), and the same goes for tags. Categories, tags and search combine with AND.

## How a filter click travels

1. **Click.** The checkbox's `data-wp-on--change` runs `toggleCategory` in the shared `wmpgf` store, which updates `state.selectedCategories`.
2. **URL.** The action builds the new address with `urlWith()`, for example `?wmpgf-category=design,culture`, and resets the page to 1.
3. **Router.** `navigate()` from `@wordpress/interactivity-router` requests that URL like any page load and adds it to the browser history.
4. **Server.** `WMPGF_Request` reads the parameters, `WMPGF_Query` runs the query, and the grid's `render.php` prints the cards and pagination.
5. **Swap.** The router replaces the grid's router region with the new HTML and updates the store's server state. The filter's `syncFromServer` callback then updates the count and checkboxes from it.

Back and Forward go through the same steps, so they step through filter changes. Without JavaScript, the filter is a plain GET form that loads the same URL, and the server renders the same HTML.

## How the blocks talk to each other, and why this way

The filter and the grid share one Interactivity API store, `wmpgf`. Each block's `view.js` calls `store( 'wmpgf', … )`, and WordPress merges them by namespace. So neither block needs to know where the other is on the page. The URL is the single source of truth: the filter writes it, and the server reads it.

I considered three other approaches:

- **Fetching posts over REST and building cards in JavaScript.** The first version did this. The card then existed three times, in PHP, in hand-built JavaScript DOM and in the editor's JSX, and the three drifted apart. With the router, `posts-grid/render.php` is the only frontend template: first load, filter changes and pagination all use it. The editor preview is a React component (`posts-grid/grid-preview.js`) fed by the block editor's data store (`useSelect` with `@wordpress/core-data`), rather than `ServerSideRender`, which WordPress documents as a fallback. That makes two templates for one card, so a Jest test reads `render.php` and fails if the two stop using exactly the same classes.
- **REST plus `data-wp-each` templates.** This would work, but the card would still be written twice, and pagination and no-JS support would each need their own code path.
- **Nesting the filter inside the grid**, or custom DOM events between the blocks. Nesting limits where the filter can go, and custom events duplicate what the store already does.

## Decisions and tradeoffs

**Own post type and taxonomies** (`wmpgf_post`, `wmpgf_category`, `wmpgf_tag`). Demo content stays separate from a site's real posts, so installing, reactivating and uninstalling never touch them. Everything uses one prefix, `wmpgf`: PHP classes, options, block names, the store, CSS classes and URL parameters. `wmpgf` is the initials of the plugin slug, `wm-posts-grid-filter`. The slug itself couldn't be the prefix: WordPress limits a post type key to 20 characters, and hyphens aren't valid in PHP names. The slug and the text domain stay `wm-posts-grid-filter`, because WordPress expects both to match the plugin's folder name.

**Readable URLs.** Links are built by hand (`src/shared/url.js`, mirrored by `WMPGF_Request::url()`), so slugs stay readable, as in `design,culture`, instead of `design%2Cculture`. The search term is the one free-text value, so it is the one value that gets encoded. The server also accepts `wmpgf-category[]=design`, which is what the no-JS form sends. Unknown slugs are ignored.

**Posts per page sits under the grid, not in the filter bar.** The review asked for it in the filter bar. I put it in the pagination block instead, for two reasons:
- The pagination block receives the grid's own default through block context. The filter can't know which grid's default applies.
- It sits inside the region the router re-renders, so its selected value is always current.

It is also a visitor-facing extra: the posts-per-page setting in the Inspector, which is what the brief asked for, is on the grid block.

**Server-rendered values instead of server directive processing.** The blocks don't declare `supports.interactivity`, so WordPress doesn't evaluate directives on the server. `render.php` prints the checked boxes, the count and the "Clear filters" visibility directly, from the same `WMPGF_Request::filters()` that drives the query. Three of those values are JavaScript getters (`isCategoryChecked`, `isTagChecked`, `hideClearFilters`). Server processing would need each one rewritten as a PHP closure, so the logic would exist in two languages either way.

**`style`, not `viewStyle`.** The editor preview uses the same markup and classes as the frontend, so it needs the same CSS, and `viewStyle` only loads on the frontend. On classic themes that don't load block styles separately, this means the plugin's block CSS (about 11 KB) loads on every page. The font file does not: browsers only download it where an element uses it.

**The blocks bring their own font, and a theme can turn it off.** The font is Bricolage Grotesque, self-hosted (41 KB, Latin, SIL Open Font License) so there is no third-party request. Overriding a theme's font is a real tradeoff, so the font is a single token scoped to the plugin's blocks, and a theme that prefers its own sets `--wmpgf-font: inherit`.

**A small design system.** There are three text sizes, two weights, one accent colour and two corner radii, all defined as tokens in `assets/css/tokens.css`. Stylelint rejects any other size, weight, radius or hex colour outside `tokens.css`. Each mode (light and dark) sets one text colour and one background, and the muted text, borders and surfaces are mixed from that text colour.

**Light and dark mode, for the plugin's blocks only.** The switch in the filter bar (a moon in light mode, a sun in dark mode) changes the filter, grid and pagination together. It doesn't change the theme's header, footer or page, because a plugin shouldn't restyle a whole site.
- A first visit follows the device's setting (`prefers-color-scheme`).
- A click saves the choice in `localStorage` and sets `data-wmpgf-color-scheme` on `<html>`, which the blocks' CSS reads.
- A saved choice is applied by a three-line inline script printed just before the first block, so the blocks never flash in the wrong mode before the plugin's scripts load.
- Without JavaScript the switch is hidden, and the device's setting still applies.
- Each mode brings its own background and text colour, so light stays light on a dark theme and the other way round. In dark mode the accent is lighter, and the active pill's text is dark to keep contrast.
- The switch is a button labelled "Dark mode" with `aria-pressed`, so screen readers announce it as a toggle.
- Authors can hide it with "Show light/dark switch" on the filter block.

**Loading states: skeletons, Suspense and lazy images.**
- **Frontend skeleton:** when a filter or page change takes longer than 200 ms, the current cards turn into placeholder shapes (a pill for the category, rounded blocks for the title and excerpt) until the new page arrives. A quicker load only dims the grid, because a skeleton that shows for a split second reads as a flicker.
- **Image placeholders:** each card image shimmers until it has loaded, then fades in; a broken image stops shimmering too.
- **Lazy images**, following web.dev's guidance on browser-level lazy loading, and measured in Chrome:
  - The page's image optimiser decides first. That's WordPress core, or a plugin that replaces it, such as Elementor's optimised image loading: the first few content images are eager (the first with `fetchpriority="high"`), and the rest lazy. The grid follows that decision for its first image, because the optimiser counts every image on the page.
  - If the first image is eager (the grid starts near the top), the rest of the first row is eager too. Otherwise a 4-column row has its 4th image lazy though it's in view.
  - The second row then starts loading right away at `fetchpriority="low"`. It's in view on most laptop and desktop screens, and a lazy image can't start until the page's CSS has loaded. Measured on Fast 3G at 1280×800, the second row started at about 1.6 s instead of about 4.1 s. On a phone it's below the fold, but Chrome would load most of it at once anyway (lazy images within about 1250 px are fetched immediately), so it costs one extra small request. Low priority keeps it from delaying the first image.
  - Everything after the second row is lazy, and nothing changes when the grid starts further down the page.
  - `WMPGF_Image_Loading` makes these changes after the optimiser's own: on `wp_content_img_tag` for grids in post content (where core decides late), and on `wp_get_loading_optimization_attributes` elsewhere. Each card image carries `data-wmpgf-load` (first, first-row or second-row).
  - Every image has `width` and `height` and a CSS `aspect-ratio`, so nothing shifts as images arrive (measured CLS 0.001 on a phone, 0.008 on desktop). The loading placeholder hides the image with `opacity`, not `display: none`, so the browser still loads it. No image is both lazy and high priority, and a page has one high-priority image.
  - The editor preview loads its first row eagerly and the rest lazily.
- **Editor Suspense:** the grid and filter previews read their data with `useSuspenseSelect` inside `<Suspense>`. Until the posts, categories and images have loaded, the fallback is a skeleton in the block's final layout (the chosen columns and page size), so nothing jumps when the content arrives. The filter's count and its pills are separate boundaries, and each appears as soon as its own data arrives.
- **Reduced motion:** placeholders keep their shape without the moving sweep.

**Code splitting only where it pays off.** The Interactivity Router, the largest frontend dependency, is a lazy chunk: it's imported the first time someone filters or pages, so visitors who never do never download it. The editor scripts are not split with `React.lazy`. They are 3 to 5 KB each, and a separate chunk would add a request before the preview could render, making the editor slower rather than faster.

**Single posts live at `/grid-post/{slug}/`.** Version 1.3 put the category first (`/{category}/{slug}/`). A rewrite rule with no fixed text in front matches every two-segment URL, so author archives, date archives, feeds, `/page/2/` and nested pages all returned 404. The brief didn't ask for category URLs, so I removed them. A test now covers those five URLs.

**Seeding checks its own work, and only touches what the plugin created.** The seeder records the ID of everything it creates (posts, cover images, terms, the demo page) at the moment it creates it, and tags each demo post with the demo entry it belongs to. Content is treated as demo content only through those records, never because its title or slug matches, so a post or page the site owner made is never changed or counted as demo content. Terms are the one exception: an existing term with the same name is reused for assignment, but it isn't recorded as created.

After each run the seeder reads everything back and checks it. Every demo post must be published, have an excerpt, a featured image whose file exists, and its categories and tags. The demo page must be published and contain the filter and a grid with the pagination block inside it. Only then is seeding marked complete. Failed image, attachment, featured-image and term assignments are logged (with `WP_DEBUG_LOG`) and leave seeding open. The next activation repairs the plugin's own posts and page in place (status, excerpt, cover, terms, blocks) instead of creating duplicates.

`uninstall.php` deletes the demo page and demo posts first, then each seeded image and term only if nothing left on the site uses it. That covers:
- a post of any status that still has the term;
- an image that is a featured image, the site icon or the logo, or whose file name appears in post content, post meta, options (widgets, theme mods such as a header image), term meta or user meta. Transients don't count, because they're caches.

If one of those checks fails, the item is kept. Cover images are generated as SVG text, so activation needs no network access and no PHP image extension.

## Tests and tooling

```bash
npm run lint:js      # ESLint (@wordpress/scripts)
npm run lint:css     # Stylelint, including the token rules
npm run lint:php     # WordPress Coding Standards (run composer install first)
npm run test:js      # Jest: the URL builder
npm run test:php     # PHPUnit in wp-env's tests container (start wp-env first)
npm run test:e2e     # Playwright in a real browser against wp-env (start wp-env first)
npm run plugin-zip   # Builds wm-posts-grid-filter.zip
```

The PHPUnit suite covers:
- The filter logic: OR within a taxonomy, AND across, search on top, unknown slugs, both URL forms, and clamping of posts per page and page number.
- The PHP URL builder.
- The rewrite regression above.
- Seeding:
  - a fresh run creates complete demo content;
  - failed image creation and failed term assignment leave seeding open;
  - a retry repairs the plugin's own posts and page without duplicates;
  - ownership records survive partial failures;
  - a user's post or page with a matching title or slug is left untouched.
- Uninstall:
  - only seeded content is removed;
  - a seeded term still used by a user's draft or private post is kept;
  - a seeded image still used by other content is kept.

To run it without Docker, run `composer install` and set `WP_PHPUNIT__TESTS_CONFIG` to a `wp-tests-config.php` that points at an empty database.

The Playwright suite (`tests/e2e`) drives a real browser.
- On the frontend, as a visitor: the OR/AND filter logic, Back and Forward, pagination and page size, the search debounce, keyboard focus, the form without JavaScript, and light and dark mode (following the device, remembering a choice, and applying it before the scripts load), the skeleton on a slow load, image placeholders and lazy loading, and the pill row keeping its first label in view on a phone.
- In the editor: the React preview, the Suspense skeleton while posts load, both Inspector controls, the light/dark switch setting, and the locked pagination inside a new grid.

It finds the demo page through the REST API, so it runs under any permalink setting. To run it against another disposable site, set `WP_BASE_URL`, `WP_USERNAME` and `WP_PASSWORD`.

GitHub Actions runs all linters, the build, and the Jest, PHPUnit and Playwright suites on every push, on WordPress 6.7 and the latest release. It also fails if the committed `build/` doesn't match the source. It then starts a fresh wp-env and requests the demo page twice:
- with pretty permalinks;
- with plain permalinks, where the page is `/?page_id=N`, checking a filtered view and that the Clear filters link, the Next link and the forms all keep `page_id`.

By hand, I've checked the demo page on Twenty Twenty-Four (a block theme, desktop and 375px) and on a classic theme: filtering, Back, pagination, and the editor preview.

## Known limitations

- **One grid and one filter per page.** The store holds one selection and the router region has a fixed name. Several independent pairs would need per-block state, and the brief doesn't call for it.
- **Multisite.** Blocks and permalinks work on every site. Demo content is created only on the site where the plugin is activated; network activation creates it on the main site only. I chose that on purpose: a network admin wouldn't want 12 demo posts on every site.
- **Upgrading from 1.x.** 2.0 renamed the prefix, including the post type and block names, so 1.x content doesn't carry over. Delete 1.x first (its uninstall removes its demo content), then install 2.0.
- **Search** is WordPress's built-in search on title, excerpt and content, with no relevance ranking beyond that.
- **Every filter combination can be indexed.** With 4 categories and 6 tags, each combination is a different, legitimate page. On a site with thousands of terms I would limit indexing of combinations, based on real traffic data.

## Project layout

```
wm-posts-grid-filter.php     Bootstrap and constants
uninstall.php                Removes only what the seeder created and nothing still uses
includes/
  class-wmpgf-plugin.php         Hooks, activation, rewrite flush per version
  class-wmpgf-post-type.php      Post type and taxonomies
  class-wmpgf-request.php        The only code that reads $_GET; builds URLs
  class-wmpgf-query.php          Queries, cached per request
  class-wmpgf-blocks.php         Block and style registration
  class-wmpgf-seeder.php         Demo content
  class-wmpgf-single-template.php
  demo-content.php               The 12 demo posts
templates/single-wmpgf_post.php  Single post template for themes without one
assets/css/tokens.css            Design tokens and the font
src/shared/                      url.js (URL builder), navigate.js (router call)
src/blocks/*/                    block.json, edit.js, render.php, view.js, style.css
tests/php/                       PHPUnit
build/                           Compiled from src/, committed
```

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
