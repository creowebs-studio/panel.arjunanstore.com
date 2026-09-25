-- Bootstrap database lokal ARJ (MySQL 8 di WSL / server apa pun).
-- Idempoten: aman dijalankan berulang.
--   mysql -uroot < tools/create-test-db.sql
--
-- Membuat:
--   * DB aplikasi  : arj        (dipakai .env aplikasi di repo root)
--   * DB tes       : arj_test   (dipakai phpunit.xml)
--   * user aplikasi: arj / secret untuk localhost DAN 127.0.0.1

CREATE DATABASE IF NOT EXISTS arj CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS arj_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'arj'@'localhost' IDENTIFIED BY 'secret';
CREATE USER IF NOT EXISTS 'arj'@'127.0.0.1' IDENTIFIED BY 'secret';
ALTER USER 'arj'@'localhost' IDENTIFIED BY 'secret';
ALTER USER 'arj'@'127.0.0.1' IDENTIFIED BY 'secret';

GRANT ALL PRIVILEGES ON arj.*      TO 'arj'@'localhost';
GRANT ALL PRIVILEGES ON arj.*      TO 'arj'@'127.0.0.1';
GRANT ALL PRIVILEGES ON arj_test.* TO 'arj'@'localhost';
GRANT ALL PRIVILEGES ON arj_test.* TO 'arj'@'127.0.0.1';

FLUSH PRIVILEGES;

SELECT 'DB siap: arj + arj_test' AS status;
