#!/bin/sh
# Creates the parallel test database phpunit.xml/CI expect
# (machinery_maintenance_test). Runs once, on first cluster init.
#
# --dbname is required: without it psql connects to a database named after
# the user, which doesn't exist here.
set -e
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-SQL
    CREATE DATABASE ${POSTGRES_DB}_test OWNER ${POSTGRES_USER};
SQL
