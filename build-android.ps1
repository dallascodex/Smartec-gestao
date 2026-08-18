$ErrorActionPreference = 'Stop'

$projectRoot = $PSScriptRoot
$toolsRoot = Join-Path $projectRoot '.tools'
$javaHome = Join-Path $toolsRoot 'jdk17-portable\jdk-17.0.20+8'
$androidSdk = Join-Path $toolsRoot 'android-sdk'
$androidProject = Join-Path $projectRoot 'android-twa'
$privateRoot = 'C:\Users\delis\Documents\Codex\smartec-android-private'
$keystore = Join-Path $privateRoot 'smartec-release.keystore'
$credentialFile = Join-Path $privateRoot 'LEIA-ME-CREDENCIAL-DE-ASSINATURA.txt'

foreach ($path in @($javaHome, $androidSdk, $androidProject, $keystore, $credentialFile)) {
    if (-not (Test-Path -LiteralPath $path)) {
        throw "Arquivo necessário não encontrado: $path"
    }
}

$passwordLine = Get-Content -LiteralPath $credentialFile | Where-Object { $_ -like 'Senha do cofre*' }
$password = $passwordLine -replace '^Senha do cofre e da chave: ', ''
if ([string]::IsNullOrWhiteSpace($password)) {
    throw 'Não foi possível ler a credencial de assinatura privada.'
}

$env:JAVA_HOME = $javaHome
$env:PATH = "$javaHome\bin;$env:PATH"
$env:ANDROID_SDK_ROOT = $androidSdk
$env:ANDROID_USER_HOME = Join-Path $env:LOCALAPPDATA 'smartec-android-user'
$env:GRADLE_USER_HOME = Join-Path $env:LOCALAPPDATA 'smartec-gradle-cache'
$env:JAVA_TOOL_OPTIONS = "-Duser.home=$env:ANDROID_USER_HOME"
New-Item -ItemType Directory -Force -Path $env:ANDROID_USER_HOME, $env:GRADLE_USER_HOME | Out-Null

Push-Location $androidProject
try {
    Write-Host 'Compilando o APK Android do Smartec...'
    & '.\gradlew.bat' --no-daemon assembleRelease

    $unsignedApk = Join-Path $androidProject 'app\build\outputs\apk\release\app-release-unsigned.apk'
    if (-not (Test-Path -LiteralPath $unsignedApk)) {
        throw 'O Gradle não produziu o APK de release.'
    }

    $buildTools = Get-ChildItem (Join-Path $androidSdk 'build-tools') -Directory |
        Sort-Object { [version]$_.Name } -Descending |
        Select-Object -First 1
    if (-not $buildTools) {
        throw 'Android Build Tools não encontrado.'
    }

    $zipalign = Join-Path $buildTools.FullName 'zipalign.exe'
    $apksigner = Join-Path $buildTools.FullName 'apksigner.bat'
    $alignedApk = Join-Path $androidProject 'smartec-gestao-aligned.apk'
    $signedApk = Join-Path $androidProject 'smartec-gestao-v1.0.0.apk'

    & $zipalign -f 4 $unsignedApk $alignedApk
    & $apksigner sign --ks $keystore --ks-key-alias smartec --ks-pass "pass:$password" --key-pass "pass:$password" --out $signedApk $alignedApk
    & $apksigner verify --verbose --print-certs $signedApk

    $outputDir = 'C:\Users\delis\Documents\Codex\2026-08-17\c\outputs'
    New-Item -ItemType Directory -Force -Path $outputDir | Out-Null
    Copy-Item -LiteralPath $signedApk -Destination (Join-Path $outputDir 'Smartec-gestao-v1.0.0.apk') -Force
    Write-Host "APK assinado pronto: $signedApk"
}
finally {
    Pop-Location
}
