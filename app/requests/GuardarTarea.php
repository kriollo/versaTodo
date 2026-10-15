<?php

declare(strict_types=1);

namespace app\requests;

use app\models\Tableros;
use versaWYS\kernel\FormRequest;

final class GuardarTarea extends FormRequest
{
    public function rules(): array
    {
        return [
            'titulo' => 'required|max:200',
            'descripcion' => 'nullable|max:5000',
            'columna' => 'nullable|in:' . implode(',', Tableros::COLUMNAS),
            'prioridad' => 'nullable|in:' . implode(',', Tableros::PRIORIDADES),
            'fecha_vence' => 'nullable|date',
            'id_responsable' => 'nullable|int:positive',
            'etiquetas' => 'nullable|array',
            'etiquetas.*' => 'int:positive',
        ];
    }
}
