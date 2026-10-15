<?php

declare(strict_types=1);

namespace app\requests;

use versaWYS\kernel\FormRequest;

final class GuardarEtiqueta extends FormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => 'required|max:40',
            'color' => 'nullable|min:7|max:7',
        ];
    }
}
