param(
    [string] $OutputDirectory
)

$ErrorActionPreference = 'Stop'
$sourceRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$installSlug = 'm360-mega-bolao-360'
$mainFile = 'mengao360-bolao.php'

if ([string]::IsNullOrWhiteSpace($OutputDirectory)) {
    $OutputDirectory = [IO.Path]::GetFullPath((Join-Path $sourceRoot '..\work'))
}
if (-not (Test-Path -LiteralPath $OutputDirectory -PathType Container)) {
    New-Item -ItemType Directory -Path $OutputDirectory | Out-Null
}

$header = [IO.File]::ReadAllText((Join-Path $sourceRoot $mainFile))
$match = [regex]::Match($header, '(?m)^\s*\*\s*Version:\s*([^\s]+)\s*$')
if (-not $match.Success) {
    throw 'Versão do plugin não encontrada.'
}

$version = $match.Groups[1].Value
$package = Join-Path $OutputDirectory "$installSlug-$version.zip"
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$stream = [IO.File]::Open($package, [IO.FileMode]::Create)
try {
    $zip = [IO.Compression.ZipArchive]::new(
        $stream,
        [IO.Compression.ZipArchiveMode]::Create,
        $false
    )
    try {
        Get-ChildItem -LiteralPath $sourceRoot -Recurse -File |
            Where-Object {
                $relative = $_.FullName.Substring($sourceRoot.Length).TrimStart('\', '/')
                $relative -notmatch '^(?:\.git|scripts)[\\/]'
            } |
            ForEach-Object {
                $relative = $_.FullName.Substring($sourceRoot.Length).TrimStart('\', '/').Replace('\', '/')
                [IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                    $zip,
                    $_.FullName,
                    "$installSlug/$relative",
                    [IO.Compression.CompressionLevel]::Optimal
                ) | Out-Null
            }
    } finally {
        $zip.Dispose()
    }
} finally {
    $stream.Dispose()
}

$check = [IO.Compression.ZipFile]::OpenRead($package)
try {
    $entries = @($check.Entries)
    if (@($entries | Where-Object FullName -eq "$installSlug/$mainFile").Count -ne 1) {
        throw 'Arquivo principal ausente ou duplicado.'
    }
    if (@($entries | Where-Object { $_.FullName.Contains('\') }).Count -ne 0) {
        throw 'Caminho ZIP incompatível com Linux.'
    }
    if (@($entries | Where-Object {
        -not $_.FullName.StartsWith("$installSlug/", [StringComparison]::Ordinal)
    }).Count -ne 0) {
        throw 'Mais de uma pasta raiz no ZIP.'
    }
} finally {
    $check.Dispose()
}

$hash = Get-FileHash -LiteralPath $package -Algorithm SHA256
[pscustomobject]@{
    Package = $package
    Version = $version
    InstallPath = "$installSlug/$mainFile"
    SHA256 = $hash.Hash
}
