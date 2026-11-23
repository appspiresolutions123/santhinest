<?php

namespace App\Http\Requests\Admin;

use App\Models\Property;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyImagesRequest extends FormRequest
{
    /**
     * The largest photo an admin may upload, in kilobytes.
     */
    public const int MAX_KILOBYTES = 5120;

    /**
     * The most photos an admin may upload at once.
     */
    public const int MAX_FILES = 20;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Property $property */
        $property = $this->route('property');

        return [
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')->where('property_id', $property->id)],
            'images' => ['required', 'array', 'max:'.self::MAX_FILES],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KILOBYTES],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'images.*' => 'photo',
        ];
    }
}
