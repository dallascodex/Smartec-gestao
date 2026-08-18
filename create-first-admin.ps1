$ErrorActionPreference = 'Stop'

$setupKey = Read-Host 'Chave de instalação gerada no passo anterior'
$name = Read-Host 'Nome do administrador'
$email = Read-Host 'E-mail do administrador'
$passwordSecure = Read-Host 'Senha do administrador (mínimo 10 caracteres)' -AsSecureString
$pointer = [IntPtr]::Zero
try {
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($passwordSecure)
    $password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    $response = Invoke-RestMethod -Method Post -Uri 'https://sistema.gessosolution.com.br/api/index.php?resource=auth/setup' -Headers @{ 'X-Setup-Key' = $setupKey } -ContentType 'application/json' -Body (@{ name = $name; email = $email; password = $password } | ConvertTo-Json)
    Write-Host "Administrador criado com ID $($response.id)." -ForegroundColor Green
} finally {
    if ($pointer -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }
}
