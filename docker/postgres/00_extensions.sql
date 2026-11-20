-- Se ejecuta primero al crear la BD por primera vez (como superusuario POSTGRES_USER).
-- Equivale a: sudo apt install postgresql-17-postgis-3 (ya incluido en la imagen postgis/postgis).
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS postgis;
