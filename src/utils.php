<?php
    function isEmpty(string $word):bool {
        $trimmed = trim($word);
    return $trimmed === '';
    }

    function isInvalidEmail(string $email): bool {
        if(filter_var($email, FILTER_VALIDATE_EMAIL) == false) {
            return true;
        }
        return false;
    }


    function userExist(PDO $pdo, string $email): bool{
        $stmt = $pdo->prepare("SELECT email FROM user_accounts WHERE email = :email");
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);

        if($stmt->execute() && $stmt -> fetchColumn()) {
            return true;
        }
        return false;
    }

    function isInvalidName(string $name): bool {
        return !preg_match('/^[A-Za-z]+$/', trim($name));
    }

    function isInvalidPassword(string $password, string $password_repeat) : bool {
          return $password !== $password_repeat;
    }

    function saveUserSession(PDO $pdo, int $userId): bool{
            $loginType = 'LOCAL'; 

        // Получаем IP адрес пользователя
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        // SQL запрос - время создастся автоматически (CURRENT_TIMESTAMP)
        $sql = "INSERT INTO login_history (user_id, login_type, ip_address) 
                VALUES (:user_id, :login_type, :ip_address)";

        $stmt = $pdo->prepare($sql);

        return $stmt->execute([
            ':user_id' => $userId,
            ':login_type' => $loginType,
            ':ip_address' => $ipAddress
        ]);
    }
?>