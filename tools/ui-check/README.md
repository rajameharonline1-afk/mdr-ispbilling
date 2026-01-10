# UI Snapshot Check (Playwright)

Headless Playwright script to verify sidebar, topbar, and content responsiveness for key pages (dashboard + `olt_mac_table.php`) in both desktop and mobile viewports.

## Setup
```bash
cd tools/ui-check
npm install
```

## Run (local PHP server)
Start the app locally (example):
```bash
cd /var/www/isp_billing
php -S 127.0.0.1:8000 -t .
```

Then run the checks:
```bash
cd tools/ui-check
npm run check          # dashboard + OLT MAC table
npm run check:dashboard
npm run check:olt
```

## Config (env)
- `UI_BASE_URL` (default `http://127.0.0.1:8000`)
- `UI_USER` / `UI_PASS` (default `admin` / `admin`)

## Output
Screenshots land in `tools/ui-check/artifacts/`:
- `<page>-desktop.png`
- `<page>-mobile.png`
- `<page>-mobile-sidebar.png`

## Notes
- Uses Playwright `chromium` only.
- Mobile viewport emulates iPhone-ish size (390x844, deviceScaleFactor 3).
