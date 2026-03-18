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
        $message = '<div class="error-message-global"><i class="fas fa-exclamation-circle"></i> ' . $errors . '</div>';
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
    <style>
        /* ----- VŠEOBECNÉ ŠTÝLY (fialová téma) ----- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(145deg, #f3e8ff 0%, #f5d0fe 100%);
            font-family: 'Segoe UI', Roboto, system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            position: relative;
        }

        /* Jemný geometrický vzor na pozadí */
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 30% 40%, rgba(200, 180, 255, 0.2) 0%, transparent 20%),
                radial-gradient(circle at 70% 80%, rgba(220, 190, 255, 0.15) 0%, transparent 25%),
                repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.03) 0px, rgba(255, 255, 255, 0.03) 2px, transparent 2px, transparent 8px);
            pointer-events: none;
            z-index: 0;
        }

        .main-navigation {
            width: 100%;
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .register-wrapper {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 500px;
            padding: 0 20px;
            margin: 0 auto;
        }

        .register-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px 35px;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.6);
            margin-bottom: 30px;
        }

        .register-card:hover {
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            background: rgba(255, 255, 255, 0.9);
        }

        h1 {
            text-align: center;
            color: #2d1b4e;
            font-size: 2rem;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .subtitle {
            text-align: center;
            color: #5b4b7a;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }

        /* Globálne správy (chyba / úspech) */
        .error-message-global, .success-message-global {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .error-message-global {
            background: #fff3f3;
            border-left: 4px solid #f44336;
            color: #d32f2f;
        }
        .error-message-global i {
            font-size: 1.2rem;
            color: #f44336;
        }
        .success-message-global {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            color: #2e7d32;
        }
        .success-message-global i {
            font-size: 1.2rem;
            color: #4caf50;
        }

        /* Odkaz v úspešnej správe (tlačidlo) */
        .btn-link {
            display: inline-block;
            background: #8b5cf6;
            color: white;
            padding: 8px 16px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            margin-top: 10px;
            transition: background 0.2s;
        }
        .btn-link:hover {
            background: #7c3aed;
        }

        /* Formulár */
        form {
            margin-top: 20px;
        }

        .input-group {
            margin-bottom: 22px;
            position: relative;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2d1b4e;
            font-size: 0.95rem;
        }

        .input-group i {
            position: absolute;
            left: 15px;
            top: 42px;
            color: #9b87b8;
            font-size: 1.1rem;
        }

        .input-group input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid #d9c9f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border 0.2s, box-shadow 0.2s;
            background: white;
        }

        .input-group input:focus {
            border-color: #8b5cf6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
        }

        .input-group input.error {
            border-color: #dc3545;
            background: #fff5f5;
        }

        .input-group input.success {
            border-color: #28a745;
        }

        .error-message-field {
            color: #dc3545;
            font-size: 0.85rem;
            margin-top: 5px;
            padding-left: 45px;
            display: none;
        }

        .error-message-field.visible {
            display: block;
        }

        /* Počítadlo znakov pre meno a priezvisko */
        .char-counter {
            font-size: 0.8em;
            color: #8b5cf6;
            margin-top: 3px;
            text-align: right;
            padding-right: 5px;
        }
        .char-counter.limit {
            color: #dc3545;
            font-weight: bold;
        }

        .btn-register {
            width: 100%;
            padding: 12px;
            background: #8b5cf6;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin: 10px 0 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-register:hover {
            background: #7c3aed;
        }

        .login-link {
            text-align: center;
            border-top: 1px solid #e0d6f0;
            padding-top: 25px;
            margin-top: 10px;
            color: #4a3a6e;
        }

        .login-link a {
            color: #8b5cf6;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .login-link a:hover {
            color: #7c3aed;
            text-decoration: underline;
        }

        /* QR kód */
        .qr-code {
            max-width: 200px;
            margin: 15px 0;
            border-radius: 10px;
        }

        @media (max-width: 500px) {
            .register-card {
                padding: 30px 20px;
            }
            .input-group i {
                top: 40px;
                left: 12px;
            }
            .input-group input {
                padding-left: 40px;
            }
            .nav-container {
                padding: 0 10px;
            }
        }
    </style>
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

    <script>
    // Pockame na nacitanie DOM stromu
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('registerForm');
        if (!form) return; // Ak formular neexistuje (po uspesnej registracii), koncime

        // Referencie na vstupne polia
        const firstName = document.getElementById('firstname');
        const lastName = document.getElementById('lastname');
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        const passwordRepeat = document.getElementById('password_repeat');

        // Pocitadla znakov
        const firstnameCounter = document.getElementById('firstname-counter');
        const lastnameCounter = document.getElementById('lastname-counter');
        const maxLength = 64;

        // Regulárny výraz pre meno a priezvisko (povoľuje len latinku, medzery a spojovníky)
        const nameRegex = /^[A-Za-z\s\-]+$/;

        // Funkcia na aktualizaciu pocitadla a odstranenie vizualnych stavov
        function updateNameField(field, counter) {
            const len = field.value.length;
            counter.textContent = len + '/' + maxLength;
            if (len > maxLength) {
                counter.classList.add('limit');
            } else {
                counter.classList.remove('limit');
            }
            // Odstranime triedy error/success – budu nastavene az pri submit
            field.classList.remove('error', 'success');
        }

        // Pridanie event listenerov na pocitanie znakov pri písaní
        firstName.addEventListener('input', function() {
            updateNameField(firstName, firstnameCounter);
        });
        lastName.addEventListener('input', function() {
            updateNameField(lastName, lastnameCounter);
        });

        // Inicializacia pocitadiel pri nacitani stranky (ak je hodnota z POST)
        updateNameField(firstName, firstnameCounter);
        updateNameField(lastName, lastnameCounter);

        // Funkcia na vymazanie vsetkych chybovych stavov
        function clearErrors() {
            document.querySelectorAll('.error-message-field').forEach(el => {
                el.textContent = '';
                el.classList.remove('visible');
            });
            document.querySelectorAll('input').forEach(el => {
                el.classList.remove('error', 'success');
            });
        }

        // Funkcia na zobrazenie chyby pre konkretne pole
        function showError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const errorDiv = document.getElementById('error-' + fieldId);
            if (field) field.classList.add('error');
            if (errorDiv) {
                errorDiv.textContent = message;
                errorDiv.classList.add('visible');
            }
        }

        // Obsluha odoslania formulara (klient-side validacia)
        form.addEventListener('submit', function(e) {
            clearErrors(); // Najprv odstranime stare chyby
            let isValid = true;

            // Validacia mena
            const firstNameVal = firstName.value.trim();
            if (firstNameVal === '') {
                showError('firstname', 'Meno je povinné.');
                isValid = false;
            } else if (firstNameVal.length > maxLength) {
                showError('firstname', 'Meno môže mať maximálne ' + maxLength + ' znakov.');
                isValid = false;
            } else if (!nameRegex.test(firstNameVal)) {
                showError('firstname', 'Meno môže obsahovať len latinské písmená, medzery a spojovníky.');
                isValid = false;
            } else {
                firstName.classList.add('success');
            }

            // Validacia priezviska
            const lastNameVal = lastName.value.trim();
            if (lastNameVal === '') {
                showError('lastname', 'Priezvisko je povinné.');
                isValid = false;
            } else if (lastNameVal.length > maxLength) {
                showError('lastname', 'Priezvisko môže mať maximálne ' + maxLength + ' znakov.');
                isValid = false;
            } else if (!nameRegex.test(lastNameVal)) {
                showError('lastname', 'Priezvisko môže obsahovať len latinské písmená, medzery a spojovníky.');
                isValid = false;
            } else {
                lastName.classList.add('success');
            }

            // Validacia emailu
            const emailVal = email.value.trim();
            if (emailVal === '') {
                showError('email', 'E-mail je povinný.');
                isValid = false;
            } else {
                const simpleEmailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!simpleEmailRegex.test(emailVal)) {
                    showError('email', 'Neplatný formát e-mailu (napr. meno@domena.sk).');
                    isValid = false;
                } else {
                    email.classList.add('success');
                }
            }

            // Validacia hesla (nesmie byt prazdne)
            if (password.value === '') {
                showError('password', 'Heslo je povinné.');
                isValid = false;
            } else {
                password.classList.add('success');
            }

            // Validacia potvrdenia hesla
            if (passwordRepeat.value === '') {
                showError('password_repeat', 'Zopakovanie hesla je povinné.');
                isValid = false;
            } else if (password.value !== passwordRepeat.value) {
                showError('password_repeat', 'Heslá sa nezhodujú.');
                isValid = false;
            } else {
                passwordRepeat.classList.add('success');
            }

            // Ak niektora validacia zlyhala, zablokujeme odoslanie formulara
            if (!isValid) {
                e.preventDefault();
            }
        });
    });
    </script>
</body>
</html>