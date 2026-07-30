# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-07-30

### Added
- Initial public release.
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

[Unreleased]: https://github.com/jwincek/shelter-pet-sync/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/jwincek/shelter-pet-sync/releases/tag/v1.0.0
