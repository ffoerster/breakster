<?php

declare(strict_types = 1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAmenGuessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'artist' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
        ];
    }
}
