# RULES

# AI 개발 규칙

## 제품 방향성 (반드시 준수)
- 제품 방향성은 [PRODUCT_VISION.md](./PRODUCT_VISION.md), 운영 기획은 [OPERATIONS.md](./OPERATIONS.md)를 **단일 소스**로 따른다.
- 핵심 정체성: 단순 운행 게시판이 아니라 **"오늘 받을 운행을 앱이 먼저 골라주는 운행 최적화 플랫폼"**. 운행을 많이 보여주는 것보다 필요한 선택지를 줄여 판단 피로도를 낮춘다.
- 용어: UI·문서·주석에서 "오더" 대신 **"운행"** 을 사용한다. (코드 식별자 `order/Order`는 유지)
- UI 미니멀: 한 화면 Primary 버튼 하나 / 현재 행동을 가장 크게 / 정보 → 추천 이유 → 행동 / 색상은 의미가 있을 때만.
- 홈 추천: 연결고리 / 단일 / 셋트 3개 섹션. 추천 카드에는 조건%(`match_score`) + 근거 체크리스트(`match_reasons`)를 노출한다. 선호도가 `recommendation.min_match_score`(기본 60%) 미만이면 추천에서 제외한다.
- 상태 흐름: [ORDER_FLOW.md](./ORDER_FLOW.md) 단일 소스. 주요 상태 변경은 전부 타임라인으로 기록한다.
- 운영 원칙: 정상 운행은 자동 처리, 문제 운행만 관리자가 개입한다.

## 모듈화 규칙
- 아키텍처 철학은 [ARCHITECTURE.md](./ARCHITECTURE.md)를 기준으로 유지한다.
- NoWhere의 상위 구조 기준은 `Application > Core / Shared / Modules`다.
- `Core`는 프로젝트 실행에 필요한 핵심 기능만 둔다.
- `Shared`는 프로젝트 전체에서 재사용되는 공통 UI와 공통 기능만 둔다.
- `Modules`는 실제 업무 기능만 둔다.
- 비즈니스 도메인은 `Modules/{Domain}` 구조를 기준으로 확장한다.
- `Modules/{Domain}/Models`는 도메인 데이터 처리, API 호출, 상수, 매퍼, 서비스, 헬퍼만 둔다.
- `Modules/{Domain}/UI`는 실제 화면 UI, 페이지 컴포넌트, 도메인 전용 뷰 조합만 둔다.
- NoWhere 비즈니스 UI는 공통 Foundation과 분리된 독립적인 UI 디자인과 스타일 레이어를 가진다.
- 모듈의 `Models` 계층에 화면 UI 컴포넌트를 넣지 않는다.
- 모듈의 `UI` 계층에 공통 컴포넌트를 중복 생성하지 않는다.
- 도메인 전용 UI가 필요해도 먼저 `Shared` 공통 컴포넌트 조합으로 해결 가능한지 검토한다.
- 비즈니스 UI 전용 레이아웃, 패널 구조, 화면 밀도, 상태 표현, 모듈 전용 스타일은 각 `Modules/{Domain}/UI` 계층에서 관리한다.
- `Customer`, `Company`, `Vehicle`, `Driver`, `Common Code`, `Order`, `Dispatch`, `Settlement` 같은 업무 기능은 장기적으로 `Modules/{Domain}/Models`와 `Modules/{Domain}/UI` 구조를 기준으로 확장한다.
- 파일 업로드, 삭제, 다운로드, 미리보기 같은 파일 관련 기능은 특정 도메인 모듈 안에 직접 구현하지 않는다.
- 파일 관련 기능은 반드시 독립된 공통 `File Module`로 분리하고, 필요한 모듈에서 가져다 사용한다.
- `Board`, `Profile`, `Order`, `Driver` 등은 각 모듈 안에서 개별 업로드 로직을 따로 만들지 않고 공통 파일 모듈을 재사용한다.
- 업로드 기능과 파일 관리 기능은 가능하면 분리한다.
- 일반 화면은 `Upload` 성격의 공통 기능을 사용하고, 관리자 화면은 `Manager` 성격의 공통 기능을 사용한다.
- 앞으로 파일 업로드가 필요한 기능이 생기면 먼저 공통 파일 모듈 사용 가능 여부를 확인하고, 없으면 파일 모듈을 기준으로 확장한다.
- AI는 파일 업로드 요구가 생겨도 특정 모듈 내부에 전용 업로드 구조를 먼저 만들지 않는다.
- AI는 파일 기능 구현 시 공통 `File Module` 기준 구조를 먼저 제안하거나 재사용한다.
- 회원관리, 게시판, 운행, 기사, 배차, 정산 등 목록 화면은 가능하면 공통 `DataTable` 기준 구조를 우선 사용한다.
- 목록 화면은 도메인 전용 `UserTable`, `BoardTable`, `OrderTable` 같은 개별 출력 컴포넌트를 먼저 만들지 않는다.
- 목록 화면은 공통 `shared/components/DataTable/`를 기준으로 만들고, 각 모듈은 `columns`, `rows` 같은 데이터 정의와 셀 슬롯만 제공한다.
- 공통 목록 구조가 필요한 경우 먼저 `DataTable` 재사용 가능 여부를 검토하고, 없으면 `DataTable`을 기준으로 확장한다.
- 게시판, 공지사항, FAQ, 문의, 운영 매뉴얼처럼 문서형 본문 입력이 필요한 화면은 도메인 전용 에디터를 따로 만들지 않는다.
- 문서형 입력은 공통 `shared/components/ToastEditor/ToastEditor.vue`, 조회는 `shared/components/ToastEditor/ToastViewer.vue`를 기준으로 재사용한다.
- 새 문서형 모듈은 먼저 공통 `ToastEditor` / `ToastViewer` 재사용 가능 여부를 확인하고, 없으면 공통 모듈 기준으로 확장한다.
- Editor 내부 이미지 업로드는 도메인 모듈 안에서 별도 저장 로직을 만들지 않고 공통 `File Module` / `spatie/laravel-medialibrary` 훅에 연결한다.
- 일반 입력 폼 화면은 가능하면 공통 `shared/components/Form/` 기준 구조를 우선 사용한다.
- 폼 화면은 도메인 전용 `UserForm`, `BoardForm` 같은 개별 입력 컴포넌트를 먼저 만들지 않는다.
- 폼이 필요한 모듈은 공통 `Form`, `FormGroup`, `BaseInput`, `BaseTextarea`, `BaseSelect`, `BaseCheckbox`, `BaseMarkdownEditor` 구조를 먼저 검토하고, 없으면 공통 폼 모듈 기준으로 확장한다.
- 폼 라벨, 설명, 오류 메시지 출력은 가능하면 공통 `FormGroup` 구조를 기준으로 묶는다.
- 문서형 이미지 삽입은 가능하면 기본 업로드 버튼 직접 처리보다 공통 `ToastEditor + File Manager Modal` 구조를 우선 사용한다.
- 문서형 이미지의 업로드, 선택, 삭제는 도메인 전용 처리보다 공통 `File Module` 라이브러리 API를 우선 재사용한다.
- Modal이 필요한 화면은 도메인 전용 모달 마크업을 직접 만들지 않는다.
- Vue 영역의 Modal은 공통 `shared/components/Modal/BaseModal.vue`를, Blade 영역의 Modal은 공통 `<x-modal>` 컴포넌트를 우선 사용한다.
- Modal 스타일은 도메인 전용 클래스를 만들지 않고 공통 `app.css`의 `.modal__*` 클래스를 사용한다.
- Modal 열기/닫기 제어는 `open` 속성 또는 `data-modal-close` / `close` 이벤트로 통일하고, 닫기 처리 방식은 도메인마다 다르게 구현하지 않는다.
- 테이블 행의 상세보기는 특별한 지시가 없으면 별도 상세 페이지로 이동하지 않고 공통 Modal(`BaseModal` / `<x-modal>`)로 표시한다.
- 목록에서 상세보기 진입은 행 클릭 또는 액션 메뉴의 `상세보기` 항목으로 처리한다.
- 테이블 행의 액션은 텍스트 버튼이나 아이콘 여러 개를 나열하지 않고, 아래 방향 점 3개 아이콘(`ellipsis-vertical`) 하나로 액션 메뉴를 연다.
- 점 3개 메뉴 안의 각 액션은 `아이콘 + 텍스트` 리스트로 나열하며, 공통 액션 아이콘(`view`, `edit`, `trash` 등)을 최대한 사용한다.
- 행 액션 버튼 배치는 특별한 지시가 없으면 점 3개 아이콘 메뉴를 우선하고, 직접 노출하는 아이콘 버튼은 예외로 둔다.
- Dialog(파괴적 작업 확인)가 필요한 화면은 도메인 전용 마크업을 직접 만들지 않는다.
- Vue 영역의 Dialog는 공통 `shared/components/Dialog/BaseDialog.vue`를, Blade 영역의 Dialog는 공통 `<x-dialog>` 컴포넌트를 우선 사용한다.
- Dialog 스타일은 도메인 전용 클래스를 만들지 않고 공통 `app.css`의 `.dialog__*` 클래스를 사용한다.
- Dialog 열기/닫기 제어는 `open` 속성 또는 `data-dialog-open` / `data-dialog-close` / `data-dialog-confirm` 속성으로 통일한다.
- 삭제와 같은 파괴적 작업은 Modal이 아닌 공통 Dialog(`BaseDialog` / `<x-dialog>`)의 danger 변형으로 확인받는다.
- 같은 기능 규칙이 두 개 이상의 화면, 컴포넌트, 도메인에서 반복되기 시작하면 즉시 분리 가능한 공통 구조인지 검토한다.
- 공통으로 반복되는 기능은 Blade 안의 임시 클로저나 인라인 가공으로 계속 확장하지 않고, 가능한 한 PHP class, 공통 함수, 공통 컴포넌트로 분리한다.
- 프론트엔드에서 재사용해야 하는 기능은 화면 안에서 직접 중복 구현하지 않고, 먼저 `Shared` 또는 `Modules/{Domain}/Models` 계층으로 뺄 수 있는지 검토한다.
- 목록 row 조립, 상태 라벨 변환, 날짜/시간/금액 포맷, 그룹 정렬 규칙처럼 표시 규칙이 있는 기능은 화면 템플릿보다 분리된 PHP class 또는 전용 builder 계층으로 관리하는 것을 우선한다.
- Blade 템플릿은 가능하면 렌더링 역할만 담당하고, 데이터 조립, 정렬, 포맷, 조건 분기 규칙은 Blade 밖으로 분리한다.
- Vue 컴포넌트는 가능한 한 표시와 상호작용에 집중하고, 재사용 가능한 데이터 생성 규칙이나 비즈니스 가공 로직은 프론트 내부 유틸 또는 백엔드 PHP class로 분리한다.
- 새 기능을 만들 때는 먼저 공통화 후보를 찾고, 공통화 가능한 경우 화면 구현보다 분리 가능한 구조를 먼저 설계한다.
- 분리 기준은 재사용 가능성, 반복 횟수, 규칙 존재 여부, 프론트 재사용 필요 여부를 우선으로 판단한다.

## Backend 규칙
- 권한 패키지는 `spatie/laravel-permission`을 공식 기준으로 사용한다.
- 파일 업로드/첨부 저장 엔진은 `spatie/laravel-medialibrary`를 공식 기준으로 사용한다.
- NoWhere의 파일 목록, 미리보기, 다운로드, 삭제, 사용처 표시 같은 업무형 화면은 공통 `File Module`로 직접 개발한다.
- 파일 관련 도메인 로직은 가능하면 `app/Services/FileService.php`를 통해 Media Library를 호출한다.
- 권한 이름은 반드시 `모듈.기능` 규칙으로 통일한다.
- 예시: `user.view`, `user.create`, `dispatch.assign`, `setting.update`
- 메뉴 접근 권한은 기능 권한과 분리하여 `menu.*` 규칙으로 관리한다.
- 예시: `menu.dashboard`, `menu.user`, `menu.order`
- 메뉴 권한과 화면 액션 권한을 혼합하지 않는다.
- Role은 초기 기준으로 `Super Admin`, `Admin`, `Operator`, `Driver` 4개만 사용한다.
- `Super Admin`은 고유번호 `1` 사용자만 허용한다.
- `Super Admin` Role은 신규 부여 대상이 아니며, 고유번호 `1` 사용자만 유지한다.
- 고유번호 `1` 이외의 사용자는 `Super Admin` Role을 가질 수 없다.
- 고유번호 `1` 이외의 사용자는 다른 사용자에게 `Admin` 권한 이상을 부여할 수 없다.
- 고유번호 `1` 사용자는 `Super Admin`을 제외한 모든 하위 권한과 Role을 부여할 수 있다.
- 현재 권한이 `Admin`인 사용자는 `Admin` 하위 권한만 부여할 수 있다.
- 사용자는 자신의 권한과 동등한 권한 또는 자신의 권한보다 상위 권한을 다른 사용자에게 부여할 수 없다.
- 권한 부여 기준은 항상 `부여하는 사용자 > 부여받는 사용자` 관계를 만족해야 한다.
- 권한 위임 로직은 화면 표시, 요청 검증, 저장 처리에서 모두 동일하게 강제해야 한다.
- 현재 권한 구조는 `users.role` + `users.permissions(json)` 임시 구조다. Spatie는 **아직 미설치** 상태이므로 전환 완료 전까지 이 구조를 유지한다.
- Spatie 설치 및 전환 완료 후에는 기존 임시 권한 컬럼을 단계적으로 제거한다.
- 권한은 처음부터 과도하게 세분화하지 않고 `모듈 단위 → 기능 단위` 순서로 확장한다.
- 권한 제어는 Seeder, Middleware, Blade/Vue UI 제어에서 동일한 네이밍 규칙을 공유해야 한다.

## Order Group 규칙
- 비즈니스 모델 및 개발 순서는 [BUSINESS.md](./BUSINESS.md)를 기준으로 유지한다.
- 모든 예약(Order)은 기본적으로 독립된 하나의 Order다.
- Set(`OrderGroup`)은 Order를 묶기 위한 그룹일 뿐이며, Order를 소유하지 않고 그룹 정보만 관리한다.
- Set에 속하지 않은 Order는 Single Order이며, 하나의 Order는 독립적으로 존재할 수 있다.
- 두 개 이상의 Order를 하나의 Set으로 묶을 수 있다.
- Set은 최소 2개의 Order를 가져야 하며, Order가 1개 이하가 되면 Set은 존재할 수 없다.
- Single → Set: 하나 이상의 Single Order를 선택해 새 Set을 생성한다.
- Set → Single: Set에서 특정 Order를 제거하면 해당 Order는 다시 Single Order가 된다.
- Set → 분리(Split): `단일로 변경`, `Set 해제`, `그룹 해제`, `예약 분리` 요청 시 해당 Order를 Set에서 제거한다.
- 분리된 Order는 `group_id = null` 상태가 되어 Single Order가 된다.
- 분리 결과 남은 Order가 2개 이상이면 기존 Set을 유지하고, 1개 이하가 되면 Set을 자동 해제한다.
- 분리 작업은 Order 데이터를 변경하지 않으며 `group_id`만 수정한다.
- AI는 분리 작업 시 절대로 새 Order를 생성하지 않고 기존 Order를 삭제하지 않으며 `group_id`만 수정한다.
- Set → Set: Order를 다른 Set으로 이동할 수 있다.
- Rule 1: Set에 Order가 1개만 남으면 자동으로 Set을 해제하고 남은 Order를 Single로 되돌린다.
- Rule 2: Set은 최소 2개의 Order를 가져야 한다.
- Rule 3: Order 삭제 시 Set에 포함되어 있었다면 자동으로 Set을 다시 계산한다.
- Rule 4: Order는 동시에 두 개 이상의 Set에 속할 수 없다.
- Rule 5: Set이 비어있으면 자동으로 삭제한다.
- 자동 정리: Set의 남은 Order 수가 0개 또는 1개가 되면 Set을 자동 삭제하고 남은 Order는 모두 Single로 변경한다.
- AI는 항상 Order를 먼저 생성하고, 절대로 Set을 먼저 생성하지 않는다.
- Order Group 변경 로직은 전용 `app/Services/OrderGroupService.php`를 통해 트랜잭션으로 처리한다.
- `createGroup`, `removeFromGroup`, `moveToGroup`, `recalculateAfterDelete`는 각각 Single → Set, Set → Single, Set → Set, Order 삭제 후 재계산 규칙을 담당한다.
- `recalculateAfterDelete` 호출 시에는 삭제 전에 조회한 Order 인스턴스를 넘긴다.

## 추천 알고리즘 규칙
- 운행 추천(홈 '강력추천') 알고리즘은 [RECOMMENDATION.md](./RECOMMENDATION.md)를 기준으로 유지한다.
- 기본 패턴: 샌딩 → 랜딩은 샌딩 기준 **30분~2시간**, 랜딩 → 샌딩은 **랜딩 시작(service_time) 기준 3시간~6시간(랜딩 + 3시간 이후)** 간격을 이어 붙이는 구조를 우선한다.
- 랜딩 운행의 service_time은 **항공기 도착 시각**이며, 승객 퇴장 대기(`config/recommendation.php`의 `landing_wait_minutes`, env `LANDING_WAIT_MINUTES`, 기본 60분)가 운행 행의 하차 시각 계산에 반영된다.
- 서비스 지역: **서울/인천/경기만** 취급하며, 시작·도착지가 서비스 지역 밖(부산·대구·광주·대전·울산·세종·강원·충청·전라·경상·제주 등)인 운행은 추천에서 제외한다. 공항은 김포공항(국내선/국제선), 인천공항(T1/T2)이며 같은 공항의 터미널·선은 연결된다.
- 지역 연속성: 연결은 **하차지와 출발지가 가까운 위치일 때만** 허용한다. 같은 구/동/읍/면(상세 구역) 또는 같은 공항(터미널·선 무관)이면 연결되며, **같은 시/도(서울/인천/경기)만으로는 연결하지 않는다.** **강력추천은 반드시 양방향 왕복(공항 샌딩↔랜딩)이어야 하며**, 도심↔도심 픽업처럼 공항을 오가지 않는 방향은 제외한다. 연결된 운행은 전부 강력추천(`recommend_level=strong`)이다. (인천공항 T1↔T2, 김포 국내선↔국제선도 같은 공항이므로 강력추천)
- 홈은 **강력추천(양방향 왕복)만** 노출한다. 추천일정(매칭 설정·이력 단건 추천)은 홈에서 표시하지 않는다. 강력추천은 맡은 운행이 있으면 그 하차지에서 이어지는 왕복 체인, 없으면 마켓에서 샌딩↔랜딩 왕복 짝을 묶어 보여준다.
- 기본 시간 창은 지금부터 4시간(오늘~내일)이며, 매칭 설정 조건 운행은 창 밖이어도 추천한다(알람과 동일). 과거 운행은 추천하지 않는다.

## Frontend 규칙
- 스타일링은 프로젝트 내부 CSS 파일로 직접 작성한다.
- `Tailwind CSS`, `Bootstrap`, `Bulma` 같은 외부 CSS 라이브러리/프레임워크는 사용하지 않는다.
- 새 화면이나 컴포넌트에 필요한 스타일은 공통 CSS 또는 모듈 전용 CSS로 직접 정의한다.
- 외부 CSS 유틸리티 라이브러리를 추가하지 않는다.
- 단, 위 외부 CSS 금지 규칙은 **관리자 대시보드/Blade 프론트에 적용**되며, SPA(`frontend/`)에는 [SPA 규칙](#spa-규칙)의 예외 조항이 우선한다.
- 공통 CSS 클래스 이름은 시각 표현보다 역할 중심의 semantic naming을 우선한다.
- 공통 UI는 `Shared` 계층에 둔다.
- 새 공통 컴포넌트는 대시보드에 미리보기 모듈 페이지를 함께 만든다. 등록 절차는 [DASHBOARD.md](./DASHBOARD.md)를 따른다.
- 비즈니스 데이터 처리 코드는 각 `Modules/{Domain}/Models` 계층에 둔다.
- 비즈니스 화면 UI 코드는 각 `Modules/{Domain}/UI` 계층에 둔다.
- 모듈의 `Models` 계층에는 `.vue` 화면 컴포넌트를 두지 않는다.
- 모듈의 `UI` 계층은 공통 Foundation을 소비하는 위치이지, 공통 Foundation 자체를 다시 정의하는 위치가 아니다.
- 비즈니스 UI는 `Shared`와 다른 독립적인 화면 디자인과 스타일을 가질 수 있다.
- 다만 독립적인 스타일을 만들더라도 버튼, 폼, 테이블, 모달 같은 Foundation primitive 자체를 중복 구현하지 않는다.
- 프론트엔드에서 두 번 이상 반복될 가능성이 있는 UI 동작이나 화면 데이터 구조는 즉시 공통 컴포넌트, composable, helper, builder 후보로 검토한다.
- 프론트엔드에서 같은 데이터 가공 규칙을 여러 화면에서 사용하면 각 화면 안에서 복사하지 않고 공통 구조로 분리한다.
- 화면 전용 `.vue` 파일은 렌더링과 인터랙션을 우선 담당하고, 재사용 가능한 조립 로직은 별도 계층으로 분리한다.
- 도메인 전용 프론트 기능이라도 이후 다른 화면에서 다시 쓸 가능성이 있으면 처음부터 분리 가능한 형태로 만든다.

## SPA 규칙
- SPA는 `frontend/` 디렉터리의 **독립 Vite 프로젝트**다 (Vue 3 + 자체 번들러 설정). 관리자 대시보드/Blade와 분리되어 있다.
- SPA가 필요한 데이터는 Laravel `routes/api.php`의 API 엔드포인트로 공급한다. SPA 기능 개발에 필요한 API 추가·수정은 프론트와 함께 진행한다.
- SPA 프론트엔드의 CSS·컴포넌트·아이콘·유틸리티 등 SPA 프론트엔드 요소는 전부 `frontend/src/` 내부에서 관리하며, 기존 대시보드/Blade 공통 리소스를 그대로 끌어 쓰지 않는다.
- SPA에서는 대시보드 공용 CSS 클래스(`page-panel`, `status-badge`, `meta-badge`, `input-field`, `btn-primary` 등)를 사용하지 않는다. 동일한 시각적 결과가 필요하면 SPA 전용 CSS 클래스로 새로 만들어 사용한다.
- SPA UI 추구 방향은 `유저가 사용하기 쉽게, 편하게, 최대한 미니멀하고 심플하게`다.
- SPA UI에서 장황한 설명 문구, 불필요한 안내 텍스트, 과도한 장식 요소는 넣지 않는다.
- SPA UI는 필수 정보와 핵심 동작만 노출하고, 화면 밀도를 낮추며 군더더기 없는 구성을 유지한다.
- SPA 규칙이 대시보드 규칙과 충돌할 때는 **SPA 전용 예외 조항(외부 CSS 허용, SPA 전용 리소스 등)이 SPA 범위에서 우선한다.** SPA 범위 밖(대시보드/Blade)에는 적용되지 않는다.

## Database 규칙
- 데이터베이스 구조는 [DATABASE.md](./DATABASE.md)를 기준으로 유지한다.

## API 규칙
- API 통신 규약은 [API.md](./API.md)를 기준으로 유지한다.

## UI 규칙
- 전체 디자인 시스템과 화면 설계 기준은 [UI.md](./UI.md)를 기준으로 유지한다.
- UI 작업 전후에는 반드시 `docs/UI.md`를 기준 문서로 확인한다.
- 대시보드와 기능 테스트 페이지 구조는 `docs/DASHBOARD.md`를 함께 확인한다.

### 색상 토큰 (필수 준수)
- 색상 토큰·팔레트는 **단일 기준 [COLORS.md](./COLORS.md)** 를 참조한다. (이전 RULES 내부 색상 표는 COLORS.md로 통합되어 제거됨)
- 모든 색상은 COLORS.md 토큰을 벗어나지 않는다.
- 다크 모드에서 밝은 색(#fff, 순색, 고채도)을 절대 사용하지 않는다.
- 강조가 필요해도 Gray → Dark Gray → Black 범위 안에서만 해결한다.
- 상태 배지는 기본 `status-badge`, 완료 `--completed`, 정산 `--settled`, 활성 `--active` 변형을 사용한다.
- 목록 화면은 조회 중심으로 유지하고, 변경 작업은 원칙적으로 상세보기 화면에서만 수행한다.
- 상세보기는 특별한 지시가 없으면 별도 상세 페이지가 아닌 공통 Modal(`BaseModal` / `<x-modal>`)로 표시한다.
- 목록 화면에서는 Role 변경, 상태 변경, 권한 수정 같은 저장 액션을 직접 제공하지 않는다.
- 모든 CRUD 화면은 `목록 = 조회/검색`, `상세 = 수정/삭제/취소` 구조를 기본 원칙으로 사용한다.
- CRUD 기준으로 `등록`은 목록 또는 전용 등록 화면에서 시작하고, `수정`, `삭제`, `취소`는 항상 상세보기 화면에서만 수행한다.
- 게시판, 회원, 운행, 기사, 배차, 정산 등 다른 CRUD를 새로 만들 때도 동일하게 `수정/삭제/취소 액션 = 상세보기(기본 Modal)` 규칙을 따른다.
- 목록 행의 액션은 아래 방향 점 3개 아이콘(`ellipsis-vertical`) 메뉴로 통일하고, 각 액션은 `아이콘 + 텍스트` 리스트로 나열한다.
- 모든 화면과 컴포넌트는 사용자가 이해하기 쉬운 한국어 `title`을 기본으로 제공한다.
- 버튼은 의미를 명확하게 아이콘으로 표현할 수 있으면 가능한 한 아이콘 우선으로 구성한다.
- 반복적으로 쓰이는 공통 액션 버튼은 텍스트 버튼보다 아이콘 버튼 또는 아이콘 중심 버튼을 우선 검토한다.
- 의미 전달이 불충분하거나 오해 가능성이 있는 버튼만 `아이콘 + 텍스트` 조합을 사용한다.
- 텍스트만 있는 UI보다 의미가 분명한 아이콘을 함께 사용하는 구성을 우선한다.
- Input에는 가능한 한 `placeholder`와 `title`을 함께 제공한다.
- 모든 버튼과 아이콘 버튼은 `title` 또는 `aria-label`을 반드시 제공한다.
- 접근성 속성은 특별한 이유가 없는 한 생략하지 않는다.
- 프로젝트의 아이콘은 하나의 라이브러리만 사용한다.
- 현재 사용 아이콘 기준은 `@vicons/ionicons5`이며, 모든 아이콘은 공통 `BaseIcon` 컴포넌트(공통 Wrapper)를 통해 사용한다.
- 새로운 아이콘 라이브러리를 임의로 추가하지 않는다.
- 모든 아이콘은 공통 `BaseIcon` 컴포넌트(공통 Wrapper)를 통해 사용한다.
- 직접 SVG를 반복 작성하지 않는다.
- 메뉴는 기본적으로 `아이콘 + 텍스트` 형태를 유지한다.
- 공통 아이콘 정책이 정해진 뒤에는 화면마다 다른 아이콘 스타일을 혼용하지 않는다.
- 문서형 입력 화면은 가능하면 `ToastEditor`를 통해 Markdown 문자열을 저장하고, 조회 화면은 `ToastViewer`로 렌더링한다.
- 현재 프로젝트 UI 스타일은 외부 CSS 라이브러리 없이 내부 plain CSS 기준으로 유지한다.
- 시각 스타일(색상, 배경, 호버, 간격, 테두리 등)은 한 번에 크게 변경하지 않고 단계별로 적용한다.
- 스타일을 단계별로 적용한 뒤에는 반드시 사용자 확인을 받고, 확인된 단계만 확정한다.
- 사용자가 확인하기 전에는 다음 스타일 단계로 넘어가지 않는다.
- 색상, 강조, 호버 효과처럼 감각적 판단이 필요한 요소는 기본값으로 임의 확정하지 않고 단계별로 제안하고 확정한다.
- 확정된 스타일 규칙은 이후 화면에도 동일하게 적용한다.
- 테이블 액션 버튼(점 3개 메뉴)은 항상 마지막 오른쪽 컬럼에 배치하고 오른쪽 정렬한다(컬럼 `align: 'right'` + 셀 `flex justify-end`). 모든 테이블에 동일하게 적용한다.
- 삭제 등 사용자 확인이 필요한 액션은 `window.confirm`을 사용하지 않고 공용 확인 다이얼로그(`confirmDialog` / `confirmDelete`)를 사용한다. 모든 삭제/위험 액션에 동일하게 적용한다.

## Git 규칙
- Commit 단위는 항상 한 개 기능 기준으로 잡는다.
- 하나의 Commit에 여러 기능을 섞지 않는다.
- 작업 흐름은 반드시 아래 순서를 따른다.
  - 한 개 기능 선택
  - 개발
  - 검증
  - Commit
  - 다음 기능 진행
- 기능 개발이 끝나기 전에는 Commit하지 않는다.
- 검증이 끝나기 전에는 Commit하지 않는다.
- 검증 없이 Commit하지 않는다.
- 프로젝트 규칙(RULES.md·UI.md·BUSINESS.md·ARCHITECTURE.md 등)에 완벽히 부합해야 Commit한다.
- 규칙을 완벽히 준수하지 않은 상태에서는 Commit하지 않는다.
- 현재 기능 Commit이 끝나기 전에는 다음 기능 개발로 넘어가지 않는다.
- Commit은 작은 단위로 유지하고, 의미 없는 대규모 묶음 Commit을 만들지 않는다.
- 문서 수정도 독립적인 작업이면 별도 Commit 단위로 분리할 수 있다.
- 버그 수정은 버그 수정끼리, 기능 개발은 기능 개발끼리 Commit을 분리한다.
- 리팩터링이 필요하더라도 버그 수정이나 새 기능 개발 Commit에 섞지 않는다.
- 완료된 기능 수정은 버그 또는 명시적 요청이 없는 한 새 Commit 대상으로 잡지 않는다.
- Commit 메시지는 작업 목적이 바로 드러나야 한다.
- 예시
  - `feat: add shared modal foundation`
  - `fix: resolve toast notification mount`
  - `docs: update architecture structure`

## 테스트 규칙
- 테스트 기준은 [TEST.md](./TEST.md)를 기준으로 유지한다.

## 기능 검증 규칙
- 기능 구현 후에는 반드시 검증을 거친다. 순서는 `개발 → 검증 → 커밋`이며, 검증 통과 전에는 커밋하지 않는다.
- 검증은 아래 3단계를 기본으로 수행한다. 자체 검증으로 충분하지 않거나 변경 범위가 크면 검증 전용 서브에이전트를 반드시 활용한다.
- 1·2단계는 통합 검증 스크립트 `verify_all.ps1`(repo 루트)로 한 번에 실행할 수 있다. `.\verify_all.ps1`은 Pint → Pest → vitest → 빌드 → check:refs 순서로 실행하고 실패한 단계를 리포트한다. pre-commit 훅(`hooks/pre-commit`)도 PHP 변경 시 백엔드 테스트를 자동 실행한다.

### 1단계: 정적 검증 (Static)
- 변경 파일을 grep/Read로 다시 읽어 실제 반영 여부를 확인한다. 원복 현상(import·선언·블록 일부 되돌아감)은 이 프로젝트에서 반복 발생하므로 반드시 대조한다.
- 백엔드(PHP): `php vendor/bin/pint --dirty --format agent`로 코드 스타일을 정리한다.
- 프론트엔드(Vue): `npm run build`로 컴파일 오류(누락 import, ReferenceError, 문법 오류)를 확인하고, `npm run check:refs`로 템플릿 식별자/스크립트 정의 누락을 확인한다. 프론트 파일을 건드렸다면 이 두 명령은 필수다.

### 2단계: 동적 검증 (Runtime)
- 백엔드: 관련 테스트를 `php artisan test --compact --filter=<기능>`으로 실행한다. 새 기능에는 테스트(`tests/Feature` 또는 `tests/Unit`)를 작성하거나 기존 테스트를 갱신한다. 인증·권한·비즈니스 로직 변경은 테스트 없이 통과시킬 수 없다.
- 프론트엔드: 순수 로직·유틸·스토어·컴포저블을 변경하면 `npm test`(vitest, `frontend/src/**/*.test.js`)를 실행하고 테스트를 작성·갱신한다. 화면 렌더링 확인은 헤드리스 브라우저(Edge `--headless=new --dump-dom --virtual-time-budget=15000 <url>`)로 수행한다.
- 배포 대상이면 배포 후 `/up`(200), 루트 페이지(200), 새 빌드 asset 적용 여부로 검증한다.

### 3단계: 에이전트 검증 (Agent Review)
- 기능 구현을 마치면 검증 전용 서브에이전트를 실행해 변경 범위를 교차 검토하게 한다.
- 서브에이전트가 반드시 확인할 항목
  - 변경 파일의 diff와 실제 파일 내용 일치(원복 현상 재발 여부)
  - import·선언·블록 누락으로 인한 ReferenceError 가능성
  - 기존 규칙(RULES.md·UI.md·BUSINESS.md·ARCHITECTURE.md 등) 부합 여부
  - 테스트 통과 여부와 새 테스트 작성 여부
- 검증 결과 지적사항은 모두 수정한 뒤 다시 검증하고, 통과 전에는 커밋하지 않는다.
- AI가 스스로 검증한 경우에도 결과(빌드 성공, 테스트 통과, 체크리스트)를 사용자에게 보고한다.

## 보안 규칙
- 보안 기준은 [SECURITY.md](./SECURITY.md)를 기준으로 유지한다.

## 배포·운영 규칙
- **서버 접속 정보(아이디·비밀번호·호스트 키)와 테스트 계정·API 키 같은 민감 정보는 로컬 전용 `.cursor/skills/nowhere-deployment/SECRETS.md`에 기록한다. 이 파일은 `.gitignore` 대상이라 GitHub에 올라가지 않는다.** 문서·코드·커밋에 민감 정보를 직접 넣지 않는다.
- **서버 SSH 접속은 비밀번호 대신 키 인증(OpenSSH)을 사용한다.** 로컬 개인키는 `.deploy/id_rsa`(커밋 금지, 패스프레이즈 있음). Windows에서 접속할 때는 ① 키를 `%TEMP%\trae-agent-toolhost\`에 복사 → ② `icacls /inheritance:r` + 본인 계정만 `F` 권한 부여(안 하면 OpenSSH가 "bad permissions"로 거부) → ③ `SSH_ASKPASS=<askpass.bat>`·`SSH_ASKPASS_REQUIRE=force` 설정 후 `ssh -i`로 접속한다. 정확한 절차·패스프레이즈·호스트 키 지문은 SECRETS.md를 참조한다.
- 배포는 `.deploy/deploy_all.ps1` 통합 스크립트로 진행한다. 프론트는 `npm run build` → dist를 tar로 업로드해 서버 nginx root(`/var/www/frontend/dist`)에 반영하고, 백엔드는 변경 파일을 tar로 업로드해 `/var/www/nowhere`에 반영한다. 서버의 `.deploy/deploy_all.sh`가 해제·마이그레이션·캐시 정리·php-fpm 리로드·검증까지 수행한다.
- 사용법: `.\deploy_all.ps1 -Frontend`, `.\deploy_all.ps1 -BackendFiles "app/..., routes/api.php"`, 또는 두 옵션 조합.
- 백엔드 단일 파일 즉시 반영(빠른 수정)은 scp → `sudo cp` → `sudo systemctl reload php8.3-fpm`(opcache 갱신 필수)으로 처리한다.
- 프론트 빌드본(`public/assets`·`public/index.html`·`public/build` 등)은 git에 커밋하지 않는다(.gitignore 대상). 서버는 npm 빌드 없이 배포 스크립트가 만든 dist를 그대로 서빙한다.
- 의존성(composer.json)이 바뀌면 서버에서 `composer install`을 실행한다. 서버에서 GitHub 접속이 느리면 Aliyun composer 미러(`composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/`)를 사용하고, 그래도 느리면 로컬 vendor를 tar.gz로 압축해 업로드·해제 후 `composer dump-autoload -o`로 정리한다.
- 마이그레이션은 `php artisan migrate --force`, 캐시는 `config:clear / route:clear / view:clear`, php-fpm은 `systemctl reload php8.3-fpm`으로 반영한다.
- 배포 후에는 반드시 `/up`(200), 루트 페이지(200), 새 빌드 asset 적용 여부로 검증한다.
- 데이터베이스는 서버가 MariaDB(`nowhere`, MySQL 호환), 로컬이 SQLite(`database/database.sqlite`)다. 로컬 데이터는 서버 MariaDB 덤프를 `.deploy/import_sqlite.php`로 변환·주입해 동기화한다.
- 개선 작업은 시간이 오래 걸리는 작업(DB 이전, 도메인·SSL, 고정 터널 등)보다 빠르게 완료되는 작업부터 우선 진행한다.

## 개발 워크플로우 규칙
- 기능 개발·수정·업데이트는 원칙적으로 로컬에서만 진행하고 검증한다. 서버 배포·빌드는 사용자가 명시적으로 요청할 때만 수행한다.
- 데이터베이스는 로컬 SQLite(`database/database.sqlite`)를 사용한다: 로컬 SPA(vite dev `localhost:5174`) → 로컬 API(`php artisan serve`, 기본 8000 프록시) → 로컬 DB. 서버 데이터가 필요하면 사용자 요청 시 서버 MySQL 덤프를 받아 `.deploy/import_sqlite.php`로 동기화한다.
- 외부 확인용 임시 주소는 로컬 개발 서버 기준 Cloudflare Quick Tunnel로 발급한다. "플래어주소 열어줘" 같은 요청이 오면 기존 터널만 종료 → 새 터널 발급 → `/up`·루트 200 검증 → URL 공유한다.
- 백엔드 PHP 단일 파일 수정은 서버 즉시 반영이 가능하다 (scp → `sudo cp` → `sudo systemctl reload php8.3-fpm`, opcache 갱신 필수). 단, 사용자 지시 없이 임의로 배포하지 않는다.
- 편집 후에는 반드시 grep/Read로 변경 내용이 실제 파일에 반영됐는지 재검증한다. 이 프로젝트는 import·선언·블록이 부분적으로 되돌아가는 원복 현상이 반복 발생한다.
- 서버 API 응답은 nginx SPA 폴백으로 HTML(200)이 오는 함정이 있으므로 `Array.isArray(data?.data)` 같은 방어 코드로 JSON을 판별한다.

## 외부 접속 (Cloudflare Tunnel) 규칙
- 상용화 전 임시 외부 접속 주소는 서버에서 cloudflared Quick Tunnel을 사용한다 (nginx 포트 80 경유, `cloudflared tunnel --url http://127.0.0.1:80`).
- Quick Tunnel 주소는 프로세스가 살아있는 동안만 유효하며, 서버 재부팅 시 사라지고 새로 발급된다. 재사용할 수 없다.
- 고정 주소가 필요하면 Named Tunnel + 도메인 연결로 전환한다.
- 상세 절차는 [DEPLOY.md](./DEPLOY.md)를 기준으로 유지한다.

## AI 행동 규칙
AI도 위의 모든 규칙(모듈화·Backend·추천·Frontend·SPA·Database·API·UI·색상·Git·테스트·기능 검증·보안·배포·개발 워크플로우·외부 접속)을 **동일하게 준수**한다. 아래는 AI 작업 시 특히 주의해야 할 사항만 정리한다.

### 재사용·구조
- 새 UI 전에 공통 컴포넌트(`frontend/src/components`의 `Ui*`·`common/*`)와 공통 아이콘 `BaseIcon` 사용 가능 여부를 먼저 확인한다. ([FOUNDATION.md](./FOUNDATION.md) 참조)
- 반복될 가능성이 있는 로직·포맷·정렬·row 조립은 화면에 누적하지 않고 공통화/분리 가능성을 먼저 검토한다.
- 권한 설계는 `menu 권한`과 `action 권한`을 분리하고, `Super Admin = id 1 단일 계정`·`자기 권한보다 낮은 권한만 부여` 규칙을 따른다.
- 새 공통 컴포넌트를 만들면 대시보드 허브와 개별 미리보기 페이지(`/dashboard/modules/{module}`)에 함께 등록하고 모든 variant·상태를 확인 가능하게 한다.

### 검증
- 파일 편집 후 grep/Read로 실제 반영 여부를 확인하고, 프론트 변경 후에는 import·선언 누락으로 인한 ReferenceError를 검증한다.
- 프론트 변경 시 `npm run build`와 `npm run check:refs`를 반드시 실행한다.
- 백엔드 변경 시 관련 테스트(`php artisan test --compact --filter=...`)를 실행하고, 새 기능·권한·인증 변경에는 테스트를 작성하거나 갱신한다.
- 변경 범위가 크거나 스스로 검증하기 어려운 작업은 검증 전용 서브에이전트로 교차 검토를 받고, 지적사항을 모두 수정한 뒤 재검증한다.

### 기록
- 기능이나 페이지가 새로 완성되면 `docs/TASKS.md` Completed 목록에 기록하고, 사용자 확인을 거친 항목은 `docs/CHANGELOG.md`도 갱신한다. 완료 기록 없이 다음 작업으로 넘어가지 않는다.
- 기능을 완성하면 다음에 필요한 기능·부족한 점·미래에 할 일을 `docs/TASKS.md` Next/Scope 후보로 기록하고 사용자에게 제안한다. (구현은 하지 않고 기록·제안만)
- AI는 기능 개발 중 개선이 필요한 점(코드 구조·성능·UX·중복·안정성 등)을 발견하면 `docs/TASKS.md`에 기록하고 사용자에게 제안한다. 즉시 리팩터링하지 않고 기록·제안만 한다.

## 개발 원칙
- Laravel 기본 구조와 관례를 우선 사용한다.
- 기존 디렉터리 구조를 유지하고, 임의의 새 베이스 폴더는 만들지 않는다.
- 기능 구현 시 작은 단위로 변경한다.

## 작업 집중 규칙

### 현재 작업만 수행
- AI는 현재 지정된 작업만 수행한다.
- 현재 작업과 관련 없는 기능은 구현하지 않는다.
- 추가 기능을 임의로 생성하지 않는다.
- 향후 기능을 미리 개발하지 않는다.
- 현재 작업 범위를 벗어나는 파일은 생성하거나 수정하지 않는다.

---

### 필요한 경우
- 현재 작업에 필요한 파일만 생성한다.
- 불필요한 폴더를 만들지 않는다.
- 불필요한 컴포넌트를 만들지 않는다.
- 불필요한 API를 만들지 않는다.
- 불필요한 CSS를 만들지 않는다.

---

### 제안 규칙
- 필요한 기능이 있더라도 구현하지 않는다.
- 반드시 제안만 한다.
- 예시:
  - `추후 Toast가 필요할 수 있습니다.`

---

### 작업 완료 기준
- 현재 작업이 100% 완료될 때까지 다음 작업을 시작하지 않는다.
- 현재 작업 완료 후에는 `docs/TASKS.md`가 갱신되기 전까지 임의로 다음 작업을 진행하지 않는다.
- 새 기능이나 페이지가 완성되면 반드시 완료 기록을 남긴다. `docs/TASKS.md`의 Completed 목록에 항목을 추가·체크하고, 사용자 확인을 거친 항목은 `docs/CHANGELOG.md`에 함께 반영한다.
- 완료 기록(문서 갱신)은 해당 기능 커밋에 포함하거나, 독립 문서 커밋으로 분리한다. 미기록 상태로 다음 작업을 시작하지 않는다.
- 기능 완성 후에는 다음에 필요한 기능, 부족한 점, 미래에 해야 할 일을 `docs/TASKS.md`의 Next/Scope 후보로 미리 기록한다. 이때 구현은 하지 않고 기록·제안만 한다. 미기록 상태로 다음 작업을 시작하지 않는다.
- 기능을 개발하는 동안 발견한 개선점(코드 구조, 성능, UX, 중복, 안정성 등)도 `docs/TASKS.md`에 별도로 기록한다. 기록만 하고 즉시 구현하지 않으며, 사용자 확인 후 별도 작업으로 진행한다.

---
