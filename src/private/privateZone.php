<?php
// Zacneme session pre pracu s prihlasenym pouzivatelom
session_start();

// Nacitame navigacne menu
require_once '../navigation.php';

// Zisti, ci je pouzivatel prihlaseny
$isLoggedIn = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;

// Nacitame konfiguracne udaje (prihlasovacie udaje k databaze atd.)
require_once __DIR__ . '/../config.php';

// ================== FUNKCIA NA EXTRAKCIU KATEGÓRIE Z NÁZVU DISCIPLÍNY ==================
// Tato funkcia sa pokusi z nazvu discipliny (napr. "box do 60kg") odvodit kategoriu (napr. "box")
function extractCategoryFromDiscipline(string $disciplineName): ?string {
    // Ak nazov obsahuje " - ", berieme prvu cast ako kategoriu
    if (strpos($disciplineName, ' - ') !== false) {
        return explode(' - ', $disciplineName)[0];
    }
    // Rucne priradenie pre zname sporty
    if (strpos($disciplineName, 'box') === 0) {
        if (strpos($disciplineName, 'box do') === 0) return 'box';
    }
    if (strpos($disciplineName, 'futbal') === 0) return 'futbal';
    if (strpos($disciplineName, 'ľadový hokej') === 0) return 'hokej';
    if (strpos($disciplineName, 'krasokorčuľovanie') === 0) return 'krasokorčuľovanie';
    if (strpos($disciplineName, 'tenis') === 0) return 'tenis';
    if (strpos($disciplineName, 'vodný slalom') === 0) return 'vodný slalom';
    if (strpos($disciplineName, 'rýchlostná kanoistika') === 0) return 'kanoistika';
    if (strpos($disciplineName, 'dráhová cyklistika') === 0) return 'cyklistika';
    if (strpos($disciplineName, 'športová streľba') === 0) return 'streľba';
    if (strpos($disciplineName, 'atletika') === 0) return 'atletika';
    if (strpos($disciplineName, 'plávanie') === 0) return 'plávanie';
    return null; // ziadna kategoria nebola rozpoznana
}

// ================== FUNKCIE NA PRÁCU S KRAJINAMI ==================
// Funkcia vrati ID krajiny podla mena, ak neexistuje, tak ju vytvori
function getOrCreateCountry(PDO $pdo, string $name): int {
    // Skusime najst existujucu krajinu
    $stmt = $pdo->prepare("SELECT id FROM countries WHERE name = :name LIMIT 1");
    $stmt->execute([':name' => $name]);
    $id = $stmt->fetchColumn();
    if ($id) return (int) $id; // nasli sme, vratime ID
    
    // Krajina neexistuje - vlozime ju
    $stmt = $pdo->prepare("INSERT INTO countries (name) VALUES (:name)");
    $stmt->execute([':name' => $name]);
    return (int) $pdo->lastInsertId(); // vratime ID prave vlozeneho zaznamu
}

// ================== FUNKCIE NA PRÁCU S OLYMPIJSKÝMI HRAMI ==================
// Funkcia vrati ID olympijskych hier podla roku, typu a mesta, pripadne ich vytvori
function getOrCreateGames(PDO $pdo, int $year, string $type, string $city, int $countryId): int {
    // Najprv hladame existujuce hry
    $stmt = $pdo->prepare("SELECT id FROM olympic_games WHERE year = :year AND type = :type AND city = :city LIMIT 1");
    $stmt->execute([':year' => $year, ':type' => $type, ':city' => $city]);
    $id = $stmt->fetchColumn();
    if ($id) return (int) $id; // nasli sme
    
    // Kontrola spravneho typu (len LOH alebo ZOH)
    if (!in_array($type, ['LOH', 'ZOH'])) throw new InvalidArgumentException("Type must be LOH or ZOH");
    
    // Vlozenie novych hier
    $stmt = $pdo->prepare("INSERT INTO olympic_games (year, type, city, country_id) VALUES (:year, :type, :city, :country_id)");
    $stmt->execute([':year' => $year, ':type' => $type, ':city' => $city, ':country_id' => $countryId]);
    return (int) $pdo->lastInsertId();
}

// ================== FUNKCIE NA PRÁCU SO ŠPORTOVCAMI ==================
// Funkcia vytvori alebo vrati existujuceho sportovca podla mena, priezviska a datumu narodenia
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
    // Hladame existujuceho sportovca (kombinacia mena, priezviska a datumu narodenia by mala byt unikatna)
    $stmt = $pdo->prepare("SELECT id FROM athletes WHERE first_name = :first_name AND last_name = :last_name AND birth_date = :birth_date LIMIT 1");
    $stmt->execute([':first_name' => $firstName, ':last_name' => $lastName, ':birth_date' => $birthDate]);
    $id = $stmt->fetchColumn();
    if ($id) return (int) $id; // uz existuje
    
    // Ak neexistuje, vlozime ho
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
    return (int) $pdo->lastInsertId(); // vratime ID noveho sportovca
}

// ================== FUNKCIE NA PRÁCU S DISCIPLÍNAMI ==================
// Funkcia vrati alebo vytvori disciplinu, volitelne aj s kategorou
function getOrCreateDiscipline(PDO $pdo, string $name, ?string $category = null): int {
    // Hladame disciplinu podla nazvu
    $stmt = $pdo->prepare("SELECT id FROM disciplines WHERE name = :name LIMIT 1");
    $stmt->execute([':name' => $name]);
    $id = $stmt->fetchColumn();
    if ($id) {
        // Ak disciplina uz existuje, mozeme doplnit kategorii ak este nie je nastavena
        if ($category) {
            $checkCategory = $pdo->prepare("SELECT category FROM disciplines WHERE id = :id");
            $checkCategory->execute([':id' => $id]);
            $existingCategory = $checkCategory->fetchColumn();
            if (empty($existingCategory)) {
                $update = $pdo->prepare("UPDATE disciplines SET category = :category WHERE id = :id");
                $update->execute([':category' => $category, ':id' => $id]);
            }
        }
        return (int) $id;
    }
    // Disciplina neexistuje - vlozime ju
    $stmt = $pdo->prepare("INSERT INTO disciplines (name, category) VALUES (:name, :category)");
    $stmt->execute([':name' => $name, ':category' => $category]);
    return (int) $pdo->lastInsertId();
}

// ================== FUNKCIE NA PRÁCU S TYPOM MEDAILY ==================
// Funkcia vrati ID typu medaily podla umiestnenia (1,2,3 = zlato, striebro, bronz, inak "Umiestnenie X")
function getOrCreateMedalType(PDO $pdo, int $placing): ?int {
    // Hladame podla umiestnenia
    $stmt = $pdo->prepare("SELECT id FROM medal_types WHERE placing = :placing LIMIT 1");
    $stmt->execute([':placing' => $placing]);
    $id = $stmt->fetchColumn();
    if ($id) return (int) $id; // uz existuje
    
    // Pripravime nazov a popis podla umiestnenia
    $medalName = match($placing) {
        1 => 'Zlato',
        2 => 'Striebro',
        3 => 'Bronz',
        default => 'Umiestnenie ' . $placing
    };
    $description = match($placing) {
        1 => 'Zlatá medaila',
        2 => 'Strieborná medaila',
        3 => 'Bronzová medaila',
        default => 'Umiestnenie bez medaily'
    };
    
    // Vlozime novy typ medaily
    $stmt = $pdo->prepare("INSERT INTO medal_types (placing, name, description) VALUES (:placing, :name, :description)");
    $stmt->execute([':placing' => $placing, ':name' => $medalName, ':description' => $description]);
    return (int) $pdo->lastInsertId();
}

// Funkcia na vlozenie medailoveho zaznamu pre sportovca
function insertMedal(PDO $pdo, int $athleteId, int $gamesId, int $disciplineId, int $medalTypeId): int {
    $sql = "INSERT INTO athlete_medals (athlete_id, olympic_games_id, discipline_id, medal_type_id) VALUES (:athlete_id, :games_id, :discipline_id, :medal_type_id)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':athlete_id' => $athleteId, ':games_id' => $gamesId, ':discipline_id' => $disciplineId, ':medal_type_id' => $medalTypeId]);
    return (int) $pdo->lastInsertId();
}

// ================== PARSOVANIE CSV ==================
// Funkcia nacita CSV subor a vrati ho ako asociativne pole
function parseCsvToAssocArray(string $filePath, string $delimiter = ";"): array {
    $result = [];
    if (!file_exists($filePath)) return [];
    $handle = fopen($filePath, 'r');
    if ($handle === false) return [];
    
    // Nacitame hlavicku (prvy riadok)
    $headers = fgetcsv($handle, 0, $delimiter, '"', '\\');
    if ($headers === false) { fclose($handle); return []; }
    
    // Postupne nacitavame riadky a kombinujeme s hlavickou
    while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
        if (count($row) === count($headers)) {
            $result[] = array_combine($headers, $row);
        }
    }
    fclose($handle);
    return $result;
}

// Funkcia na konverziu datumu zo slovenskeho formatu (DD.MM.RRRR) do SQL formatu (RRRR-MM-DD)
function convertDate(?string $date): ?string {
    if (empty($date)) return null;
    $parts = explode('.', trim($date));
    if (count($parts) === 3) {
        $day = str_pad((int)$parts[0], 2, '0', STR_PAD_LEFT);
        $month = str_pad((int)$parts[1], 2, '0', STR_PAD_LEFT);
        $year = (int)$parts[2];
        return sprintf("%04d-%02d-%02d", $year, $month, $day);
    }
    return null; // nepodarilo sa konvertovat
}

// ================== HLAVNÝ KÓD ==================
// Inicializacia premennych
$data = [];      // data z CSV (zobrazene v tabulke)
$report = [];    // spravy o priebehu spracovania
$dbMessage = ''; // sprava o stave pripojenia k DB

// Pokus o pripojenie k databaze
try {
    $conn = connectDatabase($hostname, $database, $username, $password);
    $dbMessage = $conn ? 'Pripojené k databáze' : '✗ Nepripojené k databáze';
} catch (Throwable $e) {
    $dbMessage = 'Chyba: ' . $e->getMessage();
}

// Spracovanie CSV suboru (ak bol odoslany formular a nie je to mazanie)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && !isset($_POST['delete_all'])) {
    $file = $_FILES['csv_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Kontrola ci je subor CSV
    if ($ext !== 'csv') {
        $report[] = "Povolené sú iba CSV súbory.";
    } elseif ($file['error'] !== 0) {
        $report[] = "Chyba pri nahrávaní súboru.";
    } else {
        // Nacitame data z CSV
        $data = parseCsvToAssocArray($file['tmp_name'], ";");
        $report[] = "CSV nahratý, " . count($data) . " záznamov.";
        
        // Ak je pripojenie k DB a data nie su prazdne, vlozime ich do databazy
        if ($conn && !empty($data)) {
            $_SESSION['csv_data'] = $data; // ulozime do session pre pripadne zobrazenie
            $inserted = 0;
            $errors = 0;
            
            // Pre kazdy riadok CSV skusime vlozit data
            foreach ($data as $index => $row) {
                try {
                    // Povinne polia
                    if (empty($row['name']) || empty($row['surname'])) {
                        throw new Exception("Chýba meno alebo priezvisko");
                    }
                    
                    // Ziskame ID krajin (ak su zadane)
                    $birthCountryId = !empty($row['birth_country']) ? getOrCreateCountry($conn, $row['birth_country']) : null;
                    $deathCountryId = !empty($row['death_country']) ? getOrCreateCountry($conn, $row['death_country']) : null;
                    $gamesCountryId = !empty($row['oh_country']) ? getOrCreateCountry($conn, $row['oh_country']) : null;
                    
                    // Ziskame ID olympijskych hier (ak su zadane vsetky potrebne udaje)
                    $gamesId = null;
                    if (!empty($row['oh_year']) && !empty($row['oh_type']) && !empty($row['oh_city']) && $gamesCountryId) {
                        $gamesId = getOrCreateGames($conn, (int)$row['oh_year'], $row['oh_type'], $row['oh_city'], $gamesCountryId);
                    }
                    
                    // Ziskame ID sportovca (vytvorime ho ak este neexistuje)
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
                    
                    // Ziskame ID discipliny
                    $disciplineId = null;
                    if (!empty($row['discipline'])) {
                        $disciplineName = $row['discipline'] ?? '';
                        $category = extractCategoryFromDiscipline($disciplineName);
                        $disciplineId = getOrCreateDiscipline($conn, $disciplineName, $category);
                    }
                    
                    // Ziskame ID typu medaily
                    $medalTypeId = null;
                    if (!empty($row['placing']) && is_numeric($row['placing'])) {
                        $medalTypeId = getOrCreateMedalType($conn, (int)$row['placing']);
                    }
                    
                    // Ak mame vsetky ID, vlozime medailu
                    if ($athleteId && $gamesId && $disciplineId && $medalTypeId) {
                        insertMedal($conn, $athleteId, $gamesId, $disciplineId, $medalTypeId);
                        $categoryText = $category ? " (kategória: $category)" : "";
                        $report[] = "Vložený záznam pre " . $row['name'] . " " . $row['surname'] . " (umiestnenie: " . $row['placing'] . ")" . $categoryText;
                    }
                    $inserted++;
                } catch (Exception $e) {
                    $errors++;
                    $report[] = "Chyba pri zázname č. " . ($index + 1) . ": " . $e->getMessage();
                }
            }
            $report[] = "Spracovaných $inserted záznamov, $errors chýb.";
            $report[] = "Kategórie boli extrahované z názvov disciplín a uložené do databázy.";
        }
    }
}

// Ak mame v session ulozene CSV data, obnovime ich do premennej $data (pre zobrazenie tabulky)
if (isset($_SESSION['csv_data']) && !empty($_SESSION['csv_data'])) {
    $data = $_SESSION['csv_data'];
}

// Spracovanie poziadavky na vymazanie vsetkych dat
if (isset($_POST['delete_all'])) {
    if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
        try {
            // Zacneme transakciu
            $conn->beginTransaction();
            
            // Vymazeme vsetky tabulky v spravnom poradi (kvôli cudzim klúčom)
            $conn->exec("DELETE FROM athlete_medals");
            $conn->exec("DELETE FROM athletes");
            $conn->exec("DELETE FROM disciplines");
            $conn->exec("DELETE FROM olympic_games");
            $conn->exec("DELETE FROM countries WHERE id > 24"); // ponechame prve zakladne krajiny (ID 1-24)
            
            // Potvrdime transakciu
            $conn->commit();
            
            $message = '<div class="success">Všetky údaje boli vymazané.</div>';
            unset($_SESSION['csv_data']); // odstranime data zo session
            $data = ""; // vycistime premennu $data, aby tabulka zmizla
        } catch (Exception $e) {
            // V pripade chyby vratime transakciu spat
            $conn->rollBack();
            $message = '<div class="error">Chyba pri mazaní: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="error">Potvrďte vymazanie zaškrtnutím políčka.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>CSV Upload + DB</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Kniznica DataTables pre pekne tabulky -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <!-- Font Awesome pre ikony -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/privateZone.css">
</head>
<body>

    <?php if (!$isLoggedIn): ?>
        <!-- Ak pouzivatel nie je prihlaseny, zobrazime peknu spravu a ukoncime dalsie spracovanie -->
        <div class="auth-required">
            <i class="fas fa-lock"></i>
            <p>Pre pokračovanie sa prosím <a href="login.php">prihláste</a> alebo sa <a href="register.php">zaregistrujte</a>.</p>
        </div>
        <?php exit; ?>
    <?php endif; ?>
    
    <h1>CSV Upload + Databáza</h1>

    <!-- Blok s privitanim prihlaseneho pouzivatela -->
    <div class="welcome">
        <h3>Vitaj <?php echo htmlspecialchars($_SESSION['full_name']); ?></h3>
        <p><a href="restricted.php">Zabezpečená stránka</a></p>
    </div>

    <!-- Stav pripojenia k databaze -->
    <div class="status <?php echo $conn ? 'ok' : 'err'; ?>">
        <?php echo $dbMessage; ?>
    </div>

    <!-- Formular pre nahratie CSV suboru -->
    <div class="form-group">
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="csv_file" accept=".csv" required>
            <button type="submit">Nahrať a spracovať</button>
        </form>
    </div>

    <!-- Formular pre vymazanie vsetkych dat (vyžaduje potvrdenie checkboxom) -->
    <form method="post">
        <label>
            <input type="checkbox" name="confirm_delete" value="yes" required>
            Potvrdzujem vymazanie všetkých údajov (táto akcia je nenávratná).
        </label>
        <button type="submit" name="delete_all" class="btn-danger">Vymazať všetky údaje</button>
    </form>

    <!-- Vypis sprav (uspech / chyba) ak existuju -->
    <?php if (isset($message)) echo $message; ?>
    <!-- Tabulka s prave nahranymi datami z CSV (zobrazi sa len ak su data) -->
    <?php if ($conn && !empty($data)): ?>
       
        <div class="table-container">
             <h2>Nahrané dáta z CSV</h2>
            <table id="csvTable" class="display" style="width:100%">
                <thead>
                    <tr>
                        <?php if (!empty($data[0])): ?>
                            <?php foreach (array_keys($data[0]) as $header): ?>
                                <th><?php echo htmlspecialchars($header); ?></th>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td><?php echo htmlspecialchars($cell ?? ''); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Javascript pre inicializaciu DataTables s prekladom do slovenciny -->
        <script>
        $(document).ready(function() {
            $('#csvTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/sk.json' // slovensky preklad
                },
                pageLength: 10,          // predvoleny pocet riadkov na stranu
                lengthMenu: [[10, 20, 50, -1], [10, 20, 50, 'Všetky']], // moznosti poctu riadkov
                ordering: false,          // vypneme triedenie (zachovame poradie z CSV)
                order: []                 // ziadne predvolene triedenie
            });
        });
        </script>
    <?php endif; ?>
</body>
</html>