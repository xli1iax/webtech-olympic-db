<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/register.php';

// ================== FUNKCIE NA PRÁCU S KRAJINAMI ==================
function getOrCreateCountry(PDO $pdo, string $name): int {
    // Najprv skús nájsť existujúcu krajinu
    $stmt = $pdo->prepare("SELECT id FROM countries WHERE name = :name LIMIT 1");
    $stmt->execute([':name' => $name]);
    $id = $stmt->fetchColumn();

    if ($id) {
        return (int) $id;
    }

    // Ak neexistuje, vlož novú
    $stmt = $pdo->prepare("INSERT INTO countries (name) VALUES (:name)");
    $stmt->execute([':name' => $name]);
    return (int) $pdo->lastInsertId();
}

// ================== FUNKCIE NA PRÁCU S OLYMPIJSKÝMI HRAMI ==================
function getOrCreateGames(PDO $pdo, int $year, string $type, string $city, int $countryId): int {
    // Skús nájsť podľa roku a typu (unikátny kombinácia)
    $stmt = $pdo->prepare("SELECT id FROM olympic_games WHERE year = :year AND type = :type LIMIT 1");
    $stmt->execute([
        ':year' => $year,
        ':type' => $type
    ]);
    $id = $stmt->fetchColumn();

    if ($id) {
        return (int) $id;
    }

    // TODO: kontrola ENUM (LOH/ZOH) - môžeš pridať neskôr
    if (!in_array($type, ['LOH', 'ZOH'])) {
        throw new InvalidArgumentException("Type must be LOH or ZOH");
    }

    // Vlož nové hry
    $stmt = $pdo->prepare("INSERT INTO olympic_games (year, type, city, country_id) VALUES (:year, :type, :city, :country_id)");
    $stmt->execute([
        ':year' => $year,
        ':type' => $type,
        ':city' => $city,
        ':country_id' => $countryId
    ]);
    return (int) $pdo->lastInsertId();
}

// ================== FUNKCIE NA PRÁCU SO ŠPORTOVCAMI ==================
function getOrCreateAthlete(
    PDO $pdo,
    string $firstName,
    string $lastName,
    ?string $birthDate = null,
    ?string $birthPlace = null,
    ?int $birthCountryId = null,
    ?string $deathDate = null,
    ?string $deathPlace = null,
    ?int $deathCountryId = null
): int {
    // Skús nájsť podľa mena, priezviska a dátumu narodenia (malo by byť unikátne)
    $stmt = $pdo->prepare("SELECT id FROM athletes WHERE first_name = :first_name AND last_name = :last_name AND (birth_date = :birth_date OR (birth_date IS NULL AND :birth_date IS NULL)) LIMIT 1");
    $stmt->execute([
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':birth_date' => $birthDate
    ]);
    $id = $stmt->fetchColumn();

    if ($id) {
        return (int) $id;
    }

    // Vlož nového športovca
    $sql = "INSERT INTO athletes
            (first_name, last_name, birth_date, birth_place, birth_country_id,
             death_date, death_place, death_country_id)
            VALUES
            (:first_name, :last_name, :birth_date, :birth_place, :birth_country_id,
             :death_date, :death_place, :death_country_id)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':birth_date' => $birthDate,
        ':birth_place' => $birthPlace,
        ':birth_country_id' => $birthCountryId,
        ':death_date' => $deathDate,
        ':death_place' => $deathPlace,
        ':death_country_id' => $deathCountryId
    ]);
    return (int) $pdo->lastInsertId();
}

// ================== FUNKCIE NA PRÁCU SO Disciplinami ==================
function getOrCreateDiscipline(PDO $pdo, string $name, ?string $category = null): int {
    $stmt = $pdo->prepare("SELECT id FROM disciplines WHERE name = :name LIMIT 1");
    $stmt->execute([':name' => $name]);
    $id = $stmt->fetchColumn();

    if ($id) {
        return (int) $id;
    }

    $stmt = $pdo->prepare("INSERT INTO disciplines (name, category) VALUES (:name, :category)");
    $stmt->execute([
        ':name' => $name,
        ':category' => $category
    ]);

    return (int) $pdo->lastInsertId();
}

// ================== FUNKCIE NA PRÁCU SO typom medal ==================

function getMedalTypeId(PDO $pdo, int $placing): ?int {
    $stmt = $pdo->prepare("SELECT id FROM medal_types WHERE placing = :placing LIMIT 1");
    $stmt->execute([':placing' => $placing]);
    $id = $stmt->fetchColumn();

    return $id ? (int) $id : null;
}

function insertMedal(PDO $pdo, int $athleteId, int $gamesId, int $disciplineId, int $medalTypeId): int {
    $sql = "INSERT INTO athlete_medals (athlete_id, olympic_games_id, discipline_id, medal_type_id) 
            VALUES (:athlete_id, :games_id, :discipline_id, :medal_type_id)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':athlete_id' => $athleteId,
        ':games_id' => $gamesId,
        ':discipline_id' => $disciplineId,
        ':medal_type_id' => $medalTypeId
    ]);

    return (int) $pdo->lastInsertId();
}

// ================== FUNKCIA NA VLOŽENIE VÝSLEDKU ==================
//function insertResult(PDO $pdo, int $athleteId, int $gamesId, int $placing, string $discipline): int {
//    $sql = "INSERT INTO results (athlete_id, olympic_games_id, placing, discipline) VALUES (:athlete_id, :games_id, :placing, :discipline)";
//    $stmt = $pdo->prepare($sql);
//    $stmt->execute([
//        ':athlete_id' => $athleteId,
//        ':games_id' => $gamesId,
//        ':placing' => $placing,
//        ':discipline' => $discipline
//    ]);
//    return (int) $pdo->lastInsertId();
//}

// ================== PARSOVANIE CSV (VAŠA FUNKCIA) ==================
function parseCsvToAssocArray(string $filePath, string $delimiter = ";"): array
{
    $result = [];

    if (!file_exists($filePath)) {
        return [];
    }

    $handle = fopen($filePath, 'r');
    if ($handle === false) {
        return [];
    }

    $headers = fgetcsv($handle, 0, $delimiter, '"', '\\');
    if ($headers === false) {
        fclose($handle);
        return [];
    }

    while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
        if (count($row) === count($headers)) {
            $result[] = array_combine($headers, $row);
        }
    }

    fclose($handle);
    return $result;
}

function convertDate(?string $date): ?string {
    if (empty($date)) {
        return null;
    }
    $parts = explode('.', trim($date));
    if (count($parts) === 3) {
        $day = str_pad((int)$parts[0], 2, '0', STR_PAD_LEFT);
        $month = str_pad((int)$parts[1], 2, '0', STR_PAD_LEFT);
        $year = (int)$parts[2];
        return sprintf("%04d-%02d-%02d", $year, $month, $day);
    }
    return null;
}

// ================== HLAVNÝ KÓD ==================
$data = [];
$report = [];
$dbMessage = '';

try {
    $conn = connectDatabase($hostname, $database, $username, $password);
    $dbMessage = $conn ? 'Pripojené k databáze' : '✗ Nepripojené k databáze';
} catch (Throwable $e) {
    $dbMessage = 'Chyba: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($ext !== 'csv') {
        $report[] = "Povolené sú iba CSV súbory.";
    } elseif ($file['error'] !== 0) {
        $report[] = "Chyba pri nahrávaní súboru.";
    } else {
        $data = parseCsvToAssocArray($file['tmp_name'], ";");
        $report[] = "CSV nahratý, " . count($data) . " záznamov.";

        // ===== VKLADANIE DO DATABÁZY =====
        if ($conn && !empty($data)) {
            $inserted = 0;
            $errors = 0;

            foreach ($data as $row) {
                try {
                    // 1. Krajina narodenia
                    $birthCountryId = null;
                    if (!empty($row['birth_country'])) {
                        $birthCountryId = getOrCreateCountry($conn, $row['birth_country']);
                        
                    }

                    // 2. Krajina úmrtia
                    $deathCountryId = null;
                    if (!empty($row['death_country'])) {
                        $deathCountryId = getOrCreateCountry($conn, $row['death_country']);
                        
                    }

                    // 3. Krajina konania OH
                    $gamesCountryId = null;
                    if (!empty($row['oh_country'])) {
                        $gamesCountryId = getOrCreateCountry($conn, $row['oh_country']);
                    }

                    // 4. Olympijské hry
                    $gamesId = null;
                    if (!empty($row['oh_year']) && !empty($row['oh_type']) && !empty($row['oh_city']) && $gamesCountryId) {
                        $gamesId = getOrCreateGames(
                            $conn,
                            (int)$row['oh_year'],
                            $row['oh_type'],
                            $row['oh_city'],
                            $gamesCountryId
                        );
                    }

//			echo "<pre>";
//var_dump(
//    $row['name'] ?? '',
//    $row['surname'] ?? '',
//    convertDate($row['birth_day'] ?? ''),
//    $row['birth_place'] ?? null,
//    $birthCountryId,
//    convertDate($row['death_day'] ?? ''),
//    $row['death_place'] ?? null,
//    $deathCountryId
//);
//echo "</pre>";

                    // 5. Športovec
                    $athleteId = getOrCreateAthlete(
                        $conn,
                        $row['name'] ?? '',
                        $row['surname'] ?? '',
                        convertDate($row['birth_day'] ?? ''),
                        $row['birth_place'] ?? null,
                        $birthCountryId,
                        convertDate($row['death_day'] ?? ''),
                        $row['death_place'] ?? null,
                        $deathCountryId
                    );

                    // 6. Disciplína
                    $disciplineId = null;
                    if (!empty($row['discipline'])) {
                        $disciplineId = getOrCreateDiscipline(
                            $conn,
                            $row['discipline'] ?? '',
                            $row['category'] ?? null
                        );
                    }

                    // 7. Typ medaily
                    $medalTypeId = null;
                    if (!empty($row['placing']) && in_array((int)$row['placing'], [1,2,3])) {
                        $medalTypeId = getMedalTypeId($conn, (int)$row['placing']);
                    }

                    // 8. Vloženie medaily
                    if ($athleteId && $gamesId && $disciplineId && $medalTypeId) {
                        insertMedal($conn, $athleteId, $gamesId, $disciplineId, $medalTypeId);
                    }

                    // // 9. Výsledok (umiestnenie)
                    // if ($athleteId && $gamesId && !empty($row['placing']) && !empty($row['discipline'])) {
                    //     insertResult(
                    //         $conn,
                    //         $athleteId,
                    //         $gamesId,
                    //         (int)$row['placing'],
                    //         $row['discipline']
                    //     );
                    // }

                    $inserted++;
                } catch (Exception $e) {
                    $errors++;
                    $report[] = "Chyba pri zázname: " . $e->getMessage();
                }
            }

            $report[] = "Do databázy vložených $inserted záznamov, $errors chýb.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>CSV Upload + DB</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
            background: #f5f5f5;
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        input[type="file"] {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 300px;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #0056b3;
        }
        .data-box h3 {
            margin-top: 0;
            color: #333;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        pre {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
<h1>CSV Upload + Databáza</h1>

<div class="status <?php echo $conn ? 'ok' : 'err'; ?>">
    <?php echo $dbMessage; ?>
</div>

<div class="form-group">
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="csv_file" accept=".csv" required>
        <button type="submit">Nahrať a spracovať</button>
    </form>
</div>

<?php if (!empty($report)): ?>
    <div class="report">
        <?php echo implode('<br>', array_map('htmlspecialchars', $report)); ?>
    </div>
<?php endif; ?>

<?php if (!empty($data)): ?>
    <div class="data-box">
        <h3>
            Obsah súboru
            <span class="badge"><?php echo count($data); ?> záznamov</span>
        </h3>
        <pre><?php print_r($data); ?></pre>
    </div>
<?php endif; ?>
</body>
</html>
