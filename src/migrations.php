<?php

function migrate(PDO $pdo)
{
//    $stmt = $pdo->query("SELECT * FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'results'");
//    while ($stmt->fetch()) {
//        printLn("skip migration");
//        return;
//    }

    printLn("start migration");

    $pdo->exec(<<<SQL
        DROP SCHEMA public CASCADE ;
        CREATE SCHEMA public;

        CREATE TABLE status_string(
            value VARCHAR PRIMARY KEY
        );
        INSERT INTO status_string VALUES ('draft'), ('published'), ('approve'), ('reject'), ('delivery'), ('complete');
        
        CREATE TABLE status_int(
            id SMALLSERIAL PRIMARY KEY,
            value VARCHAR
        );
        INSERT INTO status_int (value) VALUES ('draft'), ('published'), ('approve'), ('reject'), ('delivery'), ('complete');
        
        CREATE TYPE status_enum AS ENUM ('draft', 'published', 'approve', 'reject', 'delivery', 'complete');

        ---------
        
        CREATE TABLE entity_random(
             id BIGSERIAL PRIMARY KEY,
             status SMALLINT CHECK (status > 0 AND status <= 6)
        );
        
        CREATE TABLE entity_string(
            id BIGSERIAL PRIMARY KEY,
            version INTEGER DEFAULT 0,
            status VARCHAR CHECK (status IN ('draft', 'published', 'approve', 'reject', 'delivery', 'complete'))
        );
        CREATE INDEX entity_string_status ON entity_string(status);
        
        CREATE TABLE entity_string_fk(
            id BIGSERIAL PRIMARY KEY,
            version INTEGER DEFAULT 0,
            status VARCHAR REFERENCES status_string(value)
        );
        
        CREATE TABLE entity_int(
           id BIGSERIAL PRIMARY KEY,
           version INTEGER DEFAULT 0,
           status SMALLINT CHECK (status > 0 AND status <= 6)
        );
        CREATE INDEX entity_int_status ON entity_int(status);
        
        CREATE TABLE entity_int_fk(
            id BIGSERIAL PRIMARY KEY,
            version INTEGER DEFAULT 0,
            status SMALLINT REFERENCES status_int(id)
        );
        
        CREATE TABLE entity_enum(
           id BIGSERIAL PRIMARY KEY,
           version INTEGER DEFAULT 0,
           status status_enum
        );
        CREATE INDEX entity_enum_status ON entity_enum(status);


        CREATE TABLE results(
           id BIGSERIAL PRIMARY KEY,
           total_row_count integer,
           attempt_num integer,
           query VARCHAR,
           type VARCHAR,
           execute_time_ms numeric
        );
    SQL);

    printLn("end migration");
}