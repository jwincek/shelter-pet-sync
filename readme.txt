=== Shelter Pets ===
Contributors: jeromewincek
Tags: pets, adoption, animal shelter, rescue, animals
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adoptable pet listings for animal shelters — add pets by hand or sync from Petstablished, with blocks for cards, grids, filters and comparison.

== Description ==

Shelter Pets puts your shelter's adoptable animals on your own website as a custom post type, then gives you a full set of blocks to display and filter them on the front end — so the listings live on your domain, get indexed by search engines, and match your theme, rather than sitting inside a third-party embed.

Add animals by hand from the WordPress editor, or connect a Petstablished account and have them imported and kept up to date automatically. Both work the same on the front end, and the two can be mixed — a sync only ever touches the pets it imported itself.

Built on the modern WordPress stack — the Abilities API, Block Bindings, and the Interactivity API — so the front end is reactive (favorites, compare, filters, galleries) with no build step required.

Petstablished is the supported platform today; support for additional shelter-management platforms (such as Shelterluv) is planned. No platform account is required — the plugin works standalone.

**Features**

* Add pets by hand in the WordPress editor — adoption fee, health, and compatibility details in grouped sidebar panels.
* Optional batched sync from Petstablished, with an admin progress UI and WP-Cron scheduling.
* Pet blocks: cards, listing grid, slider, filters, gallery, attributes, health, compatibility, comparison, favorites, adoption CTA, and more.
* Adoption call-to-action links to the pet's Petstablished application form, an internal page of your choice, or a downloadable PDF application.
* Taxonomy filtering (species, breed, age, size, gender, color) plus URL-driven compatibility filters (good with dogs/cats/kids, house-trained, etc.).
* Block Bindings connect block attributes to pet data.
* Anonymous favorites and side-by-side comparison that work without a login.
* Toast notifications confirm favorites, comparison, and sharing actions — visible on screen and announced to screen readers.

This plugin is not affiliated with, endorsed by, or sponsored by Petstablished. "Petstablished" is a trademark of its respective owner and is used here only to describe compatibility.

== External services ==

This plugin can connect to the Petstablished public API to import your shelter's adoptable pet listings. The connection is **optional** — it is used only if you enter a Petstablished API key. Without one, no external requests are made at all and pets are entered by hand.

* **What it does:** retrieves your organization's pet records (name, photos, breed, age, description, and adoption status).
* **What is sent and when:** your Petstablished public API key and pagination parameters are sent to `https://petstablished.com/api/v2/public/pets`. Requests are made only when you click **Sync Now** in the admin, or on the schedule you configure for the automatic sync (WP-Cron). No visitor data and no personal data from your site are ever sent.
* **Where it goes:** Petstablished (petstablished.com).

Review Petstablished's terms and privacy policy before use:

* Terms of Service: https://petstablished.com/tos
* Privacy Policy: https://petstablished.com/privacy

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install it through the Plugins screen in WordPress.
2. Activate the plugin through the **Plugins** screen.
3. Go to **Pets → Add New** and enter your first animal — name, photo, description, and the species/breed/age/size details in the sidebar panels.
4. Add the pet blocks to your pages and templates from the block inserter.

Using Petstablished? Instead of step 3, go to **Pets → Sync Settings**, enter your public API key, and click **Sync Now** to import your listings. Optionally enable scheduled syncing to keep them current.

== Frequently Asked Questions ==

= Do I need a Petstablished account? =

No. You can add pets by hand from the WordPress editor — name, photo, description, species, breed, age, size, sex and colour, plus adoption fee, health and compatibility details. Every block works the same either way.

If you do use Petstablished, connect it and your listings are imported and kept up to date automatically.

= Can I mix imported pets and hand-entered ones? =

Yes. Each pet records where it came from. A sync only ever updates the pets it imported itself, so hand-entered pets are never overwritten and never removed when an animal leaves the platform. Fields on imported pets are read-only in the editor, because the platform is the source of record for them.

= Does the sync run automatically? =

You can enable automatic syncing on a schedule from the Sync Settings screen. It runs through WP-Cron. You can also sync on demand at any time with the Sync Now button.

= Will syncing delete pets that are no longer in Petstablished? =

Pets that are no longer returned by the Petstablished API are removed on the next sync so your site reflects current availability.

= Is a build step required? =

No. The blocks use the WordPress Interactivity API and ship as ready-to-run source — no compilation needed.

= What happens to my data if I delete the plugin? =

By default, deleting the plugin keeps your data — imported pets, settings, and template customizations all stay in place, so you can delete and reinstall without losing anything. Only temporary state (the sync schedule and caches) is cleaned up. To remove everything on deletion instead, enable **Delete all data when this plugin is deleted** under Pets → Sync Settings first. Imported pets can always be re-fetched with one Sync Now; pets you entered by hand exist only in WordPress, so keep the default unless you mean it.

== Changelog ==

= 1.0.0 =
* Initial public release.
* Pets can be created entirely by hand — no Petstablished account required. Adoption, health and compatibility details are entered in grouped panels in the editor sidebar.
* Imported and hand-entered pets can coexist: each pet records its source, so a sync only ever updates or removes the pets it imported itself.
* Optional batched sync from the Petstablished public API with admin progress UI and WP-Cron scheduling.
* Pet custom post type with taxonomy and compatibility filtering.
* Block library: pet card, listing grid, slider, filters, details, gallery, actions, attributes, health, compatibility, comparison, compare bar, favorites (toggle and modal), adoption CTA, adoption action, adoption fee, breadcrumb, tagline, notifications toast, and back-to-top.
* Adoption action supports three modes: Petstablished application form, internal page link, or PDF download.
* Anonymous favorites and comparison via the Interactivity API, with toast confirmations.
* Built on the WordPress Abilities API and Block Bindings.

== Upgrade Notice ==

= 1.0.0 =
Initial public release.
