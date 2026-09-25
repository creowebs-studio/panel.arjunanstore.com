# Ekspor satu sheet .xlsx → CSV deterministik untuk MIGRASI (Tahap 6).
# - Nilai sel apa adanya (bukan formula); baris diproses per-<row> agar aman utk sheet besar.
# - Header: -HeaderRow N (nilai baris N) atau huruf kolom A,B,C,... bila -HeaderRow 0.
# - -DateCols/-DateTimeCols: serial Excel → yyyy-MM-dd / yyyy-MM-dd HH:mm:ss.
# Contoh:
#   & tools\xlsx-to-csv.ps1 -Path 'Upload Mengantar 2026.xlsx' -Sheet 'ADV_CS' -HeaderRow 2 -StartRow 3 -OutFile 'out\migrasi\adv_cs.csv'
#   & tools\xlsx-to-csv.ps1 -Path 'Upload Mengantar 2026.xlsx' -Sheet 'DBMengantar' -StartRow 2 -DateTimeCols 'P,Q' -OutFile 'out\migrasi\dbmengantar.csv'
param(
  [Parameter(Mandatory=$true)][string]$Path,
  [Parameter(Mandatory=$true)][string]$Sheet,
  [Parameter(Mandatory=$true)][string]$OutFile,
  [int]$HeaderRow = 0,
  [int]$StartRow = 1,
  [int]$EndRow = 0,
  [string]$DateCols = '',
  [string]$DateTimeCols = '',
  [int]$MaxDataRows = 0
)
Add-Type -AssemblyName System.IO.Compression.FileSystem
$ErrorActionPreference = 'Stop'

$zip = [System.IO.Compression.ZipFile]::OpenRead((Resolve-Path $Path))
function Read-Entry($name) {
  $e = $zip.Entries | Where-Object { $_.FullName -eq $name }
  if (-not $e) { return $null }
  $sr = New-Object System.IO.StreamReader($e.Open())
  $txt = $sr.ReadToEnd(); $sr.Close(); return $txt
}

# sharedStrings
$ss = @()
$ssx = Read-Entry 'xl/sharedStrings.xml'
if ($ssx) {
  foreach ($m in [regex]::Matches($ssx, '<si>(.*?)</si>', 'Singleline')) {
    $inner = $m.Groups[1].Value
    $txt = ([regex]::Matches($inner, '<t[^>]*>(.*?)</t>', 'Singleline') | ForEach-Object { $_.Groups[1].Value }) -join ''
    $txt = $txt -replace '&lt;','<' -replace '&gt;','>' -replace '&quot;','"' -replace '&apos;',"'" -replace '&amp;','&'
    $ss += $txt
  }
}

# nama sheet -> part
$wb = Read-Entry 'xl/workbook.xml'
$rels = Read-Entry 'xl/_rels/workbook.xml.rels'
$rid = $null
foreach ($m in [regex]::Matches($wb, '<sheet\b([^>]*?)/>')) {
  $a = $m.Groups[1].Value
  if ([regex]::Match($a, 'name="([^"]*)"').Groups[1].Value -eq $Sheet) { $rid = [regex]::Match($a, 'r:id="([^"]*)"').Groups[1].Value; break }
}
if (-not $rid) { Write-Error "SHEET NOT FOUND: $Sheet"; exit 1 }
$target = $null
foreach ($m in [regex]::Matches($rels, '<Relationship\b([^>]*?)/>')) {
  $a = $m.Groups[1].Value
  if ([regex]::Match($a,'Id="([^"]*)"').Groups[1].Value -eq $rid) {
    $t = [regex]::Match($a,'Target="([^"]*)"').Groups[1].Value
    $target = 'xl/' + ($t -replace '^/','' -replace '^xl/','')
    break
  }
}
$sheetXml = Read-Entry $target
if (-not $sheetXml) { Write-Error "NO SHEET XML for $Sheet"; exit 1 }

function Convert-ColIdx([string]$col) {
  $n = 0
  foreach ($ch in $col.ToCharArray()) { $n = $n * 26 + ([int][char]$ch - 64) }
  return $n
}

# Kumpulkan baris (chunk per <row> agar hemat memori).
$rowsData = @{}       # rowNum -> @{ col -> value }
$colsSeen = @{}
$rowChunks = [regex]::Split($sheetXml, '<row\b')
foreach ($chunk in $rowChunks) {
  $rm = [regex]::Match($chunk, '^\s*r="(\d+)"')
  if (-not $rm.Success) { continue }
  $rowNum = [int]$rm.Groups[1].Value
  $maxNeeded = if ($EndRow -gt 0) { $EndRow } else { [int]::MaxValue }
  if ($rowNum -gt $maxNeeded) { continue }
  $vals = @{}
  foreach ($c in [regex]::Matches($chunk, '<c\b([^>]*?)(?:/>|>(.*?)</c>)', 'Singleline')) {
    $attrs = $c.Groups[1].Value; $inner = $c.Groups[2].Value
    if ($null -eq $inner) { continue }
    $ref = [regex]::Match($attrs,'r="([A-Z]+)(\d+)"')
    if (-not $ref.Success) { continue }
    $col = $ref.Groups[1].Value
    $t = [regex]::Match($attrs,'t="([^"]+)"').Groups[1].Value
    $val = ''
    if ($t -eq 's') { $vi = [regex]::Match($inner,'<v>(.*?)</v>').Groups[1].Value; if ($vi -ne '') { $val = $ss[[int]$vi] } }
    elseif ($t -eq 'inlineStr') { $val = [regex]::Match($inner,'<t[^>]*>(.*?)</t>').Groups[1].Value }
    else { $val = [regex]::Match($inner,'<v>(.*?)</v>').Groups[1].Value }
    if ($val -ne '') { $vals[$col] = $val; $colsSeen[$col] = $true }
  }
  if ($vals.Count -gt 0) { $rowsData[$rowNum] = $vals }
}

$allCols = $colsSeen.Keys | Sort-Object { Convert-ColIdx $_ }
$dateSet = @{};   foreach ($c in ($DateCols -split ','))     { $c = $c.Trim(); if ($c) { $dateSet[$c] = $true } }
$dtSet = @{};     foreach ($c in ($DateTimeCols -split ',')) { $c = $c.Trim(); if ($c) { $dtSet[$c] = $true } }

function Format-Date([string]$raw, [bool]$withTime) {
  $num = 0.0
  # Wajib invariant: culture id-ID membaca '.' sebagai pemisah ribuan (462.5 gagal parse).
  $ok = [double]::TryParse($raw, [System.Globalization.NumberStyles]::Float, [System.Globalization.CultureInfo]::InvariantCulture, [ref]$num)
  if (-not $ok) { return $raw }
  if ($num -lt 20000 -or $num -gt 80000) { return $raw }   # bukan serial Excel wajar
  $dt = [DateTime]::FromOADate($num)
  if ($withTime) { return $dt.ToString('yyyy-MM-dd HH:mm:ss') }
  return $dt.ToString('yyyy-MM-dd')
}

function Csv-Field([string]$v) {
  if ($v -match '[",\r\n]') { return '"' + ($v -replace '"','""') + '"' }
  return $v
}

$lines = New-Object System.Collections.Generic.List[string]

# Header
$header = New-Object System.Collections.Generic.List[string]
foreach ($col in $allCols) {
  $label = $col
  if ($HeaderRow -gt 0 -and $rowsData.ContainsKey($HeaderRow) -and $rowsData[$HeaderRow].ContainsKey($col)) {
    $label = $rowsData[$HeaderRow][$col]
  }
  $header.Add((Csv-Field $label))
}
$lines.Add(($header -join ','))

$count = 0
foreach ($rowNum in ($rowsData.Keys | Sort-Object)) {
  if ($rowNum -lt $StartRow) { continue }
  if ($HeaderRow -gt 0 -and $rowNum -eq $HeaderRow) { continue }
  $fields = New-Object System.Collections.Generic.List[string]
  foreach ($col in $allCols) {
    $v = ''
    if ($rowsData[$rowNum].ContainsKey($col)) { $v = $rowsData[$rowNum][$col] }
    if ($dateSet.ContainsKey($col)) { $v = Format-Date $v $false }
    elseif ($dtSet.ContainsKey($col)) { $v = Format-Date $v $true }
    $fields.Add((Csv-Field $v))
  }
  $lines.Add(($fields -join ','))
  $count++
  if ($MaxDataRows -gt 0 -and $count -ge $MaxDataRows) { break }
}

$outPath = [System.IO.Path]::GetFullPath($OutFile)
$outDir = [System.IO.Path]::GetDirectoryName($outPath)
if (-not (Test-Path $outDir)) { New-Item -ItemType Directory -Path $outDir | Out-Null }
[System.IO.File]::WriteAllText($outPath, ($lines -join "`n") + "`n", (New-Object System.Text.UTF8Encoding($false)))
$zip.Dispose()
Write-Output "OK $Sheet -> $OutFile ($count baris data, $($allCols.Count) kolom)"
