# Bubba Hub

Fresh modular WordPress plugin foundation.

## Version
2.0.0

## Current module
**Find Activities** — searchable activity REST endpoint and lightweight frontend.

## Design rules
- WordPress-native PHP.
- No React build required for the foundation.
- No dependency on the previous Bubba Hub plugin code.
- Modules boot once.
- Public read endpoints are deliberately small and isolated.
- Future modules should be added under `includes/` without modifying unrelated modules.

## Shortcode
`[bubba_hub]`

## REST
- `GET /wp-json/bubba-hub/v1/health`
- `GET /wp-json/bubba-hub/v1/activities`

## Planned modules
Find Activities, My Hub, Family Planner, Bookings, Group/Class Pages, Leader Space, Payments, Memberships, Notifications, Family Support, Integrations/Hardening.
