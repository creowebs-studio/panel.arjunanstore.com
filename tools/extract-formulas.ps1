param(
  [Parameter(Mandatory=$true)][string]$Path,
  [Parameter(Mandatory=$true)][string]$Sheet,
  [int]$HeaderRows = 4
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

# shared strings
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

# map sheet name -> target
$wb = Read-Entry 'xl/workbook.xml'
$rels = Read-Entry 'xl/_rels/workbook.xml.rels'
$rid = $null
foreach ($m in [regex]::Matches($wb, '<sheet\b([^>]*)/>')) {
  $a = $m.Groups[1].Value
  $nm = [regex]::Match($a, 'name="([^"]*)"').Groups[1].Value
  if ($nm -eq $Sheet) { $rid = [regex]::Match($a, 'r:id="([^"]*)"').Groups[1].Value; break }
}
if (-not $rid) { Write-Output "SHEET NOT FOUND: $Sheet"; $zip.Dispose(); exit 1 }
$target = $null
foreach ($m in [regex]::Matches($rels, '<Relationship\b([^>]*)/>')) {
  $a = $m.Groups[1].Value
  if ([regex]::Match($a,'Id="([^"]*)"').Groups[1].Value -eq $rid) {
    $t = [regex]::Match($a,'Target="([^"]*)"').Groups[1].Value
    $target = 'xl/' + ($t -replace '^/','' -replace '^xl/','')
    break
  }
}
Write-Output "==== $Path :: $Sheet -> $target ===="
$sheetXml = Read-Entry $target
if (-not $sheetXml) { Write-Output 'NO SHEET XML'; $zip.Dispose(); exit 1 }

$cells = [regex]::Matches($sheetXml, '<c\b([^>]*?)(?:/>|>(.*?)</c>)', 'Singleline')
$seen = @{}
foreach ($c in $cells) {
  $attrs = $c.Groups[1].Value; $inner = $c.Groups[2].Value
  if ($null -eq $inner) { $inner = '' }
  $ref = [regex]::Match($attrs,'r="([A-Z]+)(\d+)"')
  if (-not $ref.Success) { continue }
  $col = $ref.Groups[1].Value; $rowNum = [int]$ref.Groups[2].Value
  $t = [regex]::Match($attrs,'t="([^"]+)"').Groups[1].Value
  $f = [regex]::Match($inner, '<f[^>]*>(.*?)</f>', 'Singleline').Groups[1].Value
  $f = $f -replace '&lt;','<' -replace '&gt;','>' -replace '&quot;','"' -replace '&apos;',"'" -replace '&amp;','&'
  if ($rowNum -le $HeaderRows) {
    if ($t -eq 's') { $vi = [regex]::Match($inner,'<v>(.*?)</v>').Groups[1].Value; if($vi -ne ''){ Write-Output ("  HDR {0}{1} = {2}" -f $col,$rowNum,$ss[[int]$vi]) } }
    elseif ($t -eq 'inlineStr') { $it = [regex]::Match($inner,'<t[^>]*>(.*?)</t>').Groups[1].Value; Write-Output ("  HDR {0}{1} = {2}" -f $col,$rowNum,$it) }
    else { $vi = [regex]::Match($inner,'<v>(.*?)</v>').Groups[1].Value; if($vi -ne ''){ Write-Output ("  HDR {0}{1} = {2}" -f $col,$rowNum,$vi) } }
  }
  if ($f -ne '') {
    if (-not $seen.ContainsKey($col)) { $seen[$col] = $true; Write-Output ("  F {0}{{row{1}}} : {2}" -f $col,$rowNum,$f) }
  }
}
$zip.Dispose()
