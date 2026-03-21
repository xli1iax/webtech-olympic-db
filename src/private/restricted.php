<?php
// Zacneme session pre pracu s prihlasenym pouzivatelom
session_start();
// Nacitame navigacne menu
require_once '../navigation.php';
// Nacitame konfiguraciu databazy
require_once __DIR__ . '/../config.php';
// Nacitame pomocne funkcie (validacia, pripojenie k DB)
require_once __DIR__ . '/utils.php';

use Google\Client; // Kniznica pre Google API

// Kontrola, ci je pouzivatel prihlaseny - ak nie, posleme ho na prihlasenie
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}

// Pokus o pripojenie k databaze
try {
    $pdo = connectDatabase($hostname, $database, $username, $password);
} catch (PDOException $e) {
    die("Chyba pripojenia k databáze: " . $e->getMessage());
}

// Ak v session chyba user_id (napr. po zmene uctu), ziskame ho z databazy podla emailu
if (!isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT id FROM user_accounts WHERE email = ?");
    $stmt->execute([$_SESSION['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
    } else {
        die("Používateľ nebol nájdený.");
    }
}

$user_id = $_SESSION['user_id'];
$is_google_user = isset($_SESSION['gid']); // Ak existuje gid, znamena to prihlasenie cez Google

// --- Spracovanie formulara pre zmenu mena a priezviska ---
$name_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_name'])) {
    $new_first = trim($_POST['first_name'] ?? '');
    $new_last = trim($_POST['last_name'] ?? '');
    
    $errors = [];
    
    // Validacia na strane servera (rovnaka ako pri registracii)
    if (isEmpty($new_first)) {
        $errors[] = "Meno je povinné.";
    } elseif (isInvalidName($new_first)) {
        $errors[] = "Meno obsahuje nepovolené znaky.";
    }
    
    if (isEmpty($new_last)) {
        $errors[] = "Priezvisko je povinné.";
    } elseif (isInvalidName($new_last)) {
        $errors[] = "Priezvisko obsahuje nepovolené znaky.";
    }
    
    // Ak nie su chyby, ulozime zmeny do databazy
    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE user_accounts SET first_name = ?, last_name = ? WHERE id = ?");
        if ($stmt->execute([$new_first, $new_last, $user_id])) {
            $_SESSION['full_name'] = $new_first . ' ' . $new_last;
            $name_message = '<div class="success">Údaje boli úspešne zmenené.</div>';
        } else {
            $name_message = '<div class="error">Chyba pri ukladaní.</div>';
        }
    } else {
        $name_message = '<div class="error">' . implode('<br>', $errors) . '</div>';
    }
}

// --- Spracovanie formulara pre zmenu hesla (len pre lokalnych uzivatelov) ---
$password_message = '';
if (!$is_google_user && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Zakladne kontroly
    if (empty($old) || empty($new) || empty($confirm)) {
        $password_message = '<div class="error">Vyplňte všetky polia.</div>';
    } elseif ($new !== $confirm) {
        $password_message = '<div class="error">Nové heslá sa nezhodujú.</div>';
    } else {
        // Ziskame aktualny hash hesla z databazy
        $stmt = $pdo->prepare("SELECT password_hash FROM user_accounts WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        // Overime stare heslo a ak je spravne, ulozime nove
        if ($user && $user['password_hash'] && password_verify($old, $user['password_hash'])) {
            $new_hash = password_hash($new, PASSWORD_ARGON2ID);
            $update = $pdo->prepare("UPDATE user_accounts SET password_hash = ? WHERE id = ?");
            if ($update->execute([$new_hash, $user_id])) {
                $password_message = '<div class="success">Heslo bolo zmenené.</div>';
            } else {
                $password_message = '<div class="error">Chyba pri ukladaní hesla.</div>';
            }
        } else {
            $password_message = '<div class="error">Nesprávne staré heslo.</div>';
        }
    }
}

// --- Ziskanie historie prihlaseni ---
$history = [];
$stmt = $pdo->prepare("SELECT login_type, created_at FROM login_history WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Ziskanie aktualnych udajov o pouzivatelovi (pre predvyplnenie formulara) ---
$stmt = $pdo->prepare("SELECT first_name, last_name, created_at FROM user_accounts WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$first_name = $user_data['first_name'] ?? '';
$last_name = $user_data['last_name'] ?? '';
$created_at = $user_data['created_at'] ?? '';

// Pre Google uzivatelov mozeme dodatočne nacitat udaje z Google API (ak treba)
if ($is_google_user) {
    require_once '../vendor/autoload.php'; // Nacitanie Composer autoloadu
    
    if (isset($_SESSION['access_token']) && $_SESSION['access_token']) {
        $client = new Client();
        $client->setAuthConfig(__DIR__ . '/client_secret.json'); // Konfiguracia pre Google
        $client->setAccessToken($_SESSION['access_token']);
        $oauth = new Google\Service\Oauth2($client);
        $account_info = $oauth->userinfo->get();
        // Tu by sme mohli aktualizovat meno, ak by sme chceli
    }
}
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Môj účet</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Kniznica DataTables pre pekne tabulky -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <!-- Font Awesome pre ikony -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/restricted.css">
</head>
<body>
    <h1>Môj účet</h1>

    <!-- Zakladne informacie o pouzivatelovi -->
    <div class="account-info">
        <h3>Vitaj, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'používateľ'); ?></h3>
        <p><strong>E-mail:</strong> <?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></p>
        <?php if ($is_google_user): ?>
            <p><span class="badge badge-oauth">Prihlásený cez Google</span></p>
        <?php else: ?>
            <p><span class="badge badge-local">Lokálny účet</span></p>
            <p><strong>Dátum vytvorenia konta:</strong> <?php echo htmlspecialchars($created_at); ?></p>
        <?php endif; ?>
    </div>

    <!-- Formular pre zmenu mena a priezviska -->
    <div class="name-form">
        <h3>Zmeniť meno a priezvisko</h3>
        <?php echo $name_message; ?>
        <form method="post" id="updateNameForm">
            <input type="hidden" name="update_name" value="1">
            <label for="first_name">Meno:</label>
            <div class="field-container">
                <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required maxlength="64">
            </div>
            <div class="char-counter" id="firstname-counter">0/64</div>
            <div class="error-message" id="error-firstname"></div>

            <label for="last_name">Priezvisko:</label>
            <div class="field-container">
                <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required maxlength="64">
            </div>
            <div class="char-counter" id="lastname-counter">0/64</div>
            <div class="error-message" id="error-lastname"></div>

            <button type="submit">Uložiť zmeny</button>
        </form>
    </div>

    <!-- Formular pre zmenu hesla (zobrazuje sa len pre lokalnych uzivatelov) -->
    <?php if (!$is_google_user): ?>
    <div class="password-form">
        <h3>Zmeniť heslo</h3>
        <?php echo $password_message; ?>
        <form method="post" id="changePasswordForm">
            <input type="hidden" name="change_password" value="1">
            <label>
                Staré heslo:
                <input type="password" name="old_password" required>
            </label>
            <label>
                Nové heslo:
                <input type="password" name="new_password" required>
            </label>
            <label>
                Potvrďte nové heslo:
                <input type="password" name="confirm_password" required>
            </label>
            <button type="submit">Zmeniť heslo</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Tabulka s historiou prihlaseni -->
    <div class="history-table">
        <h3>História prihlásení</h3>
        <?php if (empty($history)): ?>
            <p>Žiadne záznamy o prihlásení.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Dátum a čas</th>
                        <th>Typ prihlásenia</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                            <td>
                                <?php if ($row['login_type'] == 'LOCAL'): ?>
                                    <span class="badge badge-local">Lokálne</span>
                                <?php else: ?>
                                    <span class="badge badge-oauth">Google</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Navigacne odkazy -->
    <p class = "back"><a href="privateZone.php"><i class="fas fa-arrow-left"></i> Späť na hlavnú stránku</a> | <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Odhlásiť sa</a></p>

    <!-- <script>
    fetch('/api/users/me', {credentials: 'include'})
        .then(res => res.json())
        .then(data => {
            console.log(data);
        });
</script> -->

    <script src="js/restricted.js"> </script>
</body>
</html>