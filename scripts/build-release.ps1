param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$')]
    [string] $Version,

    [string] $OutputDir = 'dist'
)

$ErrorActionPreference = 'Stop'

$root = Resolve-Path (Join-Path $PSScriptRoot '..')
$pluginSlug = Split-Path $root -Leaf
$outputPath = Join-Path $root $OutputDir
$zipPath = Join-Path $outputPath "$pluginSlug-$Version.zip"

function Update-TextFile {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Path,

        [Parameter(Mandatory = $true)]
        [scriptblock] $Update
    )

    $fullPath = Join-Path $root $Path
    if (-not (Test-Path $fullPath)) {
        return
    }

    $encoding = [System.Text.UTF8Encoding]::new($false)
    $content = [System.IO.File]::ReadAllText($fullPath, $encoding)
    $newline = if ($content.Contains("`r`n")) { "`r`n" } elseif ($content.Contains("`r")) { "`r" } else { "`n" }
    $updated = & $Update $content

    if ($updated -ne $content) {
        $updated = $updated.Replace("`r`n", "`n").Replace("`r", "`n")
        if ($newline -ne "`n") {
            $updated = $updated.Replace("`n", $newline)
        }

        [System.IO.File]::WriteAllText($fullPath, $updated, $encoding)
        Write-Host "Atualizado: $Path"
    }
}

function ConvertTo-RelativePath {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Path
    )

    $rootUri = [System.Uri]::new(($root.ProviderPath.TrimEnd('\') + '\'))
    $pathUri = [System.Uri]::new($Path)
    $relative = [System.Uri]::UnescapeDataString($rootUri.MakeRelativeUri($pathUri).ToString())
    return ($relative -replace '\\', '/')
}

function Get-DistIgnorePatterns {
    $patterns = @('.git', '.github', $OutputDir)
    $distIgnore = Join-Path $root '.distignore'

    if (Test-Path $distIgnore) {
        $patterns += Get-Content $distIgnore |
            ForEach-Object { $_.Trim() } |
            Where-Object { $_ -and -not $_.StartsWith('#') }
    }

    return $patterns |
        ForEach-Object { $_.Trim('/').Replace('\', '/') } |
        Where-Object { $_ } |
        Select-Object -Unique
}

function Test-IsIgnored {
    param(
        [Parameter(Mandatory = $true)]
        [string] $RelativePath,

        [Parameter(Mandatory = $true)]
        [string[]] $Patterns
    )

    foreach ($pattern in $Patterns) {
        if (
            $RelativePath -eq $pattern -or
            $RelativePath.StartsWith("$pattern/") -or
            (Split-Path $RelativePath -Leaf) -eq $pattern
        ) {
            return $true
        }
    }

    return $false
}

Update-TextFile -Path 'cepcerto.php' -Update {
    param($content)
    $content = [regex]::Replace($content, '(\* Version:\s*)[^\r\n]+', "`${1}$Version")
    $content = [regex]::Replace($content, "(define\(\s*'CEPCERTO_VERSION'\s*,\s*')[^']+('\s*\);)", "`${1}$Version`${2}")
    return $content
}

Update-TextFile -Path 'readme.txt' -Update {
    param($content)
    return [regex]::Replace($content, '(Stable tag:\s*)[^\r\n]+', "`${1}$Version")
}

Update-TextFile -Path 'languages/cepcerto.pot' -Update {
    param($content)
    return [regex]::Replace($content, '(Project-Id-Version:\s*CepCerto\s+)[^\\]+(\\n)', "`${1}$Version`${2}")
}

if (-not (Test-Path $outputPath)) {
    New-Item -ItemType Directory -Path $outputPath | Out-Null
}

if (Test-Path $zipPath) {
    Remove-Item -LiteralPath $zipPath
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$patterns = @(Get-DistIgnorePatterns)
$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)

try {
    Get-ChildItem -Path $root -Recurse -File -Force |
        ForEach-Object {
            $relative = ConvertTo-RelativePath -Path $_.FullName

            if (Test-IsIgnored -RelativePath $relative -Patterns $patterns) {
                return
            }

            $entryName = "$pluginSlug/$relative"
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                $zip,
                $_.FullName,
                $entryName,
                [System.IO.Compression.CompressionLevel]::Optimal
            ) | Out-Null
        }
}
finally {
    $zip.Dispose()
}

Write-Host "ZIP gerado: $zipPath"
