# Migrating a live install: `10f1e4f` → `1.0.0`

This upgrade is **not** a normal code bump. Between commit `10f1e4f` (what the
live site runs) and `1.0.0` there are renames that touch **live data and stored
content**. Done in the wrong order, pets disappear and pet blocks render as
"unexpected content." Done in the order below, it's safe and reversible.

> Run every command on the **live server** with its own `wp-cli` (plain `wp …`,
> not the local Flywheel socket override). Replace `PLUGIN_DIR` with the actual
> plugin folder name on the server (likely `vcpahumane-petstablished-sync`).

---

## What changes, and why each needs care

| Change | Impact | Handled by |
|---|---|---|
| **CPT `pet` → `vcps_pet`** | Existing pet posts are orphaned (invisible) until their `post_type` is updated. | `2026-06-23-rename-cpt-pet-to-vcps_pet.php` |
| **Block names `petstablished/*` → `petsync/*`** (and binding sources `petstablished/pet-data`, `petstablished/adoption-stats`) | Any page/template/widget using the old block names breaks ("this block contains unexpected content"); bindings go blank. | `wp search-replace` (step 5) |
| **API snapshot now stores only the display subset** | Old `_pet_api_response` rows still hold full PII until purged. | `2026-06-23-purge-pii-from-api-snapshots.php` |
| **Change-detection hash basis changed** | The **first sync after upgrade re-processes every pet once** (expected; then steady-state resumes). | nothing — just expect it |
| **Plugin dir/slug/text-domain → `shelter-pets`** | Optional rename for consistency; needs a reactivate. | step 3 (optional) |

**Preserved — no action needed:** `/adopt/pets/…` permalinks (rewrite slug
unchanged), all `_pet_*` post meta, all `pet_*` taxonomies and term
relationships, and visitors' browser-saved favorites/comparison (the
`petstablished_*` localStorage keys are intentionally unchanged).

> Note: the version number goes **3.2.0 → 1.0.0** on purpose (first public
> release). Deploying via `git pull` ignores version ordering, so this is fine.

---

## 0. Back up first (non-negotiable)

```bash
# Database
wp db export ~/backup-pre-1.0.0-$(date +%F).sql

# Plugin files (so you can restore the exact old tree)
cd wp-content/plugins
tar czf ~/PLUGIN_DIR-pre-1.0.0.tgz PLUGIN_DIR
```

Confirm both files exist and are non-empty before continuing.

## 1. Maintenance mode on

During steps 2–7 pets are briefly invisible and pet blocks are unregistered, so
take the front end down:

```bash
wp maintenance-mode activate
```

## 2. Pull the new code

```bash
cd wp-content/plugins/PLUGIN_DIR
git fetch origin && git checkout main && git pull --ff-only origin main
```

The plugin stays active (the main file name `petstablished-sync.php` is
unchanged), but it now expects `vcps_pet` / `petsync/*` — which is exactly why
we're in maintenance mode and about to migrate.

## 3. (Recommended) Rename the plugin directory to match the slug

The text domain is now `shelter-pets`; matching the folder avoids a
text-domain/slug mismatch. **Skip this whole step if you prefer to keep the
folder name** — the plugin still works either way.

```bash
cd wp-content/plugins
wp plugin deactivate PLUGIN_DIR
mv PLUGIN_DIR shelter-pets
wp plugin activate shelter-pets
```

(If you skip the rename, instead run `wp plugin deactivate PLUGIN_DIR && wp
plugin activate PLUGIN_DIR` once, so the activation hook re-flushes rewrite
rules under the new code.)

From here, `PLUGIN_DIR` is `shelter-pets` if you renamed it.

## 4. Migrate the post type (`pet` → `vcps_pet`)

```bash
wp eval-file wp-content/plugins/shelter-pets/migration-scripts/2026-06-23-rename-cpt-pet-to-vcps_pet.php
```

Expected: `Migrated N post(s) from "pet" to "vcps_pet" …`. Idempotent — safe to
re-run (a second run reports "Nothing to migrate").

## 5. Update block names in stored content

Renames `wp:petstablished/*` block delimiters and `petstablished/pet-data`
binding sources to `petsync/*`. **Dry-run first** and review the count:

```bash
# Preview
wp search-replace 'petstablished/' 'petsync/' wp_posts \
  --include-columns=post_content --dry-run --report-changed-only

# Apply
wp search-replace 'petstablished/' 'petsync/' wp_posts \
  --include-columns=post_content --report-changed-only
```

`wp_posts` covers regular pages/posts **and** Site-Editor templates/parts and
reusable blocks (all stored there). If you use **block-based widgets** with pet
blocks, also run it for `wp_options --include-columns=option_value` (dry-run
first). The string `petstablished/` only occurs in block names/sources, so this
is targeted (it does **not** match `petstablished.com/…` or the
`petsync_settings` option).

## 6. Purge PII from stored API snapshots

```bash
wp eval-file wp-content/plugins/shelter-pets/migration-scripts/2026-06-23-purge-pii-from-api-snapshots.php
```

Expected: `N pet(s) … slimmed (… KB of PII/unused data removed)`. Idempotent.
(It also retroactively drafts any pet flagged "don't show in public search.")

## 7. Flush rewrites and verify (still in maintenance mode)

```bash
wp rewrite flush
wp post list --post_type=vcps_pet --post_status=publish --format=count   # pets present?
wp eval 'echo home_url("/adopt/pets/");'                                  # archive URL unchanged
```

Spot-check on the server with curl (bypassing maintenance for your shell):

```bash
curl -s -o /dev/null -w '%{http_code}\n' "$(wp option get siteurl)/adopt/pets/"
```

## 8. Maintenance mode off

```bash
wp maintenance-mode deactivate
```

## 9. Post-deploy checks

- Load `/adopt/pets/`, a single pet, and any page that used pet blocks.
- Click **Favorite** and **Compare** on a card — confirm the badge count and
  compare bar update live (this exercises the `petsync` Interactivity store that
  the `petstablished:: → petsync::` fix repaired).
- In **Pets → Sync Settings**, run **Sync Now** once. The first sync re-processes
  every pet (hash basis changed) and should report mostly "updated", then future
  syncs settle to "unchanged". Confirm `wp cron event list` still shows
  `petsync_scheduled_sync` if auto-sync is enabled.

---

## Rollback (if anything looks wrong)

```bash
wp maintenance-mode activate
cd wp-content/plugins
rm -rf shelter-pets   # or the renamed/checked-out tree
tar xzf ~/PLUGIN_DIR-pre-1.0.0.tgz          # restores the old plugin folder
wp db import ~/backup-pre-1.0.0-$(date +%F).sql
wp plugin activate PLUGIN_DIR                # original slug
wp rewrite flush
wp maintenance-mode deactivate
```

Because the DB dump predates the CPT/content/PII migrations, restoring it undoes
all of them in one step.

## Edge cases to check

- **Customized Site-Editor templates.** If someone edited the *Pet Archive* or
  *Single Pet* template in **Appearance → Editor**, that customization was saved
  under the old slug (`…//archive-pet`). WP now looks for `archive-vcps_pet`, so
  the customization is stranded (the plugin's bundled template takes over). The
  block-name search-replace (step 5) fixes block names *inside* any template, but
  not the template's slug — re-apply such customizations by hand if needed.
- **Deploying via the built ZIP instead of git.** `migration-scripts/` is
  excluded from the distributed zip, so run the migrations from a git checkout
  (or copy the two scripts up manually).
- **Multisite.** Run steps 4–7 per site with `--url=…`, or loop with
  `wp site list --field=url`.
