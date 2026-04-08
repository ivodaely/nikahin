# nikahin 💍

**E-Wedding Invitation Platform** — AI-powered, mobile-first digital wedding invitations.

---

## Quick Start

### 1. Requirements
- PHP 8.1+
- MySQL 8.0+
- Apache/Nginx with mod_rewrite
- Node.js 18+ (dev tooling only)

### 2. Database
```bash
mysql -u root -p < database/schema.sql
```

### 3. Environment Variables
Set these in your server environment or a `.env` file loaded at bootstrap:

```env
# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=nikahin
DB_USER=root
DB_PASS=yourpassword

# App
APP_URL=https://yourdomain.com

# AI
ANTHROPIC_API_KEY=sk-ant-...

# SMTP (for email blasts)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your@gmail.com
SMTP_PASS=yourapppassword
SMTP_FROM=noreply@yourdomain.com

# JWT
JWT_SECRET=your_random_secret_string
```

### 4. Web Server

**Apache** — the included `.htaccess` handles routing. Enable:
```
mod_rewrite, mod_headers
```

**Nginx** sample config:
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/nikahin;
    index frontend/pages/landing.html;

    # API routing
    location /api/ {
        try_files $uri $uri/ /backend/index.php?$query_string;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root/backend/index.php;
        include fastcgi_params;
    }

    # Public invitation pages
    location ~* ^/([a-z0-9\-]+)$ {
        try_files $uri /frontend/invitation/index.html;
    }

    location / {
        try_files $uri $uri/ =404;
    }
}
```

### 5. Upload Directory
```bash
mkdir -p uploads && chmod 755 uploads
```

### 6. Install (optional dev tools)
```bash
npm install
npm run dev    # Starts live-server for frontend dev
```

---

## Project Structure

```
nikahin/
├── backend/
│   ├── api/           → REST endpoints (auth, invitation, ai, guest, upload)
│   ├── config/        → Database + AI config
│   ├── helpers/       → JWT, response, mailer, AI functions
│   └── index.php      → Router
├── frontend/
│   ├── pages/         → App pages (login, landing, create, view, publish)
│   ├── invitation/    → Public invitation viewer
│   └── assets/        → CSS + JS
├── database/
│   └── schema.sql
├── uploads/           → User uploaded photos (create this dir)
├── .htaccess
└── README.md
```

---

## API Reference

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/auth/register` | — | Create account |
| POST | `/api/auth/login` | — | Login → JWT |
| GET | `/api/auth/me` | ✓ | Current user |
| GET | `/api/invitation` | ✓ | List my invitations |
| POST | `/api/invitation` | ✓ | Create draft |
| GET | `/api/invitation/{id}` | ✓ | Get invitation |
| PUT | `/api/invitation/{id}` | ✓ | Update invitation |
| DELETE | `/api/invitation/{id}` | ✓ | Delete invitation |
| POST | `/api/invitation/{id}/publish` | ✓ | Publish |
| POST | `/api/invitation/{id}/simulate-pay` | ✓ | Simulate payment (dev) |
| GET | `/api/invitation/public/{slug}` | — | Public view |
| POST | `/api/ai/generate-design` | ✓ | AI design spec |
| POST | `/api/ai/generate-bio` | ✓ | AI bio writer |
| POST | `/api/ai/generate-photo-prompt` | ✓ | AI photo scene |
| POST | `/api/ai/autocomplete-greeting` | ✓ | AI greeting complete |
| POST | `/api/ai/generate-thankyou` | ✓ | AI RSVP thank-you |
| POST | `/api/guest/add` | ✓ | Add guests |
| GET | `/api/guest/{id}` | ✓ | List guests |
| POST | `/api/guest/blast` | ✓ | Email + WA blast |
| POST | `/api/guest/rsvp` | — | Submit RSVP |
| GET | `/api/guest/rsvp/{id}` | ✓ | List RSVPs |
| POST | `/api/guest/greeting` | — | Post greeting |
| GET | `/api/guest/greetings/{id}` | — | List greetings |
| POST | `/api/upload` | ✓ | Upload photo |

---

## AI Features (Claude)

| Feature | Endpoint | Model |
|---------|----------|-------|
| Invitation design generation | `/api/ai/generate-design` | claude-sonnet-4-20250514 |
| Couple bio writing | `/api/ai/generate-bio` | claude-opus-4-20250514 |
| Pre-wedding photo prompt | `/api/ai/generate-photo-prompt` | claude-sonnet-4-20250514 |
| Greeting autocomplete | `/api/ai/autocomplete-greeting` | claude-sonnet-4-20250514 |
| RSVP thank-you message | `/api/ai/generate-thankyou` | claude-sonnet-4-20250514 |

---

## User Flow

1. **Register/Login** → `login.html`
2. **Landing** → `landing.html` (5 menu: Create, View, Planner, Gallery, Report)
3. **Create** → `create.html` (10-step wizard with AI bio + AI photo prompt)
4. **View** → `view.html` (preview, stats, AI design display, RSVP list)
5. **Publish** → `publish.html` (guest management + WA/email blast)
6. **Public URL** → `/[groom-bride]` → `invitation/index.html` (cinematic experience)

---

Made with ❤️ + AI by nikahin team
