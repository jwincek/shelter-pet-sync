# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-07-30

### Added
- Initial public release.
- Manual pet entry: every field a sync would supply can be entered by hand from
  grouped panels in the editor sidebar, so the plugin works with no Petstablished
  account at all. Field list, grouping and control types come from
  `entities.json`, so adding a field is a config change.
- Pets record which platform they came from. Fields on imported pets are
  read-only in the editor, since the platform is their source of record, and a
  sync never touches pets it did not import.

### Fixed
- Pet listings issue 3 database queries for a 99-pet archive instead of 202.
  Batch priming covered the pets' own meta and terms but not the separate
  posts hydration reaches for — featured images, gallery attachments and
  bonded partners — so each of those cost a query per pet.
- Hand-created pets are given the `Available` status when saved with none, so
  they appear on the pet archive. The listing grid filters on that status, so a
  pet without one rendered correctly on its own page while being invisible on
  the archive — missing rather than visibly broken.
- Pets record which platform they were imported from (`_pet_provider`), and the
  sync matches records on provider + ID rather than ID alone. Stale-pet pruning
  is scoped the same way, so a sync only ever drafts pets it imported itself —
  never hand-authored pets, and never another platform's.
- Versioned schema rail (`petsync_db_version`) so changes to stored data run
  once, in order, on upgrade.
- Batched sync from the Petstablished public API with an admin progress UI and WP-Cron scheduling.
- `vcps_pet` custom post type with taxonomy filtering (species, breed, age, size, gender, color) and URL-driven compatibility filters.
- Block library: pet card, listing grid, slider, filters, details, gallery, actions, attributes, health, compatibility, comparison, compare bar, favorites (toggle and modal), adoption CTA, adoption action, adoption fee, breadcrumb, tagline, notifications toast, and back-to-top.
- Adoption action block supports three modes: Petstablished application form link, internal page link, or PDF download.
- Plugin-wide notification region (`petsync/pet-toast`) surfacing favorites/comparison/sharing confirmations and sync errors — visible toast plus screen-reader announcement from a single aria-live region.
- Hover/focus tooltips on the icon-only pet-actions overlay buttons, driven by their state-aware aria-labels.
- Anonymous favorites and side-by-side comparison powered by the Interactivity API.
- Block templates and template parts editable in the Site Editor, including user customizations (served from the plugin's `wp_theme` namespace).
- Built on the WordPress Abilities API and Block Bindings; server-rendered blocks with no build step.
- Uninstall handler with an opt-in "Delete all data when this plugin is deleted" setting (default off, so delete + reinstall loses nothing). Ephemeral state (cron event, transients) is always cleaned up; pets, `pet_*` taxonomy terms, Site Editor template customizations, options, and per-user favorites/comparison meta are removed only when opted in (multisite-aware).

[Unreleased]: https://github.com/jwincek/shelter-pets/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/jwincek/shelter-pets/releases/tag/v1.0.0
