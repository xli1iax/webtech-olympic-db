<?php
session_start();
// Uvolnenie session premennych. Nasledujuce dva prikazy su ekvivalentne a staci pouzit jeden.
$_SESSION = array();
session_unset();

session_destroy();  // Vymazanie session 
header("location: /public/index.php");  // Presmerovanie na inu stranku.
exit;  // Ukoncenie vykonavania PHP skriptu.