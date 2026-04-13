<?php
function getDB() {
    static $conn;

    if ($conn === null) {
        $conn = new mysqli(
            $_ENV["MYSQLHOST"],
            $_ENV["MYSQLUSER"],
            $_ENV["MYSQLPASSWORD"],
            $_ENV["MYSQLDATABASE"],
            $_ENV["MYSQLPORT"]
        );

        if ($conn->connect_error) {
            die("DB Connection Failed: " . $conn->connect_error);
        }
    }

    return $conn;
}
?>