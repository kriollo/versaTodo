<?php

declare(strict_types=1);

namespace app\requests;

use versaWYS\kernel\FormRequest;

final class GuardarNota extends FormRequest
{
    public function rules(): array
    {
        return [
            'contenido' => 'required|max:5000',
        ];
    }
}
