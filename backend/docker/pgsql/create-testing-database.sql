-- Base de datos aislada para la suite de pruebas (phpunit.xml / .env.testing).
SELECT 'CREATE DATABASE restaurante_kds_test'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'restaurante_kds_test')\gexec
