# MySQL staging verification plan

This document is for a non-production MySQL staging clone only. The verification script is read-only and refuses `APP_ENV=production` or `prod`.

## Migration execution order

Laravel executes pending migration filenames in ascending timestamp/name order. Capture the exact order on the staging clone with:

```bash
php artisan migrate:status --no-interaction
find database/migrations -maxdepth 1 -type f -exec basename {} \; | sort
```

The enhancement migration is:

```text
2026_11_14_000001_add_property_amenity_values_and_carpet_area.php
```

Its immediate preceding migration is:

```text
2026_10_23_000002_add_reels_feature_to_existing_plans.php
```

Run the complete pending chain, never the enhancement migration in isolation:

```bash
php artisan config:cache
php artisan migrate --force --no-interaction
php artisan migrate:status --no-interaction
```

The target migration adds `value_type`, pivot `quantity` and `quantity_is_minimum`, carpet/land/floor units, countable feature configuration, and legacy count backfill. Do not run `migrate:fresh`, `migrate:refresh`, or `db:wipe` against the staging clone used for comparison.

## Required staging configuration

Backend:

- `APP_ENV=staging`, `APP_KEY`, `APP_URL`, `APP_DEBUG=false`
- `FRONTEND_URL`, `FRONTEND_APP_URL`
- `CORS_ALLOWED_ORIGINS`, `SANCTUM_STATEFUL_DOMAINS`
- `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `DB_CHARSET=utf8mb4`, `DB_COLLATION=utf8mb4_unicode_ci`, and SSL variables if required by the provider
- `SESSION_DRIVER`, `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE`
- `CACHE_STORE` and its Redis/database settings if not using database cache
- `QUEUE_CONNECTION` and queue settings; run a staging worker if database/Redis queues are enabled
- `FILESYSTEM_DISK`; for S3, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, and endpoint settings
- `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`
- `OPENAI_API_KEY`, `OPENAI_MODEL` for live AI API scenarios
- `GOOGLE_MAPS_API_KEY` and OAuth variables if map/social-login flows are included
- Stripe variables only if billing is included in the staging pass
- `ABN_LOOKUP_GUID`, `ABN_LOOKUP_ENDPOINT` if registration/ABN verification is included

Frontend builds:

- Website: `VITE_APP_API_URL`, `VITE_GOOGLE_MAPS_API_KEY`
- Admin: `VITE_APP_API_URL`, `VITE_GOOGLE_MAPS_API_KEY`, and any configured `VITE_APP_*` purchase/docs URLs

## Database integrity verification

From `Backend-Briksy` after staging migration:

```bash
php artisan migrate:status --no-interaction
php scripts/verify_property_amenities_staging.php > /tmp/briksy-property-audit.json
```

Compare the JSON with a pre-migration snapshot. Required results:

- Property, feature, pivot, favorite, collection, collection-item, and inquiry counts are accounted for.
- No duplicate active `(property_listing_id, feature_id)` pivots.
- No orphaned property or feature pivots.
- No negative quantities.
- No invalid area units.
- No legacy non-zero bedroom/bathroom/car-space rows missing their active canonical pivot.
- Existing boolean pivots remain present.
- Existing property IDs, generated IDs, titles, statuses, areas, legacy fields, favorites, collections, and inquiries are unchanged except for the intended additive pivot quantity/backfill fields.

## API scenarios

Use a staging bearer token and a property fixture containing 3 bedrooms, 2 bathrooms, 2 car spaces, 1,250 sqft carpet area, 200 sqm land area, and 150 sqm floor area.

```text
Find properties with 3 bedrooms
Show properties with at least 2 bathrooms
Find houses with 2 car spaces
Show properties with carpet area above 1200 sqft
Find properties with carpet area between 100 and 150 sqm
Show properties with at least 3 bedrooms and 2 bathrooms
Find properties with 2 bathrooms and carpet area above 1200 sqft
```

For each request verify that every returned property satisfies every condition, missing quantities do not match, zero quantities do not match positive requirements, and sqft/sqm boundary values behave exactly at equal, greater-than, less-than, and between limits. Confirm response cards include the same units and quantities as the property detail endpoint.

## Browser regression scenarios

Admin:

1. Add a property with boolean amenities, numeric amenities, decimal carpet area, and mixed units.
2. Save, reload Edit Property, and verify every quantity/unit persists.
3. Set a quantity to zero/null and verify it is not rendered as an amenity.
4. Edit unrelated fields and confirm no bedroom/bathroom/car-space features are added.
5. Change an amenity from checkbox to quantity in Super Admin configuration and verify Add/Edit forms update dynamically.

Seeker:

1. Open Property Details and verify icon-based amenities, zero hiding, and all area units.
2. Open an AI recommendation card and follow it to the correct property route.
3. Run each numeric AI query above and inspect returned cards.
4. Save the property, open Favorites and Collections, and compare amenities/areas.
5. Submit an enquiry and compare Seeker and Admin enquiry property summaries.
6. Repeat detail, cards, favorites, and AI scenarios at mobile viewport widths.

## Remaining backend failures

The remaining full-suite failures are separate from this enhancement:

- `PropertyWorkflowVisibilityTest` (2 failures): current workflow intentionally treats `Approved` listings as seeker-visible and normal published-property edits preserve publication in the current controller behavior. These tests assert the older pending-review visibility contract. They block the full-suite gate but are not caused by amenity/area changes; product owner decision is required before changing workflow behavior.
- `ServicePlanLimitEnforcementTest` (1 failure): service image media persistence is zero after service creation. This is outside property amenities, area units, and AI search and should be triaged separately. It blocks a clean global suite but not this enhancement's focused suite.

## Deployment checklist

- [ ] Confirm staging hostname, MySQL version, PHP version, Composer dependencies, and frontend build artifact.
- [ ] Take and verify a restorable staging-clone backup; record row counts and checksums before migration.
- [ ] Confirm `APP_ENV=staging` and production credentials/domains are absent.
- [ ] Run `migrate:status`; review all pending migrations.
- [ ] Run `php artisan migrate --force --no-interaction` during the approved staging window.
- [ ] Run `migrate:status` again and execute the read-only audit script.
- [ ] Compare pre/post property, feature, pivot, favorite, collection, and inquiry records.
- [ ] Execute API numeric-filter and unit-boundary scenarios.
- [ ] Execute browser Admin, Seeker, AI, Favorites, Collections, Inquiry, and mobile scenarios.
- [ ] Review slow-query logs/EXPLAIN output and confirm eager loading/no N+1 regressions.
- [ ] Record failures and do not promote until critical checks pass.
- [ ] If rollback is required, stop application traffic, use the approved backup/rollback plan, and verify restored counts. Do not improvise destructive rollback commands.

Production remains explicitly out of scope for this procedure.
