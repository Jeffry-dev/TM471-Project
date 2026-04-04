param(
  [int]$Port = 8080
)

$root = Split-Path -Parent $MyInvocation.MyCommand.Path

# Windows PowerShell 5.1 already includes System.Net.HttpListener; don't Add-Type it.
$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add("http://localhost:$Port/")
$listener.Start()
Write-Host "Serving $root on http://localhost:$Port/" -ForegroundColor Green
Write-Host "Press Ctrl+C to stop." -ForegroundColor Yellow

function Get-ContentType([string]$path) {
  switch -Regex ($path) {
    '\.html$'  { 'text/html; charset=utf-8' }
    '\.css$'   { 'text/css; charset=utf-8' }
    '\.js$'    { 'application/javascript; charset=utf-8' }
    '\.json$'  { 'application/json; charset=utf-8' }
    '\.ico$'   { 'image/x-icon' }
    '\.png$'   { 'image/png' }
    '\.jpg$'   { 'image/jpeg' }
    '\.jpeg$'  { 'image/jpeg' }
    '\.svg$'   { 'image/svg+xml' }
    '\.woff2$' { 'font/woff2' }
    default    { 'application/octet-stream' }
  }
}

try {
  while ($listener.IsListening) {
    $task = $listener.GetContextAsync()
    while ($listener.IsListening -and -not $task.Wait([TimeSpan]::FromSeconds(1))) {
      # Wait in short intervals so Ctrl+C can be processed.
    }
    if (-not $listener.IsListening) { break }
    if ($task.IsFaulted -or $task.IsCanceled) { continue }

    $ctx = $task.Result
    $req = $ctx.Request
    $res = $ctx.Response

    $rel = $req.Url.AbsolutePath.TrimStart('/')
    if ([string]::IsNullOrWhiteSpace($rel)) { $rel = 'home.html' }

    $filePath = Join-Path $root $rel

    if (-not (Test-Path $filePath -PathType Leaf)) {
      $res.StatusCode = 404
      $bytes = [Text.Encoding]::UTF8.GetBytes('Not Found')
      $res.OutputStream.Write($bytes, 0, $bytes.Length)
      $res.Close()
      continue
    }

    $res.StatusCode = 200
    $res.ContentType = Get-ContentType $filePath

    # Disable caching so CSS/JS changes show up immediately during development.
    $res.Headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0'
    $res.Headers['Pragma'] = 'no-cache'

    $bytes = [IO.File]::ReadAllBytes($filePath)
    $res.OutputStream.Write($bytes, 0, $bytes.Length)
    $res.Close()
  }
} finally {
  $listener.Stop()
  $listener.Close()
}
