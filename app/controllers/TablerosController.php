<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Tableros;
use app\requests\GuardarEtiqueta;
use app\requests\GuardarNota;
use app\requests\GuardarTablero;
use app\requests\GuardarTarea;
use app\requests\MoverTarea;
use versaWYS\kernel\GlobalControllers;
use versaWYS\kernel\Tenant;

class TablerosController extends GlobalControllers
{
    private const array NO_ENCONTRADO = ['success' => 0, 'message' => 'No encontrado', 'code' => 404];

    private int $idEmpresa;

    // El contenedor crea el modelo; en una prueba se puede reemplazar por otro
    public function __construct(private readonly Tableros $modelo)
    {
        global $twig, $session;
        parent::__construct($twig, $session);
        $this->idEmpresa = Tenant::id();
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

    public function ver(int $id): array
    {
        $tablero = $this->modelo->buscar($id, $this->idEmpresa);
        if ($tablero === null) {
            return self::NO_ENCONTRADO;
        }

        return [
            'success' => 1,
            'tablero' => $tablero,
            'tareas' => $this->modelo->tareas($id),
            'etiquetas' => $this->modelo->etiquetas($id),
            'miembros' => $this->modelo->miembros($this->idEmpresa),
        ];
    }

    // Los datos llegan validados: si no, la FormRequest ya respondió 422
    public function crear(GuardarTablero $datos): array
    {
        $id = $this->modelo->guardar($this->idEmpresa, $datos->validated());

        return ['success' => 1, 'message' => 'Tablero creado', 'id' => $id, 'code' => 201];
    }

    public function editar(GuardarTablero $datos, int $id): array
    {
        if ($this->modelo->buscar($id, $this->idEmpresa) === null) {
            return self::NO_ENCONTRADO;
        }

        $this->modelo->guardar($this->idEmpresa, $datos->validated(), $id);

        return ['success' => 1, 'message' => 'Tablero actualizado'];
    }

    public function eliminar(int $id): array
    {
        if ($this->modelo->buscar($id, $this->idEmpresa) === null) {
            return self::NO_ENCONTRADO;
        }

        $this->modelo->eliminar($id, $this->idEmpresa);

        return ['success' => 1, 'message' => 'Tablero eliminado'];
    }

    // ---------- Tareas ----------

    public function crearTarea(GuardarTarea $peticion, int $id): array
    {
        if ($this->modelo->buscar($id, $this->idEmpresa) === null) {
            return self::NO_ENCONTRADO;
        }

        $datos = $peticion->validated();
        if (!$this->esMiembro($datos['id_responsable'] ?? null)) {
            return $this->responsableInvalido();
        }

        $idTarea = $this->modelo->guardarTarea($id, $datos);

        return ['success' => 1, 'message' => 'Tarea creada', 'id' => $idTarea, 'code' => 201];
    }

    public function editarTarea(GuardarTarea $peticion, int $id): array
    {
        $tarea = $this->modelo->tarea($id, $this->idEmpresa);
        if ($tarea === null) {
            return self::NO_ENCONTRADO;
        }

        $datos = $peticion->validated();
        if (!$this->esMiembro($datos['id_responsable'] ?? null)) {
            return $this->responsableInvalido();
        }

        $this->modelo->guardarTarea((int) $tarea['id_tablero'], $datos, $id);

        return ['success' => 1, 'message' => 'Tarea actualizada'];
    }

    public function moverTarea(MoverTarea $datos, int $id): array
    {

        $tarea = $this->modelo->tarea($id, $this->idEmpresa);
        if ($tarea === null) {
            return self::NO_ENCONTRADO;
        }

        $orden = $datos->input('orden');
        $orden = is_string($orden) ? json_decode($orden, true) : $orden;

        $this->modelo->moverTarea($id, (int) $tarea['id_tablero'], (string) $datos->input('columna'), (array) $orden);

        return ['success' => 1];
    }

    public function eliminarTarea(int $id): array
    {
        if ($this->modelo->tarea($id, $this->idEmpresa) === null) {
            return self::NO_ENCONTRADO;
        }

        $this->modelo->eliminarTarea($id);

        return ['success' => 1, 'message' => 'Tarea eliminada'];
    }

    // ---------- Etiquetas ----------

    public function crearEtiqueta(GuardarEtiqueta $datos, int $id): array
    {

        if ($this->modelo->buscar($id, $this->idEmpresa) === null) {
            return self::NO_ENCONTRADO;
        }

        $nombre = trim((string) $datos->input('nombre'));
        if ($this->modelo->existeEtiqueta($id, $nombre)) {
            return ['success' => 0, 'message' => 'Ya existe una etiqueta con ese nombre', 'errors' => ['nombre' => 'Repetida'], 'code' => 422];
        }

        $idEtiqueta = $this->modelo->crearEtiqueta($id, $nombre, (string) ($datos->input('color') ?: '#64748b'));

        return ['success' => 1, 'message' => 'Etiqueta creada', 'id' => $idEtiqueta, 'code' => 201];
    }

    public function eliminarEtiqueta(int $id): array
    {
        if (!$this->modelo->eliminarEtiqueta($id, $this->idEmpresa)) {
            return self::NO_ENCONTRADO;
        }

        return ['success' => 1, 'message' => 'Etiqueta eliminada'];
    }

    // ---------- Notas ----------

    public function notas(int $id): array
    {
        if ($this->modelo->tarea($id, $this->idEmpresa) === null) {
            return self::NO_ENCONTRADO;
        }

        return ['success' => 1, 'data' => $this->modelo->notas($id)];
    }

    public function crearNota(GuardarNota $datos, int $id): array
    {

        if ($this->modelo->tarea($id, $this->idEmpresa) === null) {
            return self::NO_ENCONTRADO;
        }

        $idNota = $this->modelo->crearNota($id, $this->id_user, trim((string) $datos->input('contenido')));

        return ['success' => 1, 'message' => 'Nota agregada', 'id' => $idNota, 'code' => 201];
    }

    public function eliminarNota(int $id): array
    {
        if (!$this->modelo->eliminarNota($id, $this->id_user)) {
            return self::NO_ENCONTRADO;
        }

        return ['success' => 1, 'message' => 'Nota eliminada'];
    }

    private function esMiembro(mixed $idUsuario): bool
    {
        if ($idUsuario === null || $idUsuario === '') {
            return true;
        }

        return in_array($idUsuario, array_map(intval(...), array_column($this->modelo->miembros($this->idEmpresa), 'id')), true);
    }

    private function responsableInvalido(): array
    {
        return ['success' => 0, 'message' => 'El responsable no es de la empresa', 'errors' => ['id_responsable' => 'No válido'], 'code' => 422];
    }
}
