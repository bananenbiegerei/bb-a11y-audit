# BB A11y Audit

In-admin accessibility auditing for WordPress, using [axe-core](https://github.com/dequelabs/axe-core) instead of the manual axe DevTools browser-extension workflow. Maintain a list of "Locations" (representative URLs per page template), scan each one in-browser via a same-origin iframe, keep full scan history, and export a merged CSV report.

## Requirements

- WordPress 5.9+, PHP 7.4+
- `manage_options` capability to use the plugin's admin pages (solo-admin tool, no custom roles)

See the plan/architecture notes for the full design: browser-based iframe scanning (no headless/server-side runner), auto-suggested + manually curated Location list, plain CSS-selector scan scope, full scan history with Anmerkung carry-forward across re-scans, configurable WCAG-focused vs full (best-practice) rule profiles.

## Scan profiles & estimate-aligned coverage

**Settings → Scan profile**

| Profile | Tags | Notes |
|---|---|---|
| **WCAG focused (default)** | `wcag2a`, `wcag2aa`, `wcag21aa` | Matches sold estimate-style audits. Landmark/region/heading-structure best-practice noise stays off. |
| **Full** | above + `best-practice` | Previous behaviour (`region`, `landmark-*`, `heading-order`, …). |

Open-source axe-core covers the usual estimate Rule IDs (`aria-required-children`, `aria-required-parent`, `link-name`, `image-alt`, `color-contrast`, `aria-progressbar-name`, `nested-interactive`, `autocomplete-valid`, `aria-allowed-attr`). Deque Pro’s `advanced/text-contrast` is **not** available; use `color-contrast` for WCAG 1.4.3.

**Locations → Find Suggestions → Coverage checklist** resolves estimate template slugs (`header`, `footer`, `front-page`, `page-about-us`, `page-newsletter`, `page-faq`, `page-news`, `cart`, `single-product`, `single-projects`, `form-checkout`, `archive-press`, `archive-projects`, `archive`) against the live site. Header/footer get default CSS selectors so scans isolate those regions instead of duplicating the whole home page. Remap discovery with the `bb_a11y_coverage_targets` filter.

### Re-running an estimate-comparable CSV

1. Update/activate this plugin build on the target site.
2. Confirm **A11y Audit → Settings** uses **WCAG focused**.
3. **Locations → Find Suggestions** → add every **ready** coverage row (manually add any unresolved slug once the page/CPT exists).
4. For existing `header` / `footer` rows without selectors, set Default Selector to `header, [role="banner"]` / `footer, [role="contentinfo"]` (or delete and re-add from coverage).
5. **Run Scan → Scan Selected Locations** (batch).
6. **Results → Export CSV** and compare Location + Rule ID columns to the estimate lines.

### Same-site only

Scanning works by loading a Location's URL in a same-origin `<iframe>` inside wp-admin and reaching directly into its DOM (`iframe.contentDocument`/`contentWindow`) to inject axe-core and read results back — no `postMessage` handshake, which is only possible because the frame is same-origin. **A Location's URL must therefore be on the same WordPress site the plugin is installed on.** `BB_A11y_Locations::is_same_site_url()` enforces this server-side (compares the URL's host against `home_url()`) on every Location create/update, rejecting anything else with a 400 before it ever reaches the Run Scan screen.

This isn't a config knob to relax — a third-party site generally sends its own `X-Frame-Options`/CSP `frame-ancestors` headers that no code in this plugin can bypass; the iframe silently stays on `about:blank` and Run Scan reports "the page loaded but appears empty". If you need to audit an external site, that's still a manual axe DevTools job outside this plugin. A *same*-site page that triggers that exact error instead indicates a security plugin, CDN, or reverse proxy adding those headers to your own site — relax that policy for admin-initiated requests if you hit it.

## No CSS/JS build step

Unlike some of our other plugins, this one deliberately has **no compiler/bundler** in the loop:

- `assets/js/axe.min.js` is vendored verbatim from the `axe-core` npm package root (its own prebuilt UMD bundle, MPL-2.0 licensed) -- see [Updating axe-core](#updating-axe-core) below.
- `assets/js/admin-*.js` are plain vanilla JS, enqueued as-is.
- `assets/css/admin.css` is hand-authored, no preprocessor.

This keeps the plugin trivially auditable (every shipped file is the file you'd read in the editor) and avoids maintaining `node_modules`/webpack config for a solo-admin internal tool with no framework or design-system dependency. If that ever changes (e.g. this plugin grows a themed public-facing surface), a CSS build step can be added the same way our other plugins do it, without touching the packaging/release scripts below.

## Build

```
npm install
npm run build              # copy plugin files into build/bb-a11y-audit/
npm run build -- --zip     # same, plus build/bb-a11y-audit.zip (unversioned, for quick ad-hoc testing)
```

`npm install` has nothing to install today (no dependencies) but is kept as the standard first step so the workflow matches our other plugins. `npm run build` runs `scripts/build.sh`, which `rsync`s the whole plugin directory into `build/bb-a11y-audit/` minus dev-only files (`scripts/`, `package.json`, `README.md`, `.git`, `node_modules`, etc.) -- see that file for the exact exclude list.

### Releasing

```
npm run release
```

Runs `scripts/release.js`: prompts for a version bump (`[1]` major / `[2]` minor / `[3]` patch / Enter to keep current), writes the new version into `package.json`, the plugin header comment and `BB_A11Y_AUDIT_VERSION` constant in `bb-a11y-audit.php`, and the `Stable tag` in `readme.txt`, rebuilds, and produces a versioned zip at `dist/bb-a11y-audit-<version>.zip` (each release keeps its own file rather than overwriting one). `dist/` and `build/` are gitignored.

Note: `BB_A11Y_AUDIT_DB_VERSION` (in `bb-a11y-audit.php`) is a separate database-schema version, not the plugin release version -- the release script does not touch it. Bump it manually in code when `class-bb-a11y-activator.php`'s table definitions change, so the `admin_init` upgrade check re-runs `dbDelta()`.

### Updating axe-core

`assets/js/axe.min.js` has no update mechanism beyond manual replacement: download the matching version's `axe.min.js` from the [axe-core npm package](https://www.npmjs.com/package/axe-core) (package root, not a `dist/` subfolder) and overwrite the file in place. Sanity-check afterward that `admin-run-scan.js`'s `iframe.contentWindow.axe.run(...)` call still matches that version's API (stable across the 4.x line at the time of writing).
