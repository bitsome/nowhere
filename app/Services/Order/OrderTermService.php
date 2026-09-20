<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\OrderTerm;
use App\Models\User;
use App\Support\Orders\ChineseTextNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 미매핑 용어 관리 — 사전에 없던 중국어 표기를 관리자가 한국어로 매핑한다.
 *
 * 매핑하면 캐시를 비워 저장 파이프라인에 즉시 반영하고, 이미 저장된 운행도 함께 바꾼다.
 */
class OrderTermService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, OrderTerm>
     */
    public function index(array $filters): LengthAwarePaginator
    {
        // 1회성 표기는 기본으로 숨긴다 — 반복되는 표기만 관리자 눈에 띄게 (min_occurrences=1이면 전체)
        $minOccurrences = (int) ($filters['min_occurrences'] ?? 2);

        return OrderTerm::query()
            ->with('mappedByUser:id,name')
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status),
            )
            ->when(
                $filters['field'] ?? null,
                fn ($query, $field) => $query->where('field', $field),
            )
            ->when(
                $filters['q'] ?? null,
                fn ($query, $keyword) => $query->where('term', 'like', '%'.$keyword.'%'),
            )
            ->when($minOccurrences > 1, fn ($query) => $query->where('occurrences', '>=', $minOccurrences))
            ->orderByDesc('occurrences')
            ->orderByDesc('last_seen_at')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * 용어를 직접 등록한다 — 아직 유입되지 않은 표기도 미리 사전에 넣어 둘 수 있다.
     *
     * 같은 분야·원문이 이미 있으면 매핑값을 갱신한다 (중복 행을 만들지 않는다).
     *
     * @param  array<string, mixed>  $data
     * @return array{term: OrderTerm, created: bool}
     */
    public function create(User $admin, array $data): array
    {
        $term = trim((string) $data['term']);

        $existing = OrderTerm::query()
            ->where('field', $data['field'])
            ->where('term', $term)
            ->first();

        if ($existing !== null) {
            return [
                'term' => $this->save($admin, $existing, [
                    'status' => OrderTerm::STATUS_MAPPED,
                    'mapped_to' => trim((string) $data['mapped_to']),
                ]),
                'created' => false,
            ];
        }

        $created = OrderTerm::query()->create([
            'field' => $data['field'],
            'term' => $term,
            'mapped_to' => trim((string) $data['mapped_to']),
            'status' => OrderTerm::STATUS_MAPPED,
            // 직접 등록한 용어는 아직 유입 전이므로 관측 횟수를 0으로 둔다
            'occurrences' => 0,
            'mapped_by' => $admin->id,
            'mapped_at' => now(),
        ]);

        // 사전이 바뀌었으니 캐시를 비운다 — 다음 유입부터 바로 반영되게
        ChineseTextNormalizer::forgetCache();
        $this->applyToExistingOrders($created);

        return ['term' => $created->fresh('mappedByUser'), 'created' => true];
    }

    /**
     * 매핑값을 저장한다 (매핑 완료 / 무시 / 미매핑 되돌리기).
     *
     * @param  array<string, mixed>  $data
     */
    public function save(User $admin, OrderTerm $term, array $data): OrderTerm
    {
        $mapped = $data['status'] === OrderTerm::STATUS_MAPPED;

        $term->update([
            'status' => $data['status'],
            'mapped_to' => $mapped ? $data['mapped_to'] : null,
            'mapped_by' => $mapped ? $admin->id : null,
            'mapped_at' => $mapped ? now() : null,
        ]);

        // 사전이 바뀌었으니 캐시를 비운다 — 다음 유입부터 바로 반영되게
        ChineseTextNormalizer::forgetCache();

        if ($mapped) {
            $this->applyToExistingOrders($term);
        }

        return $term->fresh('mappedByUser');
    }

    /**
     * 이미 저장된 운행·일정의 같은 표기도 함께 바꾼다 — 관리자가 매핑한 값이 과거 건에 남지 않도록.
     *
     * 태그 분야는 지명 값이 아니므로 값을 바꾸지 않고 운행 태그만 붙인다.
     */
    private function applyToExistingOrders(OrderTerm $term): void
    {
        $value = (string) $term->mapped_to;

        if ($value === '') {
            return;
        }

        if ($term->field === OrderTerm::FIELD_TAG) {
            $this->applyTagToExistingOrders($term->term, $value);

            return;
        }

        foreach ($this->columnsFor($term->field) as $column) {
            Order::query()->where($column, $term->term)->update([$column => $value]);
        }

        foreach ($this->lineItemColumnsFor($term->field) as $column) {
            OrderLineItem::query()->where($column, $term->term)->update([$column => $value]);
        }
    }

    /**
     * 태그로 등록된 표기가 들어 있는 이미 저장된 운행에 태그를 붙인다 — 지명 값은 건드리지 않는다.
     */
    private function applyTagToExistingOrders(string $term, string $tag): void
    {
        Order::query()
            ->where(function ($query) use ($term): void {
                $query->where('pickup_location', 'like', '%'.$term.'%')
                    ->orWhere('dropoff_location', 'like', '%'.$term.'%');
            })
            ->get()
            ->each(function (Order $order) use ($tag): void {
                // 태그는 저장·표시와 같은 기준으로 쓴다 — 유입 파서 내부 태그는 여기서도 걷어낸다
                $raw = array_values(array_filter(array_map('trim', (array) ($order->tags ?? []))));
                $tags = ChineseTextNormalizer::withoutInternalTags($raw);

                if (! in_array($tag, $tags, true)) {
                    $tags[] = $tag;
                }

                // 붙일 태그가 없어도 내부 태그가 남아 있으면 지운다 — 원본과 비교한다
                if ($tags !== $raw) {
                    $order->update(['tags' => $tags === [] ? null : $tags]);
                }
            });
    }

    /**
     * @return array<int, string>
     */
    private function columnsFor(string $field): array
    {
        return match ($field) {
            OrderTerm::FIELD_PICKUP => ['pickup_location'],
            OrderTerm::FIELD_DROPOFF => ['dropoff_location'],
            OrderTerm::FIELD_VEHICLE => ['vehicle_type'],
            OrderTerm::FIELD_MODEL => ['vehicle_type'],
            default => [],
        };
    }

    /**
     * 일정(order_line_items)에 있는 열만 — 차량은 일정에 없으므로 제외한다.
     *
     * @return array<int, string>
     */
    private function lineItemColumnsFor(string $field): array
    {
        return match ($field) {
            OrderTerm::FIELD_PICKUP => ['pickup_location'],
            OrderTerm::FIELD_DROPOFF => ['dropoff_location'],
            default => [],
        };
    }
}
