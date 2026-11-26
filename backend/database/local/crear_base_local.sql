-- ============================================================
-- Crea el usuario y la base del proyecto en el PostgreSQL LOCAL.
-- Se ejecuta una sola vez, como superusuario "postgres":
--
--   & "C:\Program Files\PostgreSQL\17\bin\psql.exe" -h 127.0.0.1 -U postgres -f database\local\crear_base_local.sql
--
-- Después:  php artisan migrate --seed
-- ============================================================

CREATE USER icap WITH PASSWORD 'icap_local';
CREATE DATABASE db_icap OWNER icap ENCODING 'UTF8';
-- Base para las pruebas automáticas (php artisan test)
CREATE DATABASE db_icap_test OWNER icap ENCODING 'UTF8';
