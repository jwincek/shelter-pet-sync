# ShelterKit Pets

Adoptable pet listings and printable kennel cards for animal shelters. Built on the modern WordPress stack: Abilities API, Block Bindings, and Interactivity API.

Pets can be entered by hand or synced from [Petstablished](https://petstablished.com) — no platform account is required.

## Requirements

- WordPress 6.9+
- PHP 8.1+

A Petstablished account is optional. Without one, pets are entered in the editor and everything else works the same.

## Installation

1. Download or clone this repository into `wp-content/plugins/shelterkit-pets/`.
2. Activate the plugin in **Plugins → Installed Plugins**.
3. Go to **Pets → Add New** and enter an animal.
4. Add the pet blocks to your pages and templates from the block inserter.

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

## Architecture

The plugin follows a config-driven, layered architecture:

```
config/          → JSON definitions (entities, abilities, post types, schemas)
includes/core/   → Reusable infrastructure (Config loader, CPT registry, Query builder, Hydrator)
includes/abilities/ → Ability callbacks registered via the WP 6.9 Abilities API
blocks/          → Server-rendered blocks with Interactivity API view scripts
templates/       → Block theme templates (archive-vcps_pet.html, single-vcps_pet.html)
parts/           → Template parts (pet-floating-ui; kennel-card, the printable card design)
tests/           → Unit suite (no WordPress) and integration suite (real WordPress + database)
assets/          → Editor scripts, Interactivity stores, stylesheets
```

Business logic lives in **abilities** — thin, testable operations with JSON Schema validation and permission callbacks. Blocks, REST endpoints, and admin UI are thin consumers that delegate to abilities.

## Key Features

- **Printable kennel cards** — pick animals, choose a size, print. The card's design is a block template part, so it is rearranged in the Site Editor rather than in code, and every field on it is a block binding.
- **Manual entry** — every field a sync would supply can be typed in the editor, so the plugin works with no platform account at all.
- **Batched sync** with admin progress UI and WP-Cron scheduling.
- **21 blocks** for pet cards, grids, sliders, filters, galleries, comparison, favorites, adoption CTAs, and more.
- **Interactivity API** for reactive front-end (favorites, compare, filters, gallery, toast notifications) — no build step required.
- **Block Bindings** to connect block attributes to pet post meta.
- **Taxonomy filtering** (species, breed, age, size, gender, color) with URL-driven compatibility meta filters.

## Contributing

1. Fork the repository and create a feature branch from `main`.
2. Run `composer install && npm install` to set up linting tools.
3. Make your changes and ensure `composer lint`, `npm run lint:js` and `composer test` pass.
   The integration suite needs the WordPress test library — see `tests/README.md`.
4. Open a pull request against `main`. The CI workflow will run automatically.

## License

GPL-2.0-or-later. See WordPress [license](https://wordpress.org/about/license/) for details.
