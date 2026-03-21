<?php
// Zacneme session pre pracu s prihlasenym pouzivatelom
session_start();

// Ak je uz pouzivatel prihlaseny, presmerujeme ho na hlavnu stranku (index.php)
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: index.php");
    exit;
}

// Nacitame navigacne menu
require_once '../navigation.php';
// Nacitame autoload pre kniznice (composer)
require_once '../vendor/autoload.php';

// Pouzitie kniznice pre dvojfaktorovu autentizaciu (2FA)
use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Providers\Qr\BaconQrCodeProvider; // Poskytovatel pre generovanie QR kodu

// Nacitame konfiguracne udaje (databaza) a pomocne funkcie
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/utils.php';

$registration_success = false; // Premenna na zistenie, ci registracia prebehla uspesne

// Spracovanie odoslaneho formulara
if ($_SERVER["REQUEST_METHOD"] == "POST") {  
    $errors = ""; // Zber chybovych hlaskov
    
    // Pokus o pripojenie k databaze
    try {
        $pdo = connectDatabase($hostname, $database, $username, $password);
        $dbMessage = $pdo ? 'Pripojené k databáze' : '✗ Nepripojené k databáze';
    } catch (Throwable $e) {
        $dbMessage = 'Chyba: ' . $e->getMessage();
    }
    
    // --- Validacia vstupov ---
    // Kontrola emailu
    if (isEmpty($_POST['email']) === true) {
        $errors .= "Nevyplnený e-mail.<br>";
    }

    if(isInvalidEmail($_POST['email']) == true) {
        $errors .= "Incorrect email.<br>";
    }

    // Kontrola ci uz email nie je pouzity
    if (isset($pdo) && userExist($pdo, $_POST['email']) === true) {
        $errors .= "Používateľ s týmto e-mailom už existuje.<br>";
    }

    // Kontrola mena a priezviska
    if (isEmpty($_POST['first_name']) === true) {
        $errors .= "Nevyplnené meno.<br>";
    } elseif (isEmpty($_POST['last_name']) === true) {
        $errors .= "Nevyplnené priezvisko.<br>";
    }

    // Kontrola povolenych znakov v mene a priezvisku (vola funkciu z utils.php)
    if (isInvalidName($_POST['first_name']) == true) {
        $errors .= "Invalid first name.<br>";
    } elseif (isInvalidName($_POST['last_name']) == true) {
        $errors .= "Invalid last name.<br>";
    }

    // Kontrola hesla
    if (isEmpty($_POST['password']) === true) {
        $errors .= "Nevyplnené heslo.<br>";
    }

    // Kontrola zhody hesiel
    if (isInvalidPassword($_POST['password'], $_POST['password_repeat']) == true) {
        $errors .= "Hesla sa nezhoduju.<br>";
    }
     if(checkPasswordLength($password)) {
        $errors.="heslo musi mat min dlzku 8 obsahovat aspon 1 male a velke pismeno a aspon jedne cislo";
    }

    // Ziskame hodnoty z formulara (alebo prazdny retazec ak nie su nastavene)
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password_user = $_POST['password'] ?? '';
    $password_repeat = $_POST['password_repeat'] ?? '';

    // Ak nie su ziadne chyby, pokracujeme s registraciou
    if (empty($errors)) {
        // Pripravime SQL dotaz pre vlozenie noveho pouzivatela
        $stmt = $pdo->prepare("INSERT INTO user_accounts (first_name, last_name, email, password_hash, tfa_secret) VALUES (:first_name, :last_name, :email, :password_hash, :tfa_secret)");

        // Vytvorime hash hesla pomocou bezpecneho algoritmu ARGON2ID
        $pw_hash = password_hash($password_user, PASSWORD_ARGON2ID);

        // Vytvorime objekt pre 2FA a vygenerujeme tajny kluc pre pouzivatela
        $tfa = new TwoFactorAuth(new BaconQrCodeProvider(4, '#ffffff', '#000000', 'svg'));
        $user_secret = $tfa->createSecret(); // Generovanie nahodneho secret kluca
        $qr_code = $tfa->getQRCodeImageAsDataUri('Olympic Games APP', $user_secret); // Vytvorenie QR kodu

        // Naviazanie parametrov na prepared statement
        $stmt->bindParam(":first_name", $first_name, PDO::PARAM_STR);
        $stmt->bindParam(":last_name", $last_name, PDO::PARAM_STR);
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        $stmt->bindParam(":password_hash", $pw_hash, PDO::PARAM_STR);
        $stmt->bindParam(":tfa_secret", $user_secret, PDO::PARAM_STR);

        // Vykonanie dotazu
        if ($stmt->execute()) {
            // Ak vlozenie prebehlo uspesne, nastavime flag a pripravime spravu s 2FA udajmi
            $registration_success = true;
            $message = '<div class="success-message-global"><i class="fas fa-check-circle"></i> Registrácia prebehla úspešne.</div>';
            $message .= '<p style="margin-top:15px;">Zadajte kód: <strong>' . $user_secret . '</strong> do aplikácie pre 2FA</p>';
            $message .= '<p>alebo naskenujte QR kód:<br><img src="' . $qr_code . '" alt="qr kod pre aplikaciu authenticator" style="max-width:200px; margin-top:10px;"></p>';
            $message .= '<p style="margin-top:15px;">Teraz sa môžete prihlásiť: <a href="login.php" class="btn-link">Prihlásiť sa</a></p>';
        } else {
            // V pripade chyby pri vkladani do databazy
            $error_info = $stmt->errorInfo();
            $message = '<div class="error-message-global"><i class="fas fa-exclamation-circle"></i> Chyba pri registrácii: ' . $error_info[2] . '</div>';
        }

        unset($stmt); // Uvolnenie prepared statement
    } else {
        // Ak boli chyby validacie, zobrazime ich v sprave
        $message = '<div id="globalError" class="error-message-global"><i class="fas fa-exclamation-circle"></i> ' . $errors . '</div>';
    }
    unset($stmt);
    unset($pdo); // Uvolnenie databazoveho pripojenia
}

?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrácia · Olympijská databáza</title>
    <!-- Font Awesome pre ikony -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/register.css">
       
</head>
<body>

    <!-- Menu je už vykreslené pomocou navigation.php -->

    <div class="register-wrapper">
        <div class="register-card">
            <h1>Vytvorte si konto</h1>
            <div class="subtitle">Registrácia do Olympijskej databázy</div>

            <?php if (isset($message)) echo $message; ?>

            <?php if (!$registration_success): ?>
                <!-- Zobrazenie registracneho formulara iba ak este neprebehla uspesna registracia -->
                <form method="post" action="" id="registerForm">
                    <div class="input-group">
                        <label for="firstname">Meno</label>
                        <i class="fas fa-user"></i>
                        <input type="text" name="first_name" id="firstname" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" placeholder="napr. John" maxlength="64">
                        <div class="char-counter" id="firstname-counter">0/64</div>
                        <div class="error-message-field" id="error-firstname"></div>
                    </div>

                    <div class="input-group">
                        <label for="lastname">Priezvisko</label>
                        <i class="fas fa-user"></i>
                        <input type="text" name="last_name" id="lastname" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" placeholder="napr. Doe" maxlength="64">
                        <div class="char-counter" id="lastname-counter">0/64</div>
                        <div class="error-message-field" id="error-lastname"></div>
                    </div>

                    <div class="input-group">
                        <label for="email">E-mail</label>
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" id="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" placeholder="napr. johndoe@example.com">
                        <div class="error-message-field" id="error-email"></div>
                    </div>

                    <div class="input-group">
                        <label for="password">Heslo</label>
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" id="password" value="">
                        <div class="error-message-field" id="error-password"></div>
                    </div>

                    <div class="input-group">
                        <label for="password_repeat">Heslo znova</label>
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password_repeat" id="password_repeat" value="">
                        <div class="error-message-field" id="error-password_repeat"></div>
                    </div>

                    <button type="submit" class="btn-register">
                        <i class="fas fa-user-plus"></i> Vytvoriť konto
                    </button>
                </form>

                <div class="login-link">
                    Ak už máte účet? <a href="login.php">Prihláste sa</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="js/register.js"></script>
</body>
</html>