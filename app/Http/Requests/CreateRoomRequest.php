<?php

declare(strict_types = 1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pack_id' => ['required', 'integer', 'exists:packs,id'],
            'display_name' => ['required', 'string', 'min:2', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'pack_id.required' => 'Please choose a pack.',
            'pack_id.exists' => 'Please choose a valid pack.',
        ];
    }
}
