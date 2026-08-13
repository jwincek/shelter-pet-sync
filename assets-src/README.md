# Asset sources

Editable SVG sources for the WordPress.org listing artwork. Neither this
directory nor `.wordpress-org/` ships in the plugin zip — both are excluded by
`.distignore`.

- `icon.svg` — plugin icon. Also a deliverable: WordPress.org accepts an SVG
  icon, but [requires PNG fallbacks](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/)
  for older browsers and Facebook, so both are generated.
- `banner.svg` — plugin page banner. Source only; WordPress.org banners must be
  PNG or JPG.

## Regenerating

Requires `rsvg-convert` (`brew install librsvg`) and ImageMagick.

```sh
cd assets-src
rsvg-convert -w  772 -h 250 banner.svg -o ../.wordpress-org/banner-772x250.png
rsvg-convert -w 1544 -h 500 banner.svg -o ../.wordpress-org/banner-1544x500.png
rsvg-convert -w  128 -h 128 icon.svg   -o ../.wordpress-org/icon-128x128.png
rsvg-convert -w  256 -h 256 icon.svg   -o ../.wordpress-org/icon-256x256.png
cp icon.svg ../.wordpress-org/icon.svg
for f in ../.wordpress-org/*.png; do magick "$f" -strip "$f"; done
```

## Design notes

The sync glyph is two opposing arrows rather than a circular arrow. A circular
arrow needs a near-closed arc to read as one, and at the 40px size
WordPress.org uses in search rows that arc collapses into a plain ring. The two
straight arrows stay legible all the way down.

The badge sits clear of the paw pad rather than overlapping it — at 128px an
overlap reads as a collision instead of a deliberate badge.

The title is `ShelterKit Pets`, three characters longer than the name the banner
was originally drawn for. It still fits, but with less headroom than before — if
the name ever grows again, check the render rather than assuming, because the
title has no wrapping and will simply run off the canvas.

Text in `banner.svg` is live text, not outlines, so it depends on the rendering
machine having Helvetica or Arial. If you regenerate on a machine without
either, check the output before committing: a fallback font will change the
line widths, and the tagline is sized to just fit within 772px.

## Where these go

Into the top-level `assets/` directory of the WordPress.org **SVN** repo — a
sibling of `trunk/` and `tags/`, not inside either. See issue #8.
