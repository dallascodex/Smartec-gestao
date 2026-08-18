$ErrorActionPreference = 'Stop'

$sshTarget = 'hostinger'
$remotePath = '/home/u285543561/domains/gessosolution.com.br/public_html/sistema'
$projectRoot = $PSScriptRoot

$deployItems = @('index.html', 'app.js', 'data.js', 'styles.css', 'manifest.webmanifest', 'service-worker.js', 'assets', 'api')
$missing = $deployItems | Where-Object { -not (Test-Path -LiteralPath (Join-Path $projectRoot $_)) }
if ($missing) { throw "Arquivos ausentes: $($missing -join ', ')" }

$stagePath = Join-Path ([System.IO.Path]::GetTempPath()) "smartec-deploy-$([guid]::NewGuid().ToString())"
$archivePath = "$stagePath.zip"
$remoteArchive = "/tmp/smartec-deploy-$([guid]::NewGuid().ToString()).zip"
New-Item -ItemType Directory -Path $stagePath | Out-Null

function Invoke-CheckedCommand {
    param([scriptblock]$Command, [string]$Description, [int]$Attempts = 3)
    for ($attempt = 1; $attempt -le $Attempts; $attempt++) {
        & $Command
        if ($LASTEXITCODE -eq 0) { return }
        if ($attempt -lt $Attempts) {
            Write-Host "Conexão falhou ao $Description. Tentando novamente ($attempt/$Attempts)..." -ForegroundColor Yellow
            Start-Sleep -Seconds 2
        }
    }
    throw "Falha em: $Description após $Attempts tentativas (código $LASTEXITCODE)"
}

try {
    Write-Host 'Preparando arquivos...' -ForegroundColor Cyan
    foreach ($item in $deployItems) {
        Copy-Item -LiteralPath (Join-Path $projectRoot $item) -Destination $stagePath -Recurse
    }
    # A configuração real contém a senha do banco e permanece somente no servidor.
    Remove-Item -LiteralPath (Join-Path $stagePath 'api\config.php') -Force -ErrorAction SilentlyContinue

    # Um único arquivo evita que o servidor derrube conexões SCP sucessivas.
    Compress-Archive -Path (Join-Path $stagePath '*') -DestinationPath $archivePath -Force
    Write-Host 'Enviando pacote de publicação...' -ForegroundColor Cyan
    Invoke-CheckedCommand { scp -O $archivePath "${sshTarget}:$remoteArchive" } 'enviar pacote'
    Write-Host 'Extraindo arquivos no servidor...' -ForegroundColor Cyan
    Invoke-CheckedCommand { ssh -n $sshTarget "mkdir -p $remotePath && unzip -oq $remoteArchive -d $remotePath && rm -f $remoteArchive" } 'extrair pacote'

    Write-Host "Deploy concluído: https://sistema.gessosolution.com.br" -ForegroundColor Green
}
finally {
    Remove-Item -LiteralPath $stagePath -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $archivePath -Force -ErrorAction SilentlyContinue
}
