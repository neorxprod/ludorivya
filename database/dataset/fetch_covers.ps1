# =============================================================
#  Telecharge les jaquettes officielles des jeux du dataset depuis
#  le CDN public de Steam, vers public/assets/img/covers/.
#  Le telechargement valide aussi l'appid : un appid inexistant
#  echoue et le jeu sera ecarte par build_seed.php.
#
#  Usage : powershell -File fetch_covers.ps1   (depuis ce dossier)
# =============================================================

$ErrorActionPreference = 'Continue'
$ProgressPreference = 'SilentlyContinue'

$games = Get-Content "$PSScriptRoot\games.json" -Raw -Encoding UTF8 | ConvertFrom-Json
$dir = Resolve-Path "$PSScriptRoot\..\..\public\assets\img\covers"

$ok = 0
$fail = @()

foreach ($g in $games) {
    $dest = Join-Path $dir "$($g.appid).jpg"
    if (Test-Path $dest) { $ok++; continue }   # deja telechargee

    $urls = @(
        "https://cdn.cloudflare.steamstatic.com/steam/apps/$($g.appid)/library_600x900.jpg",
        "https://shared.cloudflare.steamstatic.com/store_item_assets/steam/apps/$($g.appid)/library_600x900.jpg"
    )

    $done = $false
    foreach ($url in $urls) {
        try {
            Invoke-WebRequest -Uri $url -OutFile $dest -UseBasicParsing -TimeoutSec 15 | Out-Null
            if ((Get-Item $dest).Length -gt 5000) { $done = $true; break }
            Remove-Item $dest -Force
        } catch {}
    }

    if ($done) { $ok++ } else { $fail += $g.title }
}

Write-Output "Jaquettes OK : $ok / $($games.Count)"
if ($fail.Count -gt 0) { Write-Output "Echecs : $($fail -join ' | ')" }
