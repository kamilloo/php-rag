<?php
namespace service;

use PDO;
use PDOException;

abstract class AbstractDocumentRepository
{
    protected PDO $connection;

    public function __construct()
    {
        try {
            $this->connection = new PDO('pgsql:host=postgres;port=5432;dbname='.$_ENV["POSTGRES_DB"] .'',''.$_ENV["POSTGRES_USER"] .'',''.$_ENV["POSTGRES_PASSWORD"] .'');
        } catch (PDOException $e) {
            die('Connection failed: ' . $e->getMessage());
        }
    }
}