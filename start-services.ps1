<#
  Chạy TOÀN BỘ hệ thống CashFlow bằng code thật trong services/ (không dùng bản PHP thay thế).
  Toolchain portable nằm trong .tools/ (JDK 17, Maven, Go 1.22, .NET 10 SDK) - không cần Docker / quyền admin.

  Dùng:   powershell -ExecutionPolicy Bypass -File start-services.ps1
  Dừng:   powershell -ExecutionPolicy Bypass -File start-services.ps1 -Stop

  Yêu cầu sẵn có: XAMPP (PHP + MySQL đang chạy, root/root_password), Node.js, Python 3.
#>
param([switch]$Stop, [switch]$NoBuild, [switch]$KeepAlive)

$ErrorActionPreference = 'Stop'
$root  = $PSScriptRoot
$tools = Join-Path $root '.tools'
$php   = 'C:\xampp\php\php.exe'

$ports = 3000, 8080, 8081, 8082, 8083, 8084, 8085, 8086

function Stop-Port([int]$port) {
    Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue |
        Select-Object -ExpandProperty OwningProcess -Unique |
        ForEach-Object { Stop-Process -Id $_ -Force -ErrorAction SilentlyContinue }
}

if ($Stop) {
    $ports | ForEach-Object { Stop-Port $_ }
    Write-Host 'Đã dừng toàn bộ service.' -ForegroundColor Yellow
    return
}

# ---- toolchain portable
$env:JAVA_HOME   = (Get-ChildItem "$tools\jdk*" -Directory | Select-Object -First 1).FullName
$env:MAVEN_HOME  = (Get-ChildItem "$tools\apache-maven*" -Directory | Select-Object -First 1).FullName
$env:GOROOT      = "$tools\go"
$env:GOPATH      = "$tools\gopath"
$env:GOMODCACHE  = "$tools\gopath\pkg\mod"
$env:GOTOOLCHAIN = 'local'
$env:DOTNET_ROOT = "$tools\dotnet"
$env:DOTNET_CLI_TELEMETRY_OPTOUT = '1'
$env:NUGET_PACKAGES = "$tools\nuget"
$env:Path = "$env:JAVA_HOME\bin;$env:MAVEN_HOME\bin;$env:GOROOT\bin;$env:DOTNET_ROOT;$env:Path"

# ---- cấu hình dùng chung (MySQL của XAMPP chạy cổng 3306)
$env:DB_HOST = '127.0.0.1'; $env:DB_PORT = '3306'; $env:DB_USER = 'root'; $env:DB_PASSWORD = 'root_password'
$env:JWT_SECRET = 'cashflow-auth-midterm-development-secret-key'
# Dùng 127.0.0.1 (không dùng localhost): trên Windows localhost thử IPv6 trước -> +~200ms mỗi request.
# Asset service giữ 'localhost' vì Kestrel chỉ chấp nhận Host = localhost (lắng nghe cả IPv4/IPv6 nên vẫn nhanh).
$env:AUTH_SERVICE_URL         = 'http://127.0.0.1:8081'
$env:TRANSACTION_SERVICE_URL  = 'http://127.0.0.1:8082'
$env:ASSET_SERVICE_URL        = 'http://localhost:8083'
$env:ANALYTICS_SERVICE_URL    = 'http://127.0.0.1:8084'
$env:INTEGRATION_SERVICE_URL  = 'http://127.0.0.1:8085'
$env:NOTIFICATION_SERVICE_URL = 'http://127.0.0.1:8086'

# ---- build
$authDir  = "$root\services\auth-service\midterm"
$assetDir = "$root\services\asset-service\midterm"
$intDir   = "$root\services\integration-service\midterm"
$bin      = "$tools\build"
New-Item -ItemType Directory -Force $bin | Out-Null

if (-not $NoBuild) {
    Write-Host '[build] auth-service (Java 17 / Maven)...' -ForegroundColor Cyan
    & mvn -q -B -f "$authDir\pom.xml" "-Dmaven.repo.local=$tools\m2" -DskipTests package dependency:copy-dependencies "-DoutputDirectory=$bin\auth-lib"
    if ($LASTEXITCODE) { throw 'Build auth-service lỗi' }

    Write-Host '[build] asset-service (.NET 10)...' -ForegroundColor Cyan
    & dotnet build "$assetDir\AssetService.csproj" -c Release -o "$bin\asset" --nologo -v q
    if ($LASTEXITCODE) { throw 'Build asset-service lỗi' }

    Write-Host '[build] integration-service (Go 1.22)...' -ForegroundColor Cyan
    Push-Location $intDir; & go build -o "$bin\integration-service.exe" ./src; $rc = $LASTEXITCODE; Pop-Location
    if ($rc) { throw 'Build integration-service lỗi' }
}

$ports | ForEach-Object { Stop-Port $_ }
$logs = "$tools\logs"; New-Item -ItemType Directory -Force $logs | Out-Null

function Start-Svc([string]$name, [string]$file, [string[]]$argv, [hashtable]$envs = @{}, [string]$cwd = $root) {
    foreach ($k in $envs.Keys) { Set-Item "env:$k" $envs[$k] }
    $sp = @{ FilePath = $file; WorkingDirectory = $cwd; WindowStyle = 'Hidden'; PassThru = $true
             RedirectStandardOutput = "$logs\$name.out.log"; RedirectStandardError = "$logs\$name.err.log" }
    if ($argv -and $argv.Count) { $sp.ArgumentList = $argv }
    $p = Start-Process @sp
    foreach ($k in $envs.Keys) { Remove-Item "env:$k" -ErrorAction SilentlyContinue }
    Write-Host ("  {0,-22} PID {1}" -f $name, $p.Id)
}

Write-Host '[start]' -ForegroundColor Cyan
$authJar = (Get-ChildItem "$authDir\target\auth-service-*.jar" | Select-Object -First 1).FullName
Start-Svc 'auth-service'        "$env:JAVA_HOME\bin\java.exe" @('-cp', "`"$authJar;$bin\auth-lib\*`"", 'com.cashflow.auth.Main') @{ DB_NAME = 'auth_db' }
Start-Svc 'transaction-service' $php @('-d', 'date.timezone=Asia/Ho_Chi_Minh', '-S', '127.0.0.1:8082', '-t', 'services/transaction-service/midterm/src', 'services/transaction-service/midterm/src/index.php') @{ DB_NAME = 'transaction_db' }
Start-Svc 'asset-service'       "$bin\asset\AssetService.exe" @() @{ PORT = '8083'; LISTEN_HOST = 'localhost' }
Start-Svc 'analytics-service'   'python' @('services/analytics-service/midterm/src/main.py') @{ HOST = '127.0.0.1'; PORT = '8084' }
Start-Svc 'integration-service' "$bin\integration-service.exe" @() @{ PORT = '8085'; DB_NAME = 'integration_db' }
Start-Svc 'notification-service' 'node' @('services/notification-service/midterm/src/index.js')
Start-Svc 'api-gateway'         'node' @('gateway/midterm/src/index.js')
Start-Svc 'frontend'            $php @('-S', '127.0.0.1:3000', '-t', 'frontend', 'frontend/router.php')

Start-Sleep -Seconds 4
Write-Host '[health]' -ForegroundColor Cyan
foreach ($p in 8081..8086) {
    # auth/asset không có route /health -> chỉ cần có phản hồi HTTP (kể cả 404) là service đang chạy
    $code = try { (Invoke-WebRequest "http://127.0.0.1:$p/health" -UseBasicParsing -TimeoutSec 3).StatusCode }
            catch { if ($_.Exception.Response) { [int]$_.Exception.Response.StatusCode } else { 0 } }
    if ($code) { Write-Host "  :$p  UP (HTTP $code)" -ForegroundColor Green }
    else { Write-Host "  :$p  DOWN (xem $logs)" -ForegroundColor Red }
}
Write-Host "`nMở http://127.0.0.1:3000  (admin / password123)" -ForegroundColor Green
if ($KeepAlive) {
    Write-Host 'Đang giữ các service chạy (Ctrl+C rồi chạy với -Stop để dừng)...'
    while ($true) { Start-Sleep -Seconds 3600 }
}
