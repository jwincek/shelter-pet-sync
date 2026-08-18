# ShelterKit Pets

Adoptable pet listings and printable kennel cards for animal shelters. Built on the modern WordPress stack: Abilities API, Block Bindings, and Interactivity API.

Pets can be entered by hand or synced from [Petstablished](https://petstablished.com) — no platform account is required.

## Requirements

- WordPress 6.9+
- PHP 8.1+

A Petstablished account is optional. Without one, pets are entered in the editor and everything else works the same.

## Installation

1. Download `shelterkit-pets-<version>.zip` from the [latest release](https://github.com/jwincek/shelterkit-pets/releases)
   and install it via **Plugins → Add New → Upload Plugin**.
2. Activate the plugin in **Plugins → Installed Plugins**.
3. Go to **Pets → Add New** and enter an animal, or import a spreadsheet from
   **Pets → Import**.
4. Add the pet blocks to your pages and templates from the block inserter.

The release zip is the distributable. This repository is not: it carries tests,
build scripts and CI config that `bin/build-dist.sh` strips out, so cloning it
into `wp-content/plugins/` ships development files to a live site.

Using Petstablished? Instead of step 3, go to **Pets → Sync Settings**, enter your public key, and click **Sync Now**.

## Local Development

This plugin ships a [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) config for one-command local development.

```bash
# Install dependencies
npm install
composer install

# Start the environment (requires Docker)
npm start

# Stop the environment
npm run stop
```

The local site will be available at `http://localhost:8888` with the plugin pre-activated.

## Tests

```bash
composer test:unit          # no WordPress needed
composer test:integration   # needs the WP test library, see tests/README.md
composer test               # both
```

## Linting

PHP coding standards are enforced via PHPCS with the WordPress ruleset. JavaScript and CSS are linted with `@wordpress/scripts`. Both run automatically on pull requests via GitHub Actions.

```bash
# PHP
composer lint        # Check
composer lint:fix    # Auto-fix

# JS / CSS
npm run lint:js
npm run lint:css
```

## Releasing

Publishing is one command, **run from `main` after the release PR is merged**:

```bash
git checkout main && git pull
git tag v1.3.0 && git push origin v1.3.0
```

Tagging from the release branch instead produces a tag that points at a commit
`main` never took, because the PR is squash-merged into a new one. The content is
identical, so nothing ships wrong — but the tag is orphaned from the history, and
the release page reads "1 commit to main since this release" forever. The
workflow refuses such a tag rather than letting it through.

That builds the package, deploys it to WordPress.org, and creates the GitHub
Release with the installable zip attached.

Before tagging, bump the version everywhere it lives and write the changelogs:

```bash
php bin/bump-version.php 1.3.0   # header, PETSYNC_VERSION, Stable tag,
                                 # package.json, and all 21 block.json files
```

The release workflow refuses to publish if the tag and the plugin header
disagree, so a tag cut from an unbumped tree fails loudly rather than shipping a
`Stable tag` that points at code which does not exist.

**Do not create the GitHub Release by hand.** The tag drives everything; a
release created first cannot have been checked. That is how v1.1.1 came to be
published with no assets.

### WordPress.org assets

`.wordpress-org/` is synced to SVN `assets/` by the deploy — a *sibling* of
`trunk/`, not inside it. Banners, icons and `screenshot-N.png` go there and are
excluded from the plugin zip by `.distignore`, so they never ship to users.
Screenshot captions pair by number with the `== Screenshots ==` list in
`readme.txt`; a gap makes captions attach to the wrong image, silently.

The deploy step is skipped until the `SVN_USERNAME` and `SVN_PASSWORD` secrets
exist, so everything else still runs before the plugin is approved.

## Architecture

The plugin follows a config-driven, layered architecture:

```
config/            → JSON definitions (entities, abilities, post types, schemas)
config/providers/  → One file per shelter platform: how it names things
includes/core/     → Reusable infrastructure (Config loader, CPT registry, Query builder, Hydrator)
includes/abilities/→ Ability callbacks registered via the WP 6.9 Abilities API
includes/import/   → CSV import: header matching, validation, dry run
includes/export/   → CSV and JSON export, sharing one column schema with the importer
includes/cli/      → WP-CLI commands
includes/schema/   → AnimalShelter JSON-LD, and the per-SEO-plugin adapters
includes/shelterkit/ → SHARED between ShelterKit plugins; copied, not imported
blocks/            → Server-rendered blocks with Interactivity API view scripts
templates/         → Block theme templates (archive-vcps_pet.html, single-vcps_pet.html)
parts/             → Template parts (pet-floating-ui; kennel-card, the printable card design)
tests/             → Unit suite (no WordPress) and integration suite (real WordPress + database)
assets/            → Editor scripts, Interactivity stores, stylesheets
bin/               → build-dist, bump-version, validate-config,
                     capture-screenshots, install-wp-tests
```

### What a pet *is*, and what a platform *calls* it

`config/entities.json` declares the canonical vocabulary — what a pet has.
`config/providers/<slug>.json` is the only place that knows how one platform
spells those things: field renames, value translation (`f` → `Female`), polarity
inversion, and the nested response paths a flat key cannot express.

Adding a platform is adding a file, not editing PHP. `bin/validate-config.php`
checks every provider map against the entity, so a typo fails the build instead
of hydrating silently to nothing.

### Shared files

`includes/shelterkit/` holds files copied **byte-identically** into each
ShelterKit plugin — the Action Scheduler pattern. Each copy registers its
version; the highest present is the one loaded. That is why those classes are
neither namespaced nor prefixed with this plugin's name: `class_exists()` is
what decides the winner, so every copy must declare the same class.

Edit them here, then copy across. `phpcs.xml.dist` allows the `ShelterKit`
prefix for that directory alone.

### Config contracts

`bin/validate-config.php` runs in CI and enforces agreements the language cannot:
that every block binding resolves to a real store member, that the
change-detection hash covers every field the sync reads, that declared
Interactivity references exist, and that the version agrees across the plugin
header, `PETSYNC_VERSION`, `readme.txt` and all 21 `block.json` files.

Business logic lives in **abilities** — thin, testable operations with JSON Schema validation and permission callbacks. Blocks, REST endpoints, and admin UI are thin consumers that delegate to abilities.

## Key Features

- **Printable kennel cards** — pick animals, choose a size, print. The card's design is a block template part, so it is rearranged in the Site Editor rather than in code, and every field on it is a block binding.
- **Manual entry** — every field a sync would supply can be typed in the editor, so the plugin works with no platform account at all.
- **Import from a spreadsheet** — **Pets → Import** takes a CSV, matches column
  headings loosely so nothing needs renaming, and previews every row before
  writing. Re-uploading a corrected sheet updates rather than duplicates.
- **Export to CSV or JSON** in the same format the importer reads, so an export
  can be edited in a spreadsheet and imported straight back.
- **Batched sync** with admin progress UI and WP-Cron scheduling.
- **21 blocks** for pet cards, grids, sliders, filters, galleries, comparison, favorites, adoption CTAs, and more.
- **Interactivity API** for reactive front-end (favorites, compare, filters, gallery, toast notifications) — no build step required.
- **Block Bindings** to connect block attributes to pet post meta.
- **Taxonomy filtering** (species, breed, age, size, gender, color) with URL-driven compatibility meta filters.
- **Shelter Details** — the shelter's own name, address, phone and email, entered
  once and shared with the other ShelterKit plugins. The kennel card binds to it,
  so the contact line is data rather than text typed into a design.
- **Optional `AnimalShelter` structured data** — refines the organisation an
  active SEO plugin already describes (SEOPress, Slim SEO, The SEO Framework)
  rather than emitting a competing one. Off by default.
- **WP-CLI**: `wp shelterkit import`, `wp shelterkit export`, and
  `wp shelterkit migrate` — the last so a database upgrade is deliberate and
  observable rather than firing on whichever page load happens to be first.

## Contributing

1. Fork the repository and create a feature branch from `main`.
2. Run `composer install && npm install` to set up linting tools.
3. Make your changes and ensure `composer lint`, `npm run lint:js` and `composer test` pass.
   The integration suite needs the WordPress test library — see `tests/README.md`.
4. Open a pull request against `main`.

**CI does not start on its own for a first pull request.** GitHub holds workflow
runs from forks until a maintainer approves them, so an empty checks list on a
new PR is normal and not something you have done wrong. Once approved, five
checks must pass before anything can merge:

```
PHP Coding Standards · Config Contract Validator · JS & CSS Lint
Build distribution zip · Plugin Check (WordPress.org review suite)
```

Those checks are required for **everyone**, maintainers included — there is no
bypass — so running the linters and tests locally first is the fastest route
through.

Review and merging are done by a maintainer; only maintainers have write access,
so a pull request cannot be merged by its author.

## License

GPL-2.0-or-later. See WordPress [license](https://wordpress.org/about/license/) for details.
