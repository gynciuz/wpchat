#!/usr/bin/env bash
#
# Install the WordPress test framework so phpunit can run integration
# tests against a real WP + WC stack. Standard wp-cli-derived script:
# downloads WP core, the test scaffold, creates a fresh database, and
# writes wp-tests-config.php pointing at it.
#
# Usage:
#   bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
#
# In CI we pass the MySQL service host (mysql or 127.0.0.1) as db-host
# and "latest" as wp-version. Locally with docker-compose, default args
# work after `docker compose up`.

set -euo pipefail

DB_NAME=${1-wpchat_tests}
DB_USER=${2-root}
DB_PASS=${3-root}
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}

WP_TESTS_DIR=${WP_TESTS_DIR-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-/tmp/wordpress/}

download() {
    if command -v curl >/dev/null 2>&1; then
        curl -fsSL "$1" -o "$2"
    elif command -v wget >/dev/null 2>&1; then
        wget -q -O "$2" "$1"
    else
        echo "Need curl or wget." >&2
        exit 1
    fi
}

install_wp() {
    if [ -d "$WP_CORE_DIR" ]; then
        return
    fi
    mkdir -p "$WP_CORE_DIR"

    if [ "$WP_VERSION" = "latest" ]; then
        ARCHIVE_URL="https://wordpress.org/latest.tar.gz"
    elif [ "$WP_VERSION" = "trunk" ] || [ "$WP_VERSION" = "nightly" ]; then
        ARCHIVE_URL="https://wordpress.org/nightly-builds/wordpress-latest.zip"
    else
        ARCHIVE_URL="https://wordpress.org/wordpress-${WP_VERSION}.tar.gz"
    fi

    download "$ARCHIVE_URL" /tmp/wordpress.tar.gz
    tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C "$WP_CORE_DIR"

    download https://raw.githubusercontent.com/markoheijnen/wp-mysqli/master/db.php "$WP_CORE_DIR/wp-content/db.php" || true
}

install_test_suite() {
    if [ -d "$WP_TESTS_DIR" ]; then
        return
    fi
    mkdir -p "$WP_TESTS_DIR/includes" "$WP_TESTS_DIR/data"

    # Pull the WP test scaffold via SVN (the canonical way).
    if ! command -v svn >/dev/null 2>&1; then
        echo "svn is required to fetch the WP test scaffold." >&2
        exit 1
    fi
    svn co --quiet https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/includes "$WP_TESTS_DIR/includes" \
        || svn co --quiet https://develop.svn.wordpress.org/trunk/tests/phpunit/includes "$WP_TESTS_DIR/includes"
    svn co --quiet https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/data "$WP_TESTS_DIR/data" \
        || svn co --quiet https://develop.svn.wordpress.org/trunk/tests/phpunit/data "$WP_TESTS_DIR/data"

    download https://develop.svn.wordpress.org/trunk/wp-tests-config-sample.php "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i.bak "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR':" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i.bak "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i.bak "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i.bak "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i.bak "s|localhost|$DB_HOST|" "$WP_TESTS_DIR/wp-tests-config.php"
    rm -f "$WP_TESTS_DIR/wp-tests-config.php.bak"
}

install_db() {
    local mysql_args
    if [[ "$DB_HOST" =~ ^([^:]+):([0-9]+)$ ]]; then
        mysql_args=(-h "${BASH_REMATCH[1]}" -P "${BASH_REMATCH[2]}" --protocol=tcp)
    else
        mysql_args=(-h "$DB_HOST")
    fi
    mysqladmin "${mysql_args[@]}" -u "$DB_USER" -p"$DB_PASS" create "$DB_NAME" --force >/dev/null 2>&1 || true
}

install_wp
install_test_suite
install_db

echo "WP_TESTS_DIR=$WP_TESTS_DIR"
echo "WP_CORE_DIR=$WP_CORE_DIR"
echo "DB: $DB_NAME @ $DB_HOST"
