#!/bin/bash
set -e

# ── All config via environment variables with sensible defaults ──
MOODLE_DIR="/var/www/html"
MOODLEDATA_DIR="/var/www/moodledata"
CONFIG_FILE="${MOODLE_DIR}/config.php"

DB_TYPE="${MOODLE_DB_TYPE:-mysqli}"
DB_HOST="${MOODLE_DB_HOST:-moodle-db}"
DB_PORT="${MOODLE_DB_PORT:-3306}"
DB_NAME="${MOODLE_DB_NAME:-moodle}"
DB_USER="${MOODLE_DB_USER:-moodle}"
DB_PASS="${MOODLE_DB_PASS:-moodlepass}"
DB_PREFIX="${MOODLE_DB_PREFIX:-mdl_}"

SITE_URL="${MOODLE_SITE_URL:-http://localhost:8080}"
SITE_FULLNAME="${MOODLE_SITE_FULLNAME:-Moodle}"
SITE_SHORTNAME="${MOODLE_SITE_SHORTNAME:-moodle}"

ADMIN_USER="${MOODLE_ADMIN_USER:-admin}"
ADMIN_PASS="${MOODLE_ADMIN_PASS:-Admin1234!}"
ADMIN_EMAIL="${MOODLE_ADMIN_EMAIL:-admin@example.com}"

# ── Wait for database ────────────────────────────────────────────
echo "Waiting for database at ${DB_HOST}:${DB_PORT}..."
for i in $(seq 1 30); do
    if php -r "\$c = @new mysqli('${DB_HOST}', '${DB_USER}', '${DB_PASS}', '${DB_NAME}', ${DB_PORT}); if (\$c->connect_errno) exit(1);" 2>/dev/null; then
        echo "Database is ready."
        break
    fi
    echo "  ...waiting ($i/30)"
    sleep 2
done

# ── Write config.php if it doesn't exist ─────────────────────────
if [ ! -f "$CONFIG_FILE" ]; then
    echo "Creating Moodle config.php..."
    cat > "$CONFIG_FILE" <<CFGEOF
<?php
unset(\$CFG);
global \$CFG;
\$CFG = new stdClass();

\$CFG->dbtype    = '${DB_TYPE}';
\$CFG->dblibrary = 'native';
\$CFG->dbhost    = '${DB_HOST}';
\$CFG->dbname    = '${DB_NAME}';
\$CFG->dbuser    = '${DB_USER}';
\$CFG->dbpass    = '${DB_PASS}';
\$CFG->prefix    = '${DB_PREFIX}';
\$CFG->dboptions = array(
    'dbcollation' => 'utf8mb4_unicode_ci',
);

\$CFG->wwwroot   = '${SITE_URL}';
\$CFG->dataroot  = '${MOODLEDATA_DIR}';
\$CFG->admin     = 'admin';
\$CFG->directorypermissions = 0777;

require_once(__DIR__ . '/lib/setup.php');
CFGEOF
    chown www-data:www-data "$CONFIG_FILE"
fi

# ── Install Moodle if database is empty ──────────────────────────
TABLE_COUNT=$(php -r "
    \$c = new mysqli('${DB_HOST}', '${DB_USER}', '${DB_PASS}', '${DB_NAME}', ${DB_PORT});
    \$r = \$c->query('SHOW TABLES');
    echo \$r->num_rows;
")

if [ "$TABLE_COUNT" = "0" ]; then
    echo "Running Moodle install..."
    php "${MOODLE_DIR}/admin/cli/install_database.php" \
        --adminuser="${ADMIN_USER}" \
        --adminpass="${ADMIN_PASS}" \
        --adminemail="${ADMIN_EMAIL}" \
        --fullname="${SITE_FULLNAME}" \
        --shortname="${SITE_SHORTNAME}" \
        --agree-license
    echo "Moodle install complete."
else
    echo "Moodle database already installed (${TABLE_COUNT} tables found)."
fi

# ── Fix permissions & start ──────────────────────────────────────
chown -R www-data:www-data "$MOODLEDATA_DIR"

echo "Starting Apache..."
exec apache2-foreground
