# Cloud Guru Self-Healing V1

Self-Healing V1 is an isolated safety layer for Cloud Guru.

## V1 scope
- Health checks without modifying existing application modules.
- Centralized PHP error logging.
- Safe backup/restore primitives for explicitly allowed files.
- AI-assisted error diagnosis (read-only).
- GitHub Actions validation for PHP syntax and protected paths.
- No automatic production code modification.

## Safety rules
1. Never modify the Absensi application master data.
2. Never modify Guru master data automatically.
3. Never delete database data.
4. Never execute arbitrary AI-generated PHP.
5. Any future AI patch must be validated before deployment.
6. Production changes must be reversible.
7. Repairs are developed on isolated branches and proposed through pull requests.
8. V1 patches require an exact old-text match, an explicit approval phrase, a backup, and PHP syntax validation.
9. Failed PHP syntax validation automatically restores the pre-patch backup.

## Files
- `health.php` - diagnostic endpoint.
- `logger.php` - error/audit logger.
- `backup.php` - controlled file backup/restore helper.
- `ai_analyzer.php` - read-only AI diagnosis.
- `dashboard.php` - protected monitoring dashboard.
- `patch_engine.php` - controlled, reversible PHP patch engine.
- `config.example.php` - local configuration template.

## Installation
Copy `config.example.php` to `config.local.php` on the server and set a long random access token. Add the OpenAI API key only to the untracked local config if AI diagnosis is enabled.

The local config, runtime logs, and backups are excluded from Git.

## Validation
Every pull request targeting `main` runs PHP syntax checks and a safety check. A future patch engine must create a separate repair branch and open a pull request; it must not write directly to `main`.
