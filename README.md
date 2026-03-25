# ESMOS Healthcare Platform

Docker Compose stack for ESMOS — Odoo 17 + Moodle + Nginx on Azure Southeast Asia.

## Services
- **Odoo 17** — operations and meal planning (external, port 8069)
- **Moodle** — compliance training (internal only, port 8080)
- **Nginx** — reverse proxy + SSL termination
- **PostgreSQL 15** — Odoo database
- **MariaDB 10.6** — Moodle database

## First-time setup
1. Add SSL certs to `nginx/certs/fullchain.pem` and `nginx/certs/privkey.pem`
2. Place DB dump at `odoo_backup.sql` (gitignored)
3. Run: `docker compose up -d`
4. Import DB: `docker exec -i esmos-odoo-db-1 psql -U odoo17 odoo < odoo_backup.sql`

## Ports
| Service | Port |
|---------|------|
| Nginx HTTPS | 443 |
| Nginx HTTP (redirect) | 80 |