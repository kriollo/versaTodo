<?php

declare(strict_types=1);

namespace app\middleware;

use app\models\Tableros;
use versaWYS\kernel\helpers\Functions;

class TablerosMiddleware
{
    public function validarTablero(): true|array
    {
        return $this->validar([
            'nombre' => 'required|max:120',
            'descripcion' => 'nullable|max:500',
            'color' => 'nullable|min:7|max:7',
        ]);
    }

    public function validarTarea(): true|array
    {
        return $this->validar([
            'titulo' => 'required|max:200',
            'descripcion' => 'nullable|max:5000',
            'columna' => 'nullable|in:' . implode(',', Tableros::COLUMNAS),
            'prioridad' => 'nullable|in:' . implode(',', Tableros::PRIORIDADES),
            'fecha_vence' => 'nullable|date',
            'id_responsable' => 'nullable|int:positive',
            'etiquetas' => 'nullable|array',
        ]);
    }

    public function validarMover(): true|array
    {
        return $this->validar([
            'columna' => 'required|in:' . implode(',', Tableros::COLUMNAS),
            'orden' => 'required|array',
        ]);
    }

    public function validarEtiqueta(): true|array
    {
        return $this->validar([
            'nombre' => 'required|max:40',
            'color' => 'nullable|min:7|max:7',
        ]);
    }

    public function validarNota(): true|array
    {
        return $this->validar(['contenido' => 'required|max:5000']);
    }

    private function validar(array $reglas): true|array
    {
        global $request;

        $errores = Functions::validateParams($request->getAllParams(), $reglas);
        if ($errores !== []) {
            return ['success' => 0, 'message' => 'Los datos enviados no son correctos', 'errors' => $errores, 'code' => 422];
        }

        return true;
    }
}
