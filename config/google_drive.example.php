<?php
declare(strict_types=1);

/* TEMPLATE ONLY. Copy to google_drive.php on the server and set real values there. */
const GOOGLE_DRIVE_CLIENT_ID = 'YOUR_CLIENT_ID.apps.googleusercontent.com';
const GOOGLE_DRIVE_CLIENT_SECRET = 'SET_THIS_ONLY_ON_SERVER';
const GOOGLE_DRIVE_REDIRECT_URI = 'https://sdnciburayut01.com/gdrive/callback.php';
const GOOGLE_DRIVE_SCOPE = 'https://www.googleapis.com/auth/drive.file';
const GOOGLE_DRIVE_TOKEN_KEY = 'GENERATE_RANDOM_KEY_ON_SERVER_AND_KEEP_STABLE';
const GOOGLE_DRIVE_ROOT_FOLDER = 'Cloud Guru - SDN Ciburayut 01';
const GOOGLE_DRIVE_MAX_UPLOAD = 25 * 1024 * 1024;
