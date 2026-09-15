# Changelog

## 0.1.14
- Multi-image create: file input accepts several images; extra image URL rows; one submit creates N items. Optional 「결합하여 캐러셀로 등록」 shares a `carousel_group` uuid and consecutive `sort_order`.
- Ad list: select rows and **결합** / **결합 해제**. Combined rows show a 「캐러셀 A」 badge.
- Public payload includes `carousel_group`. Front JS flattens into one carousel ordered by group (min sort_order), then sort_order, then id. Stats remain per `item_id`.
- Migration `2026_09_15_000007` adds nullable `carousel_group`. `POST /admin/items/combine` and `/uncombine`.

## 0.1.13
- Left/right ads align to the **main content column** via `getBoundingClientRect()` (not `left/right: var(--g7-ad-offset)` viewport walls). Finder prefers `#main_content` / `max-w-7xl` / centered max-width under `user_layout_root` and **never** treats a full-bleed wrapper as the box (that clamp looked like viewport walls). Recompute on resize/orientation/scroll (rAF). `left`/`right` are set with `!important` so leftover CSS cannot pin to the viewport.
- `offset_px` / `vertical_offset_px` accept **negatives** (−500…500). Horizontal: positive = outside into the side margin, negative = inward over the content. Vertical: opposite-direction nudge. JS no longer `Math.max(0)` the offsets; final position is only nudged back if the ad would fully leave the viewport.
- New settings `vertical_align` (`top` | `middle` | `bottom`, default `middle`) and `vertical_offset_px` (default 24) for left/right ads. Top = `top: px`; middle = center plus optional px nudge (positive = down); bottom = `bottom: px`.
- Placement updates on resize/scroll (rAF) and ResizeObserver; ads are clamped on-screen if the content box is flush or wider than the viewport.
- Each reservation row has **사용** (`enabled`, default true). Global **예약 사용** is still the master switch; when it is on, disabled rows are skipped as if they were not in the list.

## 0.1.12
- Stats sub-page `/admin/ad-float/stats`: summary cards (impressions, clicks, CTR) plus tables by ad, by page, and ad × page. Date filter: today / 7d / 30d / all (default 7d). Empty state when no rows.
- Main admin page link 「통계 보기」.
- Public `POST /api/modules/custom-ad_float/track` (throttled, always 200): `{ type, item_id, page_path }`.
- Admin `GET /api/modules/custom-ad_float/admin/stats?range=7d`.
- Daily rollup table `custom_ad_float_stats` unique on `(item_id, page_path, stat_date)`; upsert increment. Registered in `getDynamicTables()`.
- Front JS: impression when a slide is shown (sessionStorage `caf_imp_{itemId}_{path}`), click beacon then navigate. `page_path` from `location.pathname` with trailing slash normalized.

## 0.1.11
- Home ads: inject `ad-float.js` on user chrome via `_user_base` `scripts`, `user_layout_root` HtmlContent, layout hooks (`filter_child_data` / `filter_merged` / `after_apply`), and SEO `extraBodyEnd` `<script src="...?v=0.1.11">`. No `main_content_area` dependency.
- Admin is one flow: **기본 설정** → **광고 목록** → **날짜 및 시간 예약**.
- Booleans use Toggle switches (including **예약 사용**).
- **예약 사용 OFF** (default): public payload uses 기본 설정 and all enabled ads.
- **예약 사용 ON**: first matching row (window + weekdays) applies that row’s visual settings and `item_ids`; no match hides ads.
- Each reservation copies 기본 설정 visual fields, date/time, weekdays, and selectable ads.

## 0.1.10
- Fix admin ad create/save: empty optional fields (`target_url`, `display_seconds`, schedule dates) no longer fail validation.
- Accept relative click URLs (no longer require a strict `url` scheme).
- Normalize blank/`null` JSON payloads from the G7 layout engine before validate.
- Create item API returns HTTP 200 (G7 admin `apiCall` success path).
- Idempotent settings/items migrations; register dynamic tables for uninstall.
- Ad create/edit: choose **이미지 업로드** (file) or **웹주소로 연결** (URL). Mutual choice; persist `image_source`; validate file vs URL accordingly.
- Settings: multiple addable display reservations (start/end) with weekday checkboxes (Sun–Sat). Ads show when now is inside a window **and** today matches (empty weekdays = every day). No rows = always visible.

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
