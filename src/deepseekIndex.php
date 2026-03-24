<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo '<p>Pre pokračovanie sa prosím <a href="login.php">prihláste</a> alebo sa <a href="register.php">zaregistrujte</a>.</p>';
} else {
    echo '<h3>Vitaj ' . htmlspecialchars($_SESSION['full_name']) . '</h3>';
    echo '<a href="restricted.php">Zabezpečená stránka</a> | <a href="logout.php">Odhlásiť</a>';
}

require_once __DIR__ . '/config.php';

// ================== FUNKCIE NA PRÁCU S KRAJINAMI ==================
function getOrCreateCountry(PDO $pdo, string $name): int {
    $stmt = $pdo->prepare("SELECT id FROM countries WHERE name = :name LIMIT 1");
    $stmt->execute([':name' => $name]);
    $id = $stmt->fetchColumn();
    if ($id) return (int) $id;
    $stmt = $pdo->prepare("INSERT INTO countries (name) VALUES (:name)");
    $stmt->execute([':name' => $name]);
    return (int) $pdo->lastInsertId();
}

// ================== FUNKCIE NA PRÁCU S OLYMPIJSKÝMI HRAMI ==================
function getOrCreateGames(PDO $pdo, int $year, string $type, string $city, int $countryId): int {
    $stmt = $pdo->prepare("SELECT id FROM olympic_games WHERE year = :year AND type = :type LIMIT 1");
    $stmt->execute([':year' => $year, ':type' => $type]);
    $id = $stmt->fetchColumn();
    if ($id) return (int) $id;
    if (!in_array($type, ['LOH', 'ZOH'])) {
        throw new InvalidArgumentException("Type must be LOH or ZOH");
    }
    $stmt = $pdo->prepare("INSERT INTO olympic_games (year, type, city, country_id) VALUES (:year, :type, :city, :country_id)");
    $stmt->execute([':year' => $year, ':type' => $type, ':city' => $city, ':country_id' => $countryId]);
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
    $stmt = $pdo->prepare("SELECT id FROM athletes WHERE first_name = :first_name AND last_name = :last_name AND (birth_date = :birth_date OR (birth_date IS NULL AND :birth_date IS NULL)) LIMIT 1");
    $stmt->execute([':first_name' => $firstName, ':last_name' => $lastName, ':birth_date' => $birthDate]);
    $id = $stmt->fetchColumn();
    if ($id) return (int) $id;
    $sql = "INSERT INTO athletes (first_name, last_name, birth_date, birth_place, birth_country_id, death_date, death_place, death_country_id)
            VALUES (:first_name, :last_name, :birth_date, :birth_place, :birth_country_id, :death_date, :death_place, :death_country_id)";
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
    if ($id) return (int) $id;
    $stmt = $pdo->prepare("INSERT INTO disciplines (name, category) VALUES (:name, :category)");
    $stmt->execute([':name' => $name, ':category' => $category]);
    return (int) $pdo->lastInsertId();
}

// ================== FUNKCIE NA PRÁCU SO TYPOM MEDAIL ==================
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

// ================== PARSOVANIE CSV ==================
function parseCsvToAssocArray(string $filePath, string $delimiter = ";"): array {
    $result = [];
    if (!file_exists($filePath)) return [];
    $handle = fopen($filePath, 'r');
    if ($handle === false) return [];
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
    if (empty($date)) return null;
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

        if ($conn && !empty($data)) {
            $inserted = 0;
            $errors = 0;

            foreach ($data as $row) {
                try {
                    $birthCountryId = !empty($row['birth_country']) ? getOrCreateCountry($conn, $row['birth_country']) : null;
                    $deathCountryId = !empty($row['death_country']) ? getOrCreateCountry($conn, $row['death_country']) : null;
                    $gamesCountryId = !empty($row['oh_country']) ? getOrCreateCountry($conn, $row['oh_country']) : null;

                    $gamesId = null;
                    if (!empty($row['oh_year']) && !empty($row['oh_type']) && !empty($row['oh_city']) && $gamesCountryId) {
                        $gamesId = getOrCreateGames($conn, (int)$row['oh_year'], $row['oh_type'], $row['oh_city'], $gamesCountryId);
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
                        $disciplineId = getOrCreateDiscipline($conn, $row['discipline'] ?? '', $row['category'] ?? null);
                    }

                    $medalTypeId = null;
                    if (!empty($row['placing']) && in_array((int)$row['placing'], [1,2,3])) {
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
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Olympijská databáza</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
            background: #f5f5f5;
        }
        h1, h2 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .status {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .ok { background: #d4edda; color: #155724; }
        .err { background: #f8d7da; color: #721c24; }
        .form-group {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .report {
            background: #cce5ff;
            color: #004085;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
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
        button:hover { background: #0056b3; }
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .filter-item {
            flex: 1;
            min-width: 200px;
        }
        .filter-item label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        .filter-item select, .filter-item button {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #007bff;
            color: white;
            cursor: pointer;
            user-select: none;
        }
        th:hover { background: #0056b3; }
        tr:hover { background: #f5f5f5; }
        .medal {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        .medal-1 { background: gold; color: #333; }
        .medal-2 { background: silver; color: #333; }
        .medal-3 { background: #cd7f32; color: white; }
        .no-data {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            color: #666;
        }
        pre {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            overflow-x: auto;
        }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <h1>Olympijská databáza</h1>

    <div class="status <?php echo $conn ? 'ok' : 'err'; ?>">
        <?php echo $dbMessage; ?>
    </div>

    <div class="form-group">
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="csv_file" accept=".csv" required>
            <button type="submit">Nahrať a spracovať CSV</button>
        </form>
    </div>

    <?php if (!empty($report)): ?>
        <div class="report">
            <?php echo implode('<br>', array_map('htmlspecialchars', $report)); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($data)): ?>
        <div style="margin-bottom: 20px;">
            <h3>Obsah nahraného CSV súboru</h3>
            <pre><?php print_r($data); ?></pre>
        </div>
    <?php endif; ?>

    <?php if ($conn): 
        // ===== ZÍSKANIE DÁT PRE FILTRE =====
        $years = $conn->query("SELECT DISTINCT year FROM olympic_games ORDER BY year DESC")->fetchAll(PDO::FETCH_COLUMN);
        $categories = $conn->query("SELECT DISTINCT category FROM disciplines WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
        
        $selectedYear = $_GET['year'] ?? '';
        $selectedCategory = $_GET['category'] ?? '';
        $showYear = empty($selectedYear);
        $showCategory = empty($selectedCategory);
        $sortColumn = $_GET['sort'] ?? 'last_name';
        $sortOrder = $_GET['order'] ?? 'asc';
        
        // ===== SQL DOTAZ =====
        $sql = "SELECT 
                    a.id,
                    a.first_name,
                    a.last_name,
                    c.name as country,
                    d.name as discipline,
                    d.category,
                    og.year,
                    og.type as games_type,
                    og.city as games_city,
                    mt.name as medal_name,
                    mt.placing
                FROM athletes a
                JOIN athlete_medals am ON a.id = am.athlete_id
                JOIN olympic_games og ON am.olympic_games_id = og.id
                JOIN countries c ON og.country_id = c.id
                JOIN disciplines d ON am.discipline_id = d.id
                JOIN medal_types mt ON am.medal_type_id = mt.id
                WHERE 1=1";
        
        $params = [];
        if (!empty($selectedYear)) {
            $sql .= " AND og.year = :year";
            $params[':year'] = $selectedYear;
        }
        if (!empty($selectedCategory)) {
            $sql .= " AND d.category = :category";
            $params[':category'] = $selectedCategory;
        }
        
        $sql .= " ORDER BY ";
        switch ($sortColumn) {
            case 'last_name':
                $sql .= "a.last_name " . ($sortOrder === 'asc' ? 'ASC' : 'DESC');
                break;
            case 'year':
                $sql .= "og.year " . ($sortOrder === 'asc' ? 'DESC' : 'ASC');
                break;
            case 'category':
                $sql .= "d.category " . ($sortOrder === 'asc' ? 'ASC' : 'DESC');
                break;
            default:
                $sql .= "a.last_name ASC";
        }
        
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $olympians = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
        <h2>Zoznam olympionikov a ich ocenení</h2>

        <div class="filter-section">
            <div class="filter-item">
                <label for="year-filter">Filter podľa roku:</label>
                <select id="year-filter" onchange="applyFilters()">
                    <option value="">Všetky roky</option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?php echo $year; ?>" <?php echo $selectedYear == $year ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item">
                <label for="category-filter">Filter podľa kategórie:</label>
                <select id="category-filter" onchange="applyFilters()">
                    <option value="">Všetky kategórie</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $selectedCategory == $category ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item">
                <button onclick="resetFilters()">Resetovať filtre</button>
            </div>
        </div>

        <?php if (!empty($olympians)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Meno</th>
                        <th onclick="sortTable('last_name')">Priezvisko <?php echo $sortColumn === 'last_name' ? ($sortOrder === 'asc' ? '▲' : '▼') : ''; ?></th>
                        <th>Krajina</th>
                        <?php if ($showYear): ?>
                            <th onclick="sortTable('year')">Rok <?php echo $sortColumn === 'year' ? ($sortOrder === 'asc' ? '▲' : '▼') : ''; ?></th>
                        <?php endif; ?>
                        <?php if ($showCategory): ?>
                            <th onclick="sortTable('category')">Kategória <?php echo $sortColumn === 'category' ? ($sortOrder === 'asc' ? '▲' : '▼') : ''; ?></th>
                        <?php endif; ?>
                        <th>Disciplína</th>
                        <th>Medaila</th>
                        <th>Miesto konania</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($olympians as $o): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($o['first_name']); ?></td>
                        <td><?php echo htmlspecialchars($o['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($o['country']); ?></td>
                        <?php if ($showYear): ?>
                            <td><?php echo $o['year']; ?></td>
                        <?php endif; ?>
                        <?php if ($showCategory): ?>
                            <td><?php echo htmlspecialchars($o['category']); ?></td>
                        <?php endif; ?>
                        <td><?php echo htmlspecialchars($o['discipline']); ?></td>
                        <td>
                            <span class="medal medal-<?php echo $o['placing']; ?>">
                                <?php echo htmlspecialchars($o['medal_name']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($o['games_city'] . ' (' . $o['games_type'] . ')'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-data">
                <p>Žiadni olympionici neboli nájdení pre zvolené filtre.</p>
                <p><a href="<?php echo $_SERVER['PHP_SELF']; ?>">Zobraziť všetkých</a></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <script>
        function applyFilters() {
            const year = document.getElementById('year-filter').value;
            const category = document.getElementById('category-filter').value;
            let url = new URL(window.location.href);
            url.searchParams.set('year', year);
            url.searchParams.set('category', category);
            const sort = new URLSearchParams(window.location.search).get('sort');
            const order = new URLSearchParams(window.location.search).get('order');
            if (sort) url.searchParams.set('sort', sort);
            if (order) url.searchParams.set('order', order);
            window.location.href = url.toString();
        }

        function resetFilters() {
            window.location.href = window.location.pathname;
        }

        function sortTable(column) {
            let url = new URL(window.location.href);
            const currentSort = url.searchParams.get('sort');
            const currentOrder = url.searchParams.get('order');
            let newOrder = 'asc';
            if (currentSort === column) {
                newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
            }
            url.searchParams.set('sort', column);
            url.searchParams.set('order', newOrder);
            window.location.href = url.toString();
        }
    </script>
</body>
</html>