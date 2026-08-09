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

const HIDE = `
  #wpadminbar, #wpfooter, .notice, .update-nag, #screen-meta, #screen-meta-links,
  #wpbody-content > .notice { display: none !important; }
  html { scroll-behavior: auto !important; }
`;

async function shot( page, selector, file, opts = {} ) {
	await page.addStyleTag( { content: HIDE } ).catch( () => {} );
	if ( opts.cap ) {
		await page.addStyleTag( {
			content: `${ opts.cap.sel } { max-height: ${ opts.cap.h }px !important; overflow: hidden !important; }`,
		} ).catch( () => {} );
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
	const ctx = await browser.newContext( { viewport: { width: 1400, height: 1000 }, deviceScaleFactor: 2 } );

	// Comparison state is a cookie, so it can be seeded rather than clicked.
	if ( COMPARE_IDS.length ) {
		await ctx.addCookies( [ {
			name: 'pet_comparison',
			value: JSON.stringify( COMPARE_IDS.map( Number ) ),
			domain: 'vchs-test.local',
			path: '/',
		} ] );
	}

	const page = await ctx.newPage();

	await page.goto( `${ SITE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
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
	await page.goto( `${ SITE }/adopt/pets/?sort=name-asc`, { waitUntil: 'networkidle' } );
	await shot( page, '.pet-listing-grid', 'screenshot-1.png', {
		settle: 1800, cap: { sel: '.pet-listing-grid', h: 1150 },
	} );

	// 2. Printed kennel cards, four to a sheet.
	if ( PRINT_IDS.length ) {
		const q = PRINT_IDS.map( ( id ) => `pets%5B%5D=${ id }` ).join( '&' );
		await page.goto(
			`${ SITE }/wp-admin/edit.php?post_type=vcps_pet&page=petsync-kennel-cards&view=print&size=index&${ q }`,
			{ waitUntil: 'networkidle' }
		);
		await shot( page, '.petsync-cards', 'screenshot-2.png', { settle: 1500 } );
	}

	// 3. The picker, mid-selection.
	await page.goto( `${ SITE }/wp-admin/edit.php?post_type=vcps_pet&page=petsync-kennel-cards`, { waitUntil: 'networkidle' } );
	const boxes = await page.$$( '.petsync-kennel__picker input[type=checkbox]' );
	for ( let i = 0; i < Math.min( 6, boxes.length ); i++ ) {
		await boxes[ i ].check();
	}
	await shot( page, '.petsync-kennel', 'screenshot-3.png', { settle: 600, cap: { sel: '.petsync-kennel', h: 1100 } } );

	// 4. A single pet page.
	await page.goto( `${ SITE }/adopt/pets/test-pet/`, { waitUntil: 'networkidle' } );
	await shot( page, 'main, .wp-site-blocks', 'screenshot-4.png', {
		settle: 1200, cap: { sel: 'main, .wp-site-blocks', h: 1500 },
	} );

	// 5. Editor sidebar panels — the manual-entry story.
	if ( EDIT_ID ) {
		await page.goto( `${ SITE }/wp-admin/post.php?post=${ EDIT_ID }&action=edit`, { waitUntil: 'domcontentloaded' } );
		await page.waitForSelector( '.edit-post-visual-editor, .editor-visual-editor', { timeout: 45000 } ).catch( () => {} );
		await page.waitForTimeout( 5000 );
		// Dismiss the welcome guide if it appears.
		const close = await page.$( '.components-modal__header button[aria-label*="lose"]' );
		if ( close ) {
			await close.click();
			await page.waitForTimeout( 800 );
		}
		// Expand the plugin's own panels.
		for ( const label of [ 'Basics', 'Health', 'Good with', 'Adoption', 'Media' ] ) {
			const btn = await page.$( `button.components-panel__body-toggle:has-text("${ label }")` );
			if ( btn ) {
				const open = await btn.getAttribute( 'aria-expanded' );
				if ( open === 'false' ) {
					await btn.click();
					await page.waitForTimeout( 300 );
				}
			}
		}
		await shot( page, '.interface-complementary-area, .editor-sidebar', 'screenshot-5.png', { settle: 1200 } );
	}

	// 6. Side-by-side comparison, seeded via the cookie above.
	if ( COMPARE_IDS.length ) {
		await page.goto( `${ SITE }/adopt/pets/?sort=name-asc`, { waitUntil: 'networkidle' } );
		await page.waitForTimeout( 1500 );
		const ok = await shot( page, '.pet-comparison, .wp-block-petsync-pet-comparison', 'screenshot-6.png', { settle: 1200 } );
		if ( ! ok ) {
			console.log( '  (comparison block not rendered on the archive)' );
		}
	}

	// 7. Sync settings.
	await page.goto( `${ SITE }/wp-admin/edit.php?post_type=vcps_pet&page=shelterkit-pets`, { waitUntil: 'networkidle' } );
	await shot( page, '.wrap', 'screenshot-7.png', { settle: 800, cap: { sel: '.wrap', h: 1400 } } );

	await browser.close();
	console.log( 'done' );
} )().catch( ( e ) => {
	console.error( 'ERROR', e.message );
	process.exit( 1 );
} );
