<?php
session_start();

require_once '../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/utils.php';

use Google\Client;

$client = new Client();

// Povinne, zavolanie funkcie setAuthConfig pre nastavenie cesty s autorizacnymi udajmi OAuth klienta 
// ktore sa nachadzaju v client_secret.json subore. Subor je mozne stiahnut z Google Cloud konzoly.
$client->setAuthConfig(__DIR__ . '/client_secret.json');
$redirect_uri = "http://localhost:8080/private/oauth2callback.php"; // Zadajte URI pre presmerovanie z OAuth2. Musi suhlasit s URI zadanym v Google Cloud konzole.
$client->setRedirectUri($redirect_uri);

// Povinne, zavolanie funkcie addScope pre ziskanie pozadovaneho rozsahu udajov.
// Mame pravo len na udaje, ktore sme povolili v konfiguracii klienta v Google konzole.
// Scopes definuju uroven pristupu a rozsahu udajov, ktore aplikacia pozaduje od Google.
$client->addScope(["email", "profile"]);
// Povolenie inkrementalnej autorizacie. Odporucane ako best practice.
$client->setIncludeGrantedScopes(true);

// Odporucane, offline pristup nam poskytne acces token a refresh token, ktore vieme pouzit 
// na obnovenie pristupu aj bez nutnej interakcie a zasahu pouzivatela.
$client->setAccessType("offline");

// Vygenerovanie URL pre autorizaciu, pokial neobsahuje uz autorizacny kod alebo chybovu hlasku
if (!isset($_GET['code']) && !isset($_GET['error'])) {
    // Generovanie a nastavenie state premennej
    $state = bin2hex(random_bytes(16));
    $client->setState($state);
    $_SESSION['state'] = $state;

    // Generovanie URL, ktora vyziada od pouzivatela opravnenie na poskytnutie udajov.
    $auth_url = $client->createAuthUrl();
    header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
}

// Pouzivatel autorizoval poziadavku a bol nam vrateny autorizacnykod na výmenu za pristupovy token a obnovovaci token.  
// Ak parameter state nie je nastavený alebo sa nezhoduje s parametrom state v autorizacnej poziadavke,
// je mozne, ze poziadavku vytvorila tretia strana a pouzivatel bude presmerovaný na URL s chybovou správou.  
// Ak bola autorizacia uspesna, URI odpovede bude obsahovat autorizacny kod.
if (isset($_GET['code'])) {
    // Skontroluj hodnotu state.
    if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['state']) {
        die('State mismatch. Possible CSRF attack.');
    }

    // Ziskaj pristupovy a obnovovaci token (ak access_type je nasteveny na offline)
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
    // Debug (dočasne)
    // echo '<pre>Token: '; print_r($token); echo '</pre>'; 
    
    if (isset($token['error'])) {
        die('Chyba pri získavaní tokenu: ' . $token['error']);
    }
    
    // NASTAV TOKEN DO KLIENTA - TOTO JE KĽÚČOVÉ!
    $client->setAccessToken($token);
    
    // Ulož do session
    $_SESSION['access_token'] = $token;
    $_SESSION['refresh_token'] = $client->getRefreshToken();

    // Skontroluj, či je token nastavený
    if (!$client->getAccessToken()) {
        die('Token nebol správne nastavený');
    }
    // Ulozenie pristupoveho a obnovovacieho tokenu do session
    // TODO: Na produkcnom prostredi by sme si to mali ulozit do nejakeho perzistentneho uloziska, napr. databaza
    try {
        $pdo = connectDatabase($hostname, $database, $username, $password);
    } catch (Throwable $e) {
        die("Chyba pripojenia k databáze: " . $e->getMessage());
    }

    // TODO: na tomto mieste je potrebne ulozit informaciu o prihlaseni pouzivatela do databazy. Typ bude OAUTH. 
    $oauth2 = new Google\Service\Oauth2($client);
    $userInfo = $oauth2->userinfo->get();

    // Skontroluj či používateľ už existuje v databáze
    if (userExist($pdo, $userInfo->email) == false) {
        // Používateľ neexistuje - vytvor nový záznam
        $sql = "INSERT INTO user_accounts (first_name, last_name, email) 
                VALUES (:first_name, :last_name, :email)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':first_name' => $userInfo->givenName ?? '',
            ':last_name' => $userInfo->familyName ?? '',
            ':email' => $userInfo->email
        ]);
        $userId = $pdo->lastInsertId();
        $_SESSION['created_at'] = date('Y-m-d H:i:s');
    } else {
        $sql = "SELECT id, created_at FROM user_accounts WHERE email = :email";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':email' => $userInfo->email]);
        $row = $stmt->fetch();
        $userId = $row['id'];
        $_SESSION['created_at'] = $row['created_at'];
    }
    $_SESSION['loggedin'] = true;
    $_SESSION['full_name'] = $userInfo->name;
    $_SESSION['email'] = $userInfo->email;
    $_SESSION['user_id'] = $userId;
    $_SESSION['gid'] = $userInfo->id;

    // Ulož do histórie
    saveLoginHistory($pdo, $userId, 'OAUTH');

        $redirect_uri = 'http://localhost:8080/private/privateZone.php'; // Presmerovanie na zabezpecenu stranku alebo index.
        header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
    }
// Ak nam Google server vratil error, zobrazime chybu na stranke - pouzivatel nie je autentifikovany
if (isset($_GET['error'])) {
    echo "Error: " . $_GET['error'];
}