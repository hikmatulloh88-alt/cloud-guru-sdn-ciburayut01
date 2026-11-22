# Cloud Guru SDN Ciburayut 01

Source repository for Cloud Administrasi Guru and the school attendance application.

## Security
Do not commit database passwords, Google OAuth client secrets, WhatsApp API tokens, database dumps, teacher/student photos, or runtime uploads.

Production credentials belong only on the server.

## Database setup

Set these environment variables in the hosting panel or PHP-FPM:

- `CLOUD_DB_HOST`
- `CLOUD_DB_NAME`
- `CLOUD_DB_USER`
- `CLOUD_DB_PASS`
- `ABSENSI_DB_HOST` (optional when it uses the Cloud database host)
- `ABSENSI_DB_NAME`
- `ABSENSI_DB_USER`
- `ABSENSI_DB_PASS`

The legacy `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` names are accepted as fallback values.
