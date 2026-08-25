<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MatchPreference;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 기사 자동 매칭 설정 CRUD — 기사 본인만 관리한다.
 */
class MatchController extends Controller
{
    /**
     * 내 매칭 설정 목록.
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function index(Request $request): JsonResponse
    {
        $preferences = $request->user()->matchPreferences()->orderByDesc('id')->get();

        return response()->json([
            'data' => $preferences->map(fn (MatchPreference $preference) => $this->payload($preference)),
        ]);
    }

    /**
     * 매칭 설정 등록.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $preference = $request->user()->matchPreferences()->create([...$data, 'user_id' => $request->user()->id]);

        return response()->json(['data' => $this->payload($preference)], 201);
    }

    /**
     * 매칭 설정 수정.
     */
    public function update(Request $request, MatchPreference $preference): JsonResponse
    {
        $this->authorizeOwnership($request->user(), $preference);

        $preference->forceFill($this->validated($request))->save();

        return response()->json(['data' => $this->payload($preference)]);
    }

    /**
     * 매칭 설정 삭제.
     */
    public function destroy(Request $request, MatchPreference $preference): JsonResponse
    {
        $this->authorizeOwnership($request->user(), $preference);
        $preference->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'date_range' => ['nullable', Rule::in(['today', 'tomorrow', 'today_tomorrow'])],
            'days' => ['nullable', 'array'],
            'days.*' => ['integer', 'min:1', 'max:7'],
            'area' => ['nullable', 'string', 'max:100'],
            'max_passengers' => ['nullable', 'integer', 'min:1', 'max:99'],
            'min_revenue' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function authorizeOwnership(User $user, MatchPreference $preference): void
    {
        abort_unless($preference->user_id === $user->id, 403, '본인 매칭 설정만 관리할 수 있습니다.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(MatchPreference $preference): array
    {
        return [
            'id' => $preference->id,
            'name' => $preference->name,
            'start_time' => $preference->start_time,
            'end_time' => $preference->end_time,
            'date_range' => $preference->date_range,
            'days' => $preference->days ?? [],
            'area' => $preference->area,
            'max_passengers' => $preference->max_passengers,
            'min_revenue' => (int) $preference->min_revenue,
            'is_active' => (bool) $preference->is_active,
            'created_at' => $preference->created_at?->toDateString(),
        ];
    }
}
