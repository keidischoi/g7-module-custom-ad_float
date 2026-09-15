# Changelog

## 0.1.22
- Admin **예약** cards no longer vanish on click. List iteration is `{{_local.form.schedules || []}}` (no `??` / `?.` — G7 often fails those and re-renders an empty list).
- **예약 추가** uses a short `concat` with `id: 's' + length + '_' + schSeq` (no `Date.now()`). New rows include enabled, dates, weekdays d0–d6, position, `_deleted: false`.
- Field/remove updates replace the whole `form.schedules` array (`map` + `Object.assign` keyed by `sch.id`), the same static-key pattern as 결합 `itemIds` — nested `form.schedules.{{sidx}}.*` keys are gone so a click cannot replace the array.
- Soft-delete sets `_deleted: true` on that row only; the card wrapper is `if: "{{!sch._deleted}}"` (missing `_deleted` stays visible). 예약 buttons keep `type: "button"`.
- Saving keeps newly added rows that only have id/enabled/position; stored JSON preserves `id`.

## 0.1.21
- Admin dark theme: 「캐러셀 옵션」 boxes no longer use `bg-gray-50` (G7 often does not apply Tailwind `dark:` fills, so the block stayed near-white and labels vanished). Main settings and per-reservation boxes are transparent with the same border as sibling toggle rows (X 버튼). Headings stay `text-sm font-semibold` / inherit.
- Same-screen filled cards that relied on `bg-white dark:bg-gray-800` (ad list rows, stats summary/by-item cards) drop the light fill so they match dark admin. Secondary admin buttons drop `bg-white` / `bg-gray-100` fills and keep a border only.
- Admin 「예약 추가」 no longer concatenates a huge template (with `??` and `id:'new'`). New rows are a short object with `id: 's' + Date.now()` and `position` only, so the page does not freeze and each card is unique.
- **X 버튼은 기본 설정 기준. 예약이 덮어쓰지 않음.** `overlayVisual` pins `show_close` from base settings. Reservation cards hide the X toggle.
- 광고 목록: cards sit in a non-iterated `flex flex-col gap-1` parent (G7 `iteration` on the flex node did not space siblings). Card padding `p-2.5`. **광고 등록** and **새로고침** share one compact `gap-1` row above the list.
- Admin create/edit uses G7 **FileUploader** (업로드 박스) instead of a raw file input. Store/update drop empty `image`/`images` strings before validate (`image` is nullable); missing files return 「이미지 파일을 선택해 주세요.」 Validation toasts show the full errors list.

## 0.1.20
- Admin sidebar: **플로팅 광고** is a parent with children **광고 설정** (`/admin/ad-float`) and **통계** (`/admin/ad-float/stats`), same `children` pattern as `custom-digital_product`.
- Both admin layouts have a top tab bar **광고 설정 | 통계** (active underline). The old 「통계 보기」 / 「설정으로」 header buttons are replaced by those tabs.

## 0.1.19
- Front JS honors **X 버튼** (`show_close`) OFF: G7 often saves `0` / `"0"` / `"false"`, and `0 !== false` still created the ×. `settingOn()` now treats those as off and **defaults ON** when the flag is missing (same helper for arrows, dots, autoplay, pause-on-hover, new-tab, enabled, home_only).
- Each payload window keeps `show_close` after reservation overlay; PHP `AdminPayload::toBool()` avoids Laravel `(bool) "0" === true`.
- Admin label `show_close` → 「X 버튼」 (hint: 광고 닫기(×) 표시). EN: Close / X button.
- Float frame background is **transparent** (was `#fff`), so unused top/bottom of the box no longer show as white bars. Slide/link/img fill the frame (`object-fit: cover`, no baseline gap); vertical (상하) track slides are clamped to 100% height.

## 0.1.18
- When **예약 사용 ON**, apply **every** matching reservation (not only the first). Each distinct `position` mounts its own float root (`g7-custom-ad-float-{left|right|top|bottom}`).
- Matching rows that share a position merge into **one** carousel: first row’s visuals, union of `item_ids` (empty = all enabled ads).
- **예약 사용 OFF** stays a single window from 기본 설정. Close cookie is per-position (`close_cookie_key + '_' + position`); the legacy `default` window keeps the unsuffixed key.
- Admin hint under 예약: 「같은 시간대에 위치만 다른 예약을 여러 개 두면 동시에 여러 곳에 표시됩니다.」
- Payload `windows: [{ id, settings, items }]` plus first window as `settings`/`items` for old JS. Combine/carousel and stats (per `item_id` + page) unchanged.

## 0.1.17
- Admin labels: `show_arrows` → 「슬라이딩 버튼」 (hint: 이전/다음 화살표 표시), `show_dots` → 「DOT 표시」 (hint: 캐러셀 인디케이터 점). Grouped with **자동재생** as Toggle controls in 기본 설정 and per-reservation rows.
- Front JS still hides arrows/dots when the flag is false (also `0` / `"false"`), and when there is only one slide (`items.length > 1`).

## 0.1.16
- Fix admin **결합** / **결합 해제**: G7 `setState` keys cannot use `{{}}`, so `itemSel.{{ad.id}}` never persisted and the POST body sent empty ids (`combine_min` / generic 「캐러셀 결합에 실패했습니다.」). Checkboxes now toggle `_local.itemIds` (numeric array; static key + `.concat`/`.filter`). POST `{ item_ids, ids, sels }`.
- Client toast 「결합하려면 광고를 2개 이상 선택하세요」 when fewer than 2 are selected. onError prefers `error.errors[0]` then `error.message`.
- Backend `AdminPayload::selectedItemIds` accepts `item_ids` array or JSON string, `sels` object or JSON string, comma-separated `ids`, and top-level `sel_{id}` flags.
- Combine 422 uses `combine_min` / `combine_unavailable` as the **primary** message (not only buried in `errors`). Missing `carousel_group` column tells the admin to run `php artisan migrate` (migration `2026_09_15_000007`).
- `/admin/items/combine` remains registered before `/{id}`.

## 0.1.15
- Admin `/admin/ad-float` 기본 설정: 「닫힘 상태 초기화」 / 「다시 보이게 하기」 rotates `close_cookie_key` (e.g. `g7_custom_ad_float_closed_<unix>_<hex>`) so visitor X-close cookies/localStorage under the old key are ignored and ads show again. No need for each visitor to clear cookies.
- `POST /api/modules/custom-ad_float/admin/settings/reset-closed` (auth + `custom-ad_float.ads.update`) saves the new key and returns updated settings. Toast: 방문자가 닫았던 기록이 무효화되어 다시 표시됩니다.
- Editable `close_cookie_key` field kept; hint explains per-browser storage vs global key rotate. Single-browser test: DevTools clear of that key.
- Existing X-close behavior unchanged (still writes cookie + localStorage for the current key).

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
- Each reservation row has a **사용** checkbox (`enabled`, default true). Global **예약 사용** is still the master switch; when it is on, disabled rows are skipped as if they were not in the list.

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
