<?php

declare(strict_types=1);

namespace app\models;

use versaWYS\kernel\model\VersaORMModel;

/**
 * Tableros kanban y lo que cuelga de ellos (tareas, etiquetas y notas).
 *
 * Toda lectura o escritura pasa por la empresa: un tablero, tarea, etiqueta o nota
 * de otra empresa se trata como inexistente.
 */
class Tableros extends VersaORMModel
{
    public const array COLUMNAS = ['todo', 'doing', 'done'];

    public const array PRIORIDADES = ['baja', 'media', 'alta'];

    protected string $table = 'tableros';

    // ---------- Tableros ----------

    public function listar(int $idEmpresa): array
    {
        return $this->getAll(
            "SELECT t.id, t.nombre, t.descripcion, t.color, t.created_at,
                    COUNT(ta.id) AS total,
                    COALESCE(SUM(CASE WHEN ta.columna = 'done' THEN 1 ELSE 0 END), 0) AS hechas,
                    COALESCE(SUM(CASE WHEN ta.columna <> 'done' AND ta.fecha_vence < CURRENT_DATE THEN 1 ELSE 0 END), 0) AS vencidas
               FROM tableros t
               LEFT JOIN tareas ta ON ta.id_tablero = t.id
              WHERE t.id_empresa = ? AND t.estado = ?
              GROUP BY t.id, t.nombre, t.descripcion, t.color, t.created_at
              ORDER BY t.created_at DESC",
            [$idEmpresa, true],
        );
    }

    public function buscar(int $id, int $idEmpresa): ?array
    {
        $tablero = $this->qb('tableros')
            ->select(['id', 'nombre', 'descripcion', 'color'])
            ->where('id', '=', $id)
            ->where('id_empresa', '=', $idEmpresa)
            ->where('estado', '=', true)
            ->firstArray();

        return $tablero ?: null;
    }

    public function guardar(int $idEmpresa, array $datos, ?int $id = null): int
    {
        $fila = [
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
            'color' => $datos['color'] ?? '#2563eb',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id !== null) {
            $this->qb('tableros')->where('id', '=', $id)->where('id_empresa', '=', $idEmpresa)->update($fila);

            return $id;
        }

        return (int) $this->qb('tableros')->insertGetId($fila + [
            'id_empresa' => $idEmpresa,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function eliminar(int $id, int $idEmpresa): void
    {
        $this->qb('tableros')->where('id', '=', $id)->where('id_empresa', '=', $idEmpresa)->delete();
    }

    /** Usuarios activos de la empresa: los que se pueden asignar como responsables. */
    public function miembros(int $idEmpresa): array
    {
        return $this->qb('versa_users')
            ->select(['id', 'name', 'email'])
            ->where('id_empresa', '=', $idEmpresa)
            ->where('status', '=', true)
            ->orderBy('name', 'asc')
            ->getAll();
    }

    // ---------- Tareas ----------

    public function tareas(int $idTablero): array
    {
        $tareas = $this->getAll(
            'SELECT ta.id, ta.titulo, ta.descripcion, ta.columna, ta.prioridad, ta.fecha_vence, ta.posicion,
                    ta.id_responsable, u.name AS responsable,
                    (SELECT COUNT(*) FROM notas n WHERE n.id_tarea = ta.id) AS notas
               FROM tareas ta
               LEFT JOIN versa_users u ON u.id = ta.id_responsable
              WHERE ta.id_tablero = ?
              ORDER BY ta.posicion ASC, ta.id ASC',
            [$idTablero],
        );

        $etiquetas = [];
        foreach ($this->getAll(
            'SELECT te.id_tarea, te.id_etiqueta FROM tarea_etiqueta te JOIN tareas ta ON ta.id = te.id_tarea WHERE ta.id_tablero = ?',
            [$idTablero],
        ) as $fila) {
            $etiquetas[$fila['id_tarea']][] = (int) $fila['id_etiqueta'];
        }

        foreach ($tareas as &$tarea) {
            $tarea['etiquetas'] = $etiquetas[$tarea['id']] ?? [];
        }

        return $tareas;
    }

    /** La tarea, solo si su tablero es de la empresa. */
    public function tarea(int $idTarea, int $idEmpresa): ?array
    {
        $tarea = $this->getRow(
            'SELECT ta.id, ta.id_tablero, ta.columna FROM tareas ta
               JOIN tableros t ON t.id = ta.id_tablero
              WHERE ta.id = ? AND t.id_empresa = ? AND t.estado = ?',
            [$idTarea, $idEmpresa, true],
        );

        return $tarea ?: null;
    }

    public function guardarTarea(int $idTablero, array $datos, ?int $id = null): int
    {
        $fila = [
            'titulo' => $datos['titulo'],
            'descripcion' => $datos['descripcion'] ?? null,
            'prioridad' => $datos['prioridad'] ?? 'media',
            'fecha_vence' => ($datos['fecha_vence'] ?? '') !== '' ? $datos['fecha_vence'] : null,
            'id_responsable' => ($datos['id_responsable'] ?? '') !== '' ? (int) $datos['id_responsable'] : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id !== null) {
            $this->qb('tareas')->where('id', '=', $id)->where('id_tablero', '=', $idTablero)->update($fila);
        } else {
            $columna = $datos['columna'] ?? 'todo';
            $siguiente = (int) $this->getCell(
                'SELECT COALESCE(MAX(posicion), -1) + 1 FROM tareas WHERE id_tablero = ? AND columna = ?',
                [$idTablero, $columna],
            );
            $id = (int) $this->qb('tareas')->insertGetId($fila + [
                'id_tablero' => $idTablero,
                'columna' => $columna,
                'posicion' => $siguiente,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if (isset($datos['etiquetas'])) {
            $this->asignarEtiquetas($id, $idTablero, $datos['etiquetas']);
        }

        return $id;
    }

    /**
     * Deja la tarea en $columna y reordena esa columna según $orden (ids de tareas).
     * Los ids que no son del tablero se ignoran.
     *
     * @param array<int|string> $orden viene del cliente: puede no ser una lista
     */
    public function moverTarea(int $idTarea, int $idTablero, string $columna, array $orden): void
    {
        $orm = self::orm();
        $orm->beginTransaction();

        try {
            $this->qb('tareas')
                ->where('id', '=', $idTarea)
                ->update(['columna' => $columna, 'updated_at' => date('Y-m-d H:i:s')]);

            foreach (array_values($orden) as $posicion => $id) {
                $this->qb('tareas')
                    ->where('id', '=', (int) $id)
                    ->where('id_tablero', '=', $idTablero)
                    ->where('columna', '=', $columna)
                    ->update(['posicion' => $posicion]);
            }

            $orm->commit();
        } catch (\Throwable $throwable) {
            $orm->rollBack();

            throw $throwable;
        }
    }

    public function eliminarTarea(int $idTarea): void
    {
        $this->qb('tareas')->where('id', '=', $idTarea)->delete();
    }

    /** @param list<int|string> $ids */
    private function asignarEtiquetas(int $idTarea, int $idTablero, array $ids): void
    {
        $this->qb('tarea_etiqueta')->where('id_tarea', '=', $idTarea)->delete();

        $ids = array_values(array_unique(array_map(intval(...), $ids)));
        if ($ids === []) {
            return;
        }

        // Solo etiquetas del mismo tablero
        $validas = $this->qb('etiquetas')->select(['id'])->where('id_tablero', '=', $idTablero)->whereIn('id', $ids)->getAll();
        foreach ($validas as $etiqueta) {
            $this->qb('tarea_etiqueta')->insert(['id_tarea' => $idTarea, 'id_etiqueta' => (int) $etiqueta['id']]);
        }
    }

    // ---------- Etiquetas ----------

    public function etiquetas(int $idTablero): array
    {
        return $this->qb('etiquetas')
            ->select(['id', 'nombre', 'color'])
            ->where('id_tablero', '=', $idTablero)
            ->orderBy('nombre', 'asc')
            ->getAll();
    }

    public function crearEtiqueta(int $idTablero, string $nombre, string $color): int
    {
        return (int) $this->qb('etiquetas')->insertGetId([
            'id_tablero' => $idTablero,
            'nombre' => $nombre,
            'color' => $color,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function existeEtiqueta(int $idTablero, string $nombre): bool
    {
        return $this->qb('etiquetas')->where('id_tablero', '=', $idTablero)->where('nombre', '=', $nombre)->exists();
    }

    public function eliminarEtiqueta(int $idEtiqueta, int $idEmpresa): bool
    {
        $etiqueta = $this->getRow(
            'SELECT e.id FROM etiquetas e JOIN tableros t ON t.id = e.id_tablero WHERE e.id = ? AND t.id_empresa = ?',
            [$idEtiqueta, $idEmpresa],
        );
        if (!$etiqueta) {
            return false;
        }

        $this->qb('etiquetas')->where('id', '=', $idEtiqueta)->delete();

        return true;
    }

    // ---------- Notas ----------

    public function notas(int $idTarea): array
    {
        return $this->getAll(
            'SELECT n.id, n.contenido, n.created_at, n.id_usuario, u.name AS autor
               FROM notas n JOIN versa_users u ON u.id = n.id_usuario
              WHERE n.id_tarea = ?
              ORDER BY n.created_at ASC, n.id ASC',
            [$idTarea],
        );
    }

    public function crearNota(int $idTarea, int $idUsuario, string $contenido): int
    {
        return (int) $this->qb('notas')->insertGetId([
            'id_tarea' => $idTarea,
            'id_usuario' => $idUsuario,
            'contenido' => $contenido,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Cada usuario borra solo sus propias notas. */
    public function eliminarNota(int $idNota, int $idUsuario): bool
    {
        if (!$this->qb('notas')->where('id', '=', $idNota)->where('id_usuario', '=', $idUsuario)->exists()) {
            return false;
        }

        $this->qb('notas')->where('id', '=', $idNota)->delete();

        return true;
    }
}
