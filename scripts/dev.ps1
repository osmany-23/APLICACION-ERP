$ErrorActionPreference = 'Stop'

$Root = Split-Path -Parent $PSScriptRoot
$Backend = Join-Path $Root 'backend'
$Frontend = Join-Path $Root 'frontend'
$ApiHealth = 'http://127.0.0.1:8000/api/health'

function Test-ApiHealth {
    try {
        $response = Invoke-WebRequest -Uri $ApiHealth -UseBasicParsing -TimeoutSec 2
        return $response.StatusCode -eq 200
    } catch {
        return $false
    }
}

if (-not (Test-ApiHealth)) {
    Write-Host 'Iniciando Laravel API en http://127.0.0.1:8000 ...'
    Start-Process -FilePath 'php' `
        -ArgumentList @('artisan', 'serve', '--host=127.0.0.1', '--port=8000') `
        -WorkingDirectory $Backend `
        -WindowStyle Hidden | Out-Null

    $ready = $false
    for ($attempt = 1; $attempt -le 20; $attempt++) {
        Start-Sleep -Milliseconds 500
        if (Test-ApiHealth) {
            $ready = $true
            break
        }
    }

    if (-not $ready) {
        throw 'Laravel no respondio en http://127.0.0.1:8000. Revisa XAMPP/MySQL y backend/storage/logs/laravel.log.'
    }
}

Write-Host 'Laravel API listo en http://127.0.0.1:8000'
Write-Host 'Abriendo Sistema ERP...'

& npm --prefix $Frontend run dev
