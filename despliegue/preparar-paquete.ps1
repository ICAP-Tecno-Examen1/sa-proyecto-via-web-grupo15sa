# ----------------------------------------------------------------------
# Prepara el paquete para subir a tecnoweb:
#   https://www.tecnoweb.org.bo/inf513/<grupo>/proyecto2
#
# Uso (desde la raíz del proyecto, en PowerShell):
#   .\despliegue\preparar-paquete.ps1                 # grupo15sa
#   .\despliegue\preparar-paquete.ps1 -Grupo grupo15sa
#
# Resultado:
#   despliegue\salida\proyecto2\       carpeta lista para subir (frontend + backend con vendor)
#   despliegue\salida\proyecto2.zip    la misma carpeta comprimida
#
# El .env de producción se arma con los datos de la base del grupo y las claves de
# Stripe que ya están en backend\.env. La carpeta salida\ NO se sube a git (tiene el .env).
# ----------------------------------------------------------------------
param(
    [string]$Grupo = 'grupo15sa',
    [string]$DbHost = 'localhost'   # desde el propio servidor; si falla, use db.tecnoweb.org.bo
)

$ErrorActionPreference = 'Stop'
$raiz = Split-Path -Parent $PSScriptRoot
$salida = Join-Path $PSScriptRoot 'salida'
$destino = Join-Path $salida 'proyecto2'
$base = "/inf513/$Grupo/proyecto2/"
$urlPublica = "https://www.tecnoweb.org.bo$($base.TrimEnd('/'))"
$sinBom = New-Object System.Text.UTF8Encoding($false)

function Leer-Env([string]$archivo) {
    $valores = @{}
    foreach ($linea in [IO.File]::ReadAllLines($archivo)) {
        if ($linea -match '^\s*([A-Z0-9_]+)=(.*)$') { $valores[$matches[1]] = $matches[2].Trim() }
    }
    return $valores
}

Write-Host "== Paquete para $urlPublica" -ForegroundColor Cyan
if (Test-Path $destino) { Remove-Item -Recurse -Force $destino }
New-Item -ItemType Directory -Force $destino | Out-Null

# ---------- 1. Frontend ----------
Write-Host '== 1/4 Compilando el frontend…' -ForegroundColor Cyan
$envFront = "VITE_API_URL=$($base)backend/public/api`nVITE_BASE=$base`nVITE_ROUTER=hash`n"
[IO.File]::WriteAllText((Join-Path $raiz 'frontend\.env.production'), $envFront, $sinBom)
Push-Location (Join-Path $raiz 'frontend')
npm run build
if ($LASTEXITCODE -ne 0) { Pop-Location; throw 'Falló npm run build' }
Pop-Location
Copy-Item -Recurse -Force (Join-Path $raiz 'frontend\dist\*') $destino

# ---------- 2. Backend (sin archivos locales) ----------
Write-Host '== 2/4 Copiando el backend…' -ForegroundColor Cyan
$back = Join-Path $destino 'backend'
robocopy (Join-Path $raiz 'backend') $back /E /NFL /NDL /NJH /NJS /NP `
    /XD vendor node_modules tests .phpunit.cache `
        (Join-Path $raiz 'backend\database\local') `
        (Join-Path $raiz 'backend\storage\app\private') `
        (Join-Path $raiz 'backend\storage\logs') `
        (Join-Path $raiz 'backend\storage\fonts') `
        (Join-Path $raiz 'backend\storage\framework') `
        (Join-Path $raiz 'backend\bootstrap\cache') `
    /XF .env .env.backup .phpunit.result.cache phpunit.xml | Out-Null
if ($LASTEXITCODE -ge 8) { throw 'Falló la copia del backend (robocopy)' }

# Carpetas de trabajo de Laravel (vacías)
foreach ($c in 'storage\app\private', 'storage\app\public', 'storage\logs', 'storage\fonts',
               'storage\framework\cache\data', 'storage\framework\sessions', 'storage\framework\views', 'bootstrap\cache') {
    New-Item -ItemType Directory -Force (Join-Path $back $c) | Out-Null
}

# ---------- 3. Dependencias de PHP ----------
Write-Host '== 3/4 composer install --no-dev…' -ForegroundColor Cyan
Push-Location $back
composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts
if ($LASTEXITCODE -ne 0) { Pop-Location; throw 'Falló composer install' }
# Registra solo los paquetes de producción (bootstrap\cache\packages.php)
php artisan package:discover
if ($LASTEXITCODE -ne 0) { Pop-Location; throw 'Falló package:discover' }
Pop-Location

# ---------- 4. .env de producción ----------
Write-Host '== 4/4 Generando backend\.env de producción…' -ForegroundColor Cyan
$local = Leer-Env (Join-Path $raiz 'backend\.env')
$clave = (php -r "echo 'base64:'.base64_encode(random_bytes(32));")

$envBack = @"
APP_NAME="ICAP UAGRM"
APP_ENV=production
APP_KEY=$clave
APP_DEBUG=false
APP_URL=$urlPublica/backend/public
APP_TIMEZONE=America/La_Paz
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_ES
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=$DbHost
DB_PORT=5432
DB_DATABASE=db_$Grupo
DB_USERNAME=$Grupo
DB_PASSWORD=$($local['DB_PASSWORD'])
DB_SEARCH_PATH=public
DB_TIMEZONE=America/La_Paz

SESSION_DRIVER=array
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
MAIL_MAILER=log

FRONTEND_URL=$urlPublica
FRONTEND_ROUTER=hash
CORS_ORIGENES=*

ICAP_NOTA_MINIMA=51
ICAP_ASISTENCIA_MINIMA=70
ICAP_MESES_POR_MODULO=1
ICAP_DIAS_PAGO_INICIAL=7
ICAP_ARCHIVO_MAX_KB=4096

STRIPE_SECRET_KEY=$($local['STRIPE_SECRET_KEY'])
STRIPE_PUBLISHABLE_KEY=$($local['STRIPE_PUBLISHABLE_KEY'])
STRIPE_WEBHOOK_SECRET=$($local['STRIPE_WEBHOOK_SECRET'])
STRIPE_CURRENCY=bob
"@
[IO.File]::WriteAllText((Join-Path $back '.env'), ($envBack -replace "`r`n", "`n"), $sinBom)

if ($local['DB_DATABASE'] -ne "db_$Grupo") {
    Write-Warning "backend\.env local apunta a '$($local['DB_DATABASE'])': revise DB_PASSWORD en salida\proyecto2\backend\.env"
}

# ---------- Comprimir ----------
$zip = Join-Path $salida 'proyecto2.zip'
if (Test-Path $zip) { Remove-Item -Force $zip }
Compress-Archive -Path $destino -DestinationPath $zip
Write-Host "`nListo:" -ForegroundColor Green
Write-Host "  Carpeta: $destino"
Write-Host "  Zip:     $zip"
Write-Host "Siga docs\despliegue-tecnoweb.md (subir, permisos de storage, migrate, probar)."
