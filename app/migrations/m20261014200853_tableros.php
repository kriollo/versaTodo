<?php

declare(strict_types=1);

namespace app\migrations;

use VersaORM\Schema\Blueprint;
use VersaORM\VersaORMException;
use versaWYS\kernel\model\VersaORMModel;

class m20261014200853_tableros extends VersaORMModel
{
    public function up(): array
    {
        try {
            $schema = $this->orm()->schemaBuilder();

            $schema->create('tableros', static function (Blueprint $table): void {
                $table->id();
                $table->foreignId('id_empresa');
                $table->string('nombre', 120);
                $table->string('descripcion', 500)->nullable();
                $table->string('color', 7)->default('#2563eb');
                $table->boolean('estado')->default(true);
                $table->timestamps();

                $table->index(['id_empresa'], 'idx_tableros_empresa');
                $table->foreign(['id_empresa'])->references('id')->on('empresas')->onDelete('CASCADE');
            });

            $schema->create('tareas', static function (Blueprint $table): void {
                $table->id();
                $table->foreignId('id_tablero');
                $table->string('titulo', 200);
                $table->text('descripcion')->nullable();
                $table->string('columna', 10)->default('todo');
                $table->string('prioridad', 10)->default('media');
                $table->date('fecha_vence')->nullable();
                $table->integer('posicion')->default(0);
                $table->foreignId('id_responsable')->nullable();
                $table->timestamps();

                $table->index(['id_tablero', 'columna'], 'idx_tareas_tablero_columna');
                $table->foreign(['id_tablero'])->references('id')->on('tableros')->onDelete('CASCADE');
                $table->foreign(['id_responsable'])->references('id')->on('versa_users')->onDelete('SET NULL');
            });

            $schema->create('etiquetas', static function (Blueprint $table): void {
                $table->id();
                $table->foreignId('id_tablero');
                $table->string('nombre', 40);
                $table->string('color', 7)->default('#64748b');
                $table->timestamps();

                $table->unique(['id_tablero', 'nombre'], 'uq_etiquetas_tablero_nombre');
                $table->foreign(['id_tablero'])->references('id')->on('tableros')->onDelete('CASCADE');
            });

            $schema->create('tarea_etiqueta', static function (Blueprint $table): void {
                $table->foreignId('id_tarea');
                $table->foreignId('id_etiqueta');

                $table->primary(['id_tarea', 'id_etiqueta']);
                $table->foreign(['id_tarea'])->references('id')->on('tareas')->onDelete('CASCADE');
                $table->foreign(['id_etiqueta'])->references('id')->on('etiquetas')->onDelete('CASCADE');
            });

            $schema->create('notas', static function (Blueprint $table): void {
                $table->id();
                $table->foreignId('id_tarea');
                $table->foreignId('id_usuario');
                $table->text('contenido');
                $table->timestamps();

                $table->index(['id_tarea'], 'idx_notas_tarea');
                $table->foreign(['id_tarea'])->references('id')->on('tareas')->onDelete('CASCADE');
                $table->foreign(['id_usuario'])->references('id')->on('versa_users')->onDelete('CASCADE');
            });

            return ['message' => 'Migración ejecutada con éxito.', 'success' => true];
        } catch (VersaORMException $versaormException) {
            return ['message' => $versaormException->getMessage(), 'success' => false];
        }
    }

    public function down(): array
    {
        try {
            $schema = $this->orm()->schemaBuilder();
            foreach (['notas', 'tarea_etiqueta', 'etiquetas', 'tareas', 'tableros'] as $tabla) {
                $schema->dropIfExists($tabla);
            }

            return ['message' => 'Migración ejecutada con éxito.', 'success' => true];
        } catch (VersaORMException $versaormException) {
            return ['message' => $versaormException->getMessage(), 'success' => false];
        }
    }
}
