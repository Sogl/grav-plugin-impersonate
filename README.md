English | [Русский](README_RU.md)

# Impersonate Plugin

Admin frontend impersonation for Grav users: open a frontend session as another user (or as yourself) in one click from the Admin panel while keeping the Admin session intact.

![Impersonate Interface](assets/interface.jpg)

## Features

- **Impersonate from Admin**:
  - “Impersonate” action in the Users list (works with both Flex and default user storage).
  - Quick Tray button “Impersonate Self” to open the frontend as the current admin.
- **Safe stop flow**:
  - Dedicated stop endpoint (`/impersonate/stop`) using POST + nonce.
  - Stop action available from Quick Tray and from the Users list (single toggle button).

- **Security hardening**:
  - HMAC-signed, short-lived, single-use tokens stored server-side (no raw tokens on disk).
  - CSRF protection for admin tasks and stop endpoint.
  - Admin targets are blocked by default (with explicit override if needed).
- **Real-time synchronization**:
  - Injects a lightweight JS script on the frontend.
  - Updates Admin UI icons (start/stop) instantly across tabs via `BroadcastChannel` without page reload.
- **Logs**:
  - Separate audit log `logs/impersonate.log` with structured events.

## Requirements

- Grav **1.7.0+** (see `blueprints.yaml` for details).
- Grav Admin plugin.
- **Split sessions**: Requires `system.session.split: true` (Admin and frontend sessions remain independent; impersonation only affects the frontend session).

Successfully tested with Grav **v1.7.49.5** and Admin **v1.10.50**.

## Installation

### GPM Installation (preferred)

From the root of your Grav installation:

```bash
bin/gpm install impersonate
```

This installs the plugin into `user/plugins/impersonate`.

### Manual Installation

1. Download the ZIP of this repository.
2. Unzip it to `user/plugins/` and rename the folder to `impersonate` if needed.
3. You should end up with:

   ```text
   user/plugins/impersonate
   ```

### Admin Plugin

If you use the Admin plugin, you can install **Impersonate** from the **Plugins → Add** screen by searching for `Impersonate`.

## Configuration

Copy the default configuration file:

```text
user/plugins/impersonate/impersonate.yaml
```

to:

```text
user/config/plugins/impersonate.yaml
```

and edit that copy.

Default options:

```yaml
enabled: true
allow_admin_targets: false
token_ttl_seconds: 45
default_redirect: /
log_events: true
show_ui_button: true
confirm_on_switch: true
icon_start: fa-arrow-right-arrow-left
icon_stop: fa-arrow-right-from-bracket
icon_self: fa-arrow-right-to-bracket
```

Key settings:

- **`allow_admin_targets`**  
  Allow impersonating admin accounts (`admin.login` / `admin.super`).  
  Default: `false` (strongly recommended for production).

- **`token_ttl_seconds`**  
  Lifetime of one-time impersonation tokens (in seconds).  
  Default: `45`.

- **`default_redirect`**  
  Frontend path to redirect to after successful impersonation / stop.  
  Must be an internal path starting with `/`.

- **`log_events`**  
  Enable/disable audit logging to `logs/impersonate.log`.

- **`show_ui_button`**  
  Toggle Admin UI integration (Quick Tray + user list actions).

- **`confirm_on_switch`**  
  Show a confirmation dialog when switching impersonation from one user to another.

- **`icon_start`, `icon_stop`, `icon_self`**  
  Custom Font Awesome 7 classes for the Impersonate icons in the Admin UI.

## Security

### Session model

- Requires `system.session.split: true`.
- Admin session is never reused for the frontend; impersonation affects **only** the frontend session.

### Tokens

- Tokens are HMAC-signed using a strong secret:
  - `IMPERSONATE_TOKEN_SECRET` (env) **or**
  - `system.security.salt` (fallback, only if strong enough).
- Payload includes:
  - `actor_user`, `target_user`, `mode`, `iat`, `exp`, `nonce`, `action`.
- Tokens are:
  - short-lived (`token_ttl_seconds`),
  - single-use, with server-side nonce and `used_at` marker,
  - persisted only as a **hash** (no raw tokens on disk).

### Admin tasks

- All admin tasks (`impersonate`, `impersonateSelf`, `stopImpersonate`, log endpoints) require a valid `admin-form` nonce.
- Permissions:
  - `admin.impersonate.self`
  - `admin.impersonate.users`
  - `admin.impersonate.logs`
  - `admin.super` can always impersonate and stop.

### Stop endpoint

- Frontend stop flow uses:
  - `POST /impersonate/stop`
  - one-time stop token
  - nonce `impersonate-stop`
- No stop tokens are accepted from URLs (path or query).

### Admin targets

- By default, users with `admin.login` or `admin.super` **cannot** be impersonated.
- This restriction is enforced both:
  - in the Admin task logic (when issuing tokens),
  - and during frontend activation (defense-in-depth).
- Self-impersonation (`mode=self`) is allowed and explicitly logged.

## Audit log

The plugin writes structured events to `logs/impersonate.log` (when `log_events: true`):

- Events include:
  - `event`, `actor`, `target`, `result`, `reason`, `ip`, `ua`, `mode`.
- IP is resolved via `Uri::ip()` (respects `system.http_x_forwarded.ip` and Cloudflare headers).
- Sensitive values (tokens, nonces) are never written to the log.

You can view and clear the log from the plugin configuration in Admin (Logs tab).

## Admin UI

- **Quick Tray**:
  - “Impersonate Self” opens frontend as the current admin in a new tab.
  - When impersonation is active in `self` mode, the Quick Tray button turns into “Stop”.

- **Users list**:
  - Single toggle button per user:
    - “Impersonate” when impersonation is not active for that user.
    - “Stop” when this user is the active impersonation target.
  - Works with Flex Users and with the default user storage backend.

## License

MIT (see `LICENSE`).


