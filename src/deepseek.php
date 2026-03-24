<?php
session_start();

if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: restricted.php");
    exit;
}

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/utils.php";
require_once __DIR__ . "/vendor/autoload.php";

use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;

$errors = "";
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    try {
        $pdo = connectDatabase($hostname, $database, $username, $password);
        if (!$pdo) {
            throw new Exception("Nepodarilo sa pripojiť k databáze");
        }
    } catch (Throwable $e) {
        $errors = "Chyba pripojenia k databáze: " . $e->getMessage();
    }
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $totp = trim($_POST['totp'] ?? '');
    
    // Validácia vstupov
    if (isEmpty($email)) {
        $errors .= "Nezadaný email.<br>";
    } elseif (isInvalidEmail($email)) {
        $errors .= "Neplatný formát emailu.<br>";
    }
    
    if (isEmpty($password)) {
        $errors .= "Nezadané heslo.<br>";
    }
    
    // Pokračuj len ak nie sú chyby a PDO je nastavené
    if (empty($errors) && isset($pdo)) {
        
        $sql = "SELECT id, first_name, last_name, email, password_hash, created_at, tfa_secret 
                FROM user_accounts WHERE email = :email";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            if ($stmt->rowCount() == 1) {
                $row = $stmt->fetch();
                
                if (password_verify($password, $row["password_hash"])) {
                    
                    // Kontrola, či má používateľ nastavenú 2FA
                    if (empty($row["tfa_secret"])) {
                        // 2FA nie je nastavená - prihlásenie bez kódu
                        $_SESSION["loggedin"] = true;
                        $_SESSION["full_name"] = $row['first_name'] . " " . $row['last_name'];
                        $_SESSION["email"] = $row['email'];
                        $_SESSION["created_at"] = $row['created_at'];
                        $_SESSION["user_id"] = $row['id'];
                        
                        // Uloženie histórie prihlásenia
                        if (function_exists('saveLoginHistory')) {
                            saveLoginHistory($pdo, $row['id']);
                        }
                        
                        header("location: restricted.php");
                        exit;
                        
                    } else {
                        // 2FA je nastavená - treba overiť kód
                        if (isEmpty($totp)) {
                            $errors = "Zadajte 2FA kód.<br>";
                        } else {
                            try {
                                $qrProvider = new BaconQrCodeProvider();
                                $tfa = new TwoFactorAuth($qrProvider);
                                
                                if ($tfa->verifyCode($row["tfa_secret"], $totp, 2)) {
                                    // Kód je správny
                                    $_SESSION["loggedin"] = true;
                                    $_SESSION["full_name"] = $row['first_name'] . " " . $row['last_name'];
                                    $_SESSION["email"] = $row['email'];
                                    $_SESSION["created_at"] = $row['created_at'];
                                    $_SESSION["user_id"] = $row['id'];
                                    
                                    if (function_exists('saveLoginHistory')) {
                                        saveLoginHistory($pdo, $row['id']);
                                    }
                                    
                                    header("location: restricted.php");
                                    exit;
                                    
                                } else {
                                    $errors = "Nesprávny 2FA kód.";
                                }
                            } catch (Exception $e) {
                                $errors = "Chyba pri overovaní 2FA: " . $e->getMessage();
                            }
                        }
                    }
                } else {
                    $errors = "Nesprávne meno alebo heslo.";
                }
            } else {
                $errors = "Nesprávne meno alebo heslo.";
            }
        } else {
            $errors = "Chyba pri vyhľadávaní používateľa.";
        }
    }
    
    if (!empty($errors)) {
        $message = '<div class="error">' . $errors . '</div>';
    }
    
    unset($stmt);
    unset($pdo);
}

// Funkcia pre ukladanie histórie prihlásenia (ak nie je v utils.php)
if (!function_exists('saveLoginHistory')) {
    function saveLoginHistory($pdo, $userId, $loginType = 'LOCAL') {
        try {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $sql = "INSERT INTO login_history (user_id, login_type, ip_address) 
                    VALUES (:user_id, :login_type, :ip_address)";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                ':user_id' => $userId,
                ':login_type' => $loginType,
                ':ip_address' => $ip_address
            ]);
        } catch (Exception $e) {
            error_log("Chyba pri ukladaní histórie: " . $e->getMessage());
            return false;
        }
    }
}
?>

<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prihlásenie</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            width: 100%;
            max-width: 450px;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .card-header h1 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .card-header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .card-body {
            padding: 40px;
        }
        
        .error {
            background: #fee;
            color: #c33;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid #fcc;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        
        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102,126,234,0.3);
        }
        
        .links {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 2px solid #f0f0f0;
        }
        
        .links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .links a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .totp-field {
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 480px) {
            .card-body {
                padding: 25px;
            }
            
            .card-header {
                padding: 25px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1>Vitajte späť! 👋</h1>
                <p>Prihláste sa do svojho konta</p>
            </div>
            
            <div class="card-body">
                <?php if (!empty($message)) echo $message; ?>
                
                <form action="" method="post">
                    <div class="form-group">
                        <label for="email">
                            📧 E-Mail
                        </label>
                        <input 
                            type="email" 
                            name="email" 
                            id="email" 
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                            placeholder="vas@email.sk" 
                            required
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="password">
                            🔒 Heslo
                        </label>
                        <input 
                            type="password" 
                            name="password" 
                            id="password" 
                            placeholder="••••••••" 
                            required
                        >
                    </div>
                    
                    <div class="form-group totp-field">
                        <label for="totp">
                            🔐 2FA kód
                            <span style="font-size: 12px; color: #888; margin-left: 5px;">(ak je nastavený)</span>
                        </label>
                        <input 
                            type="text" 
                            name="totp" 
                            id="totp" 
                            placeholder="123456" 
                            pattern="[0-9]{6}" 
                            title="Zadajte 6-miestny kód z aplikácie"
                        >
                    </div>
                    
                    <button type="submit">
                        Prihlásiť sa →
                    </button>
                </form>
                
                <div class="links">
                    <p>Nemáte konto? <a href="register.php">Zaregistrujte sa</a></p>
                    <p style="margin-top: 10px; font-size: 13px;">
                        <a href="#">Zabudli ste heslo?</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>