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

    if(checkPasswordLength($password)) {
        $errors.="heslo musi mat min dlzku 8 obsahovat aspon 1 male a velke pismeno a aspon jedne cislo";
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
    <link rel="stylesheet" href="css/login.css">
       
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

    <script src="js/login.js"></script>
</body>
</html>