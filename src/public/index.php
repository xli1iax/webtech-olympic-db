<?php
// Nacitame konfiguracne udaje a navigacne menu
require_once __DIR__ . '/../config.php';
require_once '../navigation.php';

// ================== FUNKCIA NA ZISKANIE OLYMPIONIKOV ==================
/**
 * Funkcia ziska vsetkych olympionikov a ich ocenenia z databazy.
 * @param PDO $pdo Objekt PDO pre pripojenie.
 * @return array Pole zaznamov (kazdy zaznam = jeden riadok vysledku).
 */
function getOlympiansWithAwards(PDO $pdo): array {
    $sql = "
        SELECT 
            a.id,
            a.first_name,
            a.last_name,
            g.year as rok,
            g.city as mesto,
            g.type as typ_oh,
            c_games.name as reprezentovana_krajina,
            d.name as sport,
            d.category as kategoria,
            mt.placing as umiestnenie,
            mt.name as medaila
        FROM athletes a
        LEFT JOIN athlete_medals am ON a.id = am.athlete_id
        LEFT JOIN olympic_games g ON am.olympic_games_id = g.id
        LEFT JOIN disciplines d ON am.discipline_id = d.id
        LEFT JOIN medal_types mt ON am.medal_type_id = mt.id
        LEFT JOIN countries c_games ON g.country_id = c_games.id
        ORDER BY g.year DESC, a.last_name ASC, a.first_name ASC
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result ?: []; // Ak je prazdne, vratime prazdne pole
    } catch (PDOException $e) {
        error_log("Chyba v SQL: " . $e->getMessage());
        return [];
    }
}

// ================== FUNKCIA NA ZISKANIE FILTROV ==================
/**
 * Ziska zoznam unikatnych rokov, v ktorych sa konali olympijske hry.
 * @param PDO $pdo Objekt PDO.
 * @return array Pole rokov (ako retazce).
 */
function getUniqueRoky(PDO $pdo): array {
    try {
        $stmt = $pdo->query("SELECT DISTINCT year FROM olympic_games ORDER BY year DESC");
        $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $result ?: [];
    } catch (PDOException $e) {
        error_log("Chyba pri načítaní rokov: " . $e->getMessage());
        return [];
    }
}

/**
 * Ziska zoznam unikatnych kategorii sportov (napr. box, plavanie).
 * @param PDO $pdo Objekt PDO.
 * @return array Pole kategorii.
 */
function getUniqueKategorie(PDO $pdo): array {
    try {
        $stmt = $pdo->query("SELECT DISTINCT category FROM disciplines WHERE category IS NOT NULL AND category != '' ORDER BY category");
        $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $result ?: [];
    } catch (PDOException $e) {
        error_log("Chyba pri načítaní kategórií: " . $e->getMessage());
        return [];
    }
}

// ================== FUNKCIA NA ZOBRAZENIE TABULKY ==================
/**
 * Vygeneruje HTML tabulku s olympionikmi a oceneniami.
 * @param PDO $pdo Objekt PDO.
 * @param array $filtre Pole filtrov (rok, kategoria).
 * @return string HTML kod tabulky.
 */
function displayOlympiansTable(PDO $pdo, array $filtre): string {
    $olympians = getOlympiansWithAwards($pdo);
    
    // Aplikacia filtrov
    $filteredOlympians = array_filter($olympians, function($o) use ($filtre) {
        if (!empty($filtre['rok']) && ($o['rok'] ?? '') != $filtre['rok']) {
            return false;
        }
        if (!empty($filtre['kategoria']) && ($o['kategoria'] ?? '') != $filtre['kategoria']) {
            return false;
        }
        return true;
    });
    
    if (empty($filteredOlympians)) {
        return '<div class="info-message">Žiadni olympionici neboli nájdení pre zvolené filtre.</div>';
    }
    
    // Zistime, ci mame zobrazit stlpce Rok a Kategoria (zobrazia sa len ak nie je aktivny filter)
    $zobrazitRok = empty($filtre['rok']);
    $zobrazitKategoriu = empty($filtre['kategoria']);
    
    $html = '
    <div class="olympians-table-container">
        <h3>Olympionici a ich ocenenia</h3>
        <table class="display" id="olympiansTable">
            <thead>
                <tr>
                    <th class="sortable" data-sort="name">Meno a priezvisko</th>';
    
    if ($zobrazitRok) {
        $html .= '<th class="sortable" data-sort="rok">Rok</th>';
    }
    
    $html .= '<th>Miesto konania</th>
              <th>Typ OH</th>
              <th>Reprezentovaná krajina</th>
              <th>Šport</th>';
    
    if ($zobrazitKategoriu) {
        $html .= '<th class="sortable" data-sort="kategoria">Kategória</th>';
    }
    
    $html .= '<th>Umiestnenie</th>
              <th>Medaila</th>
          </tr>
      </thead>
      <tbody>';
    
    foreach ($filteredOlympians as $o) {
        // Nastavenie triedy pre medailu podla umiestnenia
        $medalClass = '';
        if (!empty($o['medaila'])) {
            $medailaLower = strtolower($o['medaila']);
            if (strpos($medailaLower, 'zlato') !== false || $o['umiestnenie'] == 1) {
                $medalClass = 'medal-gold';
            } elseif (strpos($medailaLower, 'striebro') !== false || $o['umiestnenie'] == 2) {
                $medalClass = 'medal-silver';
            } elseif (strpos($medailaLower, 'bronz') !== false || $o['umiestnenie'] == 3) {
                $medalClass = 'medal-bronze';
            }
        }
        
        $html .= '<tr>';
        // Odkaz na detail sportovca
        $html .= '<td class="mena-cell"><strong><a href="athlete.php?id=' . $o['id'] . '" class="athlete-link">' . htmlspecialchars($o['first_name'] ?? '') . ' ' . htmlspecialchars($o['last_name'] ?? '') . '</a></strong></td>';
        
        if ($zobrazitRok) {
            $html .= '<td class="rok-cell">' . htmlspecialchars($o['rok'] ?? '-') . '</td>';
        }
        
        $html .= '<td>' . htmlspecialchars($o['mesto'] ?? '-') . '</td>';
        $html .= '<td>' . htmlspecialchars($o['typ_oh'] ?? '-') . '</td>';
        $html .= '<td>' . htmlspecialchars($o['reprezentovana_krajina'] ?? 'Neznáma') . '</td>';
        $html .= '<td>' . htmlspecialchars($o['sport'] ?? '-') . '</td>';
        
        if ($zobrazitKategoriu) {
            $html .= '<td class="kategoria-cell">' . htmlspecialchars($o['kategoria'] ?? '-') . '</td>';
        }
        
        $html .= '<td>' . (!empty($o['umiestnenie']) ? $o['umiestnenie'] . '. miesto' : '-') . '</td>';
        $html .= '<td class="' . $medalClass . '">' . htmlspecialchars($o['medaila'] ?? '-') . '</td>';
        $html .= '</tr>';
    }
    
    $html .= '
            </tbody>
        </table>
    </div>';
    
    return $html;
}

// ================== HLAVNY KOD ==================
$dbMessage = '';
$olympiansTableHtml = '';
$conn = null;
$selectedRok = $_GET['rok'] ?? '';        // Ziskanie filtra z URL
$selectedKategoria = $_GET['kategoria'] ?? '';

try {
    $conn = connectDatabase($hostname, $database, $username, $password);
    $dbMessage = $conn ? '✓ Pripojené k databáze' : '✗ Nepripojené k databáze';
    
    if ($conn) {
        $uniqueRoky = getUniqueRoky($conn);
        $uniqueKategorie = getUniqueKategorie($conn);
        
        $filtre = [
            'rok' => $selectedRok,
            'kategoria' => $selectedKategoria
        ];
        
        $olympiansTableHtml = displayOlympiansTable($conn, $filtre);
    }
    
} catch (Throwable $e) {
    $dbMessage = 'Chyba: ' . $e->getMessage();
}
?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Olympijská databáza - Prehľad olympionikov</title>
<!-- Kniznica DataTables pre pekne tabulky -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<!-- Font Awesome pre ikony -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link rel="stylesheet" href="css/index.css">
</head>
<body>
    <h1>Olympijská databáza - Prehľad olympionikov</h1>
    
    <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
    <!-- Vitajte pre prihlaseneho pouzivatela -->
    <div class="welcome">
        <h3>Vitaj <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'používateľ'); ?></h3>
        <p><a href="/private/restricted.php">Zabezpečená stránka</a></p>
    </div>
    <?php endif; ?>
    
    <!-- Stav pripojenia k databaze -->
    <div class="status <?php echo $conn ? 'ok' : 'err'; ?>">
        <?php echo $dbMessage; ?>
    </div>
    
    <?php if ($conn): ?>
        <!-- Formular pre filtre -->
        <div class="filters-container">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label for="rok">Filter podľa roku:</label>
                    <select name="rok" id="rok">
                        <option value="">Všetky roky</option>
                        <?php foreach ($uniqueRoky as $rok): ?>
                            <option value="<?php echo htmlspecialchars($rok); ?>" <?php echo $selectedRok == $rok ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($rok); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="kategoria">Filter podľa kategórie:</label>
                    <select name="kategoria" id="kategoria">
                        <option value="">Všetky kategórie</option>
                        <?php foreach ($uniqueKategorie as $kategoria): ?>
                            <option value="<?php echo htmlspecialchars($kategoria); ?>" <?php echo $selectedKategoria == $kategoria ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($kategoria); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Filtrovať</button>
                    <a href="?" class="btn btn-secondary">Zrušiť filtre</a>
                </div>
            </form>
        </div>
        
        <!-- Vypis tabulky s olympionikmi -->
        <?php echo $olympiansTableHtml; ?>
        
    <?php else: ?>
        <div class="info-message">
            <strong>Nie je pripojené k databáze.</strong> Skontrolujte konfiguráciu v config.php
        </div>
    <?php endif; ?>

    <!-- Cookies banner (jednoducha implementacia) -->
    <div id="cookies-banner" style="display: none; position: fixed; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.9); color: #fff; padding: 15px; text-align: center; z-index: 9999; font-family: 'Segoe UI', sans-serif;">
        <span style="margin-right: 20px;">Táto webová stránka používa cookies na ukladanie osobných informácií. Pokračovaním súhlasíte s ich používaním.</span>
        <button id="cookies-accept" style="background: #c7b4e7; color: white; border: none; padding: 8px 20px; border-radius: 4px; cursor: pointer; font-size: 14px;">Súhlasím</button>
    </div>

    <script src="js/index.js"></script>
</body>
</html>