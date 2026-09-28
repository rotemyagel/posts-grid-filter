# Development history

The full, round-by-round record of what was built, what broke, and how it was
found and fixed — kept separate from `README.md` so the README can stay a
short reference document rather than a development diary. Every claim below
was verified against a real running WordPress install (`curl`, WP-CLI,
`wp eval`, or a real browser), not just read from the diff.

## Core build

**A dedicated post type and taxonomies, not core post/category/tag.** The
plugin registers `pgf_post` with `pgf_category`/`pgf_tag`, rather than
reusing WordPress's built-in `post`/`category`/`post_tag`. This keeps all
demo content fully isolated and identifiable — reactivating or uninstalling
never touches real site content.

**Inter-block sync: the Interactivity API, with REST doing the actual
filtering.** Considered custom DOM events, a hand-rolled global JS object,
and URL-params-with-full-reload before settling on a shared Interactivity
API store namespace (`store('posts-grid-filter', {...})`, called from both
blocks' `view.js`). WordPress merges every caller's partial state/actions
into one shared store by namespace — the documented pattern for this exact
situation, and why the two blocks work together without either needing to
know the other exists.

Filtering itself isn't reimplemented in JS or a custom REST controller. Any
taxonomy registered with `show_in_rest => true` gets an automatic REST
collection parameter, and WordPress's own `tax_query` behavior — `IN` (OR)
within one taxonomy parameter, `AND` across different ones — is exactly the
"OR within a filter type, AND across filter types" logic the brief asks for.
Verified against `wp term list` counts: one category returned that count,
two categories returned their union, adding a tag on top returned the
intersection.

**Pagination is real, full-reload navigation — not AJAX, not infinite
scroll.** An early version fetched page 2+ via REST with no URL change; a
later version added `pushState()` on top. Both hit the same problem SEO
guidance on infinite scroll specifically warns against: with JS disabled
(what a crawler effectively runs), page 2 never existed as a reachable URL.
The final design has no JS in the pagination click path at all — Prev/Next
are plain `<a href="?pgf-page=2">` links with `rel="next"/"prev"`, and
`posts-grid/render.php` reads that same parameter server-side. Filtering
keeps its instant AJAX behavior (a separate concern); the one connection is
that a Next/Prev link's `href` needs to carry the *current* filter
selection forward, handled by reactive `prevHref`/`nextHref` getters in
`pagination/view.js`.

Verified via `curl` (no JS, no cookies): a filtered, paginated link returns
correct server-rendered content with the right checkboxes pre-checked. In a
real browser: filtering via AJAX (no URL change) then clicking Next produces
a genuine navigation to `?pgf-page=2&pgf_category[]=8&pgf_category[]=10`,
confirmed via `document.readyState` and the fresh page's own checkbox state
after reload.

**A hydration bug worth remembering:** `posts-grid/view.js` originally
declared `page: 1, totalPages: 1` as client-side "initial" state, even
though both are always server-seeded via `wp_interactivity_state()`.
Depending on script-module evaluation timing, the client's literal default
could win over the already-correct hydrated value, silently resetting a
direct `?pgf-page=2` load back to page 1 after hydration. Caught by
comparing the raw hydration JSON (correct) against the live post-hydration
DOM (wrong) for the same request. Fixed by never declaring server-seeded
keys client-side — the only client-declared state today is `isLoading`.

**Performance/Core Web Vitals pass:** the client-rendered card path was
missing the `width`/`height`/`loading`/`decoding` attributes the
server-rendered first paint already had for free from `the_post_thumbnail()`
— a CLS regression on every filtered update. Fixed by reading the REST
response's `media_details.sizes.medium` and carrying its dimensions through.
The filtered fetch also scopes `_embed` and `_fields` to just what the card
needs (~52% payload reduction measured: 46.8KB → 22.5KB for 6 posts), and
seeded images now get real `alt` text (previously empty).

**Validation:** `postsPerPage` was cast to `(int)` with no range check in
either render.php — since block attributes live in `post_content` and can
be edited directly via the REST API or a hand-edited import, an untrusted
value could reach `WP_Query`'s `posts_per_page`, where `-1` means "every
post." Added `PGF_Blocks::sanitize_posts_per_page()`, clamped to `[1, 24]`,
used by both render.php files. Verified by tampering a post's `postsPerPage`
to `-1` and `999999` and confirming the rendered count stayed bounded.

## First senior-review pass

Reviewed as if by a second engineer: re-read every file, cross-checked
against a competing public implementation of the same brief, and actually
*ran* the parts that hadn't been exercised yet.

- **`uninstall.php` never actually deleted taxonomy terms.** `get_terms()`/
  `wp_delete_term()` require the taxonomy to be *registered*, unlike
  `WP_Query`'s raw-string post-type matching — and `uninstall.php` never
  registered it. Verified by actually deactivating, running `uninstall.php`
  exactly as WordPress core would, and inspecting the database: posts
  deleted correctly, all 10 terms left behind. Fixed by registering the
  post type/taxonomies explicitly at the top of the file.
- Editor-only: live grid preview images had `alt=""`; the column-count
  control used a plain `ButtonGroup` instead of `ToggleGroupControl` (which
  gives correct `radiogroup`/`radio` ARIA semantics for free). Both fixed;
  the `ToggleGroupControl` swap also surfaced a cross-version export-name
  mismatch (`__experimental` vs. stable), fixed by importing both names.
- **Known, not fixed:** `npm run lint:js` fails due to an
  `@typescript-eslint`/`ts-api-utils` conflict in the installed
  `@wordpress/eslint-plugin` tree, unrelated to this plugin's own code.

## Single-post rendering

Clicking through from a grid card rendered an essentially empty page: the
active theme (Hello Elementor + Elementor Theme Builder) has no template for
a custom post type, and its `single.php` fallback assumes one always will
exist. Confirmed via `curl` before writing any code.

Fixed with a `single_template` filter (`PGF_Single_Template`) serving a
plugin-bundled `templates/single-pgf_post.php` — title, featured image,
linked category/tag terms, content, back-to-grid link — but only when the
active theme has no `single-pgf_post.php` of its own. Verified in a real
browser (Elementor's header/footer only render fully there, not via `curl`).

## UI/UX pass against a competing demo

Compared the filter UI directly against a screenshot of a competing
implementation's live demo. Two gaps: no way to reset an active selection,
and a bare unstyled checkbox list.

- **Pill-style toggles**: the native checkbox stays in the DOM (still
  focusable, still real for assistive tech) but is visually hidden, with its
  `<label>` styled as the pill via `:has(input:checked)` — no new markup,
  the browser's own `:checked` state drives the visual.
- **A real "Clear filters" link**, not just a JS action: a plain `<a href>`
  with every filter param stripped via `remove_query_arg()`, progressively
  enhanced to reset the store via AJAX when JS is available.

Verified via `curl` for both the unfiltered and filtered SSR cases, and in a
real browser: pill fills and Clear appears on click, resets instantly.

## Category-prefixed post permalinks

Changed `/grid-posts/some-post/` to `/product-news/some-post/` on request.
WordPress has no built-in equivalent of the `%category%` tag for a *custom*
post type, so the same mechanism core uses internally (and WooCommerce uses
for `%product_cat%/%postname%/`) was replicated: a custom
`add_rewrite_tag()` plus a `post_type_link` filter that fills in the post's
actual (lowest-term-ID) category slug.

Two real bugs surfaced while verifying this against actual requests:

1. A bare `%pgf_category%` placeholder made WordPress's rewrite generator
   also emit an unprefixed `([^/]+)/?$` fallback rule (the same mechanism
   that makes `/2020/06/` work as a standalone date archive) — which
   silently broke *every* top-level page on the site, including the demo
   page, ahead of WordPress's own page-matching rule. Fixed with
   `'walk_dirs' => false`.
2. Registering the post type before the taxonomy meant the CPT's generic
   two-segment pattern won over the taxonomy's own archive rule for a URL
   matching both, breaking `/pgf_category/<term>/`. Fixed by registering the
   taxonomy first.

Also closed: unlike core's `%category%` tag, WordPress doesn't auto-correct
a wrong category segment for a custom rewrite tag — `/wrong-category/
real-post-slug/` resolved with a 200, not a redirect, meaning unbounded
duplicate URLs for the same post (the canonical `<link>` tag was always
correct regardless). Closed with a `template_redirect` 301 to
`get_permalink()`, mirroring what core does for its own post type.

A version-gated auto-flush (`PGF_Plugin::maybe_flush_rewrite_rules()`) means
an already-active install picks up a structural rewrite change on its next
request, instead of 404ing every permalink until someone re-saves
Settings → Permalinks by hand.

Separately, checked the plugin against `/%category%/%postname%/` as the
*site's own* permalink structure (unrelated to the change above) — the
CPT/taxonomy URLs and the query-string-based filter/pagination state are
independent of the site's permastruct, so nothing needed to change. One
unrelated site-data bug surfaced (`default_category` pointing at a
nonexistent term, breaking an unrelated core post) and was left alone as
out of scope — the plugin never touches a site's existing content by design.

## Second, independent review pass

A separate reviewer checked the plugin again, this time including a live
`$wp->main()` request-dispatch replay for one claim. Each finding was
verified directly before acting on it.

**Confirmed and fixed:**

- REST fetch broke under the "plain" `?rest_route=` permalink fallback
  (`rest_url()` already returns a URL containing its own `?`; appending
  another put filter params inside the `rest_route` value itself). Fixed
  with `URL`/`searchParams`; verified live by switching to plain permalinks
  and confirming the AJAX filter actually works in a real browser.
- A race condition: `refresh()` had no guard against a newer request
  resolving before an older one. Fixed with a request-sequence counter.
- Pagination links could accumulate stale filter values instead of
  replacing them — `add_query_arg()`/`remove_query_arg()` round-trip an
  existing `pgf_category[]=` value into `pgf_category[0]=`/`[1]=` (confirmed
  directly via `wp eval`), and the client-side URL builder only ever cleared
  the plain `[]` key. Reproduced end to end in a real browser and fixed.
- `uninstall.php` deleted every `pgf_post` and its current featured image
  unconditionally — a site owner's own post created after activation (and
  any existing media picked as its thumbnail) would be deleted too. Fixed
  by having the seeder record exactly which post/attachment IDs it creates,
  and having uninstall delete only those.
- A total term-creation failure would have hit a modulo-by-zero in
  `create_posts()`. `seed()` now aborts cleanly and doesn't mark seeding
  complete in that case, so a later activation retries.
- `include_children` wasn't set on the server-side `tax_query`, defaulting
  to `true` where the REST controller defaults the same kind of request to
  `false` — no visible effect on the flat seeded categories, but a latent
  inconsistency for nested real-world categories. Now `false` on both.
- An out-of-range `?pgf-page=` showed an empty grid next to a pagination
  label still reporting the real total. The first fix attempt was itself
  buggy — it clamped using the main query's own `max_num_pages` *after*
  running it with the out-of-range value already applied, and `WP_Query`
  returns `0`/`0` in that case, not the true total (verified directly).
  Fixed properly with a shared `PGF_Blocks::get_total_pages()`/
  `clamp_page()` used by both render.php files.
- A latent attribute-injection risk in client-rendered cards: a title was
  interpolated into an `alt="..."` attribute with only its tags stripped,
  not its quotes escaped. Rewritten to build cards as real DOM nodes with
  element properties, which the browser escapes the same way `esc_attr()`
  does server-side.
- Hardcoded English UI strings, indistinguishable error/empty states, dead
  option cleanup (`pgf_db_version`, never actually set) and a
  `package.json`/plugin-header version mismatch — all fixed.

**Checked and found not to be a real bug:** the review's top-priority
finding was that using `pgf_category`/`pgf_tag` as this plugin's own filter
query parameters — the same names their taxonomies' default public query
vars use — would let WordPress's main query consume them as taxonomy
constraints. Reasonable-sounding, so it was actually tested rather than
dismissed: replaying the real `$wp->main()` request dispatch with
`?pgf_category[]=8` present against the demo page, an unrelated core post,
and a raw `WP_Query`, `is_page()`/`is_404()` resolved correctly every time
and `$wp_query->tax_query` was empty. This plugin's filter parameters are
always array-shaped with numeric term IDs, which doesn't match the scalar
term-*slug* shape WordPress's taxonomy query-var handling expects — that
mismatch is why the two never actually collide here.

## Plugin slug rename

The brief asks for a unique prefix on the plugin slug specifically, not
just the post type/taxonomies. `pgf_post`/`pgf_category`/`pgf_tag`/`pgf/*`
blocks already had one; the plugin's own folder/slug (`posts-grid-filter`)
didn't. Renamed to `rotem-posts-grid-filter`, then to `wm-posts-grid-filter`
on request — folder, main plugin file, `Text Domain` header, every
`block.json`'s `textdomain`, every translatable-string call site,
`package.json`'s `name` (`package-lock.json` regenerated to match each
time), `.gitignore`'s zip pattern, and the GitHub repository itself.
Deliberately left untouched: the internal `pgf_`/`PGF_` prefix on classes/
post type/taxonomies, and the Interactivity API store namespace that
happens to reuse the string `posts-grid-filter` internally — a different
kind of identifier, not what "plugin slug" refers to.

## Third review pass ("recheck")

A follow-up recheck against the renamed plugin found the previous round's
ownership/uninstall fix didn't go far enough, plus one genuine bug in a
fix from the previous round:

- **A real bug in the pagination stale-parameter fix**:
  `clearArrayParam()`'s check — `key.slice(name.length)` matching a
  bracket-index shape — never actually confirmed `key` *started with*
  `name` first. An unrelated query parameter whose name happened to be the
  same length as `pgf_category`/`pgf_tag`, followed by `[N]`, would be
  wrongly matched and deleted. Fixed by adding `key.startsWith(name)` to
  the check.
- **Ownership tracking didn't cover adoption.** `create_posts()` adopts
  (rather than duplicates) an existing post matching a seed title, for
  idempotency — but its ID was still being added to the ownership-tracked
  list, meaning a pre-existing, non-plugin-created post could get deleted
  on uninstall anyway. Same gap in `create_demo_page()`'s adoption of an
  existing page at the demo slug. Fixed by tracking *created* IDs
  separately from *matched* IDs throughout — `create_terms()`/
  `create_tags()` now return both an `ids` map (for building posts) and a
  `created_ids` list (for ownership); `create_posts()` does the same for
  `post_ids`/`created_post_ids`; `create_demo_page()` sets a separate
  `pgf_demo_page_owned` flag only when it actually inserts the page.
  `uninstall.php` now checks ownership before deleting the demo page and
  only deletes terms it created, not every term in the taxonomy — closing a
  related gap where wholesale term deletion could strip a taxonomy
  relationship from a *surviving* post that used the same term.

Verified directly: planted a post with a title matching one of the 12 seed
titles, and a page at the demo slug, before activating. Confirmed both were
*adopted* (used, not duplicated) but excluded from ownership tracking, then
confirmed both survived a real `uninstall.php` run while the genuinely
seeded content was correctly removed. Re-verified fresh-creation ownership
separately (deleted the plants, purged all terms, reactivated) — a
genuinely new demo page and 10 genuinely new terms were correctly recorded
as owned.

## Fourth pass: fixing rather than documenting two limitations

Asked directly to fix the items in "Known limitations," rather than leave
them as accepted tradeoffs. Two were genuinely fixable; two were deliberate
architecture decisions, explained rather than reverted (see the README's
"Known limitations" section for those).

**GD requirement removed entirely.** Demo images were generated with GD
(`imagecreatetruecolor()`, `imagejpeg()`), meaning a PHP build without the
GD extension seeded posts with no featured images at all. Replaced with
locally-generated SVG -- a solid-color `<rect>` plus a text `<text>` label,
written directly via `file_put_contents()` -- which needs no PHP image
extension whatsoever, since SVG is plain XML text, not a rendered raster
format. WordPress blocks SVG uploads by default (a real XSS risk for
user-supplied files, since SVG can embed `<script>`); the fix allows it
only for the exact insert of a file this method just wrote itself, via
`add_filter( 'upload_mimes', ... )` immediately followed by
`remove_filter()` -- the site's own upload restrictions for real user
uploads are never weakened. `wp_generate_attachment_metadata()` can't
introspect SVG dimensions the way it does raster images, so width/height
are set directly on the attachment metadata instead.

One thing tried and reverted during this: an explicit `sizes.medium` metadata
entry, attempting to make `the_post_thumbnail( 'medium' )` report the SVG's
real 1200×800 dimensions (matching what the REST API's `media_details`
already reports) instead of WordPress's registered `medium` size box
(300×200). Verified this doesn't work the way it looks like it should: core's
`image_downsize()` always re-constrains a *named* size's reported width/
height to that size's registered bounding box via
`image_constrain_size_for_editor()`, regardless of what's stored in
metadata -- confirmed by reading `wp-includes/media.php` directly, not by
guessing. Reverted to an empty `sizes` array: the two render paths report
different absolute numbers for the same image (300×200 vs. 1200×800), but
an identical 3:2 aspect ratio either way, which is what a browser actually
needs from width/height to reserve correct layout space -- so the
inconsistency is cosmetic, not a real Core Web Vitals regression.

**`npm run lint:js` fixed, not just documented as broken.** Reproduced the
crash directly: `ts-api-utils@1.4.3` (a transitive dependency of
`@wordpress/eslint-plugin@22.22.0` via `@typescript-eslint@6.21.0`) failed
to read a property from the `typescript` module at runtime. `npm ls`
showed why -- nothing in the dependency tree pins an upper bound on
`typescript`, so npm had resolved it to `7.0.2`, a version far newer than
what `ts-api-utils@1.4.3`'s internal code (written for the TS 4.x/5.x era)
expects, even though its declared peer range (`>=4.2.0`, no upper bound)
didn't catch the incompatibility. Fixed with an `overrides` entry in
`package.json` pinning `typescript` to `5.4.5` -- a targeted fix that
doesn't require upgrading any of the ESLint or `@wordpress/*` packages
themselves, unlike the earlier `@wordpress/scripts` v30→v36 upgrade
attempt that hit its own unrelated conflict.

With the crash resolved, the linter then ran for real and reported actual
issues that had accumulated since it was last usable: mostly Prettier
formatting (fixed automatically via `--fix`), plus two real ones fixed by
hand -- a missing JSDoc `@param` type, and a destructured `actions` binding
in `posts-grid/view.js` that was never actually used locally (the
`refresh` action it's part of is still called correctly by the other
blocks, which get their own `actions` reference from their own separate
`store()` call into the same shared namespace).

Verified via a full fresh reseed after the SVG change (fresh-install
counts, `debug.log` clean, real-browser check that both the server-rendered
first paint and a client-side filtered re-render display the images
correctly with zero console errors) and by re-running `npm run lint:js`
to a clean, zero-error exit after the JS fixes.

## Fifth pass: actually running WordPress Coding Standards

The README claimed WPCS compliance "by convention" throughout, without an
automated check ever having been run against this plugin -- PHPCS itself
was available on the development machine, but the WordPress ruleset
specifically was never installed. Set this up properly rather than
continuing to assert it: a project-local `composer.json` (dev-only,
`vendor/` gitignored the same way `node_modules/` already is) pulling in
`squizlabs/php_codesniffer` + `wp-coding-standards/wpcs` (3.4.1, current
stable) + the Dealerdirect Composer installer, plus a `phpcs.xml.dist`
scoped to the plugin's own PHP and targeting `testVersion=7.4-` to match
the plugin header's declared minimum. `npm run lint:php` / `lint:php-fix`
wrap it, alongside the existing `lint:js`/`lint:css`.

First run found 26 real issues across 6 files. `phpcbf` auto-fixed 16
(equals-sign/array-arrow alignment, doc-comment spacing, unnecessary
double-quoted strings). The rest were fixed by hand:

- **A real deprecated-function usage**: `create_posts()`'s idempotency
  check used `get_page_by_title()`, deprecated since WordPress 6.2 (this
  plugin's own minimum is 6.5) -- every seed check was silently triggering
  a `_deprecated_function()` notice. Replaced with `WP_Query`'s own
  `title` parameter, which does the same exact-match lookup, per core's
  own deprecation notice.
- **Two `$_SERVER` sanitization findings** in the canonical-redirect
  method: the values are never output, only compared for equality or
  passed to `wp_safe_redirect()` (which validates the destination host
  itself), so there was nothing for further sanitizing to protect against
  beyond the `wp_unslash()`/`wp_parse_url()` already applied -- documented
  inline and the specific sniff code added to the existing
  `phpcs:ignore` comments (they were already there for a different,
  adjacent sniff, which is why this hadn't been caught before).
- **Nine "overriding a WordPress global is prohibited" findings**
  (`$term`, `$link`, `$post_type`, `$post_id`, `$taxonomy` used as local
  variable names in `posts-filter/render.php`, `templates/single-pgf_post.php`,
  and `uninstall.php`) -- renamed to non-colliding names
  (`$category_term`, `$tag_term`, `$category_link`, `$tag_link`,
  `$pgf_post_type`, `$seeded_post_id`, `$taxonomy_name`, `$found_term`).
  None of these were an active bug (all well inside function/file scope,
  never actually read back through the global), but the sniff exists to
  catch exactly this kind of footgun before a refactor turns it into one.

One finding was a genuine false positive, given a documented, targeted
exclusion rather than silently disabled everywhere: WPCS's filename sniff
wants `templates/single-pgf-post.php` (hyphens only), but WordPress's own
template-hierarchy convention requires `single-{$post_type}.php` exactly
-- and the post type is `pgf_post` (underscore), so the "wrong" filename
is the one that actually works, matching the exact string
`PGF_Single_Template`'s own `locate_template()` call looks for so a theme
can override it the same way core's own hierarchy expects. Renaming the
file to satisfy the sniff would have silently broken that override
mechanism.

`vendor/bin/phpcs` now exits clean: 0 errors, 0 warnings, across all 11
scanned files. Verified the fixes didn't change behavior with a full
fresh-install cycle (12 posts, 4 categories, an explicit second
reactivation cycle to specifically re-exercise the replaced
`get_page_by_title()` idempotency check -- confirmed no duplicate posts
and no deprecation notice in `debug.log`) and a real-browser check with
zero console errors.
