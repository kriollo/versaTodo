<?php

declare(strict_types=1);

namespace app\seeders;

use VersaORM\VersaORMException;
use versaWYS\kernel\model\VersaORMModel;

/**
 * Agrega el módulo Tableros al menú del panel. Se puede ejecutar más de una vez.
 */
class S202610_TablerosModuloSeeder extends VersaORMModel
{
    public function run(): array
    {
        try {
            if ($this->getCell('SELECT COUNT(*) FROM versa_menu WHERE url = ?', ['/tableros']) > 0) {
                return ['message' => 'El módulo Tableros ya está en el menú.', 'success' => true];
            }

            $menu = $this->dispense('versa_menu');
            $menu->seccion = 'TRABAJO';
            $menu->nombre = 'Tableros';
            $menu->descripcion = 'Tableros kanban con tareas, etiquetas y notas';
            $menu->icono = '<svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M4 5h4v14H4zM10 5h4v9h-4zM16 5h4v6h-4z"/></svg>';
            $menu->fill = false;
            $menu->url = '/tableros';
            $menu->submenu = false;
            $menu->posicion = 1;
            $menu->store();

            return ['message' => 'Módulo Tableros agregado al menú.', 'success' => true];
        } catch (VersaORMException $versaormException) {
            return ['message' => $versaormException->getMessage(), 'success' => false];
        }
    }
}
