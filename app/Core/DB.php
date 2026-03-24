<?php
namespace Core;
use PDO;

class DB {
    public static function conn() {
        return new PDO(
            "mysql:host=localhost;dbname=yourdatabase;charset=utf8mb4",
            "root",
            "",
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
}
