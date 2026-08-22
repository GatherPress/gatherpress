# Testing Instructions: Venue Admin List (#1835)

Branch: `enhancement/1835-venue-admin-list`

## What changed

- Removed the author column from venue admin lists.
- Added physical venue details with address, phone, and website.

## Manual test pass

1. Start WordPress environment:
   ```
   npm run wp-env
   ```
2. Open **Venues** in the WordPress admin.
3. Confirm author column is absent.
4. Confirm Physical details column shows address, phone, and website.
5. Confirm empty values show a dash and links remain safe and clickable.
