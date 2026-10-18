<?php

namespace App\Http\Requests;

use App\Services\Order\OrderCorrespondenceService;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:'.OrderCorrespondenceService::ALLOWED_MIMES, 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.max' => 'Сообщение слишком длинное.',
            'attachments.max' => 'Можно прикрепить не более 10 файлов.',
            'attachments.*.mimes' => 'Неподдерживаемый формат файла.',
            'attachments.*.max' => 'Файл не должен превышать 10 МБ.',
        ];
    }
}
