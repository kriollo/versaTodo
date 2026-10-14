<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Tableros;
use versaWYS\kernel\GlobalControllers;

class TablerosController extends GlobalControllers
{
    private const array NO_ENCONTRADO = ['success' => 0, 'message' => 'No encontrado', 'code' => 404];

    private Tableros $modelo;

    private int $idEmpresa;

    public function __construct()
    {
        global $twig, $session;
        parent::__construct($twig, $session);
        $this->modelo = new Tableros();
        $this->idEmpresa = (int) ($this->empresaSelected['id'] ?? 0);
    }

    public function index(): string
    {
        return $this->template->render('dashboard/loader', [
            'm' => 'dashboard/js/tableros/dashTableros',
        ]);
    }

    // ---------- Tableros ----------

    public function listar(): array
    {
        return ['success' => 1, 'data' => $this->modelo->listar($this->idEmpresa)];
    }

    public function ver(string $id): array
    {
        $tablero = $this->modelo->buscar((int) $id, $this->idEmpresa);
        if ($tablero === null) {
            return self::NO_ENCONTRADO;
        }

        return [
            'success' => 1,
            'tablero' => $tablero,
            'tareas' => $this->modelo->tareas((int) $id),
            'etiquetas' => $this->modelo->etiquetas((int) $id),
            'miembros' => $this->modelo->miembros($this->idEmpresa),
        ];
    }

    public function crear(): array
    {
        global $request;

        $id = $this->modelo->guardar($this->idEmpresa, $request->getAllParams());

        return ['success' => 1, 'message' => 'Tablero creado', 'id' => $id, 'code' => 201];
    }

    public function editar(string $id): array
    {
        global $request;

        if ($this->modelo->buscar((int) $id, $this->idEmpresa) === null) {
            return self::NO_ENCONTRADO;
        }

        $this->modelo->guardar($this->idEmpresa, $request->getAllParams(), (int) $id);

        return ['success' => 1, 'message' => 'Tablero actualizado'];
    }

    public function eliminar(string $id): array
    {
        if ($this->modelo->buscar((int) $id, $this->idEmpresa) === null) {
            return self::NO_ENCONTRADO;
        }

        $this->modelo->eliminar((int) $id, $this->idEmpresa);

        return ['success' => 1, 'message' => 'Tablero eliminado'];
    }

    // ---------- Tareas ----------

    public function crearTarea(string $id): array
    {
        global $request;

        if ($this->modelo->buscar((int) $id, $this->idEmpresa) === null) {
            return self::NO_ENCONTRADO;
        }

        $datos = $request->getAllParams();
        if (!$this->esMiembro($datos['id_responsable'] ?? null)) {
            return $this->responsableInvalido();
        }

        $idTarea = $this->modelo->guardarTarea((int) $id, $datos);

        return ['success' => 1, 'message' => 'Tarea creada', 'id' => $idTarea, 'code' => 201];
    }

    public function editarTarea(string $id): array
    {
        global $request;

        $tarea = $this->modelo->tarea((int) $id, $this->idEmpresa);
        if ($tarea === null) {
            return self::NO_ENCONTRADO;
        }

        $datos = $request->getAllParams();
        if (!$this->esMiembro($datos['id_responsable'] ?? null)) {
            return $this->responsableInvalido();
        }

        $this->modelo->guardarTarea((int) $tarea['id_tablero'], $datos, (int) $id);

        return ['success' => 1, 'message' => 'Tarea actualizada'];
    }

    public function moverTarea(string $id): array
    {
        global $request;

        $tarea = $this->modelo->tarea((int) $id, $this->idEmpresa);
        if ($tarea === null) {
            return self::NO_ENCONTRADO;
        }

        $orden = $request->get('orden');
        $orden = is_string($orden) ? json_decode($orden, true) : $orden;

        $this->modelo->moverTarea((int) $id, (int) $tarea['id_tablero'], (string) $request->get('columna'), (array) $orden);

        return ['success' => 1];
    }

    public function eliminarTarea(string $id): array
    {
        if ($this->modelo->tarea((int) $id, $this->idEmpresa) === null) {
            return self::NO_ENCONTRADO;
        }

        $this->modelo->eliminarTarea((int) $id);

        return ['success' => 1, 'message' => 'Tarea eliminada'];
    }

    // ---------- Etiquetas ----------

    public function crearEtiqueta(string $id): array
    {
        global $request;

        if ($this->modelo->buscar((int) $id, $this->idEmpresa) === null) {
            return self::NO_ENCONTRADO;
        }

        $nombre = trim((string) $request->get('nombre'));
        if ($this->modelo->existeEtiqueta((int) $id, $nombre)) {
            return ['success' => 0, 'message' => 'Ya existe una etiqueta con ese nombre', 'errors' => ['nombre' => 'Repetida'], 'code' => 422];
        }

        $idEtiqueta = $this->modelo->crearEtiqueta((int) $id, $nombre, (string) ($request->get('color') ?: '#64748b'));

        return ['success' => 1, 'message' => 'Etiqueta creada', 'id' => $idEtiqueta, 'code' => 201];
    }

    public function eliminarEtiqueta(string $id): array
    {
        if (!$this->modelo->eliminarEtiqueta((int) $id, $this->idEmpresa)) {
            return self::NO_ENCONTRADO;
        }

        return ['success' => 1, 'message' => 'Etiqueta eliminada'];
    }

    // ---------- Notas ----------

    public function notas(string $id): array
    {
        if ($this->modelo->tarea((int) $id, $this->idEmpresa) === null) {
            return self::NO_ENCONTRADO;
        }

        return ['success' => 1, 'data' => $this->modelo->notas((int) $id)];
    }

    public function crearNota(string $id): array
    {
        global $request;

        if ($this->modelo->tarea((int) $id, $this->idEmpresa) === null) {
            return self::NO_ENCONTRADO;
        }

        $idNota = $this->modelo->crearNota((int) $id, $this->id_user, trim((string) $request->get('contenido')));

        return ['success' => 1, 'message' => 'Nota agregada', 'id' => $idNota, 'code' => 201];
    }

    public function eliminarNota(string $id): array
    {
        if (!$this->modelo->eliminarNota((int) $id, $this->id_user)) {
            return self::NO_ENCONTRADO;
        }

        return ['success' => 1, 'message' => 'Nota eliminada'];
    }

    private function esMiembro(mixed $idUsuario): bool
    {
        if ($idUsuario === null || $idUsuario === '') {
            return true;
        }

        return in_array((int) $idUsuario, array_map(intval(...), array_column($this->modelo->miembros($this->idEmpresa), 'id')), true);
    }

    private function responsableInvalido(): array
    {
        return ['success' => 0, 'message' => 'El responsable no es de la empresa', 'errors' => ['id_responsable' => 'No válido'], 'code' => 422];
    }
}
