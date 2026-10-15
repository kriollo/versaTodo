<?php

declare(strict_types=1);

namespace app\requests;

use versaWYS\kernel\FormRequest;

final class GuardarTablero extends FormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => 'required|max:120',
            'descripcion' => 'nullable|max:500',
            'color' => 'nullable|min:7|max:7',
        ];
    }
}
