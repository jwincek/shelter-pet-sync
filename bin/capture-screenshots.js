/**
 * Capture WordPress.org screenshots for ShelterKit Pets.
 *
 * Follows the conventions in wp-content/plugins/CLAUDE.md:
 *   - channel:'chrome', because the cached chromium predates this Playwright
 *   - log in once; check for #wpadminbar rather than the URL, and step past
 *     the administration-email interstitial by navigating to /wp-admin/
 *   - element screenshots, not viewport clips, so tall content is not sliced
 *   - hide #wpadminbar, .notice, .update-nag before capturing
 */

// Playwright arrives as a transitive dependency of @wordpress/scripts, so it
// is available after `npm install` without adding a dependency of our own.
const PLUGIN = require( 'path' ).resolve( __dirname, '..' );
const { chromium } = require( PLUGIN + '/node_modules/playwright' );
const path = require( 'path' );

// Usage:
//   node bin/capture-screenshots.js <outdir> <printIds> <compareIds> <editId>
// Credentials come from the environment; create a throwaway admin first and
// delete it afterwards rather than using a real account.
const SITE = process.env.WP_URL || 'http://vchs-test.local';
const USER = process.env.WP_USER || 'shotbot';
const PASS = process.env.WP_PASS || '';
const OUT = process.argv[ 2 ] || '/tmp/shots';
const PRINT_IDS = ( process.argv[ 3 ] || '' ).split( ',' ).filter( Boolean );
const COMPARE_IDS = ( process.argv[ 4 ] || '' ).split( ',' ).filter( Boolean );
const EDIT_ID = process.argv[ 5 ] || '';

// The single-pet shot needs a real pet's slug, which differs per site. Derived
// from WP_URL rather than hardcoded so this runs against any install.
const FEATURED = process.env.WP_FEATURED_SLUG || 'test-pet';

const HIDE = `
  #wpadminbar, #wpfooter, .notice, .update-nag, #screen-meta, #screen-meta-links,
  #wpbody-content > .notice { display: none !important; }
  html { scroll-behavior: auto !important; }
`;

async function shot( page, selector, file, opts = {} ) {
	await page.addStyleTag( { content: HIDE } ).catch( () => {} );
	if ( opts.cap ) {
		await page
			.addStyleTag( {
				content: `${ opts.cap.sel } { max-height: ${ opts.cap.h }px !important; overflow: hidden !important; }`,
			} )
			.catch( () => {} );
	}
	await page.waitForTimeout( opts.settle || 700 );
	const el = await page.$( selector );
	if ( ! el ) {
		console.log( `  SKIP ${ file } — no match for ${ selector }` );
		return false;
	}
	await el.screenshot( { path: path.join( OUT, file ) } );
	console.log( `  ok   ${ file }` );
	return true;
}

( async () => {
	const browser = await chromium.launch( { channel: 'chrome' } );
	const ctx = await browser.newContext( {
		viewport: { width: 1400, height: 1000 },
		deviceScaleFactor: 2,
	} );

	const page = await ctx.newPage();

	await page.goto( `${ SITE }/wp-login.php`, {
		waitUntil: 'domcontentloaded',
	} );
	await page.fill( '#user_login', USER );
	await page.fill( '#user_pass', PASS );
	await page.click( '#wp-submit' );
	await page.waitForLoadState( 'domcontentloaded' );
	await page.goto( `${ SITE }/wp-admin/`, { waitUntil: 'domcontentloaded' } );
	if ( ! ( await page.$( '#wpadminbar' ) ) ) {
		console.error( 'LOGIN FAILED — no #wpadminbar' );
		await browser.close();
		process.exit( 1 );
	}
	console.log( 'logged in' );

	// 1. Archive with filters. Sorted by name so the "Test Pet" fixture is not
	//    the first thing on the listing page.
	await page.goto( `${ SITE }/adopt/pets/?sort=name-asc`, {
		waitUntil: 'networkidle',
	} );
	await shot( page, '.pet-listing-grid', 'screenshot-1.png', {
		settle: 1800,
		cap: { sel: '.pet-listing-grid', h: 1150 },
	} );

	// 2. Printed kennel cards, four to a sheet.
	if ( PRINT_IDS.length ) {
		const q = PRINT_IDS.map( ( id ) => `pets%5B%5D=${ id }` ).join( '&' );
		await page.goto(
			`${ SITE }/wp-admin/edit.php?post_type=vcps_pet&page=petsync-kennel-cards&view=print&size=index&${ q }`,
			{ waitUntil: 'networkidle' }
		);
		await shot( page, '.petsync-cards', 'screenshot-2.png', {
			settle: 1500,
		} );
	}

	// 3. The picker, mid-selection.
	await page.goto(
		`${ SITE }/wp-admin/edit.php?post_type=vcps_pet&page=petsync-kennel-cards`,
		{ waitUntil: 'networkidle' }
	);
	const boxes = await page.$$(
		'.petsync-kennel__picker input[type=checkbox]'
	);
	for ( let i = 0; i < Math.min( 6, boxes.length ); i++ ) {
		await boxes[ i ].check();
	}
	await shot( page, '.petsync-kennel', 'screenshot-3.png', {
		settle: 600,
		cap: { sel: '.petsync-kennel', h: 1100 },
	} );

	// 4. A single pet page.
	//
	// Captured in a SEPARATE, logged-out context. This shot frames the whole
	// page including the theme header, and the header carries a "Log out" link
	// for an authenticated session — which no visitor ever sees, and which is not
	// the admin bar, so HIDE does not catch it. The pet pages are public, so
	// there is nothing to log in for.
	const publicCtx = await browser.newContext( {
		viewport: { width: 1400, height: 1500 },
		deviceScaleFactor: 2,
	} );
	const publicPage = await publicCtx.newPage();
	await publicPage.goto( `${ SITE }/adopt/pets/${ FEATURED }/`, {
		waitUntil: 'networkidle',
	} );
	await shot( publicPage, 'main, .wp-site-blocks', 'screenshot-4.png', {
		settle: 1200,
		cap: { sel: 'main, .wp-site-blocks', h: 1500 },
	} );
	await publicCtx.close();

	// 5. Editor sidebar panels — the manual-entry story.
	if ( EDIT_ID ) {
		await page.goto(
			`${ SITE }/wp-admin/post.php?post=${ EDIT_ID }&action=edit`,
			{ waitUntil: 'domcontentloaded' }
		);
		await page
			.waitForSelector(
				'.edit-post-visual-editor, .editor-visual-editor',
				{ timeout: 45000 }
			)
			.catch( () => {} );
		await page.waitForTimeout( 5000 );
		// Dismiss the welcome guide if it appears.
		const close = await page.$(
			'.components-modal__header button[aria-label*="lose"]'
		);
		if ( close ) {
			await close.click();
			await page.waitForTimeout( 800 );
		}
		// Show exactly what the caption promises — "adoption fee, health and
		// temperament in grouped panels" — and nothing else.
		//
		// Expanding all five pushed Health and Good with above the fold of the
		// sidebar's OWN scroll container, so the capture came out showing Adoption
		// and an empty Media panel: the two the caption does not mention. An
		// element screenshot captures the box, not the scrolled content, so the fix
		// is to collapse what is not wanted and scroll back to the top.
		const WANTED = [ 'Health', 'Good with', 'Adoption' ];

		// An element screenshot of the sidebar captures its BOX, which is bound by
		// the viewport — so at the default height the three panels do not fit and
		// Adoption falls below the fold, which is the one the caption names first.
		// Give this shot the room, then put the viewport back for the rest.
		// Wide rather than tall. The sidebar on its own is a ~280px column, so
		// capturing it alone produced a 560x3820 strip that WordPress.org would
		// scale down to nothing. Framing the editor WITH its sidebar is both better
		// proportioned and closer to what the caption says — "in the editor".
		await page.setViewportSize( { width: 1600, height: 1150 } );
		await page.waitForTimeout( 400 );

		for ( const label of [
			'Basics',
			'Health',
			'Good with',
			'Adoption',
			'Media',
		] ) {
			const btn = await page.$(
				`button.components-panel__body-toggle:has-text("${ label }")`
			);
			if ( ! btn ) {
				continue;
			}
			const open =
				( await btn.getAttribute( 'aria-expanded' ) ) === 'true';
			if ( open !== WANTED.includes( label ) ) {
				await btn.click();
				await page.waitForTimeout( 300 );
			}
		}

		// Scrolling to the top exposes WordPress's own document panel — featured
		// image, word count, Status/Slug/Template and a red "Move to trash". None
		// of that is what the caption is about, and the trash button is the most
		// prominent thing in the frame.
		//
		// The plugin's panels carry `shelterkit-pets-field-panel` (pet-fields.js),
		// so hide whatever sits above the first one rather than guessing at core's
		// class names, which move between releases.
		await page.evaluate( () => {
			const sidebar = document.querySelector(
				'.interface-complementary-area, .editor-sidebar'
			);
			if ( ! sidebar ) {
				return;
			}

			const first = sidebar.querySelector( '.shelterkit-pets-field-panel' );
			if ( first && first.parentElement ) {
				let node = first.previousElementSibling;
				while ( node ) {
					node.style.display = 'none';
					node = node.previousElementSibling;
				}
			}

			sidebar.scrollTop = 0;
			sidebar.querySelectorAll( '*' ).forEach( ( c ) => {
				c.scrollTop = 0;
			} );
		} );
		await page.waitForTimeout( 400 );

		await shot(
			page,
			'.interface-interface-skeleton, .block-editor__container',
			'screenshot-5.png',
			{ settle: 1200 }
		);

		await page.setViewportSize( { width: 1400, height: 1000 } );
	}

	// 6. The import dry run — the audience-defining feature, and the screen that
	// makes it trustworthy: every row checked and nothing written yet.
	//
	// Needs a real CSV posted through the form, so this drives the actual upload
	// rather than faking the report. WP_IMPORT_CSV points at a file to use; with
	// none, the shot is skipped rather than showing an empty screen.
	//
	// Use a sheet that tells the story: several rows to add, one matching an
	// existing microchip so "update" appears, one deliberately bad row so the
	// per-row error column is not empty, and a column heading the mapper cannot
	// place so the "ignored" notice shows. A sheet where everything is perfect
	// photographs as a table of identical green rows and says nothing.
	if ( process.env.WP_IMPORT_CSV ) {
		await page.goto( `${ SITE }/wp-admin/edit.php?post_type=vcps_pet&page=shelterkit-import`, {
			waitUntil: 'networkidle',
		} );
		await page.setInputFiles( 'input[name="petsync_csv"]', process.env.WP_IMPORT_CSV );
		await Promise.all( [
			page.waitForNavigation( { waitUntil: 'networkidle' } ),
			page.click( 'button[name="petsync_import_submit"]' ),
		] );
		await shot( page, '.wrap', 'screenshot-6.png', {
			settle: 800,
			cap: { sel: '.wrap', h: 1200 },
		} );
	} else {
		console.log( '\tSKIP screenshot-6.png — set WP_IMPORT_CSV to a sample sheet' );
	}

	// 7. Side-by-side comparison.
	//
	// blocks/pet-comparison/render.php returns early unless ?compare= is present,
	// and Petsync_Helpers::get_comparison() reads that parameter AHEAD of user
	// meta and the cookie. Seeding a cookie, as this used to, could never work:
	// the early return never looked at it, so the block rendered nothing and the
	// shot was silently skipped.
	if ( COMPARE_IDS.length ) {
		await page.goto(
			`${ SITE }/adopt/pets/?compare=${ COMPARE_IDS.join( ',' ) }`,
			{ waitUntil: 'networkidle' }
		);
		await page.waitForTimeout( 1500 );
		const ok = await shot(
			page,
			'.pet-comparison, .wp-block-petsync-pet-comparison',
			'screenshot-7.png',
			{ settle: 1200 }
		);
		if ( ! ok ) {
			console.log( '\t(comparison block not in the archive template)' );
		}
	}

	// 8. Sync settings.
	await page.goto(
		`${ SITE }/wp-admin/edit.php?post_type=vcps_pet&page=shelterkit-pets`,
		{ waitUntil: 'networkidle' }
	);
	await shot( page, '.wrap', 'screenshot-8.png', {
		settle: 800,
		cap: { sel: '.wrap', h: 1400 },
	} );

	await browser.close();
	console.log( 'done' );
} )().catch( ( e ) => {
	console.error( 'ERROR', e.message );
	process.exit( 1 );
} );
