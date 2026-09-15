# custom-ad_float (Gnuboard7)

플로팅(고정) 캐러셀 광고 모듈입니다. 테마 파일을 수정하지 않고 레이아웃 확장·이벤트 훅·JS로 메인 테마에 광고를 주입합니다.

## 기능

- G7 관리자 레이아웃(`_admin_base`) 설정 화면 `/admin/ad-float`
- 광고 이미지: **파일 업로드** 또는 **웹주소 연결** (둘 중 하나 선택)
- 광고 등록 / 수정 / 삭제 / ON·OFF / 정렬
- 위치: 왼쪽 / 오른쪽 / 상단 / 하단
- 캐러셀 방향: 좌우 / 상하
- 자동재생, 간격, 화살표, 도트, 닫기, hover 정지
- 홈 전용 표시, 모바일 표시/숨김
- 노출 예약: 여러 기간 + 요일 (일~토). 예약이 없으면 항상 노출
- `position: fixed` 로 스크롤 따라다님

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

## 업그레이드 (0.1.10)

기존 설치는 마이그레이션으로 `image_source`, `schedules` 컬럼을 추가합니다.

```bash
php artisan migrate
php artisan cache:clear
```

## 관리자

- UI: `/admin/ad-float`
- API: `/api/modules/custom-ad_float/admin/...`

## 프론트 주입

1. `resources/extensions/ad_float__user_base.json` — `_user_base`에 마운트 + JS 로드
2. `InjectAdFloatListener` — 레이아웃/SEO 훅 보조
3. `resources/assets/ad-float.js` — `/api/modules/custom-ad_float/payload` 조회 후 렌더

## 식별자

| 항목 | 값 |
|------|-----|
| identifier | `custom-ad_float` |
| vendor | `custom` |
| namespace | `Modules\\Custom\\AdFloat` |
| version | `0.1.10` |
