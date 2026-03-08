<?php
// config.php

// Prikazy ini_set() a error_reporting() zabezpecuju:
// vsetky chyby a upozornenia PHP budú zobrazené priamo na stranke,
// zobrazia sa aj chyby pri starte skriptu,
// E_ALL znamena, ze sa zobrazia vsetky typy chyb.

// POZOR: Pouzivajte VYHRADNE vo vyvojovom prostredi prostredí (localhost, test server). Pri nasadeni musia byt tieto nastavenia vypnute - zakomentovane.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Databazove konkfiguracne premenne:
$hostname = "db";  // adresa servera, kedze DB je na rovnakom zeleze ako Nginx, je tu localhost
$database = "Webtech";  // nazov databazy - v nasom pripade to bolo ogames_app
$username = "root";  // nazov pouzivatela - ktoreho ste vytvarali cez MariaDB konzolu. Ak ste isli podla navodu, mal by to byt vas login.
$password = "root";  // heslo, ktore ste zadavali v MariaDB konzole - mali ste si ho zapisat alebo zapamatat.

// Funkcia sluzi ako abstrakcia pripojenia k DB – po include ju mozeme zavolat.
function connectDatabase($hostname, $database, $username, $password) {
    try {
        // Vytvorenie objektu PDO a nadviazanie spojenia s databazou. Aj ked je tu mysql, MariaDB je plne kompatibilna.
        $conn = new PDO("mysql:host=$hostname;dbname=$database", $username, $password);
        // Chyby DB budu vracane ako vynimky (exceptions), mozeme ich odchytavat v try-catch blokoch.
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Ak je pripojenie uspesne, funkcia vrati PDO objekt - SQL prepare, query, transakcie, praca s DB...
        return $conn;
    } catch (PDOException $e) {
        echo "Connection failed: " . $e->getMessage();
        return null;
    }
}
?>
