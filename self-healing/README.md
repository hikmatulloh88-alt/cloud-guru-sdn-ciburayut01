# Cloud Guru Self-Healing V1

Self-Healing V1 is an isolated safety layer for Cloud Guru.

## V1 scope
- Health checks without modifying existing application modules.
- Centralized PHP error logging.
- Safe backup/restore primitives for explicitly allowed files.
- Audit trail for every self-healing action.
- No automatic code modification in V1.

## Safety rules
1. Never modify the Absensi application master data.
2. Never modify Guru master data automatically.
3. Never delete database data.
4. Never execute arbitrary AI-generated PHP.
5. Any future AI patch must be validated before deployment.
6. Production changes must be reversible.

## Files
- `health.php` - diagnostic endpoint.
- `logger.php` - error/audit logger.
- `backup.php` - controlled file backup/restore helper.
- `config.example.php` - local configuration template.

## Installation
Copy `config.example.php` to `config.local.php` on the server and set a long random access token.

The local config, runtime logs, and backups are excluded from Git.
