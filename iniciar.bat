@echo off
rem ============================================================
rem  ICAP - UAGRM · Arranque del entorno de desarrollo
rem  Abre la API (Laravel) y el cliente (React) en dos ventanas.
rem  Requisitos previos: ver README.md (instalacion, una sola vez).
rem ============================================================
cd /d "%~dp0"

echo [1/3] Base de datos local (Docker)...
docker compose up -d
if errorlevel 1 (
    echo.
    echo  No se pudo iniciar la base de datos. Abra Docker Desktop y vuelva a intentar,
    echo  o configure otra base PostgreSQL en backend\.env
    echo.
)

echo [2/3] API Laravel en http://127.0.0.1:8000 ...
start "ICAP - API (Laravel)" cmd /k "cd /d "%~dp0backend" && php artisan serve --host=127.0.0.1 --port=8000"

echo [3/3] Cliente React en http://localhost:5173 ...
start "ICAP - Cliente (React)" cmd /k "cd /d "%~dp0frontend" && npm run dev"

timeout /t 6 /nobreak >nul
start "" http://localhost:5173
