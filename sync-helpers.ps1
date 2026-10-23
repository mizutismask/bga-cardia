#Requires -Version 5.1
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$configFile = ".vscode/sftp.json"

<# /** Ensure Posh-SSH is available (install to CurrentUser if missing). */ #>
function Ensure-PoshSSH {
    if (-not (Get-Module -ListAvailable -Name Posh-SSH)) {
        try {
            Install-Module -Name Posh-SSH -Scope CurrentUser -Force -ErrorAction Stop
        } catch {
            Write-Error "Posh-SSH is required. Install it with: Install-Module Posh-SSH -Scope CurrentUser"
            exit 1
        }
    }
    Import-Module Posh-SSH -ErrorAction Stop
}

<# /** Load and validate sftp.json. */ #>
function Get-Config {
    if (-not (Test-Path $configFile)) {
        Write-Error "Config file $configFile not found."
        exit 1
    }
    $cfg = Get-Content $configFile -Raw | ConvertFrom-Json
    foreach ($k in 'host','port','username','password','remotePath') {
        if (-not $cfg.PSObject.Properties.Name.Contains($k) -or [string]::IsNullOrWhiteSpace($cfg.$k)) {
            Write-Error "Missing or empty '$k' in $configFile."
            exit 1
        }
    }
    return $cfg
}

<# /** Join remote POSIX paths safely. */ #>
function Join-RemotePath([string]$root, [string]$child) {
    $r = $root.TrimEnd('/','\')
    if ([string]::IsNullOrWhiteSpace($r)) { return "/$child" }
    return "$r/$child"
}

<# /** Download required files. Prefer SCP (simplest), fallback to SFTP stream. */ #>
<# /** Download required files using SFTP only (Posh-SSH). */ #>
function Fetch-Files {
    param(
        [Parameter(Mandatory)]$Cfg,
        [string[]]$Files = @('_ide_helper.php','bga-framework.d.ts')
    )

    if (-not (Get-Command Get-SFTPItem -ErrorAction SilentlyContinue)) {
        throw "Get-SFTPItem is not available in your Posh-SSH build."
    }

    $secure = ConvertTo-SecureString $Cfg.password -AsPlainText -Force
    $cred   = [pscredential]::new($Cfg.username, $secure)
    $port   = [int]$Cfg.port

    $sftp = New-SFTPSession -ComputerName $Cfg.host -Port $port -Credential $cred -AcceptKey -ErrorAction Stop
    try {
        $destDir = (Resolve-Path .)

        foreach ($f in $Files) {
            $remote  = Join-RemotePath $Cfg.remotePath $f
            $local   = Join-Path $destDir (Split-Path -Path $remote -Leaf)

            if (Test-Path $local) { Remove-Item $local -Force }  # ensure overwrite
            Write-Host "Downloading $remote via SFTP..."
            Get-SFTPItem -SessionId $sftp.SessionId -Path $remote -Destination $destDir -ErrorAction Stop
        }

        Write-Host "Download completed."
    } finally {
        if ($sftp) { Remove-SFTPSession -SFTPSession $sftp -ErrorAction SilentlyContinue }
    }
}


# --- main ---
Ensure-PoshSSH
$cfg = Get-Config
Fetch-Files -Cfg $cfg
