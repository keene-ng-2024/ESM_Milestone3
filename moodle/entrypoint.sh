#!/bin/bash
set -e

# Generate Moodle config.php from environment variables if it doesn't exist
if [ ! -f /var/www/html/config.php ]; then
    cat > /var/www/html/config.php <<CFGEOF
<?php
unset(\$CFG);
global \$CFG;
\$CFG = new stdClass();

\$CFG->dbtype    = '${MOODLE_DB_TYPE:-mysqli}';
\$CFG->dbhost    = '${MOODLE_DB_HOST:-moodle-db}';
\$CFG->dbport    = '${MOODLE_DB_PORT:-3306}';
\$CFG->dbname    = '${MOODLE_DB_NAME:-moodle}';
\$CFG->dbuser    = '${MOODLE_DB_USER:-moodle}';
\$CFG->dbpass    = '${MOODLE_DB_PASS:-moodle}';
\$CFG->prefix    = 'mdl_';
\$CFG->dboptions = array(
    'dbcollation' => 'utf8mb4_unicode_ci',
);

\$CFG->wwwroot   = '${MOODLE_SITE_URL:-http://localhost:8080}';
\$CFG->dataroot  = '/var/www/moodledata';
\$CFG->admin     = 'admin';
\$CFG->directorypermissions = 0777;

require_once(__DIR__ . '/lib/setup.php');
CFGEOF
    chown www-data:www-data /var/www/html/config.php
fi

exec "$@"
