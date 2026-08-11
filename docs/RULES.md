# RULES

# AI 개발 규칙

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
- 회원관리, 게시판, 오더, 기사, 배차, 정산 등 목록 화면은 가능하면 공통 `DataTable` 기준 구조를 우선 사용한다.
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
- Spatie 도입 전까지는 기존 `users.role`, `users.permissions` 구조를 임시로 유지할 수 있다.
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

## Frontend 규칙
- 스타일링은 프로젝트 내부 CSS 파일로 직접 작성한다.
- `Tailwind CSS`, `Bootstrap`, `Bulma` 같은 외부 CSS 라이브러리/프레임워크는 사용하지 않는다.
- 새 화면이나 컴포넌트에 필요한 스타일은 공통 CSS 또는 모듈 전용 CSS로 직접 정의한다.
- 외부 CSS 유틸리티 라이브러리를 추가하지 않는다.
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
- 모든 SPA 기능(데이터 공급, 라우팅, 상태 관리, 비즈니스 로직)은 기본 관리자 대시보드에서 만들어진다.
- SPA 개발 대화창에서는 오로지 SPA UI 프론트엔드(화면, 컴포넌트, 스타일)만 만든다.
- SPA 작업 시 백엔드(PHP 컨트롤러, 라우트, Blade 데이터 내장, API 엔드포인트)는 수정하거나 새로 만들지 않는다.
- SPA가 필요한 데이터는 기본 관리자 대시보드가 Blade에 내장한 JSON을 그대로 소비한다.
- SPA 화면에서 백엔드 데이터 구조를 임의로 변경하지 않으며, 내장된 JSON 계약을 기준으로 UI만 구성한다.
- SPA에서 새 API를 호출하거나 새 데이터 가공 규칙을 만들기 전에, 이미 내장된 데이터로 표현 가능한지 먼저 검토한다.
- SPA 규칙과 기본 관리자 대시보드 규칙이 충돌하면 관리자 대시보드 규칙을 우선한다.
- SPA 싱글 페이지의 프론트엔드는 기존 대시보드나 기존 Blade 앱과 무관한 완전히 독립적인 프론트엔드다.
- SPA 프론트엔드의 CSS는 기존 대시보드/Blade의 공통 CSS를 재사용하지 않고, SPA 전용 CSS를 새로 만들어 SPA 내부에서만 사용한다.
- SPA 화면·컴포넌트·스타일·아이콘·유틸리티 등 SPA 프론트엔드 요소는 전부 SPA 전용으로 새로 만들며, 기존 대시보드 공통 리소스를 그대로 끌어 쓰지 않는다.
- SPA 전용 CSS와 리소스는 `resources/js/business/spa/` 내부에서 관리한다.
- SPA에서는 대시보드 공용 CSS 클래스(`page-panel`, `status-badge`, `meta-badge`, `input-field`, `btn-primary` 등)를 사용하지 않는다.
- SPA에서 공용 클래스를 쓰고 싶어도 사용하지 않으며, 동일한 시각적 결과가 필요하면 SPA 전용 CSS 클래스로 새로 만들어 사용한다.
- SPA에서는 외부 CSS 라이브러리 사용을 허용한다. 대시보드/Blade의 `외부 CSS 라이브러리 금지` 규칙은 SPA에 적용하지 않는다.
- SPA 전용 CSS 라이브러리(예: Tailwind CSS)는 `resources/js/business/spa/` 내부의 SPA 전용 CSS에서만 사용하고, 대시보드 공통 CSS에는 적용하지 않는다.
- SPA UI 추구 방향은 `유저가 사용하기 쉽게, 편하게, 최대한 미니멀하고 심플하게`다.
- SPA UI에서 장황한 설명 문구, 불필요한 안내 텍스트, 과도한 장식 요소는 넣지 않는다.
- SPA UI는 필수 정보와 핵심 동작만 노출하고, 화면 밀도를 낮추며 군더더기 없는 구성을 유지한다.

## Database 규칙
- 데이터베이스 구조는 [DATABASE.md](./DATABASE.md)를 기준으로 유지한다.

## API 규칙
- API 통신 규약은 [API.md](./API.md)를 기준으로 유지한다.

## UI 규칙
- 전체 디자인 시스템과 화면 설계 기준은 [UI.md](./UI.md)를 기준으로 유지한다.
- UI 작업 전후에는 반드시 `docs/UI.md`를 기준 문서로 확인한다.
- 대시보드와 기능 테스트 페이지 구조는 `docs/DASHBOARD.md`를 함께 확인한다.

### 색상 토큰 (필수 준수)
```
                    Light               Dark
───────────────────────────────────────────────────
배경
  Page            #F3F3F3             #171717
  Surface         #F7F7F7             #1A1A1A
  Layered         #EFEFEF             #202020

텍스트
  Primary         #1F1F1F             #D6D6DD / #F3F3F3
  Secondary       #6A6A6A             #9EA1A8 / #B9BBC0
  Muted           #4F4F4F             #9CA3AF

테두리
  Default         #DDDDDD             #2A2A2A
  Button-primary  #D0D0D0             #343434
  Button-second   #D8D8D8             #2A2A2A

버튼
  Primary-bg      #ECECEC             #252526
  Primary-text    #1F1F1F             #D6D6DD
  Secondary-bg    #F5F5F5             #1A1A1A
  Secondary-text  #4F4F4F             #B9BBC0
  Hover-bg        #EDEDED             #222222
  Hover-text      #1F1F1F / #2D2D2D   #D6D6DD

상태 배지
  기본             #555555 on #F1F1F1   #B9BBC0 on #202020
  완료             #166534 on #F0FDF4   #86EFAC on #052E16
  정산             #1E40AF on #EFF6FF   #93C5FD on #172554
  활성             #92400E on #FEFCE8   #FCD34D on #451A03
```

- 모든 색상은 위 토큰을 벗어나지 않는다.
- 다크 모드에서 밝은 색(#fff, 순색, 고채도)을 절대 사용하지 않는다.
- 강조가 필요해도 Gray → Dark Gray → Black 범위 안에서만 해결한다.
- 상태 배지는 기본 `status-badge`, 완료 `--completed`, 정산 `--settled`, 활성 `--active` 변형을 사용한다.
- 목록 화면은 조회 중심으로 유지하고, 변경 작업은 원칙적으로 상세보기 화면에서만 수행한다.
- 상세보기는 특별한 지시가 없으면 별도 상세 페이지가 아닌 공통 Modal(`BaseModal` / `<x-modal>`)로 표시한다.
- 목록 화면에서는 Role 변경, 상태 변경, 권한 수정 같은 저장 액션을 직접 제공하지 않는다.
- 모든 CRUD 화면은 `목록 = 조회/검색`, `상세 = 수정/삭제/취소` 구조를 기본 원칙으로 사용한다.
- CRUD 기준으로 `등록`은 목록 또는 전용 등록 화면에서 시작하고, `수정`, `삭제`, `취소`는 항상 상세보기 화면에서만 수행한다.
- 게시판, 회원, 오더, 기사, 배차, 정산 등 다른 CRUD를 새로 만들 때도 동일하게 `수정/삭제/취소 액션 = 상세보기(기본 Modal)` 규칙을 따른다.
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
- 현재 권장 아이콘 기준은 `Lucide Icons`이며, Vue 3에서는 `lucide-vue-next` 사용을 우선 검토한다.
- 새로운 아이콘 라이브러리를 임의로 추가하지 않는다.
- 모든 아이콘은 공통 `Icon` 컴포넌트 또는 공통 Wrapper를 통해 사용한다.
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

## 보안 규칙
- 보안 기준은 [SECURITY.md](./SECURITY.md)를 기준으로 유지한다.

## 외부 접속 (Cloudflare Tunnel) 규칙
- 터널 설정 및 운영 절차는 [DEPLOY.md](./DEPLOY.md)를 기준으로 유지한다.

## AI 행동 규칙
- AI는 UI 생성 시 가능한 한 `title`, `Icon`, `aria-label`을 기본으로 추가한다.
- 특별한 이유가 없는 한 `title`, `Icon`, `aria-label`을 생략하지 않는다.
- AI는 새 UI를 만들기 전에 기존 공통 컴포넌트와 공통 아이콘 Wrapper 사용 가능 여부를 먼저 확인한다.
- AI는 버튼 UI를 만들 때 텍스트 버튼부터 만들지 않고 아이콘으로 표현 가능한지 먼저 검토한다.
- AI는 의미가 명확한 버튼은 아이콘 버튼 또는 아이콘 중심 버튼으로 우선 제안하거나 구현한다.
- AI는 모호한 액션, 파괴적 액션, 최초 학습이 필요한 액션만 `아이콘 + 텍스트` 조합을 유지한다.
- AI는 새로운 아이콘 라이브러리를 임의로 추가하지 않는다.
- AI는 권한 설계 시 `menu 권한`과 `action 권한`을 분리해서 다룬다.
- AI는 Spatie 전환 전 임시 권한 구조를 임의로 제거하지 않는다.
- AI는 `Super Admin = id 1 단일 계정` 규칙을 예외 없이 따른다.
- AI는 권한 위임 구현 시 `자기 권한보다 낮은 권한만 부여 가능` 규칙을 예외 없이 따른다.
- AI는 파일 업로드 요구가 생기면 도메인 모듈 내부에 직접 붙이지 않고 공통 `File Module` 재사용 여부를 먼저 확인한다.
- AI는 파일 업로드, 삭제, 다운로드, 미리보기를 새로 만들 때 공통 파일 모듈 기준으로 설계한다.
- AI는 목록 화면을 새로 만들 때 도메인 전용 테이블을 먼저 만들지 않고 공통 `DataTable` 재사용 여부를 먼저 확인한다.
- AI는 `DataTable` 관련 작업 시 `shared/components/DataTable/` 구조를 기준으로 확장한다.
- AI는 문서형 입력/조회가 필요한 작업을 받을 때 도메인 전용 에디터를 먼저 만들지 않고 공통 `ToastEditor` / `ToastViewer` 재사용 여부를 먼저 확인한다.
- AI는 문서형 이미지 업로드 요구가 생기면 `ToastEditor` 내부 훅과 공통 `File Module` 연결을 우선 검토한다.
- AI는 입력 폼 UI가 필요한 작업을 받을 때 도메인 전용 폼을 먼저 만들지 않고 공통 `shared/components/Form/` 재사용 여부를 먼저 확인한다.
- AI는 폼 관련 작업 시 `Form`, `FormGroup`, `BaseInput`, `BaseTextarea`, `BaseSelect`, `BaseCheckbox`, `BaseMarkdownEditor` 공통 구조를 기준으로 확장한다.
- AI는 폼 오류, 설명, 라벨 묶음이 필요한 경우 가능하면 공통 `FormGroup` 기준 구조를 먼저 사용한다.
- AI는 문서형 이미지 버튼이 필요한 경우 기본 에디터 처리나 도메인 전용 모달을 먼저 만들지 않고 공통 `ToastEditor + File Manager Modal` 구조를 먼저 검토한다.
- AI는 스타일 작업 시 외부 CSS 라이브러리를 추가하지 않고 프로젝트 내부 CSS 파일로 직접 작성한다.
- AI는 비즈니스 로직과 비즈니스 UI를 같은 폴더에 섞지 않는다.
- AI는 도메인 데이터 처리 코드를 `Modules/{Domain}/Models`에, 도메인 화면 UI 코드를 `Modules/{Domain}/UI`에 분리하는 구조를 우선 제안하거나 사용한다.
- AI는 비즈니스 UI 구현 시 먼저 `Shared` 공통 컴포넌트 재사용 여부를 검토한다.
- AI는 목록 행의 상세보기를 만들 때 특별한 지시가 없으면 별도 상세 페이지 대신 공통 Modal(`BaseModal` / `<x-modal>`)을 우선 사용한다.
- AI는 테이블 행 액션을 만들 때 여러 아이콘/텍스트 버튼을 나열하지 않고 아래 방향 점 3개 아이콘(`ellipsis-vertical`) 메뉴를 우선 사용한다.
- AI는 점 3개 액션 메뉴의 항목을 `아이콘 + 텍스트` 리스트로 구성하고, 가능한 한 공통 액션 아이콘을 사용한다.
- AI는 NoWhere 비즈니스 UI에 독립적인 디자인과 스타일 계층이 필요하다는 전제를 유지한다.
- AI는 비즈니스 UI 작업 시 공통 Foundation 재사용과 도메인 전용 스타일 분리를 동시에 만족시키는 방향으로 설계한다.
- AI는 화면 안에 반복 로직, 포맷 로직, 정렬 로직, row 조립 로직을 계속 누적하지 않고 공통화 또는 분리 가능 여부를 먼저 검토한다.
- AI는 프론트엔드에서 다시 사용할 가능성이 있는 기능을 구현할 때 화면 내부 임시 코드보다 재사용 가능한 분리 구조를 우선 제안하거나 구현한다.
- AI는 Blade 템플릿을 데이터 처리 계층처럼 사용하지 않고, 가능한 한 표시 역할만 남도록 정리한다.
- AI는 두 번 이상 반복될 기능은 공통 컴포넌트, helper, builder, service, formatter 후보로 판단하고 먼저 분리 가능성을 검토한다.
- AI는 스타일(색상, 배경, 호버, 간격 등)을 변경할 때 한 번에 여러 요소를 바꾸지 않고 단계별로 적용한다.
- AI는 스타일 단계를 적용한 뒤 사용자 확인을 받기 전에는 다음 스타일 단계로 넘어가지 않는다.
- AI는 색상이나 호버 효과처럼 감각적 판단이 필요한 요소를 변경할 때 먼저 단계별 적용을 제안한다.
- AI는 외부 접속 URL을 안내할 때 반드시 `/up` 헬스체크(HTTP 200)로 실제 동작을 검증한 뒤 안내한다.
- AI는 새 공통 컴포넌트를 만들면 반드시 대시보드 허브와 개별 미리보기 페이지(`/dashboard/modules/{module}`)를 함께 등록한다.
- AI는 컴포넌트의 모든 variant와 상태를 대시보드 미리보기 페이지에서 확인할 수 있게 구성한다.

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

---
