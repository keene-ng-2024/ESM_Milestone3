# ESMOS Healthcare Platform

Docker Compose stack for ESMOS — Odoo 17 + Moodle + Nginx on Azure Southeast Asia.

## Services
- **Odoo 17** — operations and meal planning (external)
- **Moodle** — compliance training (internal only, via `training.*` subdomain)
- **Nginx** — reverse proxy + SSL termination
- **PostgreSQL 15** — Odoo database
- **MariaDB 10.6** — Moodle database

## First-time setup

1. Ensure `odoo_backup.sql` is in the project root (included in the repo) — it is automatically imported by PostgreSQL on first startup
2. Set up SSL certificates (see [SSL setup](#ssl-setup) below)
3. Run:
   ```bash
   docker compose up -d --build
   ```
4. Wait a few minutes for both databases to initialize:
   - PostgreSQL will import `odoo_backup.sql` automatically
   - Moodle will install its DB and set up the training course

### Local development — hosts file

Add these entries to your hosts file (`C:\Windows\System32\drivers\etc\hosts` on Windows, `/etc/hosts` on Linux/Mac):

```
127.0.0.1 esmos.local
127.0.0.1 training.esmos.local
```

### SSL setup

The `nginx/certs/` directory is gitignored — you must generate or provide certs locally.

**For local development** — generate self-signed certs:

```bash
mkdir -p nginx/certs
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout nginx/certs/privkey.pem \
  -out nginx/certs/fullchain.pem \
  -subj "/CN=localhost/O=ESMOS/C=SG"
```

Your browser will show a security warning — click through it (Advanced > Proceed).

**For production (Azure VM)** — use Let's Encrypt with your domain:

```bash
sudo apt install certbot
sudo certbot certonly --standalone -d yourdomain.com -d training.yourdomain.com
cp /etc/letsencrypt/live/yourdomain.com/fullchain.pem nginx/certs/fullchain.pem
cp /etc/letsencrypt/live/yourdomain.com/privkey.pem nginx/certs/privkey.pem
```

Both files must exist at these paths before starting nginx:
- `nginx/certs/fullchain.pem` — the full certificate chain (public)
- `nginx/certs/privkey.pem` — the private key

## Access URLs

| Service | URL |
|---------|-----|
| Odoo (via Nginx) | `https://esmos.local` |
| Moodle (via Nginx) | `https://training.esmos.local` |
| Odoo (direct) | `http://localhost:8069` |

## Ports

| Service | Port |
|---------|------|
| Nginx HTTPS | 443 |
| Nginx HTTP (redirect) | 80 |
| Odoo (direct) | 8069 |

## Test accounts

### Moodle

| Username | Password | Role |
|----------|----------|------|
| admin | moodle2140 | Administrator |
| staff1 | Esmos2024! | Student (Alice Tan) |
| staff2 | Esmos2024! | Student (Bob Lim) |
| staff3 | Esmos2024! | Student (Carol Wong) |

### Odoo

| Username | Password |
|----------|----------|
| admin | admin |

## Fresh restart

To wipe all data and start clean:

```bash
docker compose down -v && docker compose up -d --build
```
