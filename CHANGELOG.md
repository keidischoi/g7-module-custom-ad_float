# Changelog

## 0.1.9
- Rename module from `local-popup_ad` to `custom-ad_float` (vendor `custom`, namespace `Modules\\Custom\\AdFloat`).
- Tables renamed to `custom_ad_float_settings` / `custom_ad_float_items`.
- Admin moves under G7 admin layout (`_admin_base`) at `/admin/ad-float` (no standalone HTML page).
- Admin/API prefix: `/api/modules/custom-ad_float`.
- Front-end injection via layout extension on `_user_base` + event hooks + dedicated JS asset (`ad-float.js`).
- Public payload API for theme injection without core/theme edits.

## 0.1.8
- Fix: home popup ad never rendered (missing view namespace); render popup via absolute file path.

## 0.1.7
- Fix 500 caused by route name prefix mismatch in module web routes.
- Fix Storage facade reference in admin Blade view.

## 0.1.6
- Admin Blade views via absolute file path (no view namespace dependency).

## 0.1.4
- Removed frontend admin layout route that caused empty-page issues on some installs.

## 0.1.3
- Complete server-rendered admin settings UI with advanced position/carousel/mobile/schedule settings.
