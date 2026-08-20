---
description: Create a new release branch with the next plugin version and bump version metadata
argument-hint: "[version]  (optional — defaults to next patch bump)"
allowed-tools: Bash, Read, Edit
---

You are starting a new release branch for the **Profitly (`profitly`)** plugin.

The optional argument is a target version: `$ARGUMENTS`

Follow these steps exactly. If any precondition fails, STOP and tell the user — do not
work around it.

## 1. Preconditions

- Run `git status --porcelain`. If the output is non-empty, STOP: the working tree must
  be clean. Tell the user to commit or stash first.
- Run `git rev-parse --abbrev-ref HEAD`. If it is not `main`, STOP: this command must
  start from `main`.

## 2. Sync main

- `git checkout main`
- `git pull origin main`

## 3. Determine the target version

- Read the current version from `profitly.php` — the line
  `define( 'PROFITLY_VERSION', '<x.y.z>' );`.
- If `$ARGUMENTS` was provided and is a valid `x.y.z` semver string, use it as the target
  version. It must be strictly greater than the current version — if not, STOP.
- Otherwise, compute the next **patch** version (e.g. `1.0.1` → `1.0.2`). Patch-only
  bumps are the project default.

## 4. Create the release branch

- `git checkout -b release/<version>`

## 5. Bump the version in all three locations

Profitly keeps the version in three files — they MUST stay identical (see `CLAUDE.md`).
Edit each so the version string reads `<version>`:

1. `profitly.php` — the plugin header comment line `Version: <x.y.z>` (near line 6)
   **and** `define( 'PROFITLY_VERSION', '<x.y.z>' );` (near line 22).
2. `readme.txt` — the `Stable tag: <x.y.z>` line (near line 7).
3. `src/Settings/SettingsRegistry.php` — `public const VERSION = '<x.y.z>';` (near line 34).

## 6. Add a changelog stub

In `readme.txt`, find the `== Changelog ==` section and insert a new entry **directly
above the most recent existing version entry**, matching the existing format exactly:

```
= <version> (Unreleased) =
* Development in progress.
```

(Profitly uses `= x.y.z =` headers and plain `*` bullets — no category prefixes. Match
the existing style in `readme.txt`.)

## 7. Commit and push

- `git add -A`
- `git commit -m "chore(release): start v<version>"`
- `git push -u origin release/<version>`

## 8. Report

Tell the user:
- The previous version and the new version (from → to).
- The branch name `release/<version>` (created locally and pushed to GitHub).
- A reminder to do all feature development on this branch and to replace the
  `Development in progress.` changelog placeholder with real entries before finishing
  (and to add a matching `== Upgrade Notice ==` line).
- That they should run `/finish-release` from this branch when the release is ready.
