<?php

declare(strict_types=1);

namespace app\routes;

use app\controllers\TablerosController;
use app\middleware\AuthMiddleware;
use versaWYS\kernel\Router;

// description: Tableros kanban de la empresa seleccionada.
Router::get('/admin/tableros', [TablerosController::class, 'index'])->middleware([
    [AuthMiddleware::class, 'checkSession'],
    [AuthMiddleware::class, 'onlyUserWithAccess'],
]);

// Las API validan la sesión; que cada tablero, tarea, etiqueta o nota sea de la empresa lo resuelve el controlador.
Router::group(
    '/admin/tableros/api',
    function (): void {
        // description: Tableros de la empresa con el total de tareas, las hechas y las vencidas.
        Router::get('/tableros', [TablerosController::class, 'listar']);

        // description: Un tablero con sus tareas, etiquetas y los usuarios que se pueden asignar.
        // path-param name:id type:int description:ID del tablero
        Router::get('/tableros/{id}', [TablerosController::class, 'ver']);

        // description: Crea un tablero.
        // request-body name:nombre type:string description:Nombre (máx. 120)
        // request-body name:descripcion type:string description:Descripción opcional
        // request-body name:color type:string description:Color #rrggbb
        Router::post('/tableros', [TablerosController::class, 'crear']);

        // description: Edita un tablero.
        // path-param name:id type:int description:ID del tablero
        Router::put('/tableros/{id}', [TablerosController::class, 'editar']);

        // description: Elimina un tablero con todas sus tareas.
        // path-param name:id type:int description:ID del tablero
        Router::delete('/tableros/{id}', [TablerosController::class, 'eliminar']);

        // description: Crea una tarea en el tablero.
        // request-body name:titulo type:string description:Título (máx. 200)
        // request-body name:columna type:string description:todo, doing o done
        // request-body name:prioridad type:string description:baja, media o alta
        // request-body name:fecha_vence type:string description:Fecha AAAA-MM-DD opcional
        // request-body name:id_responsable type:int description:Usuario de la empresa
        // request-body name:etiquetas type:array description:IDs de etiquetas del tablero
        // path-param name:id type:int description:ID del tablero
        Router::post('/tableros/{id}/tareas', [TablerosController::class, 'crearTarea']);

        // description: Edita una tarea.
        // path-param name:id type:int description:ID de la tarea
        Router::put('/tareas/{id}', [TablerosController::class, 'editarTarea']);

        // description: Mueve una tarea a una columna y reordena esa columna.
        // request-body name:columna type:string description:todo, doing o done
        // request-body name:orden type:array description:IDs de las tareas de la columna, en orden
        // path-param name:id type:int description:ID de la tarea
        Router::patch('/tareas/{id}/mover', [TablerosController::class, 'moverTarea']);

        // description: Elimina una tarea.
        // path-param name:id type:int description:ID de la tarea
        Router::delete('/tareas/{id}', [TablerosController::class, 'eliminarTarea']);

        // description: Crea una etiqueta en el tablero.
        // path-param name:id type:int description:ID del tablero
        Router::post('/tableros/{id}/etiquetas', [TablerosController::class, 'crearEtiqueta']);

        // description: Elimina una etiqueta (se quita de las tareas que la tenían).
        // path-param name:id type:int description:ID de la etiqueta
        Router::delete('/etiquetas/{id}', [TablerosController::class, 'eliminarEtiqueta']);

        // description: Notas de una tarea, de la más antigua a la más nueva.
        // path-param name:id type:int description:ID de la tarea
        Router::get('/tareas/{id}/notas', [TablerosController::class, 'notas']);

        // description: Agrega una nota a la tarea.
        // path-param name:id type:int description:ID de la tarea
        Router::post('/tareas/{id}/notas', [TablerosController::class, 'crearNota']);

        // description: Elimina una nota propia.
        // path-param name:id type:int description:ID de la nota
        Router::delete('/notas/{id}', [TablerosController::class, 'eliminarNota']);
    },
    [
        [AuthMiddleware::class, 'checkSession'],
    ],
);
