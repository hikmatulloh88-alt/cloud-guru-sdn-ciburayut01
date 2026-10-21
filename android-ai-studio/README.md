# Android wrapper — Cloud Guru AI Studio

This Android Studio project builds a debug APK that opens the Cloud Guru AI Studio web page:
`https://sdnciburayut01.com/ai-studio/`

## Build
The repository workflow `.github/workflows/android-ai-studio-apk.yml` builds a debug APK and uploads it as an Actions artifact for 14 days. It can also be started manually from GitHub Actions using **Build Cloud Guru AI Studio APK → Run workflow**.

## Before installing/testing
1. Deploy and test the AI Studio web module first.
2. Confirm the actual Cloud Guru login session works on the deployed domain.
3. Configure `FAL_KEY` or the private server-only file `config/ai_video.php`.
4. APK is a WebView wrapper, not an offline/native video-generation engine. Internet and a working server are required.

## Notes
- The WebView allows the school domain and opens other links in the external browser.
- File chooser support is enabled for selecting a photo.
- Video downloads use Android DownloadManager.
- This is a **debug APK** for initial testing, not a signed production release. A release APK requires a managed signing key; do not commit keystore files or passwords to Git.
