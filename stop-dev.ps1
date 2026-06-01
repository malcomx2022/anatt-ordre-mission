<#
.SYNOPSIS
    Arrete l'environnement de developpement ANaTT lance par start-dev.ps1.

.DESCRIPTION
    Stoppe les 3 services :
      - API Laravel  (php artisan serve)   sur le port 8000
      - Frontend Vite (node)               sur le port 5173
      - Worker de file (php artisan queue:work)
    Puis ferme les fenetres PowerShell intitulees "ANaTT - *".

.PARAMETER ApiPort
    Port du serveur API (defaut 8000).

.PARAMETER FrontPort
    Port du serveur Vite (defaut 5173).

.EXAMPLE
    .\stop-dev.ps1
    .\stop-dev.ps1 -FrontPort 5174
#>

[CmdletBinding()]
param(
    [int]$ApiPort = 8000,
    [int]$FrontPort = 5173
)

function Write-Step($msg) { Write-Host "==> $msg" -ForegroundColor Cyan }

# Arrete le(s) processus a l'ecoute d'un port donne.
function Stop-ByPort([int]$port, [string]$label) {
    $procIds = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue |
        Select-Object -ExpandProperty OwningProcess -Unique
    if (-not $procIds) {
        Write-Host "    -   $label (port $port) : aucun processus a l'ecoute" -ForegroundColor DarkGray
        return
    }
    foreach ($procId in $procIds) {
        $p = Get-Process -Id $procId -ErrorAction SilentlyContinue
        Stop-Process -Id $procId -Force -ErrorAction SilentlyContinue
        Write-Host ("    OK  {0} arrete : PID {1} ({2})" -f $label, $procId, $p.ProcessName) -ForegroundColor Green
    }
}

# Arrete les processus php.exe dont la ligne de commande correspond au motif.
function Stop-PhpByCommandLine([string]$pattern, [string]$label) {
    $procs = Get-CimInstance Win32_Process -Filter "Name='php.exe'" -ErrorAction SilentlyContinue |
        Where-Object { $_.CommandLine -match $pattern }
    if (-not $procs) {
        Write-Host "    -   $label : aucun processus" -ForegroundColor DarkGray
        return
    }
    foreach ($pr in $procs) {
        Stop-Process -Id $pr.ProcessId -Force -ErrorAction SilentlyContinue
        Write-Host ("    OK  {0} arrete : PID {1}" -f $label, $pr.ProcessId) -ForegroundColor Green
    }
}

Write-Step ("Arret de l'API (port {0})" -f $ApiPort)
Stop-ByPort $ApiPort 'API'
Stop-PhpByCommandLine 'artisan serve' 'API (artisan serve)'

Write-Step ("Arret du frontend Vite (port {0})" -f $FrontPort)
Stop-ByPort $FrontPort 'Frontend'

Write-Step 'Arret du worker de file'
Stop-PhpByCommandLine 'artisan queue:work' 'Worker'

Write-Step 'Fermeture des fenetres "ANaTT - *"'
$wins = Get-Process powershell, pwsh -ErrorAction SilentlyContinue |
    Where-Object { $_.MainWindowTitle -like 'ANaTT - *' -and $_.Id -ne $PID }
if ($wins) {
    $wins | ForEach-Object {
        Stop-Process -Id $_.Id -Force -ErrorAction SilentlyContinue
        Write-Host ("    OK  fenetre fermee : {0}" -f $_.MainWindowTitle) -ForegroundColor Green
    }
} else {
    Write-Host '    -   aucune fenetre ANaTT ouverte' -ForegroundColor DarkGray
}

Write-Host ''
Write-Host 'Environnement de developpement ANaTT arrete.' -ForegroundColor Green
