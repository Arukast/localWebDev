#!/bin/sh
# Runs once, when the MariaDB data volume is first created.
# Grants the .env app user (MARIADB_USER) full admin rights, matching
# PostgreSQL's POSTGRES_USER (which is a superuser by image design).
set -e
mariadb --user=root --password="$MARIADB_ROOT_PASSWORD" -e \
  "GRANT ALL PRIVILEGES ON *.* TO '${MARIADB_USER}'@'%' WITH GRANT OPTION; FLUSH PRIVILEGES;"
