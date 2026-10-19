<?php

declare(strict_types=1);

use versaWYS\kernel\Testing\TestClient;
use versaWYS\kernel\Testing\TestDatabase;

/*
 * Módulo Tableros con TestClient: la API completa (rutas, middlewares, controlador y base de datos)
 * contra la SQLite desechable de TestDatabase, con las migraciones y seeders del proyecto.
 */

/** @return array{admin: string, ana: string, olga: string} los correos de cada actor */
function tablerosUsuarios(): array
{
    static $usuarios = null;

    return $usuarios ??= [
        'admin' => TestDatabase::user('superadmin'),
        'ana' => TestDatabase::user('user'),
        'olga' => TestDatabase::user('admin', TestDatabase::empresa('Otra SA')),
    ];
}

beforeAll(fn() => TestDatabase::boot());

/** @param 'admin'|'ana'|'olga' $quien */
function como(string $quien): TestClient
{
    return TestDatabase::login(tablerosUsuarios()[$quien]);
}

const API = '/admin/tableros/api';

describe('Tableros', function (): void {
    it('crea un tablero con tareas y etiquetas y lo devuelve completo', function (): void {
        $admin = como('admin');

        $tablero = $admin->postJson(API . '/tableros', ['nombre' => 'Lanzamiento'])->assertStatus(201)->json('id');
        $etiqueta = $admin->postJson(API . "/tableros/{$tablero}/etiquetas", ['nombre' => 'bug', 'color' => '#dc2626'])->assertStatus(201)->json('id');
        $admin->postJson(API . "/tableros/{$tablero}/tareas", ['titulo' => 'Diseñar login', 'prioridad' => 'alta', 'etiquetas' => [$etiqueta]])->assertStatus(201);

        $admin->getJson(API . "/tableros/{$tablero}")
            ->assertOk()
            ->assertJsonPath('tablero.nombre', 'Lanzamiento')
            ->assertJsonPath('tareas.0.titulo', 'Diseñar login')
            ->assertJsonPath('tareas.0.etiquetas', [$etiqueta])
            ->assertJsonPath('etiquetas.0.nombre', 'bug');
    });

    it('valida los datos: 422 con el error del campo, no 500', function (): void {
        $admin = como('admin');
        $tablero = $admin->postJson(API . '/tableros', ['nombre' => 'Validación'])->json('id');

        $admin->postJson(API . '/tableros', [])->assertStatus(422)->assertJsonPath('errors.nombre', 'El campo nombre es obligatorio');
        $admin->postJson(API . "/tableros/{$tablero}/tareas", ['titulo' => 'x', 'columna' => 'nope', 'prioridad' => 'urgente'])
            ->assertStatus(422);
        $admin->postJson(API . "/tableros/{$tablero}/etiquetas", ['nombre' => 'repetida'])->assertStatus(201);
        $admin->postJson(API . "/tableros/{$tablero}/etiquetas", ['nombre' => 'repetida'])->assertStatus(422);
    });

    it('otra empresa no ve ni modifica nada del tablero', function (): void {
        $admin = como('admin');
        $tablero = $admin->postJson(API . '/tableros', ['nombre' => 'Privado'])->json('id');
        $tarea = $admin->postJson(API . "/tableros/{$tablero}/tareas", ['titulo' => 'Secreta'])->json('id');
        $etiqueta = $admin->postJson(API . "/tableros/{$tablero}/etiquetas", ['nombre' => 'interna'])->json('id');
        $nota = $admin->postJson(API . "/tareas/{$tarea}/notas", ['contenido' => 'no mirar'])->json('id');

        $olga = como('olga');
        expect($olga->getJson(API . '/tableros')->json('data'))->toBe([]);
        $olga->getJson(API . "/tableros/{$tablero}")->assertStatus(404);
        $olga->putJson(API . "/tableros/{$tablero}", ['nombre' => 'hackeado'])->assertStatus(404);
        $olga->deleteJson(API . "/tableros/{$tablero}")->assertStatus(404);
        $olga->putJson(API . "/tareas/{$tarea}", ['titulo' => 'hackeada'])->assertStatus(404);
        $olga->patchJson(API . "/tareas/{$tarea}/mover", ['columna' => 'done', 'orden' => [$tarea]])->assertStatus(404);
        $olga->getJson(API . "/tareas/{$tarea}/notas")->assertStatus(404);
        $olga->deleteJson(API . "/notas/{$nota}")->assertStatus(404);
        $olga->deleteJson(API . "/etiquetas/{$etiqueta}")->assertStatus(404);

        // Y nada cambió
        $admin->getJson(API . "/tableros/{$tablero}")->assertJsonPath('tablero.nombre', 'Privado')->assertJsonPath('tareas.0.titulo', 'Secreta');
    });

    it('no acepta etiquetas ni responsables de otra empresa', function (): void {
        $admin = como('admin');
        $tablero = $admin->postJson(API . '/tableros', ['nombre' => 'Ajeno'])->json('id');
        $etiqueta = $admin->postJson(API . "/tableros/{$tablero}/etiquetas", ['nombre' => 'mia'])->json('id');

        $olga = como('olga');
        $suyo = $olga->postJson(API . '/tableros', ['nombre' => 'De Olga'])->json('id');
        $olga->postJson(API . "/tableros/{$suyo}/tareas", ['titulo' => 'x', 'id_responsable' => 1])->assertStatus(422);
        $olga->postJson(API . "/tableros/{$suyo}/tareas", ['titulo' => 'con etiqueta ajena', 'etiquetas' => [$etiqueta]])->assertStatus(201);

        expect($olga->getJson(API . "/tableros/{$suyo}")->json('tareas.0.etiquetas'))->toBe([]);
    });

    it('mover una tarea la deja en la columna y en la posición pedidas', function (): void {
        $admin = como('admin');
        $tablero = $admin->postJson(API . '/tableros', ['nombre' => 'Kanban'])->json('id');
        $a = $admin->postJson(API . "/tableros/{$tablero}/tareas", ['titulo' => 'A', 'columna' => 'doing'])->json('id');
        $b = $admin->postJson(API . "/tableros/{$tablero}/tareas", ['titulo' => 'B', 'columna' => 'doing'])->json('id');
        $c = $admin->postJson(API . "/tableros/{$tablero}/tareas", ['titulo' => 'C'])->json('id');

        $admin->patchJson(API . "/tareas/{$c}/mover", ['columna' => 'doing', 'orden' => [$b, $c, $a]])->assertOk();

        $enCurso = array_values(array_filter(
            $admin->getJson(API . "/tableros/{$tablero}")->json('tareas'),
            static fn(array $t): bool => $t['columna'] === 'doing',
        ));
        usort($enCurso, static fn(array $x, array $y): int => $x['posicion'] <=> $y['posicion']);
        expect(array_column($enCurso, 'titulo'))->toBe(['B', 'C', 'A']);
    });

    it('cada usuario borra solo sus propias notas', function (): void {
        $admin = como('admin');
        $tablero = $admin->postJson(API . '/tableros', ['nombre' => 'Notas'])->json('id');
        $tarea = $admin->postJson(API . "/tableros/{$tablero}/tareas", ['titulo' => 'Con notas'])->json('id');
        $nota = $admin->postJson(API . "/tareas/{$tarea}/notas", ['contenido' => 'del admin'])->json('id');

        como('ana')->deleteJson(API . "/notas/{$nota}")->assertStatus(404);
        $admin->deleteJson(API . "/notas/{$nota}")->assertOk();
    });

    it('un id que no es número en la ruta responde 404 (int $id en el controlador)', function (): void {
        como('admin')->getJson(API . '/tableros/abc')->assertStatus(404);
    });

    it('la pantalla exige el módulo en el perfil del usuario', function (): void {
        como('ana')->get('/admin/tableros')->assertRedirect('/e404');

        // El administrador le da el módulo a su perfil desde el panel, como lo haría a mano
        $menu = (int) TestDatabase::pdo()->query("SELECT id FROM versa_menu WHERE url = '/tableros'")->fetchColumn();
        como('admin')->postJson('/admin/perfiles/savePerfilPermisos', [
            'id' => 2,
            'nombre' => 'Usuario',
            'pagina_inicio' => 'dashboard',
            'data' => json_encode(['TRABAJO' => [['id_menu' => $menu, 'checked' => true]]]),
        ])->assertOk();

        como('ana')->get('/admin/tableros')->assertOk()->assertSee('dashTableros');
    });
});
