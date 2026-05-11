Add-Type -AssemblyName System.Drawing

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
$referencePath = Join-Path $repoRoot 'branding\mcd-circle-reference.png'

if (-not (Test-Path $referencePath)) {
    throw "Reference logo image not found at $referencePath"
}

function Get-CircleBounds {
    param(
        [System.Drawing.Bitmap]$Bitmap
    )

    $minX = $Bitmap.Width
    $minY = $Bitmap.Height
    $maxX = -1
    $maxY = -1

    for ($y = 0; $y -lt $Bitmap.Height; $y++) {
        for ($x = 0; $x -lt $Bitmap.Width; $x++) {
            $pixel = $Bitmap.GetPixel($x, $y)

            if ($pixel.R -gt 240 -and $pixel.G -lt 80 -and $pixel.B -lt 80) {
                if ($x -lt $minX) { $minX = $x }
                if ($y -lt $minY) { $minY = $y }
                if ($x -gt $maxX) { $maxX = $x }
                if ($y -gt $maxY) { $maxY = $y }
            }
        }
    }

    if ($maxX -lt 0 -or $maxY -lt 0) {
        throw 'Could not detect the red circle bounds in the reference image.'
    }

    return [pscustomobject]@{
        MinX = $minX
        MinY = $minY
        MaxX = $maxX
        MaxY = $maxY
        Width = ($maxX - $minX + 1)
        Height = ($maxY - $minY + 1)
    }
}

function New-CircleCropBitmap {
    param(
        [System.Drawing.Bitmap]$SourceBitmap
    )

    $bounds = Get-CircleBounds -Bitmap $SourceBitmap
    $diameter = [Math]::Max($bounds.Width, $bounds.Height)
    $padding = [Math]::Max(2, [int][Math]::Round($diameter * 0.01))
    $side = $diameter + ($padding * 2)

    $centerX = ($bounds.MinX + $bounds.MaxX) / 2.0
    $centerY = ($bounds.MinY + $bounds.MaxY) / 2.0

    $srcX = [int][Math]::Round($centerX - ($side / 2.0))
    $srcY = [int][Math]::Round($centerY - ($side / 2.0))

    if ($srcX -lt 0) { $srcX = 0 }
    if ($srcY -lt 0) { $srcY = 0 }
    if ($srcX + $side -gt $SourceBitmap.Width) { $srcX = $SourceBitmap.Width - $side }
    if ($srcY + $side -gt $SourceBitmap.Height) { $srcY = $SourceBitmap.Height - $side }

    $srcRect = [System.Drawing.Rectangle]::new($srcX, $srcY, $side, $side)
    $circleBitmap = [System.Drawing.Bitmap]::new($side, $side, [System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
    $graphics = [System.Drawing.Graphics]::FromImage($circleBitmap)
    $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
    $graphics.Clear([System.Drawing.Color]::Transparent)

    $clipPath = [System.Drawing.Drawing2D.GraphicsPath]::new()
    $clipPath.AddEllipse(0, 0, $side - 1, $side - 1)
    $graphics.SetClip($clipPath)

    $destinationRect = [System.Drawing.Rectangle]::new(0, 0, $side, $side)
    $graphics.DrawImage($SourceBitmap, $destinationRect, $srcRect, [System.Drawing.GraphicsUnit]::Pixel)

    $clipPath.Dispose()
    $graphics.Dispose()

    return $circleBitmap
}

function Save-ResizedPng {
    param(
        [System.Drawing.Bitmap]$SourceBitmap,
        [string]$RelativePath,
        [int]$Size
    )

    $targetPath = Join-Path $repoRoot $RelativePath
    $targetDir = Split-Path $targetPath -Parent

    if (-not (Test-Path $targetDir)) {
        New-Item -ItemType Directory -Path $targetDir | Out-Null
    }

    $bitmap = [System.Drawing.Bitmap]::new($Size, $Size, [System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
    $graphics.Clear([System.Drawing.Color]::Transparent)
    $graphics.DrawImage(
        $SourceBitmap,
        [System.Drawing.Rectangle]::new(0, 0, $Size, $Size),
        [System.Drawing.Rectangle]::new(0, 0, $SourceBitmap.Width, $SourceBitmap.Height),
        [System.Drawing.GraphicsUnit]::Pixel
    )

    $bitmap.Save($targetPath, [System.Drawing.Imaging.ImageFormat]::Png)

    $graphics.Dispose()
    $bitmap.Dispose()
}

function Save-TransparentPng {
    param(
        [string]$RelativePath,
        [int]$Size
    )

    $targetPath = Join-Path $repoRoot $RelativePath
    $targetDir = Split-Path $targetPath -Parent

    if (-not (Test-Path $targetDir)) {
        New-Item -ItemType Directory -Path $targetDir | Out-Null
    }

    $bitmap = [System.Drawing.Bitmap]::new($Size, $Size, [System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
    $bitmap.Save($targetPath, [System.Drawing.Imaging.ImageFormat]::Png)
    $bitmap.Dispose()
}

$referenceBitmap = [System.Drawing.Bitmap]::new($referencePath)
$circleBitmap = New-CircleCropBitmap -SourceBitmap $referenceBitmap

Save-ResizedPng -SourceBitmap $circleBitmap -RelativePath 'resources/js/assets/brand-logo-circle.png' -Size 512
Save-ResizedPng -SourceBitmap $circleBitmap -RelativePath 'mobile/assets/images/brand-logo-circle.png' -Size 512
Save-ResizedPng -SourceBitmap $circleBitmap -RelativePath 'mobile/assets/images/icon.png' -Size 1024
Save-ResizedPng -SourceBitmap $circleBitmap -RelativePath 'mobile/assets/images/favicon.png' -Size 512
Save-ResizedPng -SourceBitmap $circleBitmap -RelativePath 'mobile/assets/images/splash-icon.png' -Size 1024
Save-ResizedPng -SourceBitmap $circleBitmap -RelativePath 'mobile/assets/images/android-icon-foreground.png' -Size 1024
Save-ResizedPng -SourceBitmap $circleBitmap -RelativePath 'mobile/assets/images/android-icon-monochrome.png' -Size 1024
Save-TransparentPng -RelativePath 'mobile/assets/images/android-icon-background.png' -Size 1024

$circleBitmap.Dispose()
$referenceBitmap.Dispose()
