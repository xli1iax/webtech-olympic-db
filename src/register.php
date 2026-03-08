<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {  
    $errors = "";
    
    try {
        $pdo = connectDatabase($hostname, $database, $username, $password);
        $dbMessage = $pdo ? 'Pripojené k databáze' : '✗ Nepripojené k databáze';
    } catch (Throwable $e) {
        $dbMessage = 'Chyba: ' . $e->getMessage();
    }
    
    // Validacia zadania e-mailu
    if (isEmpty($_POST['email']) === true) {
        $errors .= "Nevyplnený e-mail.<br>";
    }

    // TODO: validacia, zi pouzivatel zadal e-mail v korektnom formate
    if(isInvalidEmail($_POST['email']) == true) {
        $errors .= "Incorrect email.<br>";
    }

    // Validacia, ci pouzivatel v DB existuje - kontrolujeme stlpec e-mail, ktory sme si zadali ako UNIQUE.
    if (isset($pdo) && userExist($pdo, $_POST['email']) === true) {
        $errors .= "Používateľ s týmto e-mailom už existuje.<br>";
    }

    // Valiadacia zadania mena a priezviska
    if (isEmpty($_POST['first_name']) === true) {
        $errors .= "Nevyplnené meno.<br>";
    } elseif (isEmpty($_POST['last_name']) === true) {
        $errors .= "Nevyplnené priezvisko.<br>";
    }

    // TODO: Implementujte validaciu dlzky mena a priezviska na zaklade dlzky, ktoru ste definovali pre stlpce v DB
    // TODO: Implementujte validaciu, ci meno a priezvisko obsahuje iba povolene znaky
    if (isInvalidName($_POST['first_name']) == true) {
        $errors .= "Invalid first name.<br>";
    } elseif (isInvalidName($_POST['last_name']) == true) {
        $errors .= "Invalid last name.<br>";
    }

    // Validacia hesla
    if (isEmpty($_POST['password']) === true) {
        $errors .= "Nevyplnené heslo.<br>";
    }

    // TODO: Implementujte validaciu kontroly opakovane zadaneho hesla - kontrola, ci $_POST['password'] a $_POST['password_repeat'] su rovnake retazce.
    if (isInvalidPassword($_POST['password'], $_POST['password_repeat']) == true) {
        $errors .= "Hesla sa nezhoduju.<br>";
    }

    // TODO: Osetrite a validujte vstupy pouzivatela
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password_user = $_POST['password'] ?? '';
    $password_repeat = $_POST['password_repeat'] ?? '';

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO user_accounts (first_name, last_name, email, password_hash) VALUES (:first_name, :last_name, :email, :password_hash)");

        $pw_hash = password_hash($password_user, PASSWORD_ARGON2ID);

        $stmt->bindParam(":first_name", $first_name, PDO::PARAM_STR);
        $stmt->bindParam(":last_name", $last_name, PDO::PARAM_STR);
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        $stmt->bindParam(":password_hash", $pw_hash, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $message = '<div class="success">Registracia prebehla uspesne.</div>';
        } else {
            $error_info = $stmt->errorInfo();
            $message = '<div class="error">Chyba pri registracii: ' . $error_info[2] . '</div>';
        }

        unset($stmt);
    } else {
        $message = '<div class="error">' . $errors . '</div>';
    }
    
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
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        body {
            font-family: Arial, sans-serif;
            max-width: 500px;
            margin: 20px auto;
            padding: 20px;
        }
        form {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
        }
        label {
            display: block;
            margin-bottom: 10px;
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
    </style>
</head>
<body>
    
    <?php if (isset($message)) echo $message; ?>
    
    <form method="post" action="register.php">
        <label for="firstname">
            Meno:
            <input type="text" name="first_name" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" id="firstname" placeholder="napr. John">
        </label>

        <label for="lastname">
            Priezvisko:
            <input type="text" name="last_name" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" id="lastname" placeholder="napr. Doe">
        </label>

        <label for="email">
            E-mail:
            <input type="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" id="email" placeholder="napr. johndoe@example.com">
        </label>

        <label for="password">
            Heslo:
            <input type="password" name="password" value="" id="password">
        </label>
        
        <label for="password_repeat">
            Heslo znova:
            <input type="password" name="password_repeat" value="" id="password_repeat">
        </label>

        <button type="submit">Vytvoriť konto</button>
    </form>
</body>
</html>