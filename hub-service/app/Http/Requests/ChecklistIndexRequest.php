<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class ChecklistIndexRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $countries = implode(',', array_keys(config('checklists', [])));

        return [
            'country' => ['required', 'string', 'in:'.$countries],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('country')) {
            $this->merge([
                'country' => strtolower((string) $this->input('country')),
            ]);
        }
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $response = new JsonResponse([
            'status' => 'error',
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY);

        throw new ValidationException($validator, $response);
    }
}
