<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo '<p>Pre pokračovanie sa prosím <a href="login.php">prihláste</a> alebo sa <a href="register.php">zaregistrujte</a>.</p>';
} else {
    echo '<h3>Vitaj ' . htmlspecialchars($_SESSION['full_name']) . ' </h3>';
    echo '<a href="restricted.php">Zabezpečená stránka</a>';
}

require_once __DIR__ . '/config.php';

// ================== FUNKCIE NA PRÁCU S KRAJINAMI ==================
function getOrCreateCountry(PDO $pdo, string $name): int
{
    $stmt = $pdo->prepare("SELECT id FROM countries WHERE name = :name LIMIT 1");
    $stmt->execute([':name' => $name]);
    $id = $stmt->fetchColumn();

    if ($id) {
        return (int)$id;
    }

    $stmt = $pdo->prepare("INSERT INTO countries (name) VALUES (:name)");
    $stmt->execute([':name' => $name]);
    return (int)$pdo->lastInsertId();
}

// ================== FUNKCIE NA PRÁCU S OLYMPIJSKÝMI HRAMI ==================
function getOrCreateGames(PDO $pdo, int $year, string $type, string $city, int $countryId): int
{
    $stmt = $pdo->prepare("SELECT id FROM olympic_games WHERE year = :year AND type = :type LIMIT 1");
    $stmt->execute([
        ':year' => $year,
        ':type' => $type
    ]);
    $id = $stmt->fetchColumn();

    if ($id) {
        return (int)$id;
    }

    if (!in_array($type, ['LOH', 'ZOH'], true)) {
        throw new InvalidArgumentException("Type must be LOH or ZOH");
    }

    $stmt = $pdo->prepare("
        INSERT INTO olympic_games (year, type, city, country_id) 
        VALUES (:year, :type, :city, :country_id)
    ");
    $stmt->execute([
        ':year' => $year,
        ':type' => $type,
        ':city' => $city,
        ':country_id' => $countryId
    ]);

    return (int)$pdo->lastInsertId();
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
    $stmt = $pdo->prepare("
        SELECT id 
        FROM athletes 
        WHERE first_name = :first_name 
          AND last_name = :last_name 
          AND (birth_date = :birth_date OR (birth_date IS NULL AND :birth_date IS NULL))
        LIMIT 1
    ");
    $stmt->execute([
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':birth_date' => $birthDate
    ]);
    $id = $stmt->fetchColumn();

    if ($id) {
        return (int)$id;
    }

    $sql = "
        INSERT INTO athletes
        (first_name, last_name, birth_date, birth_place, birth_country_id,
         death_date, death_place, death_country_id)
        VALUES
        (:first_name, :last_name, :birth_date, :birth_place, :birth_country_id,
         :death_date, :death_place, :death_country_id)
    ";

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

    return (int)$pdo->lastInsertId();
}

// ================== FUNKCIE NA PRÁCU SO DISCIPLÍNAMI ==================
function getOrCreateDiscipline(PDO $pdo, string $name, ?string $category = null): int
{
    $stmt = $pdo->prepare("SELECT id FROM disciplines WHERE name = :name LIMIT 1");
    $stmt->execute([':name' => $name]);
    $id = $stmt->fetchColumn();

    if ($id) {
        return (int)$id;
    }

    $stmt = $pdo->prepare("INSERT INTO disciplines (name, category) VALUES (:name, :category)");
    $stmt->execute([
        ':name' => $name,
        ':category' => $category
    ]);

    return (int)$pdo->lastInsertId();
}

// ================== FUNKCIE NA PRÁCU S MEDAILAMI ==================
function getMedalTypeId(PDO $pdo, int $placing): ?int
{
    $stmt = $pdo->prepare("SELECT id FROM medal_types WHERE placing = :placing LIMIT 1");
    $stmt->execute([':placing' => $placing]);
    $id = $stmt->fetchColumn();

    return $id ? (int)$id : null;
}

function insertMedal(PDO $pdo, int $athleteId, int $gamesId, int $disciplineId, int $medalTypeId): int
{
    $sql = "
        INSERT INTO athlete_medals (athlete_id, olympic_games_id, discipline_id, medal_type_id) 
        VALUES (:athlete_id, :games_id, :discipline_id, :medal_type_id)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':athlete_id' => $athleteId,
        ':games_id' => $gamesId,
        ':discipline_id' => $disciplineId,
        ':medal_type_id' => $medalTypeId
    ]);

    return (int)$pdo->lastInsertId();
}

// ================== PARSOVANIE CSV ==================
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

function convertDate(?string $date): ?string
{
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

// ================== POMOCNÉ FUNKCIE PRE FILTRE A TRIEDENIE ==================
function nextOrder(string $column, string $currentSort, string $currentOrder): string
{
    if ($currentSort !== $column) {
        return $column === 'year' ? 'desc' : 'asc';
    }

    if ($currentOrder === 'asc') {
        return 'desc';
    }

    if ($currentOrder === 'desc') {
        return 'none';
    }

    return $column === 'year' ? 'desc' : 'asc';
}

function buildSortLink(
    string $column,
    string $selectedYear,
    string $selectedCategory,
    string $currentSort,
    string $currentOrder
): string {
    $next = nextOrder($column, $currentSort, $currentOrder);

    $params = [];
    if ($selectedYear !== '') {
        $params['year'] = $selectedYear;
    }
    if ($selectedCategory !== '') {
        $params['category'] = $selectedCategory;
    }

    if ($next !== 'none') {
        $params['sort'] = $column;
        $params['order'] = $next;
    }

    return '?' . http_build_query($params);
}

// ================== HLAVNÝ KÓD ==================
$data = [];
$report = [];
$dbMessage = '';
$conn = null;

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

        if ($conn && !empty($data)) {
            $inserted = 0;
            $errors = 0;

            foreach ($data as $row) {
                try {
                    $birthCountryId = null;
                    if (!empty($row['birth_country'])) {
                        $birthCountryId = getOrCreateCountry($conn, $row['birth_country']);
                    }

                    $deathCountryId = null;
                    if (!empty($row['death_country'])) {
                        $deathCountryId = getOrCreateCountry($conn, $row['death_country']);
                    }

                    $gamesCountryId = null;
                    if (!empty($row['oh_country'])) {
                        $gamesCountryId = getOrCreateCountry($conn, $row['oh_country']);
                    }

                    $gamesId = null;
                    if (
                        !empty($row['oh_year']) &&
                        !empty($row['oh_type']) &&
                        !empty($row['oh_city']) &&
                        $gamesCountryId
                    ) {
                        $gamesId = getOrCreateGames(
                            $conn,
                            (int)$row['oh_year'],
                            $row['oh_type'],
                            $row['oh_city'],
                            $gamesCountryId
                        );
                    }

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

                    $disciplineId = null;
                    if (!empty($row['discipline'])) {
                        $disciplineId = getOrCreateDiscipline(
                            $conn,
                            $row['discipline'] ?? '',
                            $row['category'] ?? null
                        );
                    }

                    $medalTypeId = null;
                    if (!empty($row['placing']) && in_array((int)$row['placing'], [1, 2, 3], true)) {
                        $medalTypeId = getMedalTypeId($conn, (int)$row['placing']);
                    }

                    if ($athleteId && $gamesId && $disciplineId && $medalTypeId) {
                        insertMedal($conn, $athleteId, $gamesId, $disciplineId, $medalTypeId);
                    }

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

// ================== NAČÍTANIE OLYMPIONIKOV ==================
$olympians = [];
$years = [];
$categories = [];

$selectedYear = $_GET['year'] ?? '';
$selectedCategory = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? '';
$order = $_GET['order'] ?? '';

if ($conn) {
    try {
        $stmtYears = $conn->query("
            SELECT DISTINCT og.year
            FROM athlete_medals am
            JOIN olympic_games og ON am.olympic_games_id = og.id
            ORDER BY og.year DESC
        ");
        $years = $stmtYears->fetchAll(PDO::FETCH_COLUMN);

        $stmtCategories = $conn->query("
            SELECT DISTINCT d.category
            FROM athlete_medals am
            JOIN disciplines d ON am.discipline_id = d.id
            WHERE d.category IS NOT NULL AND d.category <> ''
            ORDER BY d.category ASC
        ");
        $categories = $stmtCategories->fetchAll(PDO::FETCH_COLUMN);

        $sql = "
            SELECT 
                a.first_name,
                a.last_name,
                og.year,
                c.name AS country,
                d.category,
                am.id AS medal_id
            FROM athlete_medals am
            JOIN athletes a ON am.athlete_id = a.id
            JOIN olympic_games og ON am.olympic_games_id = og.id
            JOIN countries c ON og.country_id = c.id
            JOIN disciplines d ON am.discipline_id = d.id
            WHERE 1=1
        ";

        $params = [];

        if ($selectedYear !== '') {
            $sql .= " AND og.year = :year ";
            $params[':year'] = $selectedYear;
        }

        if ($selectedCategory !== '') {
            $sql .= " AND d.category = :category ";
            $params[':category'] = $selectedCategory;
        }

        $allowedSorts = ['last_name', 'year', 'category'];

        if (in_array($sort, $allowedSorts, true)) {
            if ($sort === 'last_name') {
                if ($order === 'asc') {
                    $sql .= " ORDER BY a.last_name ASC, a.first_name ASC ";
                } elseif ($order === 'desc') {
                    $sql .= " ORDER BY a.last_name DESC, a.first_name DESC ";
                } else {
                    $sql .= " ORDER BY am.id ASC ";
                }
            } elseif ($sort === 'year') {
                if ($order === 'desc') {
                    $sql .= " ORDER BY og.year DESC ";
                } elseif ($order === 'asc') {
                    $sql .= " ORDER BY og.year ASC ";
                } else {
                    $sql .= " ORDER BY am.id ASC ";
                }
            } elseif ($sort === 'category') {
                if ($order === 'asc') {
                    $sql .= " ORDER BY d.category ASC ";
                } elseif ($order === 'desc') {
                    $sql .= " ORDER BY d.category DESC ";
                } else {
                    $sql .= " ORDER BY am.id ASC ";
                }
            }
        } else {
            $sql .= " ORDER BY am.id ASC ";
        }

        $stmtOlympians = $conn->prepare($sql);
        $stmtOlympians->execute($params);
        $olympians = $stmtOlympians->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $report[] = "Chyba pri načítaní olympionikov: " . $e->getMessage();
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
        .status {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .status.ok {
            background: #d4edda;
            color: #155724;
        }
        .status.err {
            background: #f8d7da;
            color: #721c24;
        }
        .form-group, .report, .table-box, .filters-box, .data-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .badge {
            background: #007bff;
            color: white;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 14px;
        }
        .filters-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: end;
        }
        .filters-row div {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        select {
            padding: 8px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            min-width: 180px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px 12px;
            text-align: left;
        }
        th {
            background: #f0f4f8;
        }
        th a {
            color: #007bff;
            text-decoration: none;
        }
        th a:hover {
            text-decoration: underline;
        }
        .reset-link {
            display: inline-block;
            margin-left: 10px;
            color: #dc3545;
            text-decoration: none;
        }
        .reset-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h1>CSV Upload + Databáza</h1>

    <div class="status <?php echo $conn ? 'ok' : 'err'; ?>">
        <?php echo htmlspecialchars($dbMessage); ?>
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

    <div class="filters-box">
        <h3>Filtrovanie olympionikov</h3>
        <form method="GET">
            <div class="filters-row">
                <div>
                    <label for="year">Rok</label>
                    <select name="year" id="year">
                        <option value="">-- všetky roky --</option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?php echo htmlspecialchars((string)$year); ?>" <?php echo ($selectedYear == $year) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string)$year); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="category">Kategória</label>
                    <select name="category" id="category">
                        <option value="">-- všetky kategórie --</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars((string)$category); ?>" <?php echo ($selectedCategory === $category) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string)$category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <button type="submit">Filtrovať</button>
                    <a class="reset-link" href="<?php echo htmlspecialchars(strtok($_SERVER["REQUEST_URI"], '?')); ?>">Zrušiť filtre</a>
                </div>
            </div>
        </form>
    </div>

    <div class="table-box">
        <h3>
            Zoznam olympionikov
            <span class="badge"><?php echo count($olympians); ?> záznamov</span>
        </h3>

        <?php if (!empty($olympians)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Meno</th>
                        <th>
                            <a href="<?php echo htmlspecialchars(buildSortLink('last_name', $selectedYear, $selectedCategory, $sort, $order)); ?>">
                                Priezvisko
                            </a>
                        </th>

                        <?php if ($selectedYear === ''): ?>
                            <th>
                                <a href="<?php echo htmlspecialchars(buildSortLink('year', $selectedYear, $selectedCategory, $sort, $order)); ?>">
                                    Rok
                                </a>
                            </th>
                        <?php endif; ?>

                        <th>Krajina</th>

                        <?php if ($selectedCategory === ''): ?>
                            <th>
                                <a href="<?php echo htmlspecialchars(buildSortLink('category', $selectedYear, $selectedCategory, $sort, $order)); ?>">
                                    Kategória
                                </a>
                            </th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($olympians as $olympian): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($olympian['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($olympian['last_name']); ?></td>

                            <?php if ($selectedYear === ''): ?>
                                <td><?php echo htmlspecialchars((string)$olympian['year']); ?></td>
                            <?php endif; ?>

                            <td><?php echo htmlspecialchars($olympian['country']); ?></td>

                            <?php if ($selectedCategory === ''): ?>
                                <td><?php echo htmlspecialchars($olympian['category']); ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Neboli nájdení žiadni olympionici.</p>
        <?php endif; ?>
    </div>

    <?php if (!empty($data)): ?>
        <div class="data-box">
            <h3>
                Obsah súboru
                <span class="badge"><?php echo count($data); ?> záznamov</span>
            </h3>
            <?php echo '<a href="olympians.php">Zoznam olympionikov</a>'; ?>
            <pre><?php print_r($data); ?></pre>
        </div>
    <?php endif; ?>
</body>
</html>