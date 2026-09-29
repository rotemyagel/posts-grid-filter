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

The environment serves what a live site serves: minified core scripts (`SCRIPT_DEBUG` off), so a Lighthouse run measures production code. PHP notices go to `wp-content/debug.log` (`WP_DEBUG_LOG`) and never into the page (`WP_DEBUG_DISPLAY` off); read it with `npm run env run cli -- cat wp-content/debug.log`.

**From source.** `build/` is committed, so a checkout works as is. After changing anything in `src/`, run `npm install` and `npm run build`.

## What you get

Three blocks, grouped under "WM Widgets" in the inserter:

- **Posts Grid** (`wmpgf/posts-grid`). The grid shows 2, 3 or 4 columns, and the columns step down when the block itself gets narrow, not only the screen. In the sidebar you choose columns, posts per page and the card titles' heading level. A post without a featured image shows the grid's fallback image, set in the sidebar; without one, a neutral placeholder the size of a cover keeps the row aligned. A featured image that isn't a usable image (a PDF, or an ID left behind by a database edit) counts as none.
- **Pagination** (`wmpgf/pagination`). This lives inside the grid and can't be used on its own. It has Prev and Next links, "Page 2 of 3", and a posts-per-page select for visitors (6, 12 or 24).
- **Posts Filter** (`wmpgf/posts-filter`). It has a search field, category and tag pills, a result count, "Clear filters" and a light/dark switch. It isn't nested in the grid; put it anywhere on the page.

On activation the plugin creates:
- 12 posts in its own post type, each with an excerpt, three paragraphs of body text and a generated cover image.
- 4 categories with 4 posts each; 4 of the posts have two categories.
- 6 tags.
- A demo page, `/posts-grid-filter-demo/`, with the filter above the grid. If a page already uses that address, it is left alone and the demo page gets the next free one (`/posts-grid-filter-demo-2/`).

Filter rules: several categories match any of them (OR), and the same goes for tags. Categories, tags and search combine with AND.

## How a filter click travels

1. **Click.** The checkbox's change event bubbles up to its group's fieldset, whose `data-wp-on-async--change` runs `toggleCategory` in the shared `wmpgf` store. The action reads the term's slug from the checkbox's own `value` and updates `state.selectedCategories`.
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
- **Until it loads**, text shows in a fallback face: Arial with `size-adjust`, `ascent-override` and `descent-override` set from both fonts' metric tables (average width of English text, ascent and descent), so the swap doesn't move the text. On the demo page this took layout shift from 0.001 to 0.
- **No preload.** I tried `<link rel="preload">` for the font, printed only on pages with one of the blocks, and measured five Lighthouse runs each way. It didn't improve the median (mobile performance 97 either way, LCP 2004 ms with it, 2000 ms without). The largest element is the first cover image, not text, and the preload started the font at the same moment as that image, so the two shared the bandwidth. Without the preload, the image starts about 140 ms before the font. So I removed it.

**A small design system.** There are three text sizes, two weights, one accent colour and two corner radii, all defined as tokens in `src/tokens.css`. Stylelint rejects any other size, weight, radius or hex colour outside `tokens.css`. The build minifies it like the blocks' stylesheets (4.7 KB of source with comments becomes 2.4 KB), and WordPress prints it inline in `<head>`: the webpack config adds it as a stylesheet-only entry and points its font URL at `assets/fonts/`, where the font stays with its licence. Each mode (light and dark) sets one text colour and one background, and the muted text, borders and surfaces are mixed from that text colour.

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
- **Image placeholders:** each card's thumbnail has the surface colour as its background, and the image covers it when it paints. That takes no script. An earlier version shimmered each image until its `load` event, which cost five directives per card for the runtime to hydrate (see "Less work while the page hydrates").
- **Lazy images**, following web.dev's guidance on browser-level lazy loading, and measured in Chrome:
  - The page's image optimiser decides first. That's WordPress core, or a plugin that replaces it, such as Elementor's optimised image loading: the first few content images are eager (the first with `fetchpriority="high"`), and the rest lazy. The grid follows that decision for its first image, because the optimiser counts every image on the page.
  - If the first image is eager (the grid starts near the top), the rest of the first row is eager too. Otherwise a 4-column row has its 4th image lazy though it's in view.
  - The second row then starts loading right away at `fetchpriority="low"`. It's in view on most laptop and desktop screens, and a lazy image can't start until the page's CSS has loaded. Measured on Fast 3G at 1280×800, the second row started at about 1.6 s instead of about 4.1 s. On a phone it's below the fold, but Chrome would load most of it at once anyway (lazy images within about 1250 px are fetched immediately), so it costs one extra small request. Low priority keeps it from delaying the first image.
  - Everything after the second row is lazy, and nothing changes when the grid starts further down the page.
  - `WMPGF_Image_Loading` makes these changes after the optimiser's own: on `wp_content_img_tag` for grids in post content (where core decides late), and on `wp_get_loading_optimization_attributes` elsewhere. Each card image carries `data-wmpgf-load` (first, first-row or second-row).
  - Every image has `width` and `height` and a CSS `aspect-ratio`, so nothing shifts as images arrive (measured CLS 0.001 on a phone, 0.008 on desktop). The skeleton hides the images with `opacity`, not `display: none`, so the browser still loads them. No image is both lazy and high priority, and a page has one high-priority image.
  - The editor preview loads its first row eagerly and the rest lazily.
- **Editor Suspense:** the grid and filter previews read their data with `useSuspenseSelect` inside `<Suspense>`. Until the posts, categories and images have loaded, the fallback is a skeleton in the block's final layout (the chosen columns and page size), so nothing jumps when the content arrives. The filter's count and its pills are separate boundaries, and each appears as soon as its own data arrives.
- **Reduced motion:** placeholders keep their shape without the moving sweep.

**On a phone.** Measured at 375 px on Twenty Twenty-Five, which pads the page 30 px on each side.
- **No padding of their own.** Below 600 px the blocks add no side padding, so their content lines up with the page title and cards are 315 px wide, the theme's whole content area (they were 267 px, 71% of the screen). The padding exists because each mode paints its own background, so that background still runs to the screen's edges: a `box-shadow` in the background colour paints the theme's side padding, and `clip-path` keeps it to the block's height. I chose that over the negative margins I first considered. Core's constrained layout sets `margin-left: auto !important` on its children, a wrong guess at the theme's padding would make the page scroll sideways, and a shadow never changes the layout.
- **The filter**, below a 600 px block width, stacks into four rows:
  1. search, full width, at 17 px so iOS doesn't zoom on focus;
  2. the post count on the left, "Clear filters" and the switch on the right;
  3. categories, and 4. tags, each in a row of its own that scrolls sideways.
  Each group's label sits above its row, small and muted, rather than being hidden: it tells a sighted user what the row is, and screen readers get the same `<legend>` either way. Each row runs to the screen's edge, so a pill cut off there shows there is more, and the fade stays. Scrollbars are hidden in both layouts. A pill selected in the URL is scrolled into view within its row when the page loads. Pills keep their 44 px touch area.
- **Pagination** stacks into Prev, "Page 1 of 2" and Next on one row and the page size below it, both centred, with 44 px Prev and Next.
- **Spacing**: 16 px between the filter and the grid when the filter sits right above it, as one band in dark mode.
- A Playwright suite at 375 × 812 checks all of this in light and dark mode, including that the page never scrolls sideways.

Desktop is unchanged: every change is inside a 600 px media or container query.

**Less work while the page hydrates.** Before a page is interactive, the Interactivity API runtime walks every `data-wp-*` attribute and binds it. The demo page had 80 of ours; now it has 33.
- The image placeholder is CSS only (see above), which removed five directives per card.
- The pills carry no `data-wp-context` and no handler of their own. Each group's fieldset has one `data-wp-on-async--change`, which the checkboxes' change events bubble up to. Each checkbox keeps one `data-wp-bind--checked`, whose getter reads the slug from the checkbox's `value`.
- Handlers that don't call `preventDefault()` (search input, pill changes, page size, the light/dark switch) use `data-wp-on-async`. The runtime yields to the browser before running them, so a keystroke or click never waits for them.
- The three that do (submitting the search, "Clear filters", Prev/Next) are wrapped in `withSyncEvent()`, which WordPress 6.8 introduced; on 6.7, every handler already runs synchronously.

**Code splitting only where it pays off.** The Interactivity Router, the largest frontend dependency, is a lazy chunk: it's imported the first time someone filters or pages, so visitors who never do never download it. The editor scripts are not split with `React.lazy`. They are 3 to 5 KB each, and a separate chunk would add a request before the preview could render, making the editor slower rather than faster.

**Card titles are `h2` by default, and the level is a setting.** The page title is the `h1`, so `h3` card titles skipped a level, which screen reader users rely on to navigate. "Title heading level" in the grid's sidebar offers Heading 2, 3 or 4, for a grid placed under a heading of its own. `render.php` accepts only 2, 3 or 4 and falls back to 2, so a hand-edited attribute can't print another tag. The editor preview uses the same level. The filter's optional heading is an `h2` for the same reason. The Playwright suite runs axe on the demo page and a single post, in light and dark mode.

**A meta description on single Grid Posts only.** Core and most themes print no meta description, and Lighthouse's SEO audit fails without one. Normally that's an SEO plugin's job, and a blocks plugin shouldn't write to every page's `<head>`. So the plugin covers only what it owns: single `wmpgf_post` pages, whose template and post type are its own. The description is the post's excerpt, or else the start of its content, as plain text, cut at a word to at most 160 characters (what search results show). It is never a second description: the plugin collects what `wp_head` prints (first and last on the hook) and adds its own only if nothing else printed one, whether an SEO plugin, the theme or anything else. If another plugin leaves an output buffer open inside `wp_head`, it adds nothing rather than risk closing the wrong buffer. The demo page and other pages are left to the theme and SEO plugins, so the demo page's SEO score stays at 92 on a site without one.

**The single post page follows the theme type.** Block themes have no `header.php`, so a PHP template that calls `get_header()` makes WordPress fall back to a deprecated file: a notice in the page, and no doctype, viewport tag, theme header or footer. On a block theme the plugin registers a block template (`register_block_template()`, WordPress 6.7) made of core blocks: the theme's header and footer template parts, then the featured image, title, categories, content and tags. A theme's own `single-wmpgf_post.html`, or a copy edited in the Site Editor, takes precedence. Classic themes keep `templates/single-wmpgf_post.php` and its stylesheet.

**Single posts live at `/grid-post/{slug}/`.** Version 1.3 put the category first (`/{category}/{slug}/`). A rewrite rule with no fixed text in front matches every two-segment URL, so author archives, date archives, feeds, `/page/2/` and nested pages all returned 404. The brief didn't ask for category URLs, so I removed them. A test now covers those five URLs.

**Seeding checks its own work, and only touches what the plugin created.** The seeder records the ID of everything it creates (posts, cover images, terms, the demo page) at the moment it creates it, and tags each demo post with the demo entry it belongs to. Content is treated as demo content only through those records, never because its title or slug matches, so a post or page the site owner made is never changed or counted as demo content. Terms are the one exception: an existing term with the same name is reused for assignment, but it isn't recorded as created.

After each run the seeder reads everything back and checks it. Every demo post must be published, have an excerpt, a featured image whose file exists, and its categories and tags. The demo page must be published and contain the filter and a grid with the pagination block inside it. Only then is seeding marked complete. Failed image, attachment, featured-image and term assignments are logged (with `WP_DEBUG_LOG`) and leave seeding open. The next activation repairs the plugin's own posts and page in place (status, excerpt, cover, terms, blocks) instead of creating duplicates.

**Upgrading from 2.0.0.** Version 2.0.0 could mark seeding complete even when a post's featured image or its categories and tags had failed. Those installs are checked once against the current rules:
- **When it runs:** on the next activation, or, when the plugin is updated in place (which doesn't run activation), from a one-off WP-Cron event. `init` schedules that event only while the check is outstanding, which costs two autoloaded options per request. The check itself runs in the background, not during a visitor's page load.
- **What it repairs:** a missing or unusable featured image, and categories and tags that still exist. It does this only on the plugin's own posts (the ownership records), and only on those still exactly as seeded: published and never saved since.
- **What it leaves alone:** it creates, republishes and recreates nothing. A post or term the owner deleted, a post they edited, drafted or trashed, and the demo page all stay as they are, and none of them count against the check.
- **When it's done:** only after everything in scope reads back complete does it record `wmpgf_seed_validated_version` (a number raised whenever the rules change). A failed repair is tried again an hour later, and a database lock keeps two requests from repairing at once.

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
- The single post page: on Twenty Twenty-Five, a complete page (doctype, viewport tag, the theme's header and footer, nothing written to the debug log); on a classic theme, the PHP template and its stylesheet.
- The meta description: from the excerpt or the content, cut at a word, only on Grid Posts, and never a second one.
- Block output: card titles at h2, h3 or h4 only, and the design tokens inlined, minified, with the fallback font face.

To run it without Docker, run `composer install` and set `WP_PHPUNIT__TESTS_CONFIG` to a `wp-tests-config.php` that points at an empty database.

The Playwright suite (`tests/e2e`) drives a real browser.
- On the frontend, as a visitor: the OR/AND filter logic, Back and Forward, pagination and page size, the search debounce, keyboard focus, the form without JavaScript, and light and dark mode (following the device, remembering a choice, and applying it before the scripts load), the skeleton on a slow load, image placeholders, lazy loading and image sizes, fewer than 40 directives to hydrate, a single post as a complete page with the theme's header and footer, and, at 375 × 812 in light and dark mode (`mobile.spec.js`), the phone layout: no sideways scroll, full-width cards, the filter's four rows with both groups in view, the 16 px gap, pagination on two centred rows, a selected pill scrolled into view, 44 px touch targets, and a layout shift under 0.01 while the page loads. Axe checks the demo page and a single post in both modes.
- In the editor: the React preview, the Suspense skeleton while posts load, the Inspector controls (columns, posts per page, title heading level), the fallback image control, the light/dark switch setting, and the locked pagination inside a new grid.

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

### What Lighthouse will keep reporting

These come from the web server, core or the theme, so no change inside the plugin removes them. Measured on a fresh wp-env with Twenty Twenty-Five.

- **Cache lifetimes on static assets.** How long browsers may keep files is the web server's `Cache-Control` configuration, and wp-env's Apache sends none.
- **A render-blocking navigation stylesheet.** It belongs to core's navigation block in the theme's header. It shows up on the demo page and not on `/sample-page/` because core inlines stylesheets smallest first, up to 40 KB in total: the plugin's four (16 KB) and core's small ones leave no room for the navigation block's 20.8 KB, so it is printed as a `<link>`. I measured raising that budget so it's inlined as well. The median score didn't change (mobile 96 either way), and first paint came later because the HTML grew by 21 KB, so the plugin leaves core's budget alone.
- **The Interactivity API runtime's own cost.** Core loads it for the theme's navigation block too, so it runs on pages with none of the plugin's blocks: the review measured about 430 ms of blocking time for it on `/sample-page/` under mobile throttling. The plugin's part is the directives it adds (33 on the demo page) and its own view scripts.
- **The theme's Manrope font** (54 KB) and its `font-display` warning. The theme loads it on every page.
- **The `list` accessibility audit on desktop.** Core's navigation block, when it falls back to a list of pages, nests a `<ul>` directly inside another `<ul>`. That fails on every page, `/sample-page/` included, so the demo page scores 97 for accessibility on desktop and 100 on mobile, where the menu is collapsed. The axe browser test leaves that block out for the same reason.
- **A missing meta description** everywhere except single Grid Posts, the demo page included. See "A meta description on single Grid Posts only" above.

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
  class-wmpgf-single-template.php  Single post: block template, or the PHP one on classic themes
  class-wmpgf-meta-description.php Meta description on single Grid Posts, unless one exists
  demo-content.php               The 12 demo posts
templates/single-wmpgf_post.php  Single post template for classic themes without one
src/tokens.css                   Design tokens and the font's @font-face (built to build/tokens.css)
assets/fonts/                    The font and its licence
webpack.config.js                @wordpress/scripts' config, plus the tokens
src/shared/                      url.js (URL builder), navigate.js (router call)
src/blocks/*/                    block.json, edit.js, render.php, view.js, style.css
tests/php/                       PHPUnit
build/                           Compiled from src/, committed
```

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
