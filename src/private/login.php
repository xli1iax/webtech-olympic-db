<?php
// Zaciatok session pre pracu s prihlasenymi udajmi
session_start();

// Ak je uz pouzivatel prihlaseny, presmerujeme ho na chranenu stranku
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: restricted.php"); 
    exit;
}

// Nacitanie konfiguracie, pomocnych funkcii a autoloadu pre kniznice
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/utils.php"; 
require_once '../vendor/autoload.php';
require_once '../navigation.php';  // Vlozenie navigacneho menu

// URI pre navrat po prihlaseni cez Google (OAuth2)
$redirect_uri = "http://localhost:8080/private/oauth2callback.php";

// Pouzitie kniznice pre dvojfaktorovu autentizaciu
use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;

$errors = "";          // Premenna na zbieranie chybovych hlaskov
$error_message = "";   // Hotova sprava pre zobrazenie pouzivatelovi

// Spracovanie odoslaneho formulara
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Pokus o pripojenie k databaze
    try {
        $pdo = connectDatabase($hostname, $database, $username, $password);
    } catch (Throwable $e) {
        $errors = "Chyba pripojenia k databáze";
    }
    
    // Ziskaj a ocisti vstupne udaje
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validacia emailu
    if (isEmpty($email)) {
        $errors .= "Nezadaný email.<br>";
    } elseif (isInvalidEmail($email)) {
        $errors .= "Neplatný formát emailu.<br>";
    }
    
    // Validacia hesla
    if (isEmpty($password)) {
        $errors .= "Nezadané heslo.<br>";
    }
    
    // Ak nie su ziadne chyby a databazove pripojenie existuje, pokracuj
    if (empty($errors) && isset($pdo)) {
        
        // Vyhladanie pouzivatela podla emailu
        $sql = "SELECT id, first_name, last_name, email, password_hash, created_at, tfa_secret  
                FROM user_accounts WHERE email = :email";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            // Ak bol najdeny prave jeden zaznam
            if ($stmt->rowCount() == 1) {
                $row = $stmt->fetch();
                
                // Overenie hesla pomocou hash-u
                if (password_verify($password, $row["password_hash"])) {
                    // Overenie 2FA kodu
                    $qrProvider = new BaconQrCodeProvider();
                    $tfa = new TwoFactorAuth($qrProvider);
                    if ($tfa->verifyCode($row["tfa_secret"], $_POST['totp'] ?? '', 2)) {
                        // Vsetko v poriadku - vytvorime session pre prihlaseneho
                        $_SESSION["loggedin"] = true;
                        $_SESSION["full_name"] = $row['first_name'] . " " . $row['last_name'];
                        $_SESSION["email"] = $row['email'];
                        $_SESSION["created_at"] = $row['created_at'];

                        // Ulozenie historie prihlasenia
                        saveLoginHistory($pdo, $row['id'], 'LOCAL');

                        // Presmerovanie do sukromnej zony
                        header("location: privateZone.php");
                        exit;
                    } else {
                        // Neplatny 2FA kod
                        $errors = "Nesprávne meno alebo heslo.";
                    }
                } else {
                    // Nespravne heslo
                    $errors = "Nesprávne meno alebo heslo.";
                }
            } else {
                // Pouzivatel neexistuje
                $errors = "Chyba pri prihlásení.";
            }
        }
    }
    // Priprava chybovej spravy pre zobrazenie
    if (!empty($errors)) {
        $error_message = $errors;
    }
    
    // Uvolnenie prostriedkov
    unset($stmt);
    unset($pdo);
}
?>

<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prihlásenie · Olympijská databáza</title>
    <!-- Font Awesome pre ikony -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Vsetky styly zostavaju bez zmeny */
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

        /* Jemný geometrický vzor */
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

        .login-wrapper {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 450px;
            padding: 0 20px;
            margin: 0 auto;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px 35px;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.6);
        }

        .login-card:hover {
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

        .error-message-global {
            background: #fff3f3;
            border-left: 4px solid #f44336;
            color: #d32f2f;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error-message-global i {
            font-size: 1.2rem;
            color: #f44336;
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

        .btn-login {
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

        .btn-login:hover {
            background: #7c3aed;
        }

        .links {
            text-align: center;
            border-top: 1px solid #e0d6f0;
            padding-top: 25px;
            margin-top: 10px;
        }

        .links p {
            margin: 12px 0;
            color: #4a3a6e;
        }

        .links a {
            color: #8b5cf6;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .links a:hover {
            color: #7c3aed;
            text-decoration: underline;
        }

        .google-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: white;
            border: 1px solid #d9c9f0;
            border-radius: 30px;
            padding: 10px 25px;
            margin-top: 8px;
            transition: background 0.2s, border-color 0.2s;
            color: #4a3a6e;
        }

        .google-link:hover {
            background: #f3e8ff;
            border-color: #8b5cf6;
            text-decoration: none !important;
        }

        .google-link i {
            color: #ea4335;
            font-size: 1.2rem;
        }

        @media (max-width: 500px) {
            .login-card {
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

    <!-- Menu je uz vykreslene pomocou navigation.php -->

    <div class="login-wrapper">
        <div class="login-card">
            <h1>Vitajte späť</h1>
            <div class="subtitle">Prihláste sa do svojho konta</div>

            <!-- Zobrazenie chybovej spravy ak existuje -->
            <?php if (!empty($error_message)): ?>
                <div class="error-message-global">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Prihlasovaci formular -->
            <form action="" method="post" id="loginForm">
                <div class="input-group">
                    <label for="email">E-mail</label>
                    <i class="fas fa-envelope"></i>
                    <input type="text" name="email" id="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" placeholder="vas@email.sk" required>
                    <div class="error-message-field" id="error-email"></div>
                </div>

                <div class="input-group">
                    <label for="password">Heslo</label>
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" value="" placeholder="••••••••" required>
                    <div class="error-message-field" id="error-password"></div>
                </div>

                <div class="input-group">
                    <label for="totp">Kód pre 2FA</label>
                    <i class="fas fa-shield-alt"></i>
                    <input type="text" name="totp" id="totp" value="" placeholder="000000" required>
                    <div class="error-message-field" id="error-totp"></div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Prihlásiť sa
                </button>
            </form>

            <!-- Odkazy na registraciu a prihlasenie cez Google -->
            <div class="links">
                <p>Nemáte vytvorené konto? <a href="register.php">Zaregistrujte sa tu</a></p>
                <p>Alebo sa prihláste pomocou</p>
                <a href="<?php echo filter_var($redirect_uri, FILTER_SANITIZE_URL) ?>" class="google-link">
                    <i class="fab fa-google"></i> Google konto
                </a>
            </div>
        </div>
    </div>

    <script>
    // Pockame na nacitanie DOM stromu
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('loginForm');
        if (!form) return;  // ak formular neexistuje (napr. po uspesnom prihlaseni), koncime

        // Referencie na vstupne polia
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        const totp = document.getElementById('totp');

        // Funkcia na vymazanie vsetkych chybovych stavov
        function clearErrors() {
            document.querySelectorAll('.error-message-field').forEach(el => {
                el.textContent = '';
                el.classList.remove('visible');
            });
            document.querySelectorAll('input').forEach(el => el.classList.remove('error'));
        }

        // Funkcia na zobrazenie chyby pri konkretnom poli
        function showError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const errorDiv = document.getElementById('error-' + fieldId);
            if (field) field.classList.add('error');
            if (errorDiv) {
                errorDiv.textContent = message;
                errorDiv.classList.add('visible');
            }
        }

        // Obsluha odoslania formulara - spusti sa pred odoslanim na server
        form.addEventListener('submit', function(e) {
            clearErrors();   // najprv odstranime stare chyby
            let isValid = true;

            // Kontrola emailu: prazdny a spravny format
            const emailValue = email.value.trim();
            if (emailValue === '') {
                showError('email', 'E-mail je povinný.');
                isValid = false;
            } else {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(emailValue)) {
                    showError('email', 'Neplatný formát e-mailu.');
                    isValid = false;
                }
            }

            // Kontrola hesla - nesmie byt prazdne
            if (password.value === '') {
                showError('password', 'Heslo je povinné.');
                isValid = false;
            }

            // Kontrola 2FA kodu - nesmie byt prazdny
            if (totp.value === '') {
                showError('totp', 'Kód pre 2FA je povinný.');
                isValid = false;
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