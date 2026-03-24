<?php

namespace Core;

use PDO;
use App\Config\Config;

class DB
{
    public static function conn()
    {
        $dsn = "mysql:host=" . Config::DB_HOST .
               ";dbname=" . Config::DB_NAME .
               ";charset=" . Config::DB_CHARSET;

        return new PDO(
            $dsn,
            Config::DB_USER,
            Config::DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
}