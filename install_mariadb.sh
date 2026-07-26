#!/bin/bash
set -e
# -------------------------------------------------------
# Switch from MySQL to MariaDB 11.4 — LexPro
# Run: bash install_mariadb.sh
# -------------------------------------------------------

APP_DIR="/home/ubuntu/law-office"

echo "=== 1. Backup current MySQL database ==="
mysqldump -u lexpro -p'LexPro@2026!' --all-databases --routines --events > /tmp/mysql_backup.sql
echo "Backup saved to /tmp/mysql_backup.sql"

echo "=== 2. Install MariaDB 11.4 ==="
sudo apt update
sudo apt install -y software-properties-common curl
curl -fsSL https://mariadb.org/mariadb_release_signing_key.asc | sudo gpg --dearmor -o /usr/share/keyrings/mariadb-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/mariadb-keyring.gpg] https://mirror.mariadb.org/repo/11.4/ubuntu $(lsb_release -cs) main" | sudo tee /etc/apt/sources.list.d/mariadb.list
sudo apt update
sudo apt install -y mariadb-server mariadb-client

echo "=== 3. Stop & disable MySQL, start MariaDB ==="
sudo systemctl stop mysql 2>/dev/null || true
sudo systemctl disable mysql 2>/dev/null || true
sudo systemctl enable --now mariadb

echo "=== 4. Secure MariaDB ==="
sudo mariadb -e "ALTER USER 'root'@'localhost' IDENTIFIED BY 'LexPro@2026!'; FLUSH PRIVILEGES;" 2>/dev/null || true

echo "=== 5. Create database & user ==="
sudo mariadb -e "CREATE DATABASE IF NOT EXISTS lexpro DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mariadb -e "CREATE USER IF NOT EXISTS 'lexpro'@'localhost' IDENTIFIED BY 'LexPro@2026!';"
sudo mariadb -e "GRANT ALL PRIVILEGES ON lexpro.* TO 'lexpro'@'localhost'; FLUSH PRIVILEGES;"

echo "=== 6. Restore MySQL backup into MariaDB ==="
sudo mariadb -u root -p'LexPro@2026!' lexpro < /tmp/mysql_backup.sql
echo "Database restored."

echo "=== 7. Update .env (already set) ==="
# DB_CONNECTION=mysql stays — Laravel uses 'mysql' driver for MariaDB
# Only update if needed
cd $APP_DIR
sed -i 's/^DB_HOST=.*/DB_HOST=127.0.0.1/' .env
sed -i 's/^DB_PORT=.*/DB_PORT=3306/' .env
echo ".env updated."

echo "=== 8. Test Laravel connection ==="
cd $APP_DIR
php artisan config:cache
php artisan migrate:status
echo "=== DONE! MariaDB 11.4 is running ==="
echo ""
echo "Connect from Windows RDP: 141.145.144.249"
echo "DB user: lexpro"
echo "DB name: lexpro"
echo ""
echo "Verify: mariadb --version"
