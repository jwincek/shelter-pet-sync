# Shelter Pets

Sync adoptable pets from [Petstablished](https://petstablished.com) into WordPress, built on the modern WordPress 6.9 stack: Abilities API, Block Bindings, and Interactivity API.

## Requirements

- WordPress 6.9+
- PHP 8.1+
- A Petstablished public API key

## Installation

1. Download or clone this repository into `wp-content/plugins/shelter-pets/`.
2. Activate the plugin in **Plugins → Installed Plugins**.
3. Go to **Pets → Sync Settings** and enter your Petstablished public key.
4. Click **Sync Now** to import your adoptable pets.

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
parts/           → Template parts (pet-floating-ui: compare bar, favorites, notifications)
assets/          → Editor scripts, Interactivity stores, stylesheets
```

Business logic lives in **abilities** — thin, testable operations with JSON Schema validation and permission callbacks. Blocks, REST endpoints, and admin UI are thin consumers that delegate to abilities.

## Key Features

- **Batched sync** with admin progress UI and WP-Cron scheduling.
- **21 blocks** for pet cards, grids, sliders, filters, galleries, comparison, favorites, adoption CTAs, and more.
- **Interactivity API** for reactive front-end (favorites, compare, filters, gallery, toast notifications) — no build step required.
- **Block Bindings** to connect block attributes to pet post meta.
- **Taxonomy filtering** (species, breed, age, size, gender, color) with URL-driven compatibility meta filters.

## Contributing

1. Fork the repository and create a feature branch from `main`.
2. Run `composer install && npm install` to set up linting tools.
3. Make your changes and ensure `composer lint` and `npm run lint:js` pass.
4. Open a pull request against `main`. The CI workflow will run automatically.

## License

GPL-2.0-or-later. See WordPress [license](https://wordpress.org/about/license/) for details.
