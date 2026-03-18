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
    <style>
        /* ----- VŠEOBECNÉ ŠTÝLY (fialová téma) ----- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Roboto, system-ui, sans-serif;
            background: #f3e8ff;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            color: #2d1b4e;
        }

        h1 {
            color: #2d1b4e;
            border-bottom: 3px solid #8b5cf6;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        h2, h3 {
            color: #2d1b4e;
            margin-bottom: 15px;
            font-weight: 600;
        }

        /* Karticky s informaciami */
        .account-info, .name-form, .password-form, .history-table {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.15);
            margin: 25px 0;
            border: 1px solid rgba(255,255,255,0.6);
        }

        /* Spravy o uspechu / chybe */
        .success, .error {
            padding: 15px;
            border-radius: 20px;
            margin: 20px 0;
            backdrop-filter: blur(5px);
            font-weight: 500;
        }
        .success {
            background: rgba(212, 237, 218, 0.9);
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: rgba(248, 215, 218, 0.9);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Formulare */
        label {
            display: block;
            margin-bottom: 15px;
            font-weight: 500;
            color: #2d1b4e;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px 15px;
            margin-top: 5px;
            border: 2px solid #e0d6f0;
            border-radius: 30px;
            font-size: 14px;
            background: white;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        input[type="text"]:focus, input[type="password"]:focus {
            border-color: #8b5cf6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
        }
        /* Stavy pre validaciu */
        input.error {
            border-color: #dc3545 !important;
            background: #fff5f5;
        }
        input.success {
            border-color: #28a745 !important;
        }
        /* Kontajner pre pole a pocitadlo */
        .field-container {
            position: relative;
            margin-bottom: 5px;
        }
        /* Pocitadlo znakov */
        .char-counter {
            font-size: 0.8em;
            color: #8b5cf6;
            margin-top: 3px;
            text-align: right;
        }
        .char-counter.limit {
            color: #dc3545;
            font-weight: bold;
        }
        /* Chybove spravy pod polom */
        .error-message {
            color: #dc3545;
            font-size: 0.9em;
            margin-top: 5px;
            margin-bottom: 10px;
            display: none;
        }
        .error-message.visible {
            display: block;
        }

        /* Tlacitka */
        button {
            padding: 10px 24px;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            transition: background 0.2s, transform 0.1s;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            background: #8b5cf6;
            color: white;
        }
        button:hover {
            background: #7c3aed;
            transform: scale(1.02);
        }

        /* Odkazy v spodnej casti */
        p a {
            color: #8b5cf6;
            text-decoration: none;
            font-weight: 500;
            margin-right: 15px;
            transition: color 0.2s;
        }
        p a:hover {
            color: #7c3aed;
            text-decoration: underline;
        }

        /* Tabulka historie */
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.1);
        }
        th {
            background: #7c3aed;
            color: white;
            padding: 14px 12px;
            text-align: left;
            font-weight: 600;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #e0d6f0;
            color: #2d1b4e;
        }
        tr:hover {
            background: #f3e8ff;
        }

        /* Badge pre typ prihlasenia */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }
        .badge-local {
            background: #28a745;
        }
        .badge-oauth {
            background: #dc3545;
        }

        /* Navigacny panel "spat" */
        .back {
            margin: 20px 0 15px 0;
            font-size: 1rem;
            color: #4a3a6e;
        }

        .back a {
            color: #8b5cf6;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
            margin: 0 5px;
        }

        .back a:hover {
            color: #7c3aed;
            text-decoration: underline;
        }

        .back i {
            margin-right: 6px;
            font-size: 0.95rem;
        }

        /* Responzivne upravy */
        @media (max-width: 768px) {
            body { padding: 0 15px; }
            h1 { font-size: 2rem; }
        }
        @media (max-width: 480px) {
            h1 { font-size: 1.7rem; }
            button { width: 100%; }
        }
    </style>
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

    <script>
    // Pockame na nacitanie DOM stromu
    document.addEventListener('DOMContentLoaded', function() {
        const nameForm = document.getElementById('updateNameForm');
        if (nameForm) {
            // Referencie na polia a pocitadla
            const firstName = document.getElementById('first_name');
            const lastName = document.getElementById('last_name');
            const firstnameCounter = document.getElementById('firstname-counter');
            const lastnameCounter = document.getElementById('lastname-counter');
            const maxLength = 64;

            // Funkcia na aktualizaciu pocitadla a odstranenie vizualnych stavov
            function updateCounter(field, counter) {
                const len = field.value.length;
                counter.textContent = len + '/' + maxLength;
                if (len > maxLength) {
                    counter.classList.add('limit');
                } else {
                    counter.classList.remove('limit');
                }
                field.classList.remove('error', 'success');
            }

            // Pridanie event listenerov na pocitanie pri písaní
            firstName.addEventListener('input', function() {
                updateCounter(firstName, firstnameCounter);
            });
            lastName.addEventListener('input', function() {
                updateCounter(lastName, lastnameCounter);
            });

            // Inicializacia pocitadiel pri nacitani stranky
            updateCounter(firstName, firstnameCounter);
            updateCounter(lastName, lastnameCounter);

            // Funkcia na zobrazenie chyby pre konkretne pole
            function showError(fieldId, message) {
                const errorDiv = document.getElementById('error-' + fieldId);
                const field = document.getElementById(fieldId === 'firstname' ? 'first_name' : 'last_name');
                if (field) field.classList.add('error');
                if (errorDiv) {
                    errorDiv.textContent = message;
                    errorDiv.classList.add('visible');
                }
            }

            // Obsluha odoslania formulara (klient-side validacia)
            nameForm.addEventListener('submit', function(e) {
                // Vymazeme predchadzajuce chyby
                document.querySelectorAll('.error-message').forEach(el => {
                    el.textContent = '';
                    el.classList.remove('visible');
                });
                [firstName, lastName].forEach(f => f.classList.remove('error', 'success'));

                let isValid = true;
                const firstNameVal = firstName.value.trim();
                const lastNameVal = lastName.value.trim();

                // Validacia mena
                if (firstNameVal === '') {
                    showError('firstname', 'Meno je povinné.');
                    isValid = false;
                } else if (firstNameVal.length > maxLength) {
                    showError('firstname', 'Meno môže mať maximálne ' + maxLength + ' znakov.');
                    isValid = false;
                } else {
                    // Povolene znaky: pismena (aj s diakritikou), medzery, spojovnik, apostrof
                    const nameRegex = /^[a-zA-Zá-žÁ-Ž\s\-']+$/;
                    if (!nameRegex.test(firstNameVal)) {
                        showError('firstname', 'Meno obsahuje nepovolené znaky.');
                        isValid = false;
                    } else {
                        firstName.classList.add('success');
                    }
                }

                // Validacia priezviska
                if (lastNameVal === '') {
                    showError('lastname', 'Priezvisko je povinné.');
                    isValid = false;
                } else if (lastNameVal.length > maxLength) {
                    showError('lastname', 'Priezvisko môže mať maximálne ' + maxLength + ' znakov.');
                    isValid = false;
                } else {
                    const nameRegex = /^[a-zA-Zá-žÁ-Ž\s\-']+$/;
                    if (!nameRegex.test(lastNameVal)) {
                        showError('lastname', 'Priezvisko obsahuje nepovolené znaky.');
                        isValid = false;
                    } else {
                        lastName.classList.add('success');
                    }
                }

                // Ak niektora validacia zlyhala, zablokujeme odoslanie
                if (!isValid) {
                    e.preventDefault();
                }
            });
        }
    });
    </script>
</body>
</html>