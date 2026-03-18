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
    <style>
        /* Vsetky styly zostavaju bez zmeny */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Roboto, system-ui, sans-serif;
            background: #f3e8ff;
           
            margin: 0 auto;
            padding: 0 20px;
            color: #2d1b4e;
        }

        h1 {
            color: #2d1b4e;
            border-bottom: 3px solid #8b5cf6;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        /* status a welcome blok */
        .status, .welcome {
            padding: 15px;
            border-radius: 20px;
            margin: 20px 0;
            backdrop-filter: blur(5px);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.1);
        }

        .status.ok {
            background: rgba(212, 237, 218, 0.9);
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status.err {
            background: rgba(248, 215, 218, 0.9);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .welcome {
            background: rgba(255, 255, 255, 0.8);
            border-left: 4px solid #8b5cf6;
        }

        .welcome a {
            color: #8b5cf6;
            text-decoration: none;
            font-weight: bold;
        }

        .success, .error {
            padding: 15px;
            border-radius: 20px;
            margin: 20px 0;
            backdrop-filter: blur(5px);
        }
        .success {
            background: rgba(212, 237, 218, 0.9);
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: rgba(248, 215, 218, 0.9);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Formular pre nahratie suboru */
        .form-group {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.15);
            margin: 25px 0;
            border: 1px solid rgba(255,255,255,0.6);
        }

        input[type="file"] {
            padding: 10px 12px;
            border: 2px solid #e0d6f0;
            border-radius: 30px;
            font-size: 14px;
            background: white;
            transition: border-color 0.2s;
            width: 300px;
        }
        input[type="file"]:focus {
            border-color: #8b5cf6;
            outline: none;
        }

        button {
            padding: 10px 24px;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            transition: background 0.2s, transform 0.1s;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        button[type="submit"]:not(.btn-danger) {
            background: #8b5cf6;
            color: white;
        }
        button[type="submit"]:not(.btn-danger):hover {
            background: #7c3aed;
            transform: scale(1.02);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
            transform: scale(1.02);
        }

        /* Checkbox pre potvrdenie vymazania */
        label {
            font-weight: 500;
            color: #2d1b4e;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 10px 0;
        }
        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #8b5cf6;
        }

        /* Kontajner pre tabulku s moznostou horizontalneho scrollovania */
        .table-container {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.15);
            margin: 25px 0;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px; /* vynutime horizontalny scroll na malych obrazovkach */
        }

        th {
            background: #7c3aed;
            color: white;
            padding: 14px 12px;
            text-align: left;
            font-weight: 600;
        }

        /* Zaoblenie hornych rohov hlavicky */
        th:first-child {
            border-top-left-radius: 20px;
        }
        th:last-child {
            border-top-right-radius: 20px;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e0d6f0;
            color: #2d1b4e;
        }

        tr:hover {
            background: #f3e8ff;
        }

        /* Stylovanie prvkov DataTables */
        .dataTables_wrapper .dataTables_length {
            margin-bottom: 15px;
            text-align: left;
        }

        .dataTables_wrapper .dataTables_length label {
            font-weight: 500;
            color: #2d1b4e;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.9);
            padding: 8px 14px;
            border-radius: 30px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        }

        .dataTables_wrapper .dataTables_length select {
            padding: 8px 12px;
            border: 2px solid #e0d6f0;
            border-radius: 20px;
            background: white;
            color: #2d1b4e;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .dataTables_wrapper .dataTables_length select:focus {
            border-color: #8b5cf6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
        }

        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 15px;
            text-align: right;
        }

        .dataTables_wrapper .dataTables_filter label {
            font-weight: 500;
            color: #2d1b4e;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .dataTables_wrapper .dataTables_filter input[type="search"] {
            padding: 10px 15px;
            border: 2px solid #e0d6f0;
            border-radius: 30px;
            font-size: 14px;
            width: 250px;
            background: white;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .dataTables_wrapper .dataTables_filter input[type="search"]:focus {
            border-color: #8b5cf6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
        }

        /* Pridanie ikonky lupy k vyhladavaciemu polu */
        .dataTables_wrapper .dataTables_filter label:before {
            font-family: "Font Awesome 6 Free";
            content: "\f002";
            font-weight: 900;
            color: #8b5cf6;
            margin-right: 5px;
        }

        .dataTables_wrapper .dataTables_paginate {
            margin-top: 20px;
            text-align: center;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            background: #ede9fe !important;
            color: #5b21b6 !important;
            border: none !important;
            border-radius: 20px !important;
            padding: 6px 14px !important;
            margin: 0 4px !important;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #c4b5fd !important;
            color: #2d1b4e !important;
            transform: scale(1.05);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #8b5cf6 !important;
            color: white !important;
            font-weight: 600;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.next,
        .dataTables_wrapper .dataTables_paginate .paginate_button.previous {
            background: #ddd6fe !important;
            font-weight: 600;
        }

        .dataTables_wrapper .dataTables_info {
            padding-top: 15px;
            color: #2d1b4e;
            font-size: 0.95rem;
            font-weight: 500;
        }

        /* Blok pre neprihlaseneho pouzivatela */
        .auth-required {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px 25px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.6);
            text-align: center;
            color: #2d1b4e;
        }

        .auth-required i {
            font-size: 3rem;
            color: #8b5cf6;
            margin-bottom: 15px;
            display: block;
        }

        .auth-required p {
            font-size: 1.1rem;
            line-height: 1.5;
            margin-bottom: 10px;
        }

        .auth-required a {
            color: #8b5cf6;
            text-decoration: none;
            font-weight: 600;
            padding: 0 3px;
            transition: color 0.2s;
        }

        .auth-required a:hover {
            color: #7c3aed;
            text-decoration: underline;
        }

        /* Responzivne upravy */
        @media (max-width: 768px) {
            body { padding: 0 15px; }
            h1 { font-size: 2rem; }
            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter {
                float: none;
                text-align: center;
            }
            .dataTables_wrapper .dataTables_length label,
            .dataTables_wrapper .dataTables_filter label {
                justify-content: center;
                width: 100%;
            }
            .dataTables_wrapper .dataTables_filter input[type="search"] {
                width: 100%;
                max-width: 280px;
            }
        }

        @media (max-width: 480px) {
            h1 { font-size: 1.7rem; }
            .btn { width: 100%; }
        }
    </style>
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