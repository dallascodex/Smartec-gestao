$ErrorActionPreference = 'Stop'

$sshTarget = 'hostinger'
$remotePath = '/home/u285543561/smartec-private'
$database = 'u285543561_smartec_app'
$username = 'u285543561_smartec_app'

$passwordSecure = Read-Host 'Senha do MySQL (não será exibida)' -AsSecureString
$pointer = [IntPtr]::Zero
$pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($passwordSecure)
try {
    $password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    $setupKey = -join ((33..126) | Get-Random -Count 32 | ForEach-Object {[char]$_})
    $escapedPassword = $password.Replace('\', '\\').Replace("'", "\'")
    $escapedSetupKey = $setupKey.Replace('\', '\\').Replace("'", "\'")
    $config = @"
<?php
return [
    'db' => [
        'host' => 'localhost',
        'database' => '$database',
        'username' => '$username',
        'password' => '$escapedPassword',
        'charset' => 'utf8mb4',
    ],
    'setup_key' => '$escapedSetupKey',
];
"@
    $temp = Join-Path ([System.IO.Path]::GetTempPath()) "smartec-config-$([guid]::NewGuid()).php"
    [System.IO.File]::WriteAllText($temp, $config, [System.Text.UTF8Encoding]::new($false))
    try {
        Write-Host 'Enviando configuração privada...' -ForegroundColor Cyan
        & ssh -n $sshTarget "mkdir -p $remotePath"
        if ($LASTEXITCODE -ne 0) { throw "Falha ao preparar a pasta privada (código $LASTEXITCODE)." }
        & scp -O $temp "${sshTarget}:$remotePath/config.php"
        if ($LASTEXITCODE -ne 0) { throw "Falha ao enviar a configuração (código $LASTEXITCODE)." }
        Write-Host 'Validando a conexão com o MySQL...' -ForegroundColor Cyan
        try {
            $health = Invoke-RestMethod -Method Get -Uri 'https://sistema.gessosolution.com.br/api/index.php?resource=health'
            if ($health.status -ne 'ok') { throw 'Resposta inesperada da API.' }
        } catch {
            throw 'A configuração chegou ao servidor, mas o MySQL não aceitou a conexão. Confira a senha do banco no hPanel e execute este script novamente.'
        }
        Write-Host 'API configurada. Guarde esta chave de instalação, usada somente para criar o primeiro administrador:' -ForegroundColor Green
        Write-Host $setupKey -ForegroundColor Yellow
    } finally { Remove-Item -LiteralPath $temp -Force -ErrorAction SilentlyContinue }
} finally {
    if ($pointer -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }
}
