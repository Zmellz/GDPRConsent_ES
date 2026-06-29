$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$agents = Join-Path $root '.agents'
$codex = Join-Path $root '.codex'

New-Item -ItemType Directory -Force -Path $agents | Out-Null
New-Item -ItemType Directory -Force -Path $codex | Out-Null

# Los archivos de instrucciones ya estan en .agents/ y .codex/
# .agents/ — para agentes en general
# .codex/  — para modelos OpenAI (mismo contenido)

Write-Host 'Instrucciones ya instaladas en .agents y .codex.'
Write-Host 'Estructura:'
Write-Host '  .agents/AGENTS.md'
Write-Host '  .agents/PROJECT_AGENT_GUIDE.md'
Write-Host '  .agents/PRODUCT_COMPLETION_PLAN.md'
Write-Host '  .agents/TECHNICAL_REFERENCES.md'
Write-Host '  .agents/NEXT_ITERATION_CHECKLIST.md'
Write-Host '  .codex/AGENTS.md'
Write-Host '  .codex/PROJECT_AGENT_GUIDE.md'
Write-Host '  .codex/PRODUCT_COMPLETION_PLAN.md'
Write-Host '  .codex/TECHNICAL_REFERENCES.md'
Write-Host '  .codex/NEXT_ITERATION_CHECKLIST.md'
