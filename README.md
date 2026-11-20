# Fitbase API (FlightPHP)

API REST ligera construida con **PHP** y **FlightPHP**, diseñada exclusivamente para servir y consumir datos en formato **JSON** (sin renderizado de vistas HTML).

---

## Estructura del Proyecto

```text
fitbase/
├── composer.json            # Dependencias y configuración PSR-4 (App\ -> src/)
├── public/
│   ├── .htaccess            # Reescritura de URLs para Apache
│   └── index.php            # Front Controller (CORS, JSON 404/500, arranque de Flight)
└── src/
    ├── Controllers/
    │   ├── BaseController.php      # Métodos base para respuestas JSON estandarizadas
    │   ├── HealthController.php    # Endpoint de estado del servicio
    │   ├── ExerciseController.php  # CRUD de ejercicios (ejemplo REST)
    │   └── UserController.php      # Control de usuarios
    ├── Middlewares/
    │   └── CorsMiddleware.php      # Cabeceras CORS y preflight OPTIONS
    └── Routes/
        └── api.php                 # Definición de rutas y grupos de la API
```

---

## Repositorios del ecosistema Fitbase

| Componente | Descripción | Repositorio |
| :--- | :--- | :--- |
| Frontend web | Cliente web | [Moisessantos45/fitbase_frontend](https://github.com/Moisessantos45/fitbase_frontend) |
| Backend (Go) | Versión alternativa del backend en Go | [Moisessantos45/fitbase_backend](https://github.com/Moisessantos45/fitbase_backend) |
| App móvil | Versión móvil | [Moisessantos45/fitbase](https://github.com/Moisessantos45/fitbase) |

```bash
git clone https://github.com/Moisessantos45/fitbase_frontend.git
git clone https://github.com/Moisessantos45/fitbase_backend.git
git clone https://github.com/Moisessantos45/fitbase.git
```

- Este repositorio es el backend en **PHP (FlightPHP)** y comparte el esquema de BD (`sql/`) con la versión en Go.
- Apunta el frontend o la app móvil a esta API (`http://localhost:4107`).
- La URL del frontend (por defecto `http://localhost:3000`) debe estar en `HOST_URL_DEV` / `HOST_URL_PROD` del `.env` para que CORS la permita.

---

## Inicio rápido con Docker (recomendado)

Levanta **PHP 8.4 + Composer**, **PostgreSQL 17 + PostGIS 3** y **Redis 7** con un solo comando. No necesitas instalar nada más que Docker.

```bash
# 1. Variables de entorno
cp .env.example .env        # edita claves (SMTP, Stripe, JWT) si las necesitas

# 2. Levantar todo (la primera vez construye la imagen y ejecuta composer install)
docker compose up -d --build

# 3. Verificar
curl http://localhost:4107/api/health
docker compose logs -f app
```

| Servicio | Contenedor | Puerto en tu máquina |
| :--- | :--- | :--- |
| API (PHP) | `fitbase_app` | `4107` |
| PostgreSQL + PostGIS | `fitbase_db` | `5433` (`DB_EXTERNAL_PORT`) |
| Redis | `fitbase_redis` | `6379` (`REDIS_EXTERNAL_PORT`) |

### Base de datos: creación automática
La **primera vez** que arranca `db` (volumen vacío), Docker ejecuta en orden:

1. `docker/postgres/00_extensions.sql` → `CREATE EXTENSION postgis` y `uuid-ossp`
2. `sql/01_schema.sql` → tablas
3. `sql/02_seeds.sql` → catálogos y datos iniciales

- La imagen `postgis/postgis:17-3.5` ya trae lo equivalente a `sudo apt install postgresql-17-postgis-3` (necesario para coordenadas).
- El usuario `DB_USERNAME` se crea como **superusuario**, por lo que tiene permiso para `CREATE EXTENSION`.
- Si cambias los `.sql` y quieres re-ejecutarlos, **reinicia la BD desde cero** (borra los datos):
  ```bash
  docker compose down -v && docker compose up -d --build
  ```

### Comandos útiles
```bash
docker compose exec db psql -U postgres -d fitbase        # consola psql
docker compose exec db psql -U postgres -d fitbase -c "SELECT postgis_version();"
docker compose exec redis redis-cli                        # consola Redis
docker compose exec app composer require vendor/paquete    # instalar paquetes PHP
docker compose exec app sh                                 # shell en la app
docker compose down                                        # detener (conserva datos)
```
> El código está montado como volumen: los cambios en `src/` y `public/` se reflejan sin reconstruir.
> Dentro de Docker, `DB_HOST=db` y `REDIS_HOST=redis` se inyectan solos (no cambies `.env` para eso).

---

## Instalación local (sin Docker)

Requisitos: PHP 8.4 con `pdo_pgsql`, Composer, PostgreSQL 17 con PostGIS y Redis.

```bash
sudo apt install postgresql-17 postgresql-17-postgis-3 redis-server php-pgsql
composer install
cp .env.example .env     # DB_HOST=127.0.0.1, REDIS_HOST=127.0.0.1

# Crear BD (el usuario necesita ser superusuario para crear extensiones)
sudo -u postgres psql -c "CREATE ROLE dev_moy LOGIN SUPERUSER PASSWORD 'tu_password';"
sudo -u postgres createdb -O dev_moy fitbase
psql -h 127.0.0.1 -U dev_moy -d fitbase -f sql/01_schema.sql
psql -h 127.0.0.1 -U dev_moy -d fitbase -f sql/02_seeds.sql

composer start      # http://localhost:4107
```

---

## Variables de entorno (`.env`)

Ver [.env.example](.env.example) completo. Principales:

| Variable | Descripción |
| :--- | :--- |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Conexión PostgreSQL (también usadas por Docker para crear la BD) |
| `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` | Redis (sesiones y tokens) |
| `SECRET_KEY_JWT` | Clave de tokens (32 caracteres) |
| `SMTP_*`, `API_KEY_SMTP` | Envío de correos |
| `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET` | Pagos |
| `HOST_URL_*` | Orígenes permitidos por CORS |

> Nunca subas `.env` a git (ya está en `.gitignore`).

---

## Endpoints Disponibles

Todas las respuestas retornan cabecera `Content-Type: application/json`.

| Método | Endpoint | Descripción |
| :--- | :--- | :--- |
| `GET` | `/` | Información general de la API y lista de endpoints |
| `GET` | `/api/health` | Estado del servicio y versión de PHP |
| `GET` | `/api/v1/exercises` | Lista todos los ejercicios (admite `?muscle_group=Piernas`) |
| `GET` | `/api/v1/exercises/@id` | Detalle de un ejercicio por ID |
| `POST` | `/api/v1/exercises` | Crea un nuevo ejercicio (recibe JSON) |
| `PUT` | `/api/v1/exercises/@id` | Actualiza un ejercicio existente (recibe JSON) |
| `DELETE` | `/api/v1/exercises/@id` | Elimina un ejercicio por ID |
| `GET` | `/api/v1/users` | Lista todos los usuarios |
| `GET` | `/api/v1/users/@id` | Detalle de un usuario |
| `POST` | `/api/v1/users` | Crea un nuevo usuario |

---

## Ejemplos con `curl`

### 1. Consultar estado del servicio:
```bash
curl -i http://localhost:4107/api/health
```

### 2. Listar ejercicios:
```bash
curl -i http://localhost:4107/api/v1/exercises
```

### 3. Crear un nuevo ejercicio:
```bash
curl -i -X POST http://localhost:4107/api/v1/exercises \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Press militar con mancuernas",
    "muscle_group": "Hombros",
    "difficulty": "Intermedio",
    "sets": 4,
    "reps": 10
  }'
```

### 4. Actualizar un ejercicio:
```bash
curl -i -X PUT http://localhost:4107/api/v1/exercises/1 \
  -H "Content-Type: application/json" \
  -d '{
    "reps": 12
  }'
```

### 5. Eliminar un ejercicio:
```bash
curl -i -X DELETE http://localhost:4107/api/v1/exercises/1
```

### 6. Manejo de error 404 (Ruta no encontrada):
Si consultas una ruta no definida (ej. `/ruta-inexistente`), la API responde con JSON (no HTML):
```json
{
  "success": false,
  "status": 404,
  "message": "Ruta o recurso no encontrado",
  "path": "/ruta-inexistente",
  "method": "GET"
}
```

---

## Cómo agregar nuevas rutas

1. Crea tu controlador en `src/Controllers/` heredando de `BaseController`:
   ```php
   namespace App\Controllers;

   class RutinaController extends BaseController {
       public function index(): void {
           $this->jsonResponse(['rutinas' => []], 'OK');
       }
   }
   ```

2. Registra la ruta en [src/Routes/api.php](src/Routes/api.php):
   ```php
   Flight::route('GET /api/v1/rutinas', [RutinaController::class, 'index']);
   ```
