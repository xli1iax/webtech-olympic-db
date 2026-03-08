// register.php
<?php

require_once __DIR__ . '/config.php';  // Pripojenie konfiguracneho suboru s pripojenim na DB
require_once __DIR__ . '/utils.php';  // Externy subor s funkciami isEmpty, userExist a pod...

// if ($_SERVER["REQUEST_METHOD"] == "POST") {  
//     // Ak bol odoslany formular - tzn. bol urobeny HTTP POST Request na tento skript...
    
//     // Validacia zadania e-mailu
//     if (isEmpty($_POST['email']) === true) {
//         $errors .= "Nevyplnený e-mail.\n";
//     }

//     // TODO: validacia, zi pouzivatel zadal e-mail v korektnom formate

//     // Validacia, ci pouzivatel v DB existuje - kontrolujeme stlpec e-mail, ktory sme si zadali ako UNIQUE.
//     if (userExist($pdo, $_POST['email']) === true) {
//         $errors .= "Používateľ s týmto e-mailom už existuje.\n";
//         die();
//     }

//     // Valiadacia zadania mena a priezviska
//     if (isEmpty($_POST['firstname']) === true) {
//         $errors .= "Nevyplnené meno.\n";
//     } elseif (isEmpty($_POST['lastname']) === true) {
//         $errors .= "Nevyplnené priezvisko.\n";
//     }

//     // TODO: Implementujte validaciu dlzky mena a priezviska na zaklade dlzky, ktoru ste definovali pre stlpce v DB
//     // TODO: Implementujte validaciu, ci meno a priezvisko obsahuje iba povolene znaky


//     // Validacia hesla
//     if (isEmpty($_POST['password']) === true) {
//         $errors .= "Nevyplnené heslo.\n";
//     }

//     // TODO: Implementujte validaciu kontroly opakovane zadaneho hesla - kontrola, ci $_POST['password'] a $_POST['password_repeat'] su rovnake retazce.
//     // TODO: Osetrite a validujte vstupy pouzivatela

//     if (empty($errors)) {
//         $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password_hash) VALUES (:first_name, :last_name, :email, :password_hash)");

//         $pw_hash = password_hash($_POST['password'], PASSWORD_ARGON2ID);

//         $stmt->bindParam(":first_name", $_POST['first_name'], PDO::PARAM_STR);
//         $stmt->bindParam(":last_name", $_POST['last_name'], PDO::PARAM_STR);
//         $stmt->bindParam(":email", $email, PDO::PARAM_STR);
//         $stmt->bindParam(":password_hash", $pw_hash, PDO::PARAM_STR);

//         if ($stmt->execute()) {
//             $reg_status = "Registracia prebehla uspesne.";
//         } else {
//             $reg_status = "Chyba pri registracii.";
//         }

//         unset($stmt);
//     }
//     unset($pdo);
// }

?>

<!doctype html>
<html lang="sk">

<!-- HTML kod stranky s formularom -->
<form method="post">
    <label for="firstname">
        Meno:
        <input type="text" name="firs_tname" value="" id="firstname" placeholder="napr. John">
    </label>

    <label for="lastname">
        Priezvisko:
        <input type="text" name="las_tname" value="" id="lastname" placeholder="napr. Doe">
    </label>

    <br>

    <label for="email">
        E-mail:
        <input type="email" name="email" value="" id="email" placeholder="napr. johndoe@example.com">
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
</html>


