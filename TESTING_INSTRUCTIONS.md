# Testing Instructions: Venue Admin List (#1835)

Branch: `enhancement/1835-venue-admin-list`

## What changed

- Removed the author column from venue admin lists.
- Added physical venue details with address, phone, and website.
- Added featured image and stored static map columns.
- Featured image and static map columns are hidden by default and can be enabled in Screen Options.

## Manual test pass

1. Start WordPress environment:
   ```
   npm run wp-env
   ```
2. Open **Venues** in the WordPress admin.
3. Confirm author column is absent.
4. Confirm Physical details column shows address, phone, and website.
5. Open Screen Options. Enable Featured image and Static map.
6. Confirm featured image appears for venues with a featured image.
7. Confirm static map appears only for venues with a stored map descriptor.
8. Confirm empty values show a dash and links remain safe and clickable.
