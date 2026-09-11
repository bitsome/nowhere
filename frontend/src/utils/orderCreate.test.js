import { describe, expect, it } from 'vitest';
import { buildSplitOrders, toIsoDate } from './orderCreate';

/**
 * 위챗 문구 한 건에 운행이 여러 개 섞여 있으면 각각 독립 운행 초안으로 나눈다.
 * 셋트로 묶으면 추천·매칭이 한 묶음으로 다뤄져 서로 무관한 운행끼리 엮이므로, 나눠 등록이 기본이다.
 */
describe('buildSplitOrders — 구조화 결과를 등록 초안으로 나눈다', () => {
    it('일정이 없으면 최상위 값으로 1건을 만든다', () => {
        const drafts = buildSplitOrders({
            service_date: '8월10일',
            service_time: '09:00',
            service_type: '픽업',
            vehicle_type: '카니발',
            pickup_location: '서울 강남구',
            dropoff_location: '강릉 정동진',
            passenger_count: 3,
            amount_value: 250000,
        });

        expect(drafts).toHaveLength(1);
        expect(drafts[0]).toMatchObject({
            service_time: '09:00',
            // AI/규칙 파서가 주는 한글 라벨을 저장용 코드로 바꾼다
            service_type: 'pickup',
            vehicle_type: '카니발',
            pickup_location: '서울 강남구',
            dropoff_location: '강릉 정동진',
            passenger_count: 3,
            expected_revenue: 250000,
        });
        expect(drafts[0].service_date).toBe(toIsoDate('8월10일'));
    });

    it('일정이 여러 건이면 각각 독립 초안이 된다', () => {
        const drafts = buildSplitOrders({
            vehicle_type: '카니발',
            line_items: [
                {
                    service_date: '8월10일',
                    scheduled_time: '09:00',
                    pickup_location: '마포구',
                    passenger_count: 1,
                    amount_value: 80000,
                },
                {
                    service_date: '8월10일',
                    scheduled_time: '13:00',
                    pickup_location: '명동',
                    passenger_count: 4,
                    amount_value: 120000,
                },
            ],
        });

        expect(drafts).toHaveLength(2);
        // 항목에 없는 값(차량)은 문구 전체 값에서 물려받는다
        expect(drafts[0]).toMatchObject({
            vehicle_type: '카니발',
            pickup_location: '마포구',
            passenger_count: 1,
            expected_revenue: 80000,
        });
        expect(drafts[1]).toMatchObject({
            vehicle_type: '카니발',
            pickup_location: '명동',
            passenger_count: 4,
            expected_revenue: 120000,
        });
    });

    it('해석되지 않은 값은 빈 값으로 두어 화면에서 비어 있게 보인다', () => {
        const drafts = buildSplitOrders({ line_items: [{ scheduled_time: '07:30' }] });

        expect(drafts).toHaveLength(1);
        expect(drafts[0].passenger_count).toBeNull();
        expect(drafts[0].luggage_count).toBeNull();
        expect(drafts[0].expected_revenue).toBeNull();
        expect(drafts[0].pickup_location).toBe('');
        expect(drafts[0].dropoff_location).toBe('');
    });

    it('일정이 없는 빈 구조화 결과도 1건 초안을 만든다', () => {
        expect(buildSplitOrders(null)).toHaveLength(1);
        expect(buildSplitOrders({})).toHaveLength(1);
    });
});
