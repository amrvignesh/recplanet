# RecPlanet Core (companion plugin)

Owns the data. The theme draws it.

## What it registers

| Thing | Name | Notes |
|---|---|---|
| Post type | `rp_park` | editorial-only; URLs `/{state}/{city}/{slug}` and `/world/{country}/{slug}` |
| Post type | `rp_photo` | members upload; pinned to a park and optionally a contest |
| Post type | `rp_contest` | dates, prizes, winners |
| Taxonomy | `rp_activity` | the 45 fixed values, seeded on activation |
| Taxonomy | `rp_facility` | ~80 types parsed from the old Park Tags |
| Taxonomy | `rp_steward` | level (city, county, state, federal, tribal, other) > name. The park edit screen's Managed by box finds one by typing or adds a new name under its level; a steward saved without a level (the Stewards screen) is adopted under the level its name implies. `wp recplanet stewards` repairs any left over. `/managed-by/city-of-austin/` redirects to the real slug `city-city-of-austin`. |
| Taxonomy | `rp_place` | country > state > county > city; drives the city and state pages |
| Role | `rp_member` | upload photos, vote, enter contests; cannot touch parks |

Custom tables (prefix `wp_rp_`): `park_index` (one row per published park, spatial), `acre_rollup`, `acre_ledger`, `votes`, `freshness`, `redirects`.

## The counter

`RP\Counter::payload()` returns the world, US, Antarctica and rest-of-world totals, the last twenty ledger rows and the headline choice. It is always a sum over `park_index`, recomputed for the affected scopes on every park save and re-summed nightly (`rp_nightly_rebuild`). Drift is recorded and shown on the settings page.

REST: `GET /wp-json/recplanet/v1/counter` (cached five minutes, CORS for the embed widget), `/places?bbox=w,s,e,n&activity=&activity_mode=&steward=&min_acres=&year=&no_photo=` (activity: one name or a comma list, places with any of them, `activity_mode=all` for every one; steward: level slugs and/or steward term ids, comma-separated), `/near?lat=&lng=&km=`, `/stewards?q=&bbox=` (type-ahead for the atlas), `/admin/center`, `/admin/postal`, `/admin/geocode` (the park edit screen's map and postal-code helpers, editors only), `POST/DELETE /photos/{id}/vote`, `POST /parks/{id}/freshness`.

## Settings

Settings > RecPlanet: Google Maps Platform key and Map ID, counter headline, contest defaults, embed origins. `RP\Settings::maps_key()` for the theme.

## Importing from Drupal 6

1. Load the exported Drupal tables into the WordPress database with the prefix `d6_` (phpMyAdmin: import the SQL, then rename, or edit the dump's table names first).
2. Copy `sites/default/files` somewhere the server can read.
3. Over SSH:

```
wp recplanet import parks --files=/path/to/files            # first run, all parks
wp recplanet import parks --since=2026-09-01                 # later runs, only what changed
wp recplanet import photos --files=/path/to/files
wp recplanet import blogs
wp recplanet import redirects                                # the 50,309 category/... aliases
wp recplanet backfill                                        # propose activities where there are none
wp recplanet approve --activity=Hunting                      # or --all
wp recplanet rebuild                                         # index and roll-ups from scratch
wp recplanet report                                          # data-quality counts
```

Everything is keyed on the Drupal `nid` (post meta `rp_legacy_nid`), so every command can be run again without duplicating anything. `--dry-run` on `import parks` counts without writing.
