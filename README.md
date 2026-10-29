# versaTodo - Plataforma Multiempresa de Gestión Empresarial

**Aplicación web moderna para gestión multiempresa con panel de administración en Vue.js + TypeScript.**

[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4.svg)](#)
[![Vue.js](https://img.shields.io/badge/Vue.js-3.5-green.svg)](#)
[![TailwindCSS](https://img.shields.io/badge/TailwindCSS-4-blue.svg)](#)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](#)

## 📋 Descripción

**versaTodo** es una aplicación web completa para gestión empresarial multiempresa, construida sobre el framework PHP versawys. Permite administrar múltiples empresas/clientes con un sistema de usuarios y perfiles con roles y permisos específicos por empresa.

### Características Principales

- **🏢 Gestión Multiempresa**: Administración centralizada de múltiples empresas/clientes
- **👥 Sistema de Usuarios**: Control de acceso con relación a empresas específicas
- **🔐 Roles y Permisos**: Sistema flexible de perfiles con control granular por empresa
- **📊 Panel de Administración**: Dashboard moderno en Vue.js + TypeScript
- **💾 Gestión de Archivos**: Almacenamiento y gestión de medios (fotos, documentos)
- **🌐 Políticas de Privacidad**: Cumplimiento normativo con eliminación segura de datos
- **⚙️ Configuración Flexible**: Variables de entorno para personalización completa

## 🚀 Inicio Rápido

Con una base de datos MySQL o PostgreSQL vacía:

```bash
composer create-project versawys/versawys versaTodo
cd versaTodo && pnpm install && pnpm compile
php versaCLI app:install && php versaCLI serve
```

### Configuración Inicial

1. **Crear archivo `.env`**:
   ```bash
   cp .env.example .env
   # Editar .env con tus credenciales de base de datos y configuración
   ```

2. **Ejecutar migraciones iniciales**:
   ```bash
   php versaCLI migrate:refresh
   ```

3. **Acceder al panel de administración**:
   - URL: `http://localhost/dashboard` (o la que configures)
   - Credenciales iniciales se crean en el proceso de instalación

Detalles completos en la [Guía de Instalación](./documentacion/INSTALL.md).

## ✨ Funcionalidades Específicas

### 🏢 Módulo Empresas

Gestión completa de empresas/clientes:

- **Listado y búsqueda**: Filtrar por RUT, nombre, email, estado
- **CRUD completo**: Crear, editar, activar/desactivar empresas
- **Logo personalizado**: Cada empresa puede tener su logo
- **Información clave**: RUT, nombre comercial, teléfono, email

### 👥 Módulo Usuarios

Sistema de usuarios con relación multiempresa:

- **Usuarios por empresa**: Cada usuario pertenece a una empresa específica (`id_empresa`)
- **Autenticación segura**: Sistema de login/logout con sesiones
- **Perfiles y roles**: Asignación de perfiles con permisos específicos
- **Gestión completa**: CRUD de usuarios con control de estado

### 🎭 Módulo Perfiles

Sistema flexible de roles y permisos:

- **Perfiles personalizables**: Definir roles por empresa (admin, editor, viewer, etc.)
- **Permisos granulares**: Control de acceso a módulos específicos
- **Asignación dinámica**: Asignar perfiles a usuarios por empresa
- **Estructura flexible**: Adaptable a necesidades específicas de cada cliente

### 📊 Panel de Administración (Dashboard)

Interfaz moderna en Vue.js + TypeScript:

- **Estadísticas generales**: Resumen de empresas, usuarios, actividad reciente
- **Menú dinámico**: Navegación personalizada por perfil y empresa
- **Gestión centralizada**: Acceder a todos los módulos desde un solo punto
- **Diseño responsive**: Adaptable a dispositivos móviles y tablets

### 📁 Gestión de Medios

Sistema de almacenamiento y gestión de archivos:

- **Almacenamiento flexible**: Configuración para diferentes tipos de storage
- **Categorización**: Organización por carpetas y tipos de archivo
- **Seguridad**: Control de acceso a recursos multimedia
- **Optimización**: Compresión y redimensionado automático

## 📁 Estructura del Proyecto

```
versaTodo/
├── app/                    # Código de la aplicación (tu lógica)
│   ├── controllers/       # Controladores: Empresas, Usuarios, Perfiles, Dashboard, etc.
│   ├── models/           # Modelos: Empresas, Users, Perfil, MediaStore, etc.
│   ├── routes/           # Definición de rutas por módulo
│   ├── middleware/       # Middlewares personalizados (Auth, etc.)
│   ├── migrations/       # Migraciones de base de datos
│   ├── seeders/          # Datos iniciales para pruebas
│   └── templates/        # Plantillas Twig personalizadas
├── src/dashboard/        # Frontend fuente (Vue.js + TypeScript)
│   ├── css/             # Estilos con TailwindCSS
│   ├── js/              # Scripts Vue.js y composables
│   ├── types/           # Definiciones TypeScript
│   └── views/           # Componentes Vue
├── public/dashboard/    # Assets compilados (CSS, JS optimizados)
├── documentacion/       # Documentación oficial en español
├── versaWYS/           # Framework y dependencias
│   ├── vendor/         # Paquetes Composer
│   └── configs/        # Configuraciones del framework
├── .env.example        # Plantilla de variables de entorno
├── composer.json       # Dependencias PHP
├── package.json        # Dependencias frontend (pnpm)
└── versaCLI            # Línea de comandos para automatización
```

### Explicación de Directorios Clave

| Carpeta | Propósito Principal |
|---------|---------------------|
| `app/` | Lógica de negocio específica de esta aplicación multiempresa |
| `src/dashboard/` | Código fuente del frontend Vue.js + TypeScript |
| `public/dashboard/` | Assets compilados y optimizados para producción |
| `documentacion/` | Documentación oficial en español (guías, tutoriales) |

Consulta la [Guía de Estructura de Directorios](./documentacion/EstructuraDeDirectorios.md) para más detalles.

## 📚 Documentación Completa

Toda la documentación detallada se encuentra en la carpeta [`/documentacion`](./documentacion/). Te recomendamos comenzar por:

- [Guía de Inicio Rápido](./documentacion/README.md): visión general y primeros pasos.
- [Guía de Instalación](./documentacion/INSTALL.md): cómo instalar y configurar versaTodo.
- [Guía de Configuración](./documentacion/CONFIGURATION.md): personalización del entorno multiempresa.
- [Estructura de Directorios](./documentacion/EstructuraDeDirectorios.md): organización específica de esta app.
- [Enrutamiento](./documentacion/Enrutamiento.md): cómo funcionan las rutas y middlewares.
- [ORM y Base de Datos](./documentacion/ORM_y_BaseDeDatos.md): modelos con VersaORM, migraciones y seeders.
- [Interfaz de Línea de Comandos (CLI)](./documentacion/LineaDeComandos.md): automatización y utilidades.
- [Validación de datos](./documentacion/Validacion.md): reglas de validación en formularios.
- [Seguridad](./documentacion/Seguridad_Principios_Basicos.md): buenas prácticas y protección multiempresa.

## 🔒 Seguridad y Cumplimiento

- **Protección CSRF**: Todos los formularios protegidos contra ataques CSRF
- **XSS Prevention**: Sanitización automática de entradas de usuario
- **Control de Sesiones**: Gestión segura de sesiones con timeout configurable
- **Eliminación Segura**: Políticas de privacidad con eliminación completa de datos al borrar empresas/usuarios
- **Middlewares de Autenticación**: Protección contra acceso no autorizado

## 🛠️ Desarrollo y Contribución

### Requisitos del Sistema

- **PHP 8.3+** recomendado (compatible hasta PHP 8.4)
- **MySQL 8.0+** o **PostgreSQL 15+**
- **Node.js 20+** para el frontend
- **pnpm** como gestor de paquetes JavaScript

### Instalación de Dependencias

```bash
# Dependencias PHP
composer install --working-dir=versaWYS

# Dependencias Frontend
pnpm install
pnpm compile
```

### Scripts Disponibles

```bash
# Compilar frontend para producción
pnpm compile

# Modo desarrollo con watch automático
pnpm watch

# Formatear código
pnpm format

# Verificar estilo de código
pnpm lint

# Corregir automáticamente problemas de estilo
pnpm lint:fix
```

## 📦 Módulos Disponibles

| Módulo | Controlador | Descripción |
|--------|-------------|-------------|
| Empresas | `EmpresasController` | Gestión de empresas/clientes multiempresa |
| Usuarios | `UsersController` | Administración de usuarios por empresa |
| Perfiles | `PerfilController` | Roles y permisos por perfil/empresa |
| Dashboard | `DashBoardController` | Panel de administración principal |
| Tableros | `TablerosController` | Creación y gestión de tableros personalizados |
| Medios | `MediaController` | Gestión de archivos multimedia |
| Configuración | `ConfigSettingsController` | Ajustes globales del sistema |
| Copias de Seguridad | `DatabaseBackupController` | Backup y restauración de base de datos |

## 📖 Ejemplos de Uso

### Crear una nueva empresa

```php
// Desde el controlador o directamente con VersaORM
$empresa = new \app\models\Empresas();
$data = [
    'rut' => '76123456-7',
    'nombre' => 'Mi Empresa S.A.',
    'email' => 'contacto@miempresa.cl',
    'telefono' => '+56 9 1234 5678',
];
$empresa->create($data);
```

### Asignar usuario a una empresa

```php
// Crear usuario y asignarlo a una empresa específica
$user = new \app\models\Users();
$data = [
    'email' => 'admin@miempresa.cl',
    'password' => hash('sha256', 'contraseña-segura'), // Hash SHA-256
    'perfil_id' => 1, // ID del perfil (ej: admin)
];
$user->create($data);

// Asignar a empresa (se hace automáticamente en la relación)
```

### Obtener menú dinámico por perfil

```php
// Menú para administradores
$menuAdmin = getMenuAdmin();

// Menú para usuarios regulares  
$menuUser = getMenuUser();
```

## 🤝 Contribuciones

¿Quieres ayudar a mejorar versaTodo? Lee la [Guía de Contribución](./documentacion/Guia_Contribucion.md) para saber cómo reportar bugs, sugerir mejoras o enviar código.

## 🔧 Recursos Útiles

- [Preguntas Frecuentes (FAQ)](./documentacion/FAQ.md): respuestas a dudas comunes
- [Solución de Problemas (Troubleshooting)](./documentacion/Troubleshooting.md): guía para resolver errores frecuentes
- [Repositorio oficial en GitHub](https://github.com/kriollo/versaTodo)

## 📜 Licencia

Este proyecto está bajo la [Licencia MIT](https://opensource.org/licenses/MIT).

---

**Nota**: versaTodo es una aplicación concreta construida sobre el framework [versawys/kernel](https://github.com/kriollo/versawys-kernel). Para documentación del kernel genérico, consulta [`versaWYS/vendor/versawys/kernel/API.md`](./versaWYS/vendor/versawys/kernel/API.md).