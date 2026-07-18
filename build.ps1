#Requires -Version 5.1
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

# ---------------------------------------------------------------------------
# Pfade & Version
# ---------------------------------------------------------------------------
$root   = $PSScriptRoot
$pkgXml = Join-Path $root 'pkg_benutzerbilder.xml'
$dist   = Join-Path $root 'dist'

[xml]$manifest = Get-Content $pkgXml -Encoding UTF8
$version = $manifest.extension.version.Trim()
$outZip  = Join-Path $dist "pkg_benutzerbilder_v${version}.zip"

# ---------------------------------------------------------------------------
# Verzeichnis -> byte[] als ZIP (immer Forward-Slashes)
# ---------------------------------------------------------------------------
function ConvertTo-ZipBytes ([string]$Dir) {
    $ms  = [System.IO.MemoryStream]::new()
    $arc = [System.IO.Compression.ZipArchive]::new(
               $ms, [System.IO.Compression.ZipArchiveMode]::Create, $true)

    Get-ChildItem -Path $Dir -Recurse -File | ForEach-Object {
        $entryName = $_.FullName.Substring($Dir.Length + 1) -replace '\\', '/'
        $e = $arc.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::Optimal)
        $dst = $e.Open()
        $src = [System.IO.File]::OpenRead($_.FullName)
        $src.CopyTo($dst)
        $src.Dispose(); $dst.Dispose()
    }

    $arc.Dispose()
    $bytes = $ms.ToArray()
    $ms.Dispose()
    return $bytes
}

# ---------------------------------------------------------------------------
# Build
# ---------------------------------------------------------------------------
Write-Host "`n=== pkg_benutzerbilder v$version ===" -ForegroundColor Cyan

if (-not (Test-Path $dist)) { New-Item -ItemType Directory -Path $dist | Out-Null }
if (Test-Path $outZip)      { Remove-Item $outZip -Force }

Write-Host '[ 1/4 ] plg_ajax_protectedimage  ...' -NoNewline
$ajaxBytes = ConvertTo-ZipBytes (Join-Path $root 'packages\plg_ajax_protectedimage')
Write-Host ' OK' -ForegroundColor Green

Write-Host '[ 2/4 ] plg_content_benutzerimages ...' -NoNewline
$contentBytes = ConvertTo-ZipBytes (Join-Path $root 'packages\plg_content_benutzerimages')
Write-Host ' OK' -ForegroundColor Green

Write-Host '[ 3/5 ] plg_system_benutzerimages  ...' -NoNewline
$systemBytes = ConvertTo-ZipBytes (Join-Path $root 'packages\plg_system_benutzerimages')
Write-Host ' OK' -ForegroundColor Green

Write-Host '[ 4/5 ] plg_editors-xtd_benutzerimages ...' -NoNewline
$editorBytes = ConvertTo-ZipBytes (Join-Path $root 'packages\plg_editors-xtd_benutzerimages')
Write-Host ' OK' -ForegroundColor Green

Write-Host '[ 5/5 ] Paket-ZIP zusammenstellen ...' -NoNewline

$pkgMs  = [System.IO.MemoryStream]::new()
$pkgArc = [System.IO.Compression.ZipArchive]::new(
              $pkgMs, [System.IO.Compression.ZipArchiveMode]::Create, $true)

# Manifest an der Wurzel
$e = $pkgArc.CreateEntry('pkg_benutzerbilder.xml', [System.IO.Compression.CompressionLevel]::Optimal)
$s = $e.Open()
$fs = [System.IO.File]::OpenRead($pkgXml)
$fs.CopyTo($s); $fs.Dispose(); $s.Dispose()

# Plugin-ZIPs im packages/-Unterordner (forward slash, unkomprimiert da bereits ZIP)
foreach ($item in @(
    @{ name = 'packages/plg_ajax_protectedimage.zip';    data = $ajaxBytes    }
    @{ name = 'packages/plg_content_benutzerimages.zip'; data = $contentBytes }
    @{ name = 'packages/plg_system_benutzerimages.zip';       data = $systemBytes  }
    @{ name = 'packages/plg_editors-xtd_benutzerimages.zip'; data = $editorBytes  }
)) {
    $e = $pkgArc.CreateEntry($item.name, [System.IO.Compression.CompressionLevel]::NoCompression)
    $s = $e.Open()
    $s.Write($item.data, 0, $item.data.Length)
    $s.Dispose()
}

$pkgArc.Dispose()
[System.IO.File]::WriteAllBytes($outZip, $pkgMs.ToArray())
$pkgMs.Dispose()

Write-Host ' OK' -ForegroundColor Green

# ---------------------------------------------------------------------------
# Inhalt zur Kontrolle ausgeben
# ---------------------------------------------------------------------------
Write-Host "`nStruktur im ZIP:" -ForegroundColor Cyan
$check = [System.IO.Compression.ZipFile]::OpenRead($outZip)
$check.Entries | ForEach-Object { Write-Host "  $($_.FullName)" }
$check.Dispose()

$kb = [math]::Round((Get-Item $outZip).Length / 1KB, 1)
Write-Host "`nFertig: dist\pkg_benutzerbilder_v${version}.zip  ($kb KB)`n" -ForegroundColor Green
