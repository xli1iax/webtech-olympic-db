<?php
session_start();

if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: restricted.php");
    exit;
}

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/utils.php"; // Добавь подключение utils

$errors = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    try {
        $pdo = connectDatabase($hostname, $database, $username, $password);
    } catch (Throwable $e) {
        $errors = "Chyba pripojenia k databáze";
    }
    
    // 1. ОЧИСТКА ВХОДНЫХ ДАННЫХ
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // 2. ВАЛИДАЦИЯ (проверка перед запросом к БД)
    if (isEmpty($email)) {
        $errors .= "Nezadaný email.<br>";
    } elseif (isInvalidEmail($email)) {
        $errors .= "Neplatný formát emailu.<br>";
    }
    
    if (isEmpty($password)) {
        $errors .= "Nezadané heslo.<br>";
    }
    
    // 3. ТОЛЬКО если нет ошибок валидации - ищем в БД
    if (empty($errors) && isset($pdo)) {
        
        $sql = "SELECT id, first_name, last_name, email, password_hash, created_at 
                FROM user_accounts WHERE email = :email";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            if ($stmt->rowCount() == 1) {
                $row = $stmt->fetch();
                
                // 4. ПРОВЕРКА ПАРОЛЯ
                if (password_verify($password, $row["password_hash"])) {
                    
                    // 5. УСПЕШНЫЙ ВХОД
                    $_SESSION["loggedin"] = true;
                    $_SESSION["full_name"] = $row['first_name'] . " " . $row['last_name'];
                    $_SESSION["email"] = $row['email'];
                    $_SESSION["created_at"] = $row['created_at'];
                    
                    // 6. ЗАПИСЬ В ИСТОРИЮ (TODO)
                    saveLoginHistory($pdo, $row['id']);
                    
                    // 7. ПЕРЕНАПРАВЛЕНИЕ
                    header("location: restricted.php");
                    exit;
                    
                } else {
                    // Неверный пароль - общее сообщение
                    $errors = "Nesprávne meno alebo heslo.";
                }
            } else {
                // Пользователь не найден - общее сообщение
                $errors = "Nesprávne meno alebo heslo.";
            }
        } else {
            $errors = "Chyba pri prihlásení.";
        }
    }
    
    if (!empty($errors)) {
        $message = '<div class="error">' . $errors . '</div>';
    }
    
    unset($stmt);
    unset($pdo);
}
?>

<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <style>
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        body {
            font-family: Arial, sans-serif;
            max-width: 400px;
            margin: 50px auto;
            padding: 20px;
        }
        form {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
        }
        label {
            display: block;
            margin-bottom: 15px;
        }
        input {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            box-sizing: border-box;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            width: 100%;
        }
        button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    
    <?php if (isset($message)) echo $message; ?>
    
    <main>
        <form action="" method="post">
            <label for="email">
                E-Mail:
                <input type="text" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" id="email" required>
            </label>
            
            <label for="password">
                Heslo:
                <input type="password" name="password" value="" id="password" required>
            </label>

            <button type="submit">Prihlásiť sa</button>
            
            <!-- TODO: Implementacia funkcionality "zabudol som"/"resetovať heslo" -->
        </form>
        
        <p>Nemáte vytvorené konto? <a href="register.php">Zaregistrujte sa tu.</a></p>
    </main>
</body>
</html>