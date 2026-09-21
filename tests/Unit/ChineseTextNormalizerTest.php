<?php

use App\Support\Orders\ChineseTextNormalizer;

test('중국어 지명을 한국어로 바꾼다', function () {
    expect(ChineseTextNormalizer::location('明洞'))->toBe('명동')
        ->and(ChineseTextNormalizer::location('广津'))->toBe('광진구')
        ->and(ChineseTextNormalizer::location('钟路'))->toBe('종로구')
        ->and(ChineseTextNormalizer::location('中区'))->toBe('중구')
        ->and(ChineseTextNormalizer::location('机场'))->toBe('공항')
        ->and(ChineseTextNormalizer::location('仁川T1'))->toBe('인천공항 제1터미널')
        ->and(ChineseTextNormalizer::location('t1'))->toBe('인천공항 제1터미널');
});

test('한국어와 주소는 그대로 둔다', function () {
    expect(ChineseTextNormalizer::location('명동'))->toBe('명동')
        ->and(ChineseTextNormalizer::location('인천공항'))->toBe('인천공항')
        ->and(ChineseTextNormalizer::location('을지로'))->toBe('을지로')
        ->and(ChineseTextNormalizer::location('  마포  '))->toBe('마포');
});

test('유입 품질 점검에서 미정으로 남던 지명을 바꾼다', function () {
    expect(ChineseTextNormalizer::location('嘛扑'))->toBe('마포구')
        ->and(ChineseTextNormalizer::location('黑石'))->toBe('흑석')
        ->and(ChineseTextNormalizer::location('西大门'))->toBe('서대문구')
        ->and(ChineseTextNormalizer::location('城南'))->toBe('성남시')
        ->and(ChineseTextNormalizer::location('仁寺洞'))->toBe('인사동')
        ->and(ChineseTextNormalizer::location('道峰'))->toBe('도봉구')
        ->and(ChineseTextNormalizer::location('新罗'))->toBe('신라')
        ->and(ChineseTextNormalizer::location('中庭首尔钟路酒店'))->toBe('종로 호텔');
});

test('지명 칸에 섞여 들어온 차종 표기를 구분한다', function () {
    // 유입 파서가 차종을 지명 칸에 넣어 보낸 건이 있다 — 사전에 있는 차종만 걸러낸다
    expect(ChineseTextNormalizer::isVehicleToken('埃尔法'))->toBeTrue()
        ->and(ChineseTextNormalizer::isVehicleToken('阿尔法'))->toBeTrue()
        ->and(ChineseTextNormalizer::isVehicleToken('卡起'))->toBeTrue()
        ->and(ChineseTextNormalizer::isVehicleToken('利亚7'))->toBeTrue()
        // 지명은 차종이 아니다 — 잘못 지우지 않도록
        ->and(ChineseTextNormalizer::isVehicleToken('명동'))->toBeFalse()
        ->and(ChineseTextNormalizer::isVehicleToken('마포구'))->toBeFalse()
        ->and(ChineseTextNormalizer::isVehicleToken('미정'))->toBeFalse()
        ->and(ChineseTextNormalizer::isVehicleToken(''))->toBeFalse()
        ->and(ChineseTextNormalizer::isVehicleToken(null))->toBeFalse();
});

test('구(區) 접미사는 사전에 두 벌로 두지 않고 떼고 읽는다', function () {
    // 구 단위 행정구역은 값에 구를 붙여 통일한다 — 접미사를 떼도 기저 표기가 구를 가진다
    expect(ChineseTextNormalizer::location('瑞草区'))->toBe('서초구')
        ->and(ChineseTextNormalizer::location('松坡区'))->toBe('송파구')
        ->and(ChineseTextNormalizer::location('麻浦区'))->toBe('마포구')
        ->and(ChineseTextNormalizer::location('城东区'))->toBe('성동구')
        ->and(ChineseTextNormalizer::location('西大门区'))->toBe('서대문구')
        ->and(ChineseTextNormalizer::location('永登浦区'))->toBe('영등포구')
        ->and(ChineseTextNormalizer::location('龙山区'))->toBe('용산구')
        ->and(ChineseTextNormalizer::location('江南区'))->toBe('강남구')
        ->and(ChineseTextNormalizer::location('钟路区'))->toBe('종로구')
        // 번체(區)는 간체(区)로 바꿔 본다 — 떼면 `中` 만 남아 읽히지 않는다
        ->and(ChineseTextNormalizer::location('中區'))->toBe('중구');
});

test('여러 구간이 이어진 위치는 각각 바꿔 다시 잇는다', function () {
    expect(ChineseTextNormalizer::location('明洞—仁川'))->toBe('명동—인천')
        ->and(ChineseTextNormalizer::location('江南 — 金浦'))->toBe('강남구—김포');
});

test('한 칸에 붙은 두 지점과 방향 표시를 지명으로 읽는다', function () {
    expect(ChineseTextNormalizer::location('宏大送inspire'))->toBe('홍대—인스파이어')
        ->and(ChineseTextNormalizer::location('inspire送宏大'))->toBe('인스파이어—홍대')
        ->and(ChineseTextNormalizer::location('宏大送'))->toBe('홍대')
        // 한 쪽을 읽지 못하면 원문을 그대로 둔다 — 단독 接送 이 다른 낱말에 붙었을 수 있다
        ->and(ChineseTextNormalizer::location('宏大送机'))->toBe('宏大送机')
        ->and(ChineseTextNormalizer::location('接机'))->toBe('接机');
});

test('빈 값과 null 은 그대로 돌려준다', function () {
    expect(ChineseTextNormalizer::location(null))->toBeNull()
        ->and(ChineseTextNormalizer::location(''))->toBe('')
        ->and(ChineseTextNormalizer::location('   '))->toBe('');
});

test('중국어 차량 표기를 한국어 차종으로 바꾼다', function () {
    expect(ChineseTextNormalizer::vehicleType('小车'))->toBe('소형 승용차(세단/SUV)')
        ->and(ChineseTextNormalizer::vehicleType('卡起'))->toBe('카니발부터 가능')
        ->and(ChineseTextNormalizer::vehicleType('新卡'))->toBe('더뉴카니발 4세대')
        ->and(ChineseTextNormalizer::vehicleType('利亚7'))->toBe('스타리아 7인승')
        ->and(ChineseTextNormalizer::vehicleType('利亚 7'))->toBe('스타리아 7인승')
        ->and(ChineseTextNormalizer::vehicleType('卡或利亚'))->toBe('카니발 또는 스타리아')
        ->and(ChineseTextNormalizer::vehicleType('카니발'))->toBe('카니발')
        ->and(ChineseTextNormalizer::vehicleType('卡尼巴'))->toBe('카니발')
        // 9인승 의자배치 표기 — 333=3-3-3, 2223=2-2-2-3
        ->and(ChineseTextNormalizer::vehicleType('2223'))->toBe('스타리아 9인승(2-2-2-3)')
        ->and(ChineseTextNormalizer::vehicleType('需要2223车型'))->toBe('스타리아 9인승(2-2-2-3)')
        ->and(ChineseTextNormalizer::vehicleType(null))->toBeNull();
});

test('모니터 유입에서 확인된 지명·차량 표기도 바꾼다', function () {
    expect(ChineseTextNormalizer::location('迎士柏'))->toBe('인스파이어')
        ->and(ChineseTextNormalizer::location('迎世博'))->toBe('인스파이어')
        ->and(ChineseTextNormalizer::location('迎仕柏'))->toBe('인스파이어')
        ->and(ChineseTextNormalizer::location('铜雀区舍堂洞'))->toBe('동작구 사당동')
        ->and(ChineseTextNormalizer::location('狎鸥亭罗德奥地铁站'))->toBe('압구정로데오역')
        ->and(ChineseTextNormalizer::location('城北'))->toBe('성북구')
        ->and(ChineseTextNormalizer::location('文鹤'))->toBe('문학')
        ->and(ChineseTextNormalizer::location('永登浦区'))->toBe('영등포구')
        ->and(ChineseTextNormalizer::location('永澄甫'))->toBe('영등포구')
        ->and(ChineseTextNormalizer::location('中路'))->toBe('종로구')
        ->and(ChineseTextNormalizer::location('光华门'))->toBe('광화문')
        ->and(ChineseTextNormalizer::location('广津区'))->toBe('광진구')
        ->and(ChineseTextNormalizer::location('江东区'))->toBe('강동구')
        ->and(ChineseTextNormalizer::location('仁川T2'))->toBe('인천공항 제2터미널')
        ->and(ChineseTextNormalizer::vehicleType('九卡'))->toBe('카니발 9인승')
        ->and(ChineseTextNormalizer::vehicleType('利亚9'))->toBe('스타리아 9인승')
        ->and(ChineseTextNormalizer::vehicleType('大车'))->toBe('대형차');
});

test('마켓 카드에서 미정으로 보이던 지명 표기도 바꾼다', function () {
    expect(ChineseTextNormalizer::location('松坡区'))->toBe('송파구')
        ->and(ChineseTextNormalizer::location('城东区'))->toBe('성동구')
        ->and(ChineseTextNormalizer::location('城东'))->toBe('성동구')
        ->and(ChineseTextNormalizer::location('江西'))->toBe('강서구')
        ->and(ChineseTextNormalizer::location('西大门区'))->toBe('서대문구')
        ->and(ChineseTextNormalizer::location('光明市'))->toBe('광명시')
        ->and(ChineseTextNormalizer::location('中區'))->toBe('중구')
        ->and(ChineseTextNormalizer::location('南大门'))->toBe('남대문')
        ->and(ChineseTextNormalizer::location('世宗大'))->toBe('세종대')
        ->and(ChineseTextNormalizer::location('明洞乐天城市'))->toBe('명동 롯데시티')
        ->and(ChineseTextNormalizer::location('仁川2'))->toBe('인천공항 제2터미널')
        ->and(ChineseTextNormalizer::location('仁川t2'))->toBe('인천공항 제2터미널');
});

test('운영 지시 표기는 태그로 뽑아낸다', function () {
    expect(ChineseTextNormalizer::tagsFor(['帮划客路']))->toBe(['클록스텝진행'])
        ->and(ChineseTextNormalizer::isTagTerm('帮划客路'))->toBeTrue()
        ->and(ChineseTextNormalizer::tagsFor(['飞机马上降落']))->toBe(['지금 착륙'])
        ->and(ChineseTextNormalizer::tagsFor(['客人出来了']))->toBe(['고객 나옴'])
        ->and(ChineseTextNormalizer::isTagTerm('客人出来了'))->toBeTrue()
        ->and(ChineseTextNormalizer::tagDictionary()['帮划客路'])->toBe('클록스텝진행')
        ->and(ChineseTextNormalizer::tagsFor(['명동']))->toBe([]);
});

test('고객·결제 성격 표기도 태그로 뽑아낸다', function () {
    expect(ChineseTextNormalizer::tagsFor(['老外 划网页']))->toBe(['외국인 고객'])
        ->and(ChineseTextNormalizer::tagsFor(['6🌾跑完秒结']))->toBe(['바로결제'])
        ->and(ChineseTextNormalizer::tagsFor(['秒結']))->toBe(['바로결제'])
        // 지명은 그대로 지명으로 남는다
        ->and(ChineseTextNormalizer::tagsFor(['明洞']))->toBe([]);
});

test('호텔·렌트카 조건도 태그로 뽑아낸다', function () {
    expect(ChineseTextNormalizer::tagsFor(['酒店']))->toBe(['호텔'])
        ->and(ChineseTextNormalizer::tagsFor(['明洞 卡 需要 租赁车 6.5']))->toBe(['렌트카만 가능'])
        ->and(ChineseTextNormalizer::tagsFor(['租賃車']))->toBe(['렌트카만 가능'])
        ->and(ChineseTextNormalizer::isTagTerm('租赁车'))->toBeTrue();
});

test('짐 없음 조건도 태그로 뽑아낸다', function () {
    expect(ChineseTextNormalizer::tagsFor(['15米 8人 没行李']))->toBe(['짐 없음'])
        ->and(ChineseTextNormalizer::tagsFor(['沒行李']))->toBe(['짐 없음'])
        ->and(ChineseTextNormalizer::isTagTerm('没行李'))->toBeTrue();
});

test('곧 손이 필요한 표기는 긴급 태그로 본다', function () {
    expect(ChineseTextNormalizer::isUrgentTag('지금 착륙'))->toBeTrue()
        ->and(ChineseTextNormalizer::isUrgentTag('고객 나옴'))->toBeTrue()
        ->and(ChineseTextNormalizer::isUrgentTag('호텔'))->toBeFalse()
        ->and(ChineseTextNormalizer::tagsFor(['飞机马上降落']))->toBe(['지금 착륙']);
});

test('잔여 유입의 지명·차량 표기도 바꾼다', function () {
    expect(ChineseTextNormalizer::location('冠岳'))->toBe('관악구')
        ->and(ChineseTextNormalizer::location('舍堂洞'))->toBe('사당동')
        // 뒤에 붙은 판매·방향 표시(收)를 떼고 지명만 남긴다
        ->and(ChineseTextNormalizer::location('江南收'))->toBe('강남구')
        ->and(ChineseTextNormalizer::location('弘大收'))->toBe('홍대')
        ->and(ChineseTextNormalizer::vehicleType('埃尔法'))->toBe('알파드')
        // 인스파이어 축약과 대시로 이은 두 지점
        ->and(ChineseTextNormalizer::location('ins'))->toBe('인스파이어')
        ->and(ChineseTextNormalizer::location('东大门—ins'))->toBe('동대문구—인스파이어')
        // 金铺는 金浦(김포) 오기, 世运은 명동의 업체·거점명
        ->and(ChineseTextNormalizer::location('金铺'))->toBe('김포')
        ->and(ChineseTextNormalizer::location('明洞世运'))->toBe('명동 세운');
});

test('금연 차량 조건도 태그로 뽑아낸다', function () {
    expect(ChineseTextNormalizer::tagsFor(['Voco.无烟利亚']))->toBe(['금연 차량'])
        ->and(ChineseTextNormalizer::tagsFor(['無煙']))->toBe(['금연 차량'])
        ->and(ChineseTextNormalizer::isTagTerm('无烟'))->toBeTrue();
});
