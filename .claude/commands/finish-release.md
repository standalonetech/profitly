---
description: Review the current release branch (code + security) and, if clean, merge it into main
allowed-tools: Bash, Read, Edit, Agent
---

You are finishing a release branch for the **Profitly (`profitly`)** plugin: review it,
and only if it passes, merge it into `main`.

This command must NOT merge anything if the review finds blocking issues or a quality
gate fails. The review gate is the entire point of this command.

## 1. Preconditions

- Run `git rev-parse --abbrev-ref HEAD`. It MUST match `release/*`. If not, STOP and tell
  the user to check out the release branch first.
- Run `git status --porcelain`. If non-empty, STOP: the working tree must be clean.
- Extract `<version>` from the branch name (`release/<version>`).
- `git fetch origin`.

## 2. Compute the diff

- `git diff main...HEAD --stat` for an overview.
- `git diff main...HEAD` for the full review.

## 3. Code review

Review the full diff against the conventions in `CLAUDE.md`. Flag any of:
- Order data read or written outside the WooCommerce CRUD API (`$order->get_meta()` /
  `update_meta_data()`). Direct post meta access breaks HPOS — this plugin declares HPOS
  compatibility in `Compatibility/HPOS`.
- Profit or money math done with native float operators instead of routing through
  `COGS/COGSCalculator` (decimal-string arithmetic, bcmath when available). Rounding
  correctness depends on this.
- New order meta keys that aren't prefixed `_profitly_*` or aren't declared as class
  constants (e.g. `OrderLineCOGS::META_UNIT`, `ShippingCostResolver::META_SNAPSHOT`).
- Snapshot writes that aren't idempotent — an order already carrying a snapshot must be
  left untouched, so historical profit never changes retroactively.
- Reads of the raw `profitly_settings` option array instead of the typed
  `Settings/SettingsRegistry` accessors (`get_gateway_fee()`, `get_shipping_cost()`,
  `get_shipping_cost_model()`), or new settings not added to `get_defaults()`.
- Admin URLs hand-built instead of `Admin\Menu::reports_url()` /
  `Admin\Menu::settings_url( $tab )`.
- New `$wpdb` queries without `$wpdb->prepare()`, or a new
  `phpcs:ignore WordPress.DB.DirectDatabaseQuery` without a justifying comment.
- A text domain other than `profitly`; missing escaping/sanitization on output or input.
- Capability checks not using `Constants::CAP_VIEW_REPORTS` (read-only screens) or
  `Constants::CAP_MANAGE` (write/settings screens).
- New feature classes that don't follow the `register_hooks()` convention or aren't
  instantiated in `Plugin::boot()`, or logic added to `Plugin` itself (it is bootstrap only).
- General WordPress/WooCommerce coding-standard issues.

## 4. Security review

Run the built-in **`/security-review`** skill over the branch changes, or dispatch a
general-purpose Agent with the full diff and this remit: audit as an attacker for
capability/nonce bypasses on the admin screens and CSV export, IDOR on order-scoped
reads, SQL injection in the `Reports/` aggregate queries, unescaped output in the
`Views/` templates, and any settings write path that skips sanitization.

(Profitly has no project-scoped security agent — TeraWallet Core and Pro each carry their
own money-moving `security-auditor`; there is no ledger here to audit.)

Whatever you use is **read-only**: collect every finding, do not let it apply fixes.

## 5. Quality gates & translations

Run each and **read the output** — do not infer results. There is no JS/CSS build step in
this plugin, so these are the whole gate:

- `composer test` — the full PHPUnit suite MUST pass.
- `composer analyze` — PHPStan level 6 over `src/`. Errors are blocking.
- `composer lint` — PHPCS (WordPress-Extra + WordPress-Docs). Errors are blocking,
  warnings are not.
- Regenerate the translation template:
  ```
  wp i18n make-pot . languages/profitly.pot \
    --domain=profitly \
    --exclude=vendor,tests,build,dist,node_modules
  ```
  `dist` and `vendor` MUST stay in the exclude list — `dist/` holds a staged copy of the
  whole plugin from a previous `/package-plugin` run, and omitting it indexes every string
  twice. Verify with
  `grep -c '^#: \(dist\|vendor\|tests\)/' languages/profitly.pot` — it must print 0.
  If `wp` (WP-CLI) is not installed, STOP and tell the user to install it; the release
  must ship an up-to-date `.pot`. The regenerated file is committed in step 9.

## 6. Version consistency check

Confirm all of the following agree on `<version>`:
- `profitly.php` header `Version:` line.
- `profitly.php` `PROFITLY_VERSION` define.
- `readme.txt` `Stable tag:` line.
- `src/Settings/SettingsRegistry.php` `public const VERSION`.
- The `release/<version>` branch name.

If they disagree, STOP and report the mismatch.

## 7. Changelog & upgrade notice

In `readme.txt`, find the `= <version> ... =` entry under `== Changelog ==`:
- It must contain real entries — if it still only has the `* Development in progress.`
  placeholder, STOP and tell the user to write the changelog before finishing.
- Replace `(Unreleased)` in the header with today's date in `Month D, YYYY` format
  (e.g. `(August 20, 2026)`).

Then make sure `== Upgrade Notice ==` has a matching `= <version> =` block with a
one-line summary of why users should update. Add it at the top of that section if missing.

## 8. Decision gate

- If the code review or the security review found **blocking** issues, or any of
  `composer test` / `composer analyze` / `composer lint` failed, or `wp i18n make-pot`
  failed, or any STOP condition above was hit → **STOP**. Present a clear, organized
  report of every finding. Do NOT merge.
- Otherwise, present a concise summary (what changed, agent results, gate results) and
  continue.

## 9. Commit the changelog finalization & POT

The changelog date/upgrade-notice edits (step 7) and the regenerated
`languages/profitly.pot` (step 5) must land on the release branch before the merge. Run
`git status --porcelain`; if it is non-empty:
- `git add readme.txt languages/profitly.pot`
- `git commit -m "chore(release): finalize v<version> changelog and regenerate POT"`
- `git push`

(The `POT-Creation-Date` header alone will always differ run-to-run; that is expected and
fine to commit. If nothing changed at all, skip this step.)

## 10. Merge into main

- `git checkout main`
- `git pull origin main`
- `git merge --no-ff release/<version> -m "Release v<version>"`
- `git tag v<version>`
- `git push origin main`
- `git push origin v<version>`

## 11. Clean up the release branch

- `git branch -d release/<version>`
- `git push origin --delete release/<version>`

## 12. Report

Tell the user:
- The merge commit hash on `main` and the tag `v<version>` that was pushed.
- A summary of the review (code review + security findings, gate results).
- That this command handles GitHub only. To publish to WordPress.org, they should run
  **`/package-plugin`** from `main` (builds `dist/profitly.zip` with a production
  `vendor/`), then follow `RELEASING.md` for the SVN trunk/tag commit. Do NOT package
  here.
