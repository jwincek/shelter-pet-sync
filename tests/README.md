# Tests

Two suites, mirroring the split in the sibling plugins.

| Suite | Command | Needs WordPress? |
|---|---|---|
| Unit | `composer test:unit` | No — stubs a handful of functions and loads one class |
| Integration | `composer test:integration` | Yes — a real WordPress and database |

`composer test` runs both.

## Running the integration suite locally

```sh
# The version must be explicit. "latest" is not a tag in the
# wordpress-develop repository the library is fetched from.
bin/install-wp-tests.sh <db-name> <db-user> <db-pass> <db-host> 7.0.2
composer test:integration
```

Use a database name that is not your working site's — the installer **drops
and recreates** it.

On Local by Flywheel, the host is a socket rather than a port:

```sh
SOCK="$HOME/Library/Application Support/Local/run/<run-id>/mysql/mysqld.sock"
bin/install-wp-tests.sh shelter_pets_tests root root "localhost:$SOCK" 7.0.2
```

The library installs under `sys_get_temp_dir()`, which on macOS is a
`/var/folders/...` path rather than `/tmp`. Set `WP_TESTS_DIR` to override.

## A trap worth knowing

`WP_UnitTestCase::set_up()` calls `reset_post_types()`, which unregisters every
non-core post type — and `unregister_post_type()` drops that type's registered
meta along with it. `init` does not fire again, so `PetTestCase::set_up()`
re-registers the post type, taxonomies and meta for every test.

Without that, the symptom is confusing rather than obvious: reads and writes
keep working, because `get_post_meta()` and `update_post_meta()` do not require
registration. Only sanitisation and REST schemas silently stop applying.

## What is covered, and why

Each of these protects a bug that was found the hard way:

- **`TristateTest`** — `compute_compatibility()` tested tristates with
  `! empty()`, and `'no'` is a non-empty string. 22 of 93 published pets were
  advertised as good with dogs, cats or children when the shelter had recorded
  the opposite.
- **`HydratorPrecedenceTest`** — meta beats snapshot beats default. This is what
  lets a pet exist with no provider at all.
- **`ProviderScopingTest`** — a sync must only ever touch pets it imported.
- **`MetaSanitizersTest`** — `absint(-5)` is `5`, so a negative attachment ID
  silently resolved to a real, unrelated image.
- **`DefaultStatusTest`** — the block editor sends an empty term array, which
  skips `default_term`, leaving pets invisible on the archive.
- **`HydrationQueryCountTest`** — a 99-pet archive once issued 202 queries
  against a documented "~5".
