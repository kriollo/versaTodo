<?php

declare(strict_types=1);

use versaWYS\kernel\helpers\Functions;
use versaWYS\kernel\Testing\TestClient;

/*
 * Módulo Tableros con TestClient: la API completa (rutas, middlewares, controlador y base de datos)
 * contra una SQLite desechable con las migraciones y seeders del proyecto.
 */

const TABLEROS_PASSWORD = 'Clave#Segura2026';

function tablerosDir(): string
{
    return sys_get_temp_dir() . '/versatodo_tableros_' . getmypid();
}

beforeAll(function (): void {
    $dir = tablerosDir();
    @mkdir($dir, 0o700, true);

    $config = versaWYS\kernel\cli\ConfigManager::defaults();
    $config['database'] = ['driver' => 'sqlite', 'database' => $dir . '/test.sqlite', 'log_path' => $dir . '/orm/'] + $config['database'];
    $config['session']['key'] = bin2hex(random_bytes(32));
    $config['twig']['compiled_dir'] = $dir . '/twig';
    $config['logging']['dir'] = $dir . '/logs/';
    $config['rate_limiting']['cache_dir'] = $dir . '/rate_limits/';
    foreach (['global', 'api', 'upload', 'sensitive', 'login'] as $rule) {
        $config['rate_limiting']['rules'][$rule]['requests'] = 100000;
    }
    file_put_contents($dir . '/config.json', json_encode($config));

    $env = 'VERSAWYS_CONFIG=' . escapeshellarg($dir . '/config.json');
    exec("{$env} php versaCLI migrate:up 2>&1 < /dev/null && {$env} php versaCLI seeder:runAll 2>&1 < /dev/null", $output, $code);
    if ($code !== 0) {
        throw new RuntimeException("No se pudo preparar la base de prueba:\n" . implode("\n", $output));
    }

    $db = new PDO('sqlite:' . $dir . '/test.sqlite', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec("INSERT INTO empresas (token_empresa, rut, nombre, email, estado) VALUES ('" . bin2hex(random_bytes(16)) . "', '22222222-2', 'Otra SA', 'otra@todo.test', 1)");
    $otra = (int) $db->lastInsertId();

    $insert = $db->prepare(
        'INSERT INTO versa_users (id_empresa, tokenid, name, email, password, expiration_pass, role, status, id_perfil)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, 2)',
    );
    foreach ([[1, 'Admin', 'admin@todo.test', 'superadmin'], [1, 'Ana', 'ana@todo.test', 'user'], [$otra, 'Olga', 'olga@todo.test', 'admin']] as [$empresa, $name, $email, $role]) {
        $insert->execute([$empresa, bin2hex(random_bytes(16)), $name, $email, Functions::hash(TABLEROS_PASSWORD), date('Y-m-d', strtotime('+90 days')), $role]);
    }

    TestClient::boot(dirname(__DIR__, 2), $dir . '/config.json');
});

afterAll(function (): void {
    exec('rm -rf ' . escapeshellarg(tablerosDir()));
});

function como(string $email): TestClient
{
    $client = TestClient::boot(dirname(__DIR__, 2));
    $client->login($email, TABLEROS_PASSWORD);

    return $client;
}

const API = '/admin/tableros/api';

describe('Tableros', function (): void {
    it('crea un tablero con tareas y etiquetas y lo devuelve completo', function (): void {
        $admin = como('admin@todo.test');

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
        $admin = como('admin@todo.test');
        $tablero = $admin->postJson(API . '/tableros', ['nombre' => 'Validación'])->json('id');

        $admin->postJson(API . '/tableros', [])->assertStatus(422)->assertJsonPath('errors.nombre', 'El campo nombre es obligatorio');
        $admin->postJson(API . "/tableros/{$tablero}/tareas", ['titulo' => 'x', 'columna' => 'nope', 'prioridad' => 'urgente'])
            ->assertStatus(422);
        $admin->postJson(API . "/tableros/{$tablero}/etiquetas", ['nombre' => 'repetida'])->assertStatus(201);
        $admin->postJson(API . "/tableros/{$tablero}/etiquetas", ['nombre' => 'repetida'])->assertStatus(422);
    });

    it('otra empresa no ve ni modifica nada del tablero', function (): void {
        $admin = como('admin@todo.test');
        $tablero = $admin->postJson(API . '/tableros', ['nombre' => 'Privado'])->json('id');
        $tarea = $admin->postJson(API . "/tableros/{$tablero}/tareas", ['titulo' => 'Secreta'])->json('id');
        $etiqueta = $admin->postJson(API . "/tableros/{$tablero}/etiquetas", ['nombre' => 'interna'])->json('id');
        $nota = $admin->postJson(API . "/tareas/{$tarea}/notas", ['contenido' => 'no mirar'])->json('id');

        $olga = como('olga@todo.test');
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
        $admin = como('admin@todo.test');
        $tablero = $admin->postJson(API . '/tableros', ['nombre' => 'Ajeno'])->json('id');
        $etiqueta = $admin->postJson(API . "/tableros/{$tablero}/etiquetas", ['nombre' => 'mia'])->json('id');

        $olga = como('olga@todo.test');
        $suyo = $olga->postJson(API . '/tableros', ['nombre' => 'De Olga'])->json('id');
        $olga->postJson(API . "/tableros/{$suyo}/tareas", ['titulo' => 'x', 'id_responsable' => 1])->assertStatus(422);
        $olga->postJson(API . "/tableros/{$suyo}/tareas", ['titulo' => 'con etiqueta ajena', 'etiquetas' => [$etiqueta]])->assertStatus(201);

        expect($olga->getJson(API . "/tableros/{$suyo}")->json('tareas.0.etiquetas'))->toBe([]);
    });

    it('mover una tarea la deja en la columna y en la posición pedidas', function (): void {
        $admin = como('admin@todo.test');
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
        $admin = como('admin@todo.test');
        $tablero = $admin->postJson(API . '/tableros', ['nombre' => 'Notas'])->json('id');
        $tarea = $admin->postJson(API . "/tableros/{$tablero}/tareas", ['titulo' => 'Con notas'])->json('id');
        $nota = $admin->postJson(API . "/tareas/{$tarea}/notas", ['contenido' => 'del admin'])->json('id');

        como('ana@todo.test')->deleteJson(API . "/notas/{$nota}")->assertStatus(404);
        $admin->deleteJson(API . "/notas/{$nota}")->assertOk();
    });

    it('un id que no es número en la ruta responde 404 (int $id en el controlador)', function (): void {
        como('admin@todo.test')->getJson(API . '/tableros/abc')->assertStatus(404);
    });

    it('la pantalla exige el módulo en el perfil del usuario', function (): void {
        como('ana@todo.test')->get('/admin/tableros')->assertRedirect('/e404');

        // El administrador le da el módulo a su perfil desde el panel, como lo haría a mano
        $db = new PDO('sqlite:' . tablerosDir() . '/test.sqlite', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $menu = (int) $db->query("SELECT id FROM versa_menu WHERE url = '/tableros'")->fetchColumn();
        $db = null;
        como('admin@todo.test')->postJson('/admin/perfiles/savePerfilPermisos', [
            'id' => 2,
            'nombre' => 'Usuario',
            'pagina_inicio' => 'dashboard',
            'data' => json_encode(['TRABAJO' => [['id_menu' => $menu, 'checked' => true]]]),
        ])->assertOk();

        como('ana@todo.test')->get('/admin/tableros')->assertOk()->assertSee('dashTableros');
    });
});
