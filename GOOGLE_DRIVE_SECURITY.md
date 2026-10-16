# Google Drive configuration safety

- Never commit config/google_drive.php, config/database.php, OAuth secrets, token encryption keys, or user tokens.
- Keep real credentials only on the cPanel server.
- Keep GOOGLE_DRIVE_TOKEN_KEY stable after tokens have been encrypted; changing it requires migration.
- OAuth redirect URI: https://sdnciburayut01.com/gdrive/callback.php.
- Store each teacher's Google Drive authorization separately by application user.
