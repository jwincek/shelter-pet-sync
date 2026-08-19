=== ShelterKit Pets ===
Contributors: jeromewincek
Tags: pets, adoption, animal shelter, rescue, animals
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adoptable pet listings and printable kennel cards for animal shelters — add pets by hand or sync from Petstablished.

== Description ==

ShelterKit Pets puts your shelter's adoptable animals on your own website as a custom post type, then gives you a full set of blocks to display and filter them on the front end — so the listings live on your domain, get indexed by search engines, and match your theme, rather than sitting inside a third-party embed.

Add animals by hand from the WordPress editor, or connect a Petstablished account and have them imported and kept up to date automatically. Both work the same on the front end, and the two can be mixed — a sync only ever touches the pets it imported itself.

Kennel cards print straight from the same records, so the card on the kennel and the listing on your website never disagree — and nobody retypes an animal's details into a word processor.

Built on the modern WordPress stack — the Abilities API, Block Bindings, and the Interactivity API — so the front end is reactive (favorites, compare, filters, galleries) with no build step required.

Petstablished is the supported platform today; support for additional shelter-management platforms (such as Shelterluv) is planned. No platform account is required — the plugin works standalone.

**Features**

* Printable kennel cards: pick the animals, choose a size, print. Four to a sheet, two to a sheet, or one per page.
* The card's design is a block template part — rearrange it once in the Site Editor and every card follows.
* Add pets by hand in the WordPress editor — adoption fee, health, and compatibility details in grouped sidebar panels.
* Optional batched sync from Petstablished, with an admin progress UI and WP-Cron scheduling.
* Pet blocks: cards, listing grid, slider, filters, gallery, attributes, health, compatibility, comparison, favorites, adoption CTA, and more.
* Adoption call-to-action links to the pet's Petstablished application form, an internal page of your choice, or a downloadable PDF application.
* Taxonomy filtering (species, breed, age, size, gender, color) plus URL-driven compatibility filters (good with dogs/cats/kids, house-trained, etc.).
* Block Bindings connect block attributes to pet data.
* Anonymous favorites and side-by-side comparison that work without a login.
* Toast notifications confirm favorites, comparison, and sharing actions — visible on screen and announced to screen readers.

This plugin is not affiliated with, endorsed by, or sponsored by Petstablished, Adopt-a-Pet, RescueGroups.org, Shelterluv or Petfinder. Those names are trademarks of their respective owners and are used here only to describe compatibility.

Only Petstablished is contacted. The plugin ships field mappings describing how other platforms name their data, so that support for them can be added later, but nothing in this release sends a request to any of them.

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

No. You can add pets by hand from the WordPress editor — name, photo, description, species, breed, age, size, sex and color, plus adoption fee, health and compatibility details. Every block works the same either way.

If you already keep your animals in a spreadsheet, **Pets → Import** will take it in one pass rather than making you retype it. And if you do use Petstablished, connect it and your listings are imported and kept up to date automatically.

= I have a spreadsheet of animals. Can I upload it? =

Yes. Save it as CSV and go to **Pets → Import**. Column headings are matched loosely, so "Good with dogs?" finds the right field and you do not have to rename anything; only a name column is required. You get a preview showing exactly what will be added, changed or skipped — including any heading that could not be matched — and nothing is written until you confirm.

Re-uploading a corrected sheet updates the animals already on the site rather than duplicating them, matching on microchip number. Imported pets count as hand-entered, so a later sync from a connected platform will never overwrite or unpublish them.

**Pets → Export** produces a file in the same format, so an export can be edited in a spreadsheet and imported straight back.

= Can I mix pets from a platform, a spreadsheet and the editor? =

Yes. Each pet records where it came from. A sync only ever updates the pets it imported itself, so hand-entered pets are never overwritten and never removed when an animal leaves the platform. Fields on imported pets are read-only in the editor, because the platform is the source of record for them.

= Can I print kennel cards? =

Yes. Go to **Pets → Kennel Cards**, choose the animals and a size, and print. Cards are built from the same records that drive your website, so they cannot drift out of step with what visitors see.

The design is a block template part. Open **Edit card design** to rearrange it in the Site Editor — move the photo, add or remove details, put your shelter's contact information in the footer — and every card printed afterwards follows the new layout.

= Does the sync run automatically? =

You can enable automatic syncing on a schedule from the Sync Settings screen. It runs through WP-Cron. You can also sync on demand at any time with the Sync Now button.

= Will syncing delete pets that are no longer in Petstablished? =

Pets that are no longer returned by the Petstablished API are removed on the next sync so your site reflects current availability.

= Is a build step required? =

No. The blocks use the WordPress Interactivity API and ship as ready-to-run source — no compilation needed.

= What happens to my data if I delete the plugin? =

By default, deleting the plugin keeps your data — imported pets, settings, and template customizations all stay in place, so you can delete and reinstall without losing anything. Only temporary state (the sync schedule and caches) is cleaned up. To remove everything on deletion instead, enable **Delete all data when this plugin is deleted** under Pets → Sync Settings first. Imported pets can always be re-fetched with one Sync Now; pets you entered by hand exist only in WordPress, so keep the default unless you mean it.

== Screenshots ==

1. Your adoptable animals on your own site, indexed by search engines and matching your theme. Visitors filter by species, breed, age, size and temperament.
2. Kennel cards printed straight from the same records that drive the website, four to a sheet. Nothing is retyped, so the card on the kennel and the listing online cannot disagree.
3. Choose the animals, choose a size, print. Cards for a whole run of kennels in about a minute.
4. Every animal gets a full page: photo gallery, health, temperament, and a way to apply.
5. Add an animal by hand — adoption fee, health and temperament in grouped panels in the editor. No platform account is needed for any of it.
6. Already keep your animals in a spreadsheet? Upload it and see exactly what will happen before anything is written — every row checked, every column matched, nothing changed until you say so.
7. Compare animals side by side, so a family can weigh up two dogs without losing track of either.
8. Already using Petstablished? Connect it and your listings are imported and kept current on a schedule you choose.

== Changelog ==

= 1.3.1 =
* Fixed: the "Search engines" setting under Pets → Shelter Details would not save, so the structured data added in 1.3.0 could not be switched on. If you tried, turn it on again.

= 1.3.0 =
* New: **Pets → Shelter Details** — enter your shelter's address, phone and email once and they appear wherever the shelter identifies itself, including on printed kennel cards.
* New: optional structured data describing your site as an animal shelter. If you already use an SEO plugin it refines what that plugin says rather than competing with it. Off until you switch it on.
* Fixed: kennel cards printed with the placeholder text "Add your shelter's phone, email and address here" unless the card design had been edited by hand. Cards now use Shelter Details, and print nothing there until it is filled in. Reprint any cards you have already made.

= 1.2.0 =
* New: the kennel card design now previews against a real animal while you edit it, instead of showing an empty card. Choose which animal from Pets → Kennel Cards.
* Fixed: printed kennel cards broke words across lines — "Spayed/Neutere d". Reprinted cards will look correct.
* Fixed: sharing a comparison link showed "Compare Pets (0)" and cleared the recipient's own saved comparison. Their list is now left alone, and the count matches what is on screen.

= 1.1.1 =
* Fixed: "Bonded Pair" badges were missing from pet listings, sliders and the favourites modal. The badge showed on an animal's own page but nowhere else, so a bonded pair looked adoptable separately in exactly the places visitors browse. Affects sites upgraded to 1.1.0; no data was lost and no re-sync is needed.

= 1.1.0 =
* Renamed to ShelterKit Pets. Stored data is unaffected — placed blocks, pets, and their details all carry over.
* New: import pets from a spreadsheet. Pets → Import takes a CSV, matches your column headings loosely so you need not rename anything, and shows a preview before writing. Re-uploading a corrected sheet updates rather than duplicates.
* New: export pets to CSV or JSON from Pets → Export, in the same format the importer reads.
* New: `wp shelterkit migrate`, so a database upgrade can be run deliberately with `--dry-run` first.
* New: a notice when your adoption platform stops listing an animal, so a pet that quietly vanishes from the feed is visible.
* Pet photos are now capped at 1600px and generate only the sizes the plugin renders, cutting the media a shelter stores by about 39%. Existing photos are untouched.
* Fixed: a portable export left out every pet's description. If you took an export before this release, please re-export.
* Fixed: the photo lightbox could open behind the page.
* Fixed: hand-entered pets did not appear in compatibility filtering.
* Kennel-card printing now requires the Editor role.

= 1.0.0 =
* Initial public release.
* Printable kennel cards from Pets → Kennel Cards, in three sizes, with the card design editable as a block template part.
* Pets can be created entirely by hand — no Petstablished account required. Adoption, health and compatibility details are entered in grouped panels in the editor sidebar.
* Imported and hand-entered pets can coexist: each pet records its source, so a sync only ever updates or removes the pets it imported itself.
* Optional batched sync from the Petstablished public API with admin progress UI and WP-Cron scheduling.
* Pet custom post type with taxonomy and compatibility filtering.
* Block library: pet card, listing grid, slider, filters, details, gallery, actions, attributes, health, compatibility, comparison, compare bar, favorites (toggle and modal), adoption CTA, adoption action, adoption fee, breadcrumb, tagline, notifications toast, and back-to-top.
* Adoption action supports three modes: Petstablished application form, internal page link, or PDF download.
* Anonymous favorites and comparison via the Interactivity API, with toast confirmations.
* Built on the WordPress Abilities API and Block Bindings.

== Upgrade Notice ==

= 1.3.1 =
The "Search engines" setting added in 1.3.0 would not save. If you tried to switch structured data on and it did not stick, this is why — update and switch it on again.

= 1.3.0 =
Kennel cards were printing placeholder instructions unless you had edited the card design. Enter your details under Pets → Shelter Details, then reprint. Also adds optional structured data for search engines.

= 1.2.0 =
Fixes two visitor-facing bugs. Sharing a comparison link used to clear the recipient's own saved comparison list, and printed kennel cards broke words across lines. Both are fixed; reprint any cards made with 1.1.x.

= 1.1.1 =
Fixes missing "Bonded Pair" badges in pet listings. If you have bonded pairs and upgraded to 1.1.0, update — the badge is currently missing everywhere except each animal's own page.

= 1.1.0 =
The plugin is renamed to ShelterKit Pets. Your pets, photos and placed blocks carry over untouched. If you have taken a CSV or JSON export before now, re-export it — descriptions were missing from the portable format.

= 1.0.0 =
Initial public release.
