<?php

namespace App\Http\Requests\Bi;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RecalcularVentasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'desde' => ['required', 'date_format:Y-m-d', 'before:today'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde', 'before_or_equal:today'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $desde = CarbonImmutable::parse((string) $this->input('desde'));
                $hasta = CarbonImmutable::parse((string) $this->input('hasta'))->min(CarbonImmutable::yesterday());

                if ($desde->diffInDays($hasta) > 30) {
                    $validator->errors()->add('hasta', 'Recalcula un máximo de 31 días por vez.');
                }
            },
        ];
    }
}
