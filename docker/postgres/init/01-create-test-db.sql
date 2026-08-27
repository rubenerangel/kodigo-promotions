-- Runs only when the PostgreSQL cluster is initialized for the first time,
-- which is what happens after `docker compose down -v` followed by `up`.
--
-- Creates the database the PHPUnit suite connects to. The name must match the
-- DB_DATABASE value in backend/phpunit.xml.
CREATE DATABASE promotions_test;
