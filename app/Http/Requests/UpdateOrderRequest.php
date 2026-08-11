<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(Order::statusOptions()))],
            'group_type' => ['nullable', 'string', 'max:10'],
            'line_items' => ['sometimes', 'array'],
            'line_items.*.scheduled_time' => ['nullable', 'string', 'max:10'],
            'line_items.*.service_date' => ['nullable', 'string', 'max:30'],
            'line_items.*.service_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'line_items.*.service_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'line_items.*.service_weekday' => ['nullable', 'string', 'max:10'],
            'line_items.*.service_type' => ['nullable', 'string', 'max:50'],
            'line_items.*.location' => ['nullable', 'string', 'max:150'],
            'line_items.*.pickup_location' => ['nullable', 'string', 'max:150'],
            'line_items.*.dropoff_location' => ['nullable', 'string', 'max:150'],
            'line_items.*.flight_number' => ['nullable', 'string', 'max:50'],
            'line_items.*.passenger_count' => ['nullable', 'integer', 'min:1', 'max:99'],
            'line_items.*.luggage_count' => ['nullable', 'integer', 'min:0', 'max:99'],
            'line_items.*.amount_value' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'line_items.*.amount_text' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => '예약 상태',
            'line_items' => '일정 목록',
            'line_items.*.pickup_location' => '출발지',
            'line_items.*.dropoff_location' => '도착지',
            'line_items.*.flight_number' => '항공편',
            'line_items.*.scheduled_time' => '픽업 시간',
            'line_items.*.passenger_count' => '인원 수',
            'line_items.*.amount_value' => '금액',
        ];
    }
}
