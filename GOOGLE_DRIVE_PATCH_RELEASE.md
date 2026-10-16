# Google Drive per-account patch

This release adds `gdrive/connect.php`, the OAuth start endpoint. It uses the logged-in Cloud Guru `user_id`, stores a short-lived PKCE transaction for that user, signs OAuth state with the server-only token key, and requests offline access.

The complete deployment bundle is provided separately as `CLOUD_GURU_GOOGLE_DRIVE_GITHUB_COMPLETE.zip`. Extract it relative to `public_html` only after taking a backup. The bundle intentionally excludes real `config/google_drive.php` and database credentials. Keep those only on the server.

OAuth redirect URI must match exactly: `https://sdnciburayut01.com/gdrive/callback.php`.

After deployment, test each user account independently. Existing user token rows are not deleted by this patch. The connect endpoint replaces only that same user's pending OAuth transaction.
