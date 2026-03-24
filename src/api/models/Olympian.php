<?php

class Olympian
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ==================== ATHLETES ====================

    public function create(array $data): int
    {
        $sql = "INSERT INTO athletes (first_name, last_name, birth_date, birth_place, birth_country_id, death_date, death_place, death_country_id)
                VALUES (:first_name, :last_name, :birth_date, :birth_place, :birth_country_id, :death_date, :death_place, :death_country_id)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ":first_name" => $data["first_name"],
            ":last_name" => $data["last_name"],
            ":birth_date" => $data["birth_date"] ?? null,
            ":birth_place" => $data["birth_place"] ?? null,
            ":birth_country_id" => $data["birth_country_id"] ?? null,
            ":death_date" => $data["death_date"] ?? null,
            ":death_place" => $data["death_place"] ?? null,
            ":death_country_id" => $data["death_country_id"] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function getById(int $id): ?array
    {
        $sql = "
            SELECT 
                a.id,
                a.first_name,
                a.last_name,
                a.birth_date,
                a.birth_place,
                bc.name as birth_country,
                a.death_date,
                a.death_place,
                dc.name as death_country
            FROM athletes a
            LEFT JOIN countries bc ON a.birth_country_id = bc.id
            LEFT JOIN countries dc ON a.death_country_id = dc.id
            WHERE a.id = :id
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getAll(): array
    {
        $sql = "
            SELECT 
                a.id,
                a.first_name,
                a.last_name,
                a.birth_date,
                a.birth_place,
                bc.name as birth_country,
                a.death_date,
                a.death_place,
                dc.name as death_country,
                mt.placing as placing,
                d.name as discipline,
                d.category as category,
                og.year as oh_year,
                og.city as oh_city,
                og.type as oh_type,
                oc.name as oh_country
            FROM athletes a
            LEFT JOIN countries bc ON a.birth_country_id = bc.id
            LEFT JOIN countries dc ON a.death_country_id = dc.id
            LEFT JOIN athlete_medals am ON a.id = am.athlete_id
            LEFT JOIN medal_types mt ON mt.id = am.medal_type_id
            LEFT JOIN disciplines d ON d.id = am.discipline_id
            LEFT JOIN olympic_games og ON og.id = am.olympic_games_id
            LEFT JOIN countries oc ON og.country_id = oc.id
            ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByIdWithMedals(int $id): ?array
    {
        $athlete = $this->getById($id);
        if (!$athlete) return null;

        $sql = "
            SELECT 
                og.year,
                og.type as olympic_type,
                og.city,
                d.name as discipline,
                mt.name as medal,
                mt.placing
            FROM athlete_medals am
            JOIN olympic_games og ON am.olympic_games_id = og.id
            JOIN disciplines d ON am.discipline_id = d.id
            JOIN medal_types mt ON am.medal_type_id = mt.id
            WHERE am.athlete_id = :athlete_id
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':athlete_id' => $id]);
        $medals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $athlete['medals'] = $medals;
        return $athlete;
    }

    public function updateAthlete(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['first_name', 'last_name', 'birth_date', 'birth_place', 'birth_country_id', 'death_date', 'death_place', 'death_country_id'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($fields)) return false;

        $sql = "UPDATE athletes SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function deleteAthlete(int $id): bool
    {
        // Сначала удаляем связанные награды (если нет ON DELETE CASCADE)
        $sqlDelMedals = "DELETE FROM athlete_medals WHERE athlete_id = :id";
        $stmt = $this->pdo->prepare($sqlDelMedals);
        $stmt->execute([':id' => $id]);

        $sql = "DELETE FROM athletes WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function bulkDeleteAthlete(): bool
    {
        $sqlDelMedals = "DELETE FROM athlete_medals";
        $stmt = $this->pdo->prepare($sqlDelMedals);
        $stmt->execute();

        $sqlDelAthletes = "DELETE FROM athletes";
        $stmt = $this->pdo->prepare($sqlDelAthletes);
        $stmt->execute();
        return true;

    }

    // ==================== MEDALS ====================

    public function addMedal(int $athleteId, array $medalData): int
    {
        $sql = "INSERT INTO athlete_medals (athlete_id, olympic_games_id, discipline_id, medal_type_id)
                VALUES (:athlete_id, :olympic_games_id, :discipline_id, :medal_type_id)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':athlete_id' => $athleteId,
            ':olympic_games_id' => $medalData['olympic_games_id'],
            ':discipline_id' => $medalData['discipline_id'],
            ':medal_type_id' => $medalData['medal_type_id'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateMedal(int $medalId, array $medalData): bool
    {
        $fields = [];
        $params = [':id' => $medalId];
        $allowed = ['olympic_games_id', 'discipline_id', 'medal_type_id'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $medalData)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $medalData[$field];
            }
        }
        if (empty($fields)) return false;

        $sql = "UPDATE athlete_medals SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function deleteMedal(int $medalId): bool
    {
        $sql = "DELETE FROM athlete_medals WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $medalId]);
        return $stmt->rowCount() > 0;
    }

    // ==================== FILTERED LIST ====================

    public function getAllFiltered(array $filters): array
    {
        $sql = "
            SELECT 
                a.id,
                a.first_name,
                a.last_name,
                c.name as country_name,
                og.year,
                og.type as olympic_type,
                d.name as discipline,
                og.city,
                mt.placing
            FROM athletes a
            JOIN athlete_medals am ON a.id = am.athlete_id
            JOIN olympic_games og ON am.olympic_games_id = og.id
            JOIN disciplines d ON am.discipline_id = d.id
            JOIN medal_types mt ON am.medal_type_id = mt.id
            JOIN countries as c ON a.birth_country_id = c.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['year'])) {
            $sql .= " AND og.year = :year";
            $params[':year'] = $filters['year'];
        }
        if (!empty($filters['type'])) {
            $sql .= " AND og.type = :type";
            $params[':type'] = $filters['type'];
        }
        if (!empty($filters['discipline'])) {
            $sql .= " AND d.name LIKE :discipline";
    $params[':discipline'] = '%' . $filters['discipline'] . '%';
        }
        if (!empty($filters['placing'])) {
            $sql .= " AND mt.placing = :placing";
            $params[':placing'] = $filters['placing'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==================== HELPER: GET IDs FROM REFERENCE TABLES ====================

    public function getCountryIdByName(string $name): ?int
    {
        $sql = "SELECT id FROM countries WHERE name = :name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }

    public function getDisciplineIdByName(string $name): ?int
    {
        $sql = "SELECT id FROM disciplines WHERE name = :name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }

    public function getOlympicGamesId(int $year, string $type, ?string $city = null): ?int
    {
        $sql = "SELECT id FROM olympic_games WHERE year = :year AND type = :type";
        $params = [':year' => $year, ':type' => $type];
        if ($city) {
            $sql .= " AND city = :city";
            $params[':city'] = $city;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }

    public function getMedalTypeId(int $placing): ?int
    {
        $sql = "SELECT id FROM medal_types WHERE placing = :placing";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':placing' => $placing]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    } 
    
    
    /**
 * Возвращает ID страны, если не найдена – создаёт.
 */
public function getOrCreateCountryId(string $name): int
{
    $id = $this->getCountryIdByName($name);
    if ($id) return $id;

    $sql = "INSERT INTO countries (name) VALUES (:name)";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':name' => $name]);
    return (int) $this->pdo->lastInsertId();
}

/**
 * Возвращает ID дисциплины (вида спорта), если не найдена – создаёт.
 */
public function getOrCreateDisciplineId(string $name): int
{
    $id = $this->getDisciplineIdByName($name);
    if ($id) return $id;

    $sql = "INSERT INTO disciplines (name) VALUES (:name)";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':name' => $name]);
    return (int) $this->pdo->lastInsertId();
}

/**
 * Возвращает ID олимпийских игр, если не найдены – создаёт.
 * Принимает год, тип (LOH/ZOH) и город (обязателен).
 */
public function getOrCreateOlympicGamesId(int $year, string $type, string $city, string $country): int
{
    $id = $this->getOlympicGamesId($year, $type, $city);
    if ($id) return $id;
    $countryId = $this->getOrCreateCountryId($country);
    // Сначала нужно получить ID страны-организатора (можно добавить отдельно, пока ставим NULL)
    // В реальности страну тоже нужно передавать, но упростим – создаём без страны
    $sql = "INSERT INTO olympic_games (year, type, city, country_id) VALUES (:year, :type, :city, :country)";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':year' => $year, ':type' => $type, ':city' => $city, ':country' => $countryId]);
    return (int) $this->pdo->lastInsertId();
}

/**
 * Возвращает ID типа медали, если не найден – создаёт.
 * Принимает placing (1,2,3) и название медали (Gold, Silver, Bronze).
 */
public function getOrCreateMedalTypeId(int $placing): int
{
    $id = $this->getMedalTypeId($placing);
    if ($id) return $id;

    $description = match ($placing) {
        1 => 'Zlatá medaila',
        2 => 'Strieborná medaila',
        3 => 'Bronzová medaila',
        default => 'Umiestnenie bez medaily'
    };

    $name = match ($placing) {
        1 => 'Zlato',
        2 => 'Striebro',
        3 => 'Bronz',
        default => 'Umiestnenie '.$placing
    };

    $sql = "INSERT INTO medal_types (placing, name, description) VALUES (:placing, :name, :description)";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':placing' => $placing, ':name' => $name, ':description' => $description]);
    return (int) $this->pdo->lastInsertId();
}

/**
 * Находит спортсмена по уникальным данным.
 * Возвращает id или null.
 */
public function findAthlete(string $firstName, string $lastName, string $birthDate, int $birthCountryId): ?int
{
    $sql = "SELECT id FROM athletes 
            WHERE first_name = :first_name 
              AND last_name = :last_name 
              AND birth_date = :birth_date 
              AND birth_country_id = :birth_country_id";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':birth_date' => $birthDate,
        ':birth_country_id' => $birthCountryId,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['id'] : null;
}

/**
 * Проверяет, имеет ли спортсмен уже указанную награду.
 */
public function hasMedal(int $athleteId, int $olympicGamesId, int $disciplineId, int $medalTypeId): bool
{
    $sql = "SELECT id FROM athlete_medals 
            WHERE athlete_id = :athlete_id 
              AND olympic_games_id = :olympic_games_id 
              AND discipline_id = :discipline_id 
              AND medal_type_id = :medal_type_id";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
        ':athlete_id' => $athleteId,
        ':olympic_games_id' => $olympicGamesId,
        ':discipline_id' => $disciplineId,
        ':medal_type_id' => $medalTypeId,
    ]);
    return $stmt->fetch() !== false;
}
}