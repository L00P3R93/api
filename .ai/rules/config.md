---
paths:
  - config/log-viewer.php
---

# Config

## Log Viewer is gated by HTTP Basic Auth, not the User model
This app has no active web-session login or role/permission package, so opcodesio/log-viewer's production access is gated by `App\Http\Middleware\AuthenticateLogViewerBasicAuth` (checks `LOG_VIEWER_USERNAME`/`LOG_VIEWER_PASSWORD` env vars via `config('log-viewer.basic_auth')`), wired into `config/log-viewer.php`'s `middleware`/`api_middleware` arrays ahead of `AuthorizeLogViewer`.

The `viewLogViewer`/`downloadLogFile`/`downloadLogFolder` Gates in `AppServiceProvider::boot()` just return `true` (Basic Auth is the real gate) — they only exist because Log Viewer blocks everything in production if the `viewLogViewer` gate is undefined. `deleteLogFile`/`deleteLogFolder` gates return `false` (deletion via the UI is disabled by default).

If a real admin/session auth system is added later, prefer switching this middleware to that instead of maintaining separate Basic Auth credentials.
