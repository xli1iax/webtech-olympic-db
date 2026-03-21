<?php
/**
 * Subor obsahujuci pomocne funkcie pre validaciu a pracu s databazou.
 * Vsetky funkcie su pouzivane v prihlasovacich a registracnych skriptoch.
 */

/**
 * Kontrola, ci je retazec prazdny (po odstraneni medzier).
 *
 * @param string $word Vstupny retazec.
 * @return bool True, ak je prazdny, inak false.
 */
function isEmpty(string $word): bool {
    $trimmed = trim($word); // Odstranenie medzier na zaciatku a konci
    return $trimmed === '';
}

/**
 * Overenie, ci email ma platny format.
 *
 * @param string $email Emailova adresa.
 * @return bool True, ak je format neplatny, inak false.
 */
function isInvalidEmail(string $email): bool {
    // Pouzitie PHP filtra na kontrolu emailu
    if (filter_var($email, FILTER_VALIDATE_EMAIL) == false) {
        return true; // Neplatny format
    }
    return false; // Platny format
}

/**
 * Zistenie, ci pouzivatel s danym emailom uz existuje v databaze.
 *
 * @param PDO $pdo Objekt PDO pre pripojenie k databaze.
 * @param string $email Emailova adresa.
 * @return bool True, ak pouzivatel existuje, inak false.
 */
function userExist(PDO $pdo, string $email): bool {
    // Priprava SQL dotazu s parametrom
    $stmt = $pdo->prepare("SELECT email FROM user_accounts WHERE email = :email");
    $stmt->bindParam(":email", $email, PDO::PARAM_STR);

    // Vykonanie a kontrola, ci bol najdeny zaznam
    if ($stmt->execute() && $stmt->fetchColumn()) {
        return true; // Email uz je v databaze
    }
    return false; // Email nie je pouzity
}

/**
 * Kontrola, ci meno alebo priezvisko obsahuje len povolene znaky.
 * Povolene su len latinske pismena A-Z a-z (bez diakritiky).
 *
 * @param string $name Vstupny retazec (meno alebo priezvisko).
 * @return bool True, ak obsahuje nepovolene znaky, inak false.
 */
function isInvalidName(string $name): bool {
    // Pouzitie regularneho vyrazu: len pismena (velke a male)
    return !preg_match('/^[A-Za-z]+$/', trim($name));
}

/**
 * Porovnanie dvoch hesiel.
 *
 * @param string $password Prve heslo.
 * @param string $password_repeat Druhe heslo (potvrdenie).
 * @return bool True, ak sa hesla nezhoduju, inak false.
 */
function isInvalidPassword(string $password, string $password_repeat): bool {
    // Jednoduche porovnanie
    return $password !== $password_repeat;
}

function checkPasswordLength(string $password): bool {
    return strlen($password) >= 8
        && preg_match('/[a-z]/', $password)   // хотя бы 1 маленькая
        && preg_match('/[A-Z]/', $password)   // хотя бы 1 большая
        && preg_match('/[0-9]/', $password);  // хотя бы 1 цифра
}

/**
 * Ulozenie zaznamu o prihlaseni do tabulky login_history.
 *
 * @param PDO $pdo Objekt PDO.
 * @param int $userId ID pouzivatela.
 * @param string $loginType Typ prihlasenia ('LOCAL' alebo 'GOOGLE').
 * @return bool True, ak sa podarilo vlozit zaznam, inak false.
 */
function saveLoginHistory(PDO $pdo, int $userId, string $loginType): bool {
    // Ziskanie IP adresy pouzivatela zo serverovych premennych
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    // SQL dotaz na vlozenie zaznamu (cas sa doplni automaticky)
    $sql = "INSERT INTO login_history (user_id, login_type) 
            VALUES (:user_id, :login_type)";

    $stmt = $pdo->prepare($sql);

    // Vykonanie dotazu s parametrami
    return $stmt->execute([
        ':user_id' => $userId,
        ':login_type' => $loginType
    ]);
}