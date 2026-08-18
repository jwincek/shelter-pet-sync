# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.0] - 2026-08-17

### Added
- **Shelter Details.** The shelter's own name, address, phone, email and website
  are entered once under **Pets → Shelter Details** and used wherever the
  shelter identifies itself. Shared with the other ShelterKit plugins, so
  entering it here fills it in for all of them, and removing one plugin does not
  take it away from the others.
- **Optional structured data.** Switched on, the site describes itself to search
  engines as an animal shelter, with the address entered above. Where an SEO
  plugin already describes your organisation — SEOPress, Slim SEO or The SEO
  Framework — it refines what that plugin emits rather than adding a second,
  competing description. Off by default, and it says which SEO plugin it found.

### Fixed
- **Printed kennel cards no longer carry the placeholder instruction.** The card
  shipped with "Add your shelter's phone, email and address here" as part of its
  design, so it printed on every card until the design was edited by hand. The
  contact line now comes from Shelter Details, prints nothing at all until those
  are filled in, and the prompt to fill them in appears on the Kennel Cards
  screen instead — where the person who can act on it will see it.

  **If you have printed cards, reprint them.** Anyone who has not edited the
  card design has been handing out cards with that instruction on them.

### Internal
- The shelter profile is a shared class carried byte-identically by each
  ShelterKit plugin, with the newest copy winning — so a shelter running two of
  them gets the newer screen without either depending on the other.
- Contributing documents that CI waits for approval on a first pull request from
  a fork, which is otherwise indistinguishable from being ignored.

## [1.2.0] - 2026-08-13

### Added
- **The kennel card design previews against a real pet.** Editing it in the Site
  Editor used to show an empty card — bound fields carry no fallback content and
  the pet blocks render their nothing-to-show branch — so a newly inserted block
  looked identical whether it worked or not. A pet now stands in, chosen from a
  picker on the Kennel Cards screen and defaulting to the first animal you would
  print.

  Two halves, because bindings and dynamic blocks resolve differently: a shim
  for server-rendered previews, and the plugin's first JS block-bindings source
  for the name, photo and URL.

### Fixed
- **Printed kennel cards broke labels mid-word.** Cards read
  "Spayed/Neutere / d" and "YE / S". Several themes set
  `word-break: break-word`, which collapses a label's minimum width to a single
  character; the card blocks now assert their own wrapping. **Cards printed on
  1.1.x will look different when reprinted — this is the fix, not a change of
  design.**
- **A shared comparison link wiped the visitor's own comparison.** Opening the
  link the Share button produces showed "Compare Pets (0)" above a full table,
  and silently cleared whatever the visitor had saved. The client re-fetched the
  list over a request that cannot carry the `?compare=` parameter, got an empty
  answer, overwrote the correct state and persisted the emptiness. It now
  refuses to overwrite a list the URL supplied, and never lets an empty answer
  clear a list that already exists. Clearing on purpose still works.

### Internal
- Releases publish from a pushed tag: one command builds the package, deploys it
  to WordPress.org and attaches the installable zip to the GitHub release. The
  tag must match the plugin header or nothing is published.
- The screenshot capture script produces images that match their captions;
  two shots were previously wrong in ways only visible by looking at them.

## [1.1.1] - 2026-08-11

### Fixed
- **Bonded-pair badges vanished from listing grids, sliders and favourites
  modals.** A bonded pet showed its badge on its own page and nowhere else, so
  the two animals looked adoptable separately in exactly the places a visitor
  browses.

  `summary` and `grid` request the computed `is_bonded_pair` and
  `bonded_pair_names` but not the api_fields they are derived from, and
  hydration narrowed api_fields to the shape *before* computing. Those fields
  therefore derived from a partial entity and always resolved to false.
  Introduced in 1.1.0 by the provider-decoupling work, which moved the
  computation from the always-present API snapshot onto the shape-filtered
  entity.

  Fixed structurally rather than per-field: api_fields hydrate in full and the
  output is narrowed at the end, so a computed field always sees a complete
  entity. Measured at 0.7ms per pet and zero extra queries for a 60-pet grid.

## [1.1.0] - 2026-08-11

Renamed to **ShelterKit Pets**. The slug, text domain and main plugin file all
changed with it; the plugin's internal naming (`Petsync\`, `PETSYNC_`) and every
stored identifier — post type, taxonomies, meta keys, option names, cron hooks
and all 21 block names — deliberately did not, so no placed block or stored
record is affected.

### Added
- **Import pets from CSV.** Pets → Import, and `wp shelterkit import`. Column
  headings are matched loosely, so "Good with dogs?" finds the right field and a
  shelter's existing spreadsheet needs no renaming. A preview shows exactly what
  will be added, changed or skipped — including any heading that could not be
  placed — and nothing is written until it is confirmed. Re-uploading a corrected
  sheet updates rather than duplicates, matching on microchip number. Imported
  pets count as hand-entered, so a later sync never overwrites or unpublishes
  them.
- **Export pets to CSV and JSON.** Pets → Export, and `wp shelterkit export`.
  Two modes: `portable` re-imports, `full` includes computed fields for reading.
  Both use the same column schema as the importer, so an export can be edited in
  a spreadsheet and imported straight back.
- **`wp shelterkit migrate`**, so a database upgrade is deliberate and
  observable rather than firing on whichever page load happens to be first.
  `--dry-run` names every pending migration without writing. The rail no longer
  runs automatically under WP-CLI; web traffic still triggers it as before.
- **Provider vocabulary moved into `config/providers/<slug>.json`.** Adding a
  platform is adding a file, not editing PHP. Maps can rename a field, translate
  a value (Adopt-a-Pet sends sex as `f`/`m`), invert a polarity, and declare
  nested response paths.
- Fixture-backed maps for **Adopt-a-Pet** and **RescueGroups.org**, neither
  verified against a live account and both marked as such in the file. They exist
  to prove the provider layer is general, not to be shipped to a shelter.
- **A notice when the provider goes dark.** Pets carry `_pet_last_seen`, so a
  feed that stops returning an animal is visible rather than silent.
- **Read abilities offered to MCP clients** via `meta.mcp.public`, after the read
  surface was covered by tests.
- Pet gallery images can be managed from the dashboard.
- A test suite: 248 integration and unit tests at this release.

### Changed
- **Image sizes are budgeted.** A sideloaded photo is capped at 1600px on its
  longest edge and generates only the three sizes the plugin actually renders,
  cutting the pet media footprint by 39%. Filterable via
  `petsync_max_image_edge` and `petsync_rendered_image_sizes`. Existing
  attachments are untouched.
- **CSV is written and read as RFC 4180** — quotes doubled, no backslash escape —
  which is what Excel and Google Sheets both produce and consume.
- Kennel-card printing now requires `edit_others_posts` rather than being
  available to any author.
- The canonical field vocabulary is frozen, with two renames carried by a
  migration and one block-binding alias kept for compatibility.

### Fixed
- **A portable export omitted every pet's description.** `description` is
  computed from the post content, so it never arrived through the editable-field
  list — a backup carried all of a pet's data and none of its story, and
  restoring one produced empty descriptions. Found by the import round-trip.
  **Anyone holding an export taken before this release should re-export.**
- **The gallery lightbox opened behind the page.** `position: sticky` always
  creates a stacking context, even at `z-index: auto`, so a sticky ancestor
  buried the overlay. Now a native `<dialog>`, whose top layer escapes every
  stacking context without moving the element.
- **Hand-entered pets received no compatibility attribute terms**, so they were
  invisible to filtering while looking correct on their own page. Terms are now
  derived from the canonical field rather than from raw provider data.
- **Site Editor customisations survived the renames.** Migrations 4 and 5
  consolidate template customisations filed under any of the plugin's four
  previous `wp_theme` namespaces.
- A negative ID passed to the batch-get ability resolved to a real, unrelated
  pet, because `absint()` takes the absolute value. Three separate occurrences
  fixed to `intval()` with a positive filter.
- The hero crossfade read state where it should have read context.
- Kennel-card block styles are loaded on the admin screen, so the print sheet
  is styled.

### Security
- Visitor favourite and comparison cookies are guarded, and the REST security
  model is documented at `Petsync_REST::check_permission`.
- Kennel-card ID parsing and plugin deactivation paths are hardened.

## [1.0.0] - 2026-07-30

### Added
- Initial public release.
- Printable kennel cards. **Pets → Kennel Cards** filters to the animals
  currently available, and prints the selected ones four to a sheet, two to a
  sheet, or one per page. The sheet renders on the admin screen and print CSS
  hides everything WordPress wraps around it.
- The card's design is a `kennel-card` block template part rather than markup
  in PHP, so it is edited in the Site Editor and every field on it is a block
  binding. **Edit card design** deep-links straight to that one part. Its
  layout follows the card Petfinder used to print, which is the format the
  shelters that relied on it already know.
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

[Unreleased]: https://github.com/jwincek/shelterkit-pets/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/jwincek/shelterkit-pets/releases/tag/v1.0.0
