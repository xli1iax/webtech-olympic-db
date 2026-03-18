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
    
    ;
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
<style>
    /* ----- VŠEOBECNÉ STYLY (fialová téma) ----- */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Roboto, system-ui, sans-serif;
        background: #f3e8ff;
        max-width: 1400px;
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

    /* Stav a uvítanie */
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

    /* ----- FILTRE ----- */
    .filters-container {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(5px);
        border-radius: 20px;
        padding: 25px;
        box-shadow: 0 10px 30px rgba(139, 92, 246, 0.15);
        margin: 25px 0;
        border: 1px solid rgba(255,255,255,0.6);
    }

    .filters-form {
        display: flex;
        gap: 20px;
        align-items: flex-end;
        flex-wrap: wrap;
    }

    .filter-group {
        flex: 1;
        min-width: 200px;
    }

    .filter-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #2d1b4e;
    }

    .filter-group select {
        width: 100%;
        padding: 10px 12px;
        border: 2px solid #e0d6f0;
        border-radius: 30px;
        font-size: 14px;
        background: white;
        transition: border-color 0.2s;
    }

    .filter-group select:focus {
        border-color: #8b5cf6;
        outline: none;
    }

    .filter-actions {
        display: flex;
        gap: 12px;
    }

    .btn {
        padding: 10px 24px;
        border: none;
        border-radius: 30px;
        cursor: pointer;
        font-size: 15px;
        font-weight: 500;
        transition: background 0.2s, transform 0.1s;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .btn-primary {
        background: #8b5cf6;
        color: white;
    }

    .btn-primary:hover {
        background: #7c3aed;
        transform: scale(1.02);
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .btn-secondary:hover {
        background: #5a6268;
        transform: scale(1.02);
    }

    /* ----- TABULKA ----- */
    /* Stylovanie ovladacich prvkov DataTables */
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
        box-shadow: 0 7px 10px rgba(0, 0, 1, 0.08);
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

    .dataTables_wrapper .dataTables_length select:hover {
        border-color: #c4b5fd;
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

    /* Pridanie ikony lupy */
    .dataTables_wrapper .dataTables_filter label:before {
        font-family: "Font Awesome 6 Free";
        content: "\f002";
        font-weight: 900;
        color: #8b5cf6;
        margin-right: 5px;
    }

    .olympians-table-container {
        background: white;
        border-radius: 20px;
        padding: 20px;
        box-shadow: 0 10px 30px rgba(139, 92, 246, 0.15);
        margin: 25px 0;
        overflow-x: auto;  /* Horizontalny scroll pre male obrazovky */
    }

    #olympiansTable th {
        background: #7c3aed;
        color: white;
        padding: 14px 12px;
        text-align: left;
        font-weight: 600;
        cursor: pointer;
        user-select: none;
        border: none;
    }

    #olympiansTable th:first-child {
        border-top-left-radius: 20px;
    }

    #olympiansTable th:last-child {
        border-top-right-radius: 20px;
    }

    /* Indikatory triedenia */
    #olympiansTable th.sortable::after {
        content: ' ↕';
        font-size: 12px;
        opacity: 0.7;
        margin-left: 5px;
        color: white;
    }
    #olympiansTable th.sort-asc::after {
        content: ' ↑';
    }
    #olympiansTable th.sort-desc::after {
        content: ' ↓';
    }

    /* Stranovanie DataTables */
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

    .athlete-link {
        color: inherit;
        text-decoration: none;
        border-bottom: 1px dotted transparent;
        transition: border-color 0.2s, color 0.2s;
    }

    .athlete-link:hover {
        border-bottom-color: #8b5cf6;
        color: #8b5cf6;
    }

    /* Medailove triedy */
    .medal-gold { background-color: #ffd700; font-weight: bold; text-align: center; border-radius: 20px; }
    .medal-silver { background-color: #c0c0c0; font-weight: bold; text-align: center; border-radius: 20px; }
    .medal-bronze { background-color: #cd7f32; color: white; font-weight: bold; text-align: center; border-radius: 20px; }

    /* ----- ADAPTACIA ----- */
    @media (max-width: 768px) {
        body { padding: 0 15px; }
        h1 { font-size: 2rem; }
        .filters-form { flex-direction: column; align-items: stretch; }
        .filter-actions { justify-content: center; }

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

    <script>
    // Cookies banner - kontrola ci uz bolo potvrdene
    document.addEventListener('DOMContentLoaded', function() {
        if (!localStorage.getItem('cookiesAccepted')) {
            document.getElementById('cookies-banner').style.display = 'block';
        }

        document.getElementById('cookies-accept').addEventListener('click', function() {
            localStorage.setItem('cookiesAccepted', 'true');
            document.getElementById('cookies-banner').style.display = 'none';
        });
    });
    </script>

    <script>
    // Inicializacia DataTables a vlastne triedenie
    document.addEventListener('DOMContentLoaded', function() {
        const table = document.getElementById('olympiansTable');
        if (!table) return;

        const headers = table.querySelectorAll('th.sortable');

        // Inicializacia DataTables s nastaveniami
        const dataTable = $('#olympiansTable').DataTable({
            paging: true,
            pageLength: 10,
            lengthMenu: [[10, 20, 50, -1], [10, 20, 50, 'Všetky']],
            ordering: false, // Vypneme zabudovane triedenie, budeme triedit vlastne
            info: true,
            searching: true,
            language: {
                "sEmptyTable":     "Žiadne dáta k dispozícii",
                "sInfo":           "Zobrazujem _START_ až _END_ z celkových _TOTAL_ záznamov",
                "sInfoEmpty":      "Zobrazujem 0 až 0 z 0 záznamov",
                "sInfoFiltered":   "(filtrované z _MAX_ celkových záznamov)",
                "sInfoPostFix":    "",
                "sInfoThousands":  " ",
                "sLengthMenu":     "Zobraziť _MENU_ záznamov",
                "sLoadingRecords": "Načítavam...",
                "sProcessing":     "Spracúvam...",
                "sSearch":         "Hľadať:",
                "sZeroRecords":    "Nenašli sa žiadne zodpovedajúce záznamy",
                "oPaginate": {
                    "sFirst":      "Prvá",
                    "sLast":       "Posledná",
                    "sNext":       "Nasledujúca",
                    "sPrevious":   "Predchádzajúca"
                },
                "oAria": {
                    "sSortAscending":  ": aktivujte pre zoradenie vzostupne",
                    "sSortDescending": ": aktivujte pre zoradenie zostupne"
                }
            }
        });

        // Ulozenie povodneho poradia (z databazy)
        const originalRows = dataTable.rows().nodes().toArray().slice();
        console.log('DataTables rows count:', dataTable.rows().count());

        let sortState = {
            column: null,
            direction: null
        };

        // Pridanie event listenerov na hlavicky pre vlastne triedenie
        headers.forEach(header => {
            header.addEventListener('click', function() {
                const column = this.dataset.sort;
                let direction;

                if (sortState.column !== column) {
                    direction = (column === 'rok') ? 'desc' : 'asc';
                } else {
                    if (column === 'rok') {
                        // rok: desc -> asc -> reset
                        if (sortState.direction === 'desc') direction = 'asc';
                        else if (sortState.direction === 'asc') direction = 'reset';
                        else direction = 'desc';
                    } else {
                        // text: asc -> desc -> reset
                        if (sortState.direction === 'asc') direction = 'desc';
                        else if (sortState.direction === 'desc') direction = 'reset';
                        else direction = 'asc';
                    }
                }

                sortTable(column, direction);
            });
        });

        // Funkcia na triedenie tabulky
        function sortTable(column, direction) {
            headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));

            if (direction === 'reset') {
                // Vratime povodne poradie
                dataTable.clear();
                dataTable.rows.add(originalRows);
                dataTable.draw(false);

                sortState = { column: null, direction: null };
                return;
            }

            // Ziskame vsetky riadky z DataTables (nie len viditelne)
            const currentRows = dataTable.rows().nodes().toArray();

            currentRows.sort((a, b) => {
                let aVal, bVal;

                if (column === 'name') {
                    aVal = a.querySelector('.mena-cell')?.textContent.trim() || '';
                    bVal = b.querySelector('.mena-cell')?.textContent.trim() || '';
                } else if (column === 'rok') {
                    aVal = a.querySelector('.rok-cell')?.textContent.trim() || '0';
                    bVal = b.querySelector('.rok-cell')?.textContent.trim() || '0';
                } else if (column === 'kategoria') {
                    aVal = a.querySelector('.kategoria-cell')?.textContent.trim() || '';
                    bVal = b.querySelector('.kategoria-cell')?.textContent.trim() || '';
                } else {
                    aVal = '';
                    bVal = '';
                }

                if (column === 'rok') {
                    const aNum = parseInt(aVal, 10) || 0;
                    const bNum = parseInt(bVal, 10) || 0;
                    return direction === 'asc' ? aNum - bNum : bNum - aNum;
                } else {
                    return direction === 'asc'
                        ? aVal.localeCompare(bVal, 'sk')
                        : bVal.localeCompare(aVal, 'sk');
                }
            });

            dataTable.clear();
            dataTable.rows.add(currentRows);
            dataTable.draw(false);

            const activeHeader = Array.from(headers).find(h => h.dataset.sort === column);
            if (activeHeader) {
                activeHeader.classList.add(direction === 'asc' ? 'sort-asc' : 'sort-desc');
            }

            sortState = { column, direction };
        }
    });
    </script>

    <?php
    // Ladici vypis poctu riadkov (neviditelny pre bezneho uzivatela)
    if ($conn) {
        $allRows = getOlympiansWithAwards($conn);
        echo "<!-- DEBUG: Celkový počet riadkov v dopyte: " . count($allRows) . " -->";
    }
    ?>
</body>
</html>