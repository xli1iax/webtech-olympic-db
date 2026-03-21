<?php

class User
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(string $firstName, string $lastName, string $email, string $password): int
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO user_accounts 
                (first_name, last_name, email, password_hash)
                VALUES (:first_name, :last_name, :email, :password_hash)";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ":first_name" => $firstName,
            ":last_name" => $lastName,
            ":email" => $email,
            ":password_hash" => $hash
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function getById(int $id): ?array
    {
        $sql = "SELECT id, first_name, last_name, email, created_at
                FROM user_accounts
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([":id"=>$id]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public function getByEmail(string $email): ?array
    {
        $sql = "SELECT id, first_name, last_name, email, created_at
                FROM user_accounts
                WHERE email = :email";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([":email" =>$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user?:null;
    }

    public function getAll(): array
    {
        $sql = "SELECT id, first_name, last_name, email, created_at
                FROM user_accounts";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update(int $id, string $firstName, string $lastName): bool
    {
        $sql = "UPDATE user_accounts
                SET first_name = :first_name, last_name = :last_name
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([":first_name" => $firstName, 
                                ":last_name" => $lastName,
                                ":id" => $id]);
        return $stmt->rowCount() > 0;
    }

    public function changePassword(int $id, string $password): bool
    {
        $sql = "UPDATE user_accounts
                SET password_hash = :password
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([":id" => $id,
                        ":password" => password_hash($password, PASSWORD_DEFAULT)]);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM user_accounts 
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([":id"=>$id]);
        return $stmt->rowCount() > 0;
    }

    public function verifyPassword(string $email, string $password): bool
    {
        // ...
    }
}
?>