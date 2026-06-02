# OJS Magic Login

Passwordless sign-in for Open Journal Systems 3.5 — users receive a one-time link by email and sign in with one click, no password required.

Works alongside the standard username/password login; neither flow replaces the other.

---

## Features

- One-time email links with configurable expiry (default 15 minutes)
- Per-account rate limiting (configurable minimum interval between requests)
- Per-IP sliding-window rate limiting on both the send and verify endpoints
- Selector/verifier token scheme — only a SHA-256 hash is stored in the database, never the raw secret
- Single-use tokens consumed atomically before session creation
- Neutral send response prevents account enumeration
- Configurable from **Settings › Website › Plugins** — no code changes needed
- Email template editable from **Settings › Emails** (key: `MAGIC_LOGIN_LINK`)
- Theme-override support: themes can supply their own `request.tpl` / `confirm.tpl` under `templates/plugins/generic/magicLogin/templates/`

---

## Requirements

| Requirement | Version |
|-------------|---------|
| OJS | 3.5.0 or later |
| PHP | 8.1 or later |

---

## Installation

### Via Plugin Gallery

Search for **Passwordless Sign-in (Magic Link)** in **Settings › Website › Plugins › Plugin Gallery** and click Install.

### Manual installation

1. Download the latest `.tar.gz` from the [Releases](../../releases) page.
2. Unpack into `plugins/generic/` so the path is `plugins/generic/magicLogin/`.
3. Log in to OJS, go to **Settings › Website › Plugins › Generic Plugins**, find **Passwordless Sign-in (Magic Link)** and click **Enable**.
4. Click the plugin's **Settings** link and tick **Enable magic-link sign-in for this journal**.

> **Note — versions table:** If you install manually by dropping the files in without using the OJS plugin installer, the plugin will not appear as enabled until a row exists in the `versions` table. Run once:
> ```sql
> INSERT INTO versions
>   (major, minor, revision, build, date_installed, current,
>    product_type, product, product_class_name, lazy_load, sitewide)
> VALUES (1,0,0,0,NOW(),1,'plugins.generic','magicLogin','MagicLoginPlugin',1,0);
> ```
> The Plugin Gallery installer handles this automatically.

---

## Configuration

| Setting | Description | Default |
|---------|-------------|---------|
| Enable magic-link sign-in | Activates the feature for this journal | Off |
| Link validity (minutes) | How long an emailed link stays usable (1–120) | 15 |
| Minimum seconds between requests | Per-account throttle (30–3600) | 60 |

---

## How it works

```
User enters email          →  POST /magicLogin/send
                               ├─ IP rate-limit check
                               ├─ look up account (no response difference if not found)
                               ├─ issue selector + verifier; store selector + sha256(verifier)
                               ├─ email link:  /magicLogin/confirm?token=<selector>.<verifier>
                               └─ always show neutral "check your inbox" message

User clicks email link     →  GET /magicLogin/confirm?token=…
                               ├─ validate token format
                               ├─ verify selector in DB, check hash + expiry (read-only)
                               └─ show "Sign in now" button

User clicks Sign in        →  POST /magicLogin/login
                               ├─ IP rate-limit check
                               ├─ re-verify token (expiry, hash)
                               ├─ consume token (delete from DB — single-use)
                               ├─ establish OJS session
                               └─ redirect to dashboard
```

---

## Security model

- **Selector/verifier scheme**: the URL carries a random 128-bit selector (lookup key) and a random 256-bit verifier (secret). Only `sha256(verifier)` is stored. A database read cannot reconstruct a usable token.
- **Constant-time comparison**: `hash_equals()` prevents timing-based verifier enumeration.
- **Single-use**: the token is deleted before the session is created. If session setup fails, the token is already gone.
- **Short expiry**: 15 minutes by default, administrator-configurable.
- **Rate limiting**: per-IP sliding window on both `/send` (5 requests / 10 min) and `/login` (10 attempts / 5 min).
- **Neutral responses**: `/send` always returns the same page regardless of whether the email matched an account.
- **CSRF**: every mutating endpoint enforces OJS's built-in CSRF token.
- **No core changes**: zero modifications to OJS core files. Hooks only.

---

## Email template

The email sent to users is customisable under **Settings › Emails › Magic sign-in link** (`MAGIC_LOGIN_LINK`).

Available template variables:

| Variable | Description |
|----------|-------------|
| `{$recipientName}` | User's full name |
| `{$contextName}` | Journal name |
| `{$magicUrl}` | The one-time sign-in URL |
| `{$expiryMinutes}` | Link validity in minutes |

---

## Theming

The plugin ships generic templates (`templates/request.tpl`, `templates/confirm.tpl`) that work with any OJS theme. To apply your own design, create overrides inside your theme:

```
plugins/themes/<yourtheme>/templates/plugins/generic/magicLogin/templates/request.tpl
plugins/themes/<yourtheme>/templates/plugins/generic/magicLogin/templates/confirm.tpl
```

These receive the same Smarty variables (`$sendUrl`, `$loginUrl`, `$token`, `$neutralMessage`, `$error`) as the built-in templates.

---

## Roadmap

| Milestone | Status |
|-----------|--------|
| One-time email links (magic links) | ✅ Released — v1.0.0 |
| Passkey / WebAuthn sign-in | 🔜 Planned — v2.0.0 |

The passkey milestone will add `PublicKeyCredential`-based authentication as a second passwordless method. The session-establishment core (`classes/SessionService.php`) is already structured to accept a second caller alongside magic links.

---

## Contributing

Pull requests are welcome. Please open an issue first for anything beyond a small bug fix.

---

## License

Distributed under the **GNU General Public License v3.0 or later**. See `LICENSE` for the full text.
