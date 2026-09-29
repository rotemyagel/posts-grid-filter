# Performance, loading and mobile notes

The design decisions behind page speed, loading states and the phone layout, with the measurements that led to them. The [README](../README.md) has the short version.

Unless noted, numbers are medians of five headless Lighthouse 12 runs on a fresh wp-env with Twenty Twenty-Five. The machine used benchmarks at about 2300, which is fast: total blocking time was 0 ms on every page, before and after the work below. So hydration work was also measured by counting directives and by timing long tasks in real Chrome with the CPU throttled 6×.

## Results (2.4.0)

| Page | Form | Performance | Accessibility | Best practices | SEO | LCP ms | CLS |
|---|---|---|---|---|---|---|---|
| Demo page | mobile | 96 → 96 | 98 → 100 | 100 | 92 | 2140 → 2014 | 0.001 → 0 |
| Demo page | desktop | 98 → 99 | 95 → 97 | 100 | 92 | 549 → 556 | 0.002 → 0.001 |
| Single post | mobile | 98 | 100 | 89 → 100 | 92 → 100 | 1700 → 1100 | 0.01 → 0 |
| Single post | desktop | 100 → 98 | 100 → 96 | 93 → 100 | 92 → 100 | 429 → 368 | 0.006 → 0 |
| `/sample-page/` (baseline) | mobile | 99 | 100 | 100 | 92 | 1848 → 1393 | 0 |
| `/sample-page/` (baseline) | desktop | 100 | 95 | 100 | 92 | 447 → 367 | 0 |

- Five runs of the same page spread over several points. Over 15 runs each, the demo page and the baseline both have a median of 98 on mobile.
- The demo page's largest element is the first cover image, while the baseline's is text; most of its LCP is server response time, which on wp-env under Docker on Windows swings between 0.6 and 2.6 s for every page. The plugin's blocks render in 5 to 10 ms of each request.
- Plugin directives on the demo page: 80 → 33.
- The accessibility scores below 100 on desktop come from core's navigation block; see "What Lighthouse will keep reporting".

## Less work while the page hydrates

Before a page is interactive, the Interactivity API runtime walks every `data-wp-*` attribute and binds it. The demo page had 80 of the plugin's; now it has 33.

- **Image placeholders are CSS only.** Each card's thumbnail has the surface colour as its background, and the image covers it when it paints. An earlier version shimmered each image until its `load` event, which cost five directives per card.
- **One change handler per group.** The pills carry no `data-wp-context` and no handler of their own. Each group's fieldset has one `data-wp-on-async--change`, which the checkboxes' change events bubble up to. Each checkbox keeps one `data-wp-bind--checked`, whose getter reads the slug from the checkbox's `value`.
- **Async handlers.** Handlers that don't call `preventDefault()` (search input, pill changes, page size, the light/dark switch) use `data-wp-on-async`, so the runtime yields to the browser before running them.
- **`withSyncEvent()` where it exists.** The three that do call it (submitting the search, "Clear filters", Prev/Next) are wrapped in `withSyncEvent()`, which WordPress 6.8 introduced. On 6.7 every handler already runs synchronously. The helper reads it from the module namespace at run time: a static import of a name 6.7 doesn't export would stop the whole module from loading there.

**Code splitting only where it pays off.** The Interactivity Router, the largest frontend dependency, is a lazy chunk: it's imported the first time someone filters or pages. The editor scripts are not split with `React.lazy`. They are 3 to 5 KB each, and a separate chunk would add a request before the preview could render.

## Loading states

- **Frontend skeleton.** When a filter or page change takes longer than 200 ms, the current cards turn into placeholder shapes until the new page arrives. A quicker load only dims the grid, because a skeleton shown for a split second reads as a flicker.
- **Editor Suspense.** The grid and filter previews read their data with `useSuspenseSelect` inside `<Suspense>`. Until the posts, categories and images have loaded, the fallback is a skeleton in the block's final layout, so nothing jumps when the content arrives. The filter's count and its pills are separate boundaries.
- **Reduced motion.** Placeholders keep their shape without the moving sweep.

## Lazy images

Following web.dev's guidance on browser-level lazy loading, and measured in Chrome:

- **The page's image optimiser decides first.** That's WordPress core, or a plugin that replaces it, such as Elementor's optimised image loading: the first few content images are eager (the first with `fetchpriority="high"`), and the rest lazy. The grid follows that decision for its first image.
- **The first row is eager as a whole.** If the first image is eager (the grid starts near the top), the rest of the first row is eager too. Otherwise a 4-column row has its fourth image lazy though it's in view.
- **The second row starts at low priority.** It's in view on most laptop and desktop screens, and a lazy image can't start until the page's CSS has loaded. On Fast 3G at 1280×800 it started at about 1.6 s instead of 4.1 s. On a phone it's below the fold, but Chrome fetches lazy images within about 1250 px anyway.
- **Everything after is lazy**, and nothing changes when the grid starts further down the page.
- **Where it's applied.** `WMPGF_Image_Loading` applies these rules after the optimiser's own: on `wp_content_img_tag` for grids in post content, and on `wp_get_loading_optimization_attributes` elsewhere. Each card image carries `data-wmpgf-load` (first, first-row or second-row).
- **No layout shift.** Every image has `width`, `height` and a CSS `aspect-ratio`. No image is both lazy and high priority, and a page has one high-priority image.

## Fonts

The blocks use Bricolage Grotesque, self-hosted (41 KB, Latin, SIL Open Font License), scoped to the plugin's blocks; a theme that prefers its own font sets `--wmpgf-font: inherit`.

- **A metric-matched fallback.** Until the font loads, text shows in Arial with `size-adjust`, `ascent-override` and `descent-override` set from both fonts' metric tables, so the swap doesn't move the text. On the demo page this took layout shift from 0.001 to 0.
- **No preload.** A `<link rel="preload">` for the font, printed only on pages with the blocks, was measured over five runs each way and didn't improve the median (mobile 97 either way, LCP 2004 ms with it and 2000 ms without). The largest element is the first cover image, and the preload started the font at the same moment, so the two shared the bandwidth. Without it, the image starts about 140 ms before the font.

## Design tokens

Three text sizes, two weights, one accent colour and two corner radii, defined in `src/tokens.css`. Stylelint rejects any other size, weight, radius or hex colour outside that file. The build minifies it like the blocks' stylesheets (4.7 KB of source becomes 2.4 KB) and WordPress prints it inline in `<head>`: `webpack.config.js` adds it as a stylesheet-only entry and points its font URL at `assets/fonts/`, where the font stays with its licence.

## On a phone

Measured at 375 px on Twenty Twenty-Five, which pads the page 30 px on each side.

- **No side padding of their own.** Below 600 px the blocks add no side padding, so their content lines up with the page title and cards are 315 px wide (they were 267 px). Their background still runs to the screen's edges: a `box-shadow` in the background colour paints the theme's side padding, and `clip-path` keeps it to the block's height. Negative margins were the alternative, but core's constrained layout sets `margin-left: auto !important` on its children, and a wrong guess at the theme's padding would make the page scroll sideways.
- **The filter stacks into four rows** below a 600 px block width:
  1. search at full width, at 17 px so iOS doesn't zoom;
  2. the count, "Clear filters" and the switch;
  3. categories, and
  4. tags, each in its own sideways-scrolling row.

  Group labels stay as `<legend>`s and show above each row. Each row runs to the screen's edge, so a pill cut off there shows there's more. Scrollbars are hidden. A pill selected in the URL is scrolled into view within its row. Pills keep a 44 px touch area. The second row keeps the switch's 44 px height before the script shows it, so nothing moves when it appears.
- **Pagination** has Prev, the page status and Next on one row, and the page size below, both centred, with 44 px Prev and Next.
- **Spacing:** 16 px between the filter and the grid when the filter sits right above it.
- **Desktop is unchanged.** Every change is inside a 600 px media or container query. Against 2.3.1, every element keeps its position and size.

## What Lighthouse will keep reporting

These come from the web server, core or the theme, so no change inside the plugin removes them.

- **Cache lifetimes on static assets.** That's the web server's `Cache-Control` configuration, and wp-env's Apache sends none.
- **A render-blocking navigation stylesheet.** It belongs to core's navigation block. It shows up on the demo page and not on `/sample-page/` because core inlines stylesheets smallest first, up to 40 KB in total, and the plugin's four (16 KB) leave no room for the navigation block's 20.8 KB. Raising that budget so it's inlined didn't change the median (mobile 96 either way), and first paint came later because the HTML grew by 21 KB, so the plugin leaves it alone.
- **The Interactivity API runtime's own cost.** Core loads it for the theme's navigation block too, so it runs on pages with none of the plugin's blocks.
- **The theme's Manrope font** (54 KB) and its `font-display` warning.
- **The `list` accessibility audit on desktop.** Core's navigation block, falling back to a page list, nests a `<ul>` directly inside another `<ul>`, on every page. So the demo page scores 97 on desktop (100 on mobile, where the menu is collapsed) and a single post 96. The axe browser test leaves that block out.
- **A missing meta description** everywhere except single Grid Posts, the demo page included. That's an SEO plugin's job; see the README.
