# custom-ad_float (Gnuboard7)

플로팅(고정) 캐러셀 광고 모듈입니다. 테마 파일을 수정하지 않고 레이아웃 확장·이벤트 훅·JS로 메인 테마에 광고를 주입합니다.

## 기능

- 관리자: **플로팅 광고 → 광고 설정** (`/admin/ad-float`)에서 기본 설정·광고 목록·예약을 관리. **통계** 탭/메뉴로 `/admin/ad-float/stats` (노출·클릭, 광고별 × 페이지별)
- **예약 사용 ON**일 때 같은 시간대에 위치만 다른 예약을 여러 개 두면 왼쪽+오른쪽처럼 동시에 여러 곳에 표시됩니다.
- 광고 이미지: **파일 업로드**(여러 장) 또는 **웹주소 연결**(여러 행). 등록 시 「결합하여 캐러셀로 등록」 가능
- 광고 목록에서 선택 후 **결합** / **결합 해제**. 같은 그룹은 「캐러셀 A」 배지
- 위치·캐러셀·모바일·닫기 쿠키 등 기본 표시 옵션. 관리자 「닫힘 상태 초기화」로 방문자가 닫았던 광고를 다시 노출
- 왼쪽/오른쪽: 본문(콘텐츠) 박스 좌우 가장자리 기준 여백, 세로 위치(위/가운데/아래) + px
- `position: fixed` 로 스크롤 따라다님

## 노출 규칙

- **예약 사용 OFF** (기본값): `기본 설정` + 사용 중인 광고 전부. 예약 행은 표시에 쓰지 않습니다.
- **예약 사용 ON**: 현재 시각에 **일치하는 모든** 예약을 적용합니다 (첫 행만 쓰지 않음).
  - 각 행의 **사용**이 꺼져 있으면 그 행은 건너뜁니다(목록에 없는 것과 같음). 없는 값은 사용(on)으로 봅니다.
  - 일치 조건: 사용 중인 행에서, 현재 시각이 시작~종료 안에 있고, 오늘이 선택한 요일(비우거나 매일 = 전 요일).
  - 위치가 다른 일치 행은 창을 따로 붙입니다 (왼쪽+오른쪽 등).
  - 같은 위치의 일치 행은 **창 하나**로 합칩니다. 표시 설정은 그 위치에서 먼저 나온 행, 광고는 `item_ids` 합집합(비우면 사용 중 광고 전부).
  - 일치하는 행이 없으면 광고를 숨깁니다.

프론트는 `/api/modules/custom-ad_float/payload` 의 `windows` 배열을 따릅니다. (`settings`/`items`는 첫 창, 이전 JS 호환.) 숨김이면 `enabled: false` 와 빈 `windows`입니다.

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

## 닫기(X) 상태

방문자가 플로팅 광고 **X**를 누르면 `close_cookie_key`(기본 `g7_custom_ad_float_closed`)로 **쿠키 + localStorage**에 저장되어, 그 브라우저에서는 다시 보이지 않습니다. 「X 버튼」 토글을 끄면 × 자체가 나오지 않습니다. 예약으로 여러 위치에 띄운 창은 키 뒤에 `_{위치}`가 붙어서, 한쪽만 닫아도 다른 위치는 남습니다. (예약 사용 OFF의 기본 창은 예전처럼 접미사 없는 키입니다.)

- **전체 다시 노출:** `/admin/ad-float` 기본 설정에서 **닫힘 상태 초기화** / **다시 보이게 하기**. 서버가 쿠키 키를 새 값으로 바꿔 예전 기록을 무효화합니다. (`POST /api/modules/custom-ad_float/admin/settings/reset-closed`)
- **한 브라우저만 테스트:** 개발자 도구에서 해당 키의 쿠키와 localStorage를 지우면 됩니다. 방문자마다 수동으로 지울 필요는 없습니다.

## 업그레이드 (0.1.22)

```bash
php artisan cache:clear
```

마이그레이션은 없습니다. 관리자 **예약**에서 아무 버튼이나 누르면 카드가 사라지던 문제와 「예약 추가」가 안 되던 문제를 고칩니다. 프론트 `ad-float.js` 캐시 쿼리는 `?v=0.1.22` 입니다.

## 업그레이드 (0.1.21)

```bash
php artisan cache:clear
```

마이그레이션은 없습니다. 관리자 다크 테마에서 「캐러셀 옵션」 흰 박스가 빠집니다. X 버튼은 기본 설정만 따르고 예약이 덮어쓰지 않습니다. 「예약 추가」가 먹통이던 표현식을 짧게 바꿨습니다. 광고 목록 행 간격과 광고 등록/새로고침 여백을 줄였습니다. 광고 등록은 **업로드 박스(FileUploader)** 를 씁니다. 프론트 `ad-float.js` 캐시 쿼리는 `?v=0.1.21` 입니다.

## 업그레이드 (0.1.20)

```bash
php artisan cache:clear
```

마이그레이션은 없습니다. 관리자 사이드바에 **플로팅 광고 → 광고 설정 / 통계**가 생깁니다. 화면 위 탭으로도 전환합니다. 프론트 `ad-float.js` 캐시 쿼리는 `?v=0.1.20` 입니다.

## 업그레이드 (0.1.19)

```bash
php artisan cache:clear
```

마이그레이션은 없습니다. 관리자에서 「X 버튼」을 끄면 플로팅 광고에 ×가 없어집니다. 프레임 흰 배경이 빠져서 위·아래 흰 띠가 보이지 않습니다. 프론트 `ad-float.js` 캐시 쿼리는 `?v=0.1.19` 입니다. 이미 0.1.19를 받은 브라우저는 강력 새로고침하세요.

## 업그레이드 (0.1.18)

```bash
php artisan cache:clear
```

마이그레이션은 없습니다. 예약 사용을 켠 뒤, 같은 시간대에 위치만 다른 예약을 두 개 이상 두면 홈에 동시에 표시됩니다. 프론트 `ad-float.js` 캐시 쿼리는 `?v=0.1.18` 입니다.

## 업그레이드 (0.1.17)

```bash
php artisan cache:clear
```

마이그레이션은 없습니다. 관리자 화면을 새로고침하면 「슬라이딩 버튼」/「DOT 표시」 토글 라벨이 보입니다. 프론트 `ad-float.js` 캐시 쿼리는 `?v=0.1.17` 입니다.

## 업그레이드 (0.1.16)

```bash
php artisan migrate
php artisan cache:clear
```

캐러셀 **결합**은 `custom_ad_float_items.carousel_group` 컬럼이 필요합니다 (마이그레이션 `2026_09_15_000007`, 0.1.14에서 추가). 0.1.14 이상으로 올린 뒤 migrate를 안 했다면 결합이 실패합니다. 관리자 화면을 새로고침하세요.

## 업그레이드 (0.1.15)

```bash
php artisan cache:clear
```

마이그레이션은 없습니다. 프론트 `ad-float.js` 캐시 쿼리는 `?v=0.1.15` 입니다. 관리자 화면을 새로고침한 뒤 「닫힘 상태 초기화」를 쓰면 됩니다.

## 업그레이드 (0.1.14)

```bash
php artisan migrate
php artisan cache:clear
```

`custom_ad_float_items.carousel_group` 이 추가됩니다. 프론트 `ad-float.js` 캐시 쿼리는 `?v=0.1.14` 입니다.

## 업그레이드 (0.1.13)

```bash
php artisan migrate
php artisan cache:clear
```

`custom_ad_float_settings`에 `vertical_align`, `vertical_offset_px`가 추가되고 `offset_px`는 부호 있는 정수(−500…500)로 바뀝니다. 프론트 `ad-float.js` 캐시 쿼리는 `?v=0.1.13` 입니다.

왼쪽/오른쪽 광고는 본문 박스 좌우 가장자리 기준입니다. 양수 여백=바깥, 음수=본문 쪽. 세로 위치는 위/가운데/아래 + px(음수 허용). 컬럼이 아직 없으면 기본값(가운데, 24px)으로 동작합니다.

## 관리자

- 사이드 메뉴: **플로팅 광고** → **광고 설정** `/admin/ad-float`, **통계** `/admin/ad-float/stats`
- 각 화면 위 탭 **광고 설정 | 통계**. 통계 기간: 오늘 / 7일 / 30일 / 전체
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
| version | `0.1.22` |
