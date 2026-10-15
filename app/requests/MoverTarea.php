<?php

declare(strict_types=1);

namespace app\requests;

use app\models\Tableros;
use versaWYS\kernel\FormRequest;

final class MoverTarea extends FormRequest
{
    public function rules(): array
    {
        return [
            'columna' => 'required|in:' . implode(',', Tableros::COLUMNAS),
            'orden' => 'required|array',
        ];
    }
}
