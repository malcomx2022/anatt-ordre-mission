<#
.SYNOPSIS
    Demarre l'environnement de developpement ANaTT (API Laravel + worker + front Vite).

.DESCRIPTION
    Lance les 3 services, chacun dans sa propre fenetre PowerShell :
      - API      : php artisan serve            -> http://localhost:8000
      - Worker   : php artisan queue:work       -> files paiements/etats/default
      - Frontend : npm run dev                  -> http://localhost:5173

.PARAMETER Install
    Installe les dependances avant de demarrer (composer install + npm ci).
    npm ci respecte package-lock.json : versions exactes du lock (Vue 3.5.44).

.PARAMETER Fresh
    Reinitialise la base puis la re-seede (migrate:fresh --seed). DESTRUCTIF.

.PARAMETER NoWorker
    Ne lance pas le worker de file (queue:work).

.EXAMPLE
    .\start-dev.ps1
    .\start-dev.ps1 -Install -Fresh
    .\start-dev.ps1 -NoWorker
#>

[CmdletBinding()]
param(
    [switch]$Install,
    [switch]$Fresh,
    [switch]$NoWorker
)

$ErrorActionPreference = 'Stop'

$Root     = $PSScriptRoot
$Backend  = Join-Path $Root 'backend'
$Frontend = Join-Path $Root 'frontend'

function Write-Step($msg)  { Write-Host "==> $msg" -ForegroundColor Cyan }
function Write-Ok($msg)    { Write-Host "    OK  $msg" -ForegroundColor Green }
function Write-Warn2($msg) { Write-Host "    !   $msg" -ForegroundColor Yellow }
function Fail($msg)        { Write-Host "    X   $msg" -ForegroundColor Red; exit 1 }

# --- Pre-vol : outils requis ---
Write-Step 'Verification des prerequis'
foreach ($tool in 'php', 'node', 'npm', 'composer') {
    if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) {
        Fail "$tool introuvable dans le PATH."
    }
}
Write-Ok ("php {0} / node {1}" -f (php -r 'echo PHP_VERSION;' 2>$null), (node -v))

# --- Pre-vol : dossiers ---
if (-not (Test-Path $Backend))  { Fail "Dossier backend introuvable : $Backend" }
if (-not (Test-Path $Frontend)) { Fail "Dossier frontend introuvable : $Frontend" }

# --- Pre-vol : MySQL (3306) ---
Write-Step 'Verification de MySQL (127.0.0.1:3306)'
$mysqlUp = $false
try {
    $client = [System.Net.Sockets.TcpClient]::new()
    $client.Connect('127.0.0.1', 3306)
    $mysqlUp = $client.Connected
    $client.Close()
} catch { $mysqlUp = $false }
if ($mysqlUp) { Write-Ok 'MySQL repond.' }
else { Write-Warn2 'MySQL ne repond pas sur 3306 - demarre WAMP (icone verte) avant de continuer.' }

# --- .env backend ---
$envPath = Join-Path $Backend '.env'
if (-not (Test-Path $envPath)) {
    Write-Step 'Creation du .env backend'
    Copy-Item (Join-Path $Backend '.env.example') $envPath
    Push-Location $Backend; php artisan key:generate | Out-Null; Pop-Location
    Write-Ok '.env cree + APP_KEY genere.'
}

# --- Installation des dependances (optionnel) ---
if ($Install) {
    Write-Step 'composer install (backend)'
    Push-Location $Backend; composer install --no-interaction; Pop-Location
    # npm ci, et non npm install : installe exactement les versions du lock
    # (Vue 3.5.44 epinglee) sans le reecrire.
    Write-Step 'npm ci (frontend)'
    Push-Location $Frontend; npm ci; Pop-Location
}
if (-not (Test-Path (Join-Path $Backend 'vendor')))        { Fail 'vendor/ absent - relance avec -Install.' }
if (-not (Test-Path (Join-Path $Frontend 'node_modules'))) { Fail 'node_modules/ absent - relance avec -Install.' }

# --- Migrations / seed ---
Push-Location $Backend
if ($Fresh) {
    Write-Step 'migrate:fresh --seed (DESTRUCTIF)'
    php artisan migrate:fresh --seed --force
} else {
    Write-Step 'migrate (migrations en attente)'
    php artisan migrate --force
}
Pop-Location

# --- Lancement des services dans des fenetres dediees ---
function Start-DevWindow($title, $dir, $cmd) {
    Start-Process powershell -WorkingDirectory $dir -ArgumentList @(
        '-NoExit', '-Command',
        "`$host.UI.RawUI.WindowTitle = '$title'; Write-Host '>> $title' -ForegroundColor Magenta; $cmd"
    )
}

Write-Step 'Demarrage des services'
Start-DevWindow 'ANaTT - API (8000)' $Backend 'php artisan serve'
Write-Ok 'API      -> http://localhost:8000'

if (-not $NoWorker) {
    Start-DevWindow 'ANaTT - Queue worker' $Backend 'php artisan queue:work --queue=paiements,etats,default --tries=3'
    Write-Ok 'Worker   -> files paiements,etats,default'
} else {
    Write-Warn2 'Worker non lance (-NoWorker).'
}

Start-DevWindow 'ANaTT - Frontend (5173)' $Frontend 'npm run dev'
Write-Ok 'Frontend -> http://localhost:5173'

Write-Host ''
Write-Host 'Application : ' -NoNewline; Write-Host 'http://localhost:5173' -ForegroundColor Green
Write-Host 'Comptes de demo (mot de passe : password) :'
Write-Host '  admin@anatt.bj      (admin)'
Write-Host '  gestion@anatt.bj    (gestionnaire_om)'
Write-Host '  finance@anatt.bj    (agent_financier)'
Write-Host '  lecteur@anatt.bj    (lecteur)'
Write-Host ''
Write-Host 'Ferme chaque fenetre (Ctrl+C) pour arreter le service correspondant.' -ForegroundColor DarkGray
