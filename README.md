# custom-ad_float (Gnuboard7)

플로팅(고정) 캐러셀 광고 모듈입니다. 테마 파일을 수정하지 않고 레이아웃 확장·이벤트 훅·JS로 메인 테마에 광고를 주입합니다.

## 기능

- 관리자 `/admin/ad-float`: **기본 설정** → **광고 목록** → **날짜 및 시간 예약**. 「통계 보기」로 `/admin/ad-float/stats` (노출·클릭, 광고별 × 페이지별)
- 광고 이미지: **파일 업로드** 또는 **웹주소 연결** (둘 중 하나)
- 위치·캐러셀·모바일·닫기 쿠키 등 기본 표시 옵션
- `position: fixed` 로 스크롤 따라다님

## 노출 규칙

- **예약 사용 OFF** (기본값): `기본 설정` + 사용 중인 광고 전부. 예약 행은 표시에 쓰지 않습니다.
- **예약 사용 ON**: 위에서부터 **첫 번째로 일치하는** 예약만 적용합니다.
  - 일치 조건: 현재 시각이 시작~종료 안에 있고, 오늘이 선택한 요일(비우거나 매일 = 전 요일).
  - 일치하면 그 행의 표시 설정 + 선택한 광고(`item_ids`, 비우면 사용 중 광고 전부).
  - 일치하는 행이 없으면 광고를 숨깁니다.

프론트는 `/api/modules/custom-ad_float/payload` 를 따릅니다. 숨김이면 `enabled: false` 와 빈 목록입니다.

홈 페이지 JS는 `_user_base` `scripts`, `user_layout_root` HtmlContent, 레이아웃 훅, SEO `extraBodyEnd` `<script src="...ad-float.js?v=버전">` 으로 넣습니다. `main_content_area`에 의존하지 않습니다.

## 설치

```bash
php artisan extension:update-autoload
php artisan module:install custom-ad_float
php artisan module:activate custom-ad_float
php artisan cache:clear
```

테이블:

- `custom_ad_float_settings`
- `custom_ad_float_items`
- `custom_ad_float_stats` (일별 노출/클릭 롤업)

## 업그레이드 (0.1.12)

```bash
php artisan migrate
php artisan cache:clear
```

`custom_ad_float_stats` 테이블이 추가됩니다. 프론트 `ad-float.js` 캐시 쿼리는 `?v=0.1.12` 입니다.

## 관리자

- UI: `/admin/ad-float`
- 통계: `/admin/ad-float/stats` (오늘 / 7일 / 30일 / 전체)
- API: `/api/modules/custom-ad_float/admin/...`
- 공개 트래킹: `POST /api/modules/custom-ad_float/track` `{ type: "impression"|"click", item_id, page_path }` (인증 없음, 분당 제한)

## 프론트 주입

1. `resources/extensions/ad_float__user_base.json` — `_user_base` scripts + `user_layout_root`
2. `InjectAdFloatListener` — layout scripts / HtmlContent / SEO extraBodyEnd
3. `resources/assets/ad-float.js` — payload 조회 후 렌더

## 식별자

| 항목 | 값 |
|------|-----|
| identifier | `custom-ad_float` |
| vendor | `custom` |
| namespace | `Modules\\Custom\\AdFloat` |
| version | `0.1.12` |
