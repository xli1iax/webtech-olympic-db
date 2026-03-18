<?php
session_start();
require_once __DIR__ . '/config.php';

$conn = connectDatabase($hostname, $database, $username, $password);

// ===== SPRACOVANIE FILTROV A ZORADENIA =====
$selectedYear = $_GET['year'] ?? '';
$selectedCategory = $_GET['category'] ?? '';

// Stĺpce, ktoré sa MAJÚ zobraziť (filtrované stĺpce sa nezobrazujú)
$showYear = empty($selectedYear);
$showCategory = empty($selectedCategory);

// ===== ZORADENIE =====
$sortColumn = $_GET['sort'] ?? 'last_name';
$sortOrder = $_GET['order'] ?? 'asc';
$sortCycle = $_GET['cycle'] ?? 1;

// Definícia stĺpcov pre zoradenie
$sortableColumns = ['last_name', 'year', 'category'];
if (!in_array($sortColumn, $sortableColumns)) {
    $sortColumn = 'last_name';
}

// ===== ZOSTAVENIE SQL DOTAZU =====
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

// Pridanie zoradenia
$sql .= " ORDER BY ";
switch ($sortColumn) {
    case 'last_name':
        $sql .= "a.last_name " . ($sortOrder === 'asc' ? 'ASC' : 'DESC');
        break;
    case 'year':
        $sql .= "og.year " . ($sortOrder === 'asc' ? 'ASC' : 'DESC');
        break;
    case 'category':
        $sql .= "d.category " . ($sortOrder === 'asc' ? 'ASC' : 'DESC');
        break;
}

// Vykonanie dotazu
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$olympians = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== ZÍSKANIE ZOZNAMOV PRE FILTRE =====
$years = $conn->query("SELECT DISTINCT year FROM olympic_games ORDER BY year DESC")->fetchAll(PDO::FETCH_COLUMN);
$categories = $conn->query("SELECT DISTINCT category FROM disciplines WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Olympionici</title>
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
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .filter-item {
            flex: 1;
            min-width: 200px;
        }
        .filter-item label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        .filter-item select, .filter-item button {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .filter-item button {
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            transition: background 0.3s;
        }
        .filter-item button:hover {
            background: #0056b3;
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
            position: relative;
            padding-right: 20px;
        }
        th:hover {
            background: #0056b3;
        }
        th.sort-asc::after {
            content: " ▲";
            font-size: 12px;
        }
        th.sort-desc::after {
            content: " ▼";
            font-size: 12px;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .medal {
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
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
        .reset-link {
            display: inline-block;
            margin-top: 10px;
            color: #007bff;
            text-decoration: none;
        }
        .reset-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h1>Olympionici a ich ocenenia</h1>

    <div class="filter-section">
        <div class="filter-item">
            <label for="year">Filter podľa roku:</label>
            <select name="year" id="year" onchange="applyFilter()">
                <option value="">Všetky roky</option>
                <?php foreach ($years as $year): ?>
                    <option value="<?php echo $year; ?>" <?php echo $selectedYear == $year ? 'selected' : ''; ?>>
                        <?php echo $year; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-item">
            <label for="category">Filter podľa kategórie:</label>
            <select name="category" id="category" onchange="applyFilter()">
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

    <?php if (count($olympians) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th onclick="sortTable('first_name')">Meno</th>
                    <th onclick="sortTable('last_name')" class="<?php echo $sortColumn === 'last_name' ? 'sort-' . $sortOrder : ''; ?>">Priezvisko</th>
                    <th>Krajina</th>
                    <?php if ($showYear): ?>
                        <th onclick="sortTable('year')" class="<?php echo $sortColumn === 'year' ? 'sort-' . $sortOrder : ''; ?>">Rok</th>
                    <?php endif; ?>
                    <?php if ($showCategory): ?>
                        <th onclick="sortTable('category')" class="<?php echo $sortColumn === 'category' ? 'sort-' . $sortOrder : ''; ?>">Kategória</th>
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
        </div>
    <?php endif; ?>

    <script>
        function applyFilter() {
            const year = document.getElementById('year').value;
            const category = document.getElementById('category').value;
            
            let url = new URL(window.location.href);
            url.searchParams.set('year', year);
            url.searchParams.set('category', category);
            
            // Zachovaj aktuálne zoradenie
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
            const currentCycle = parseInt(url.searchParams.get('cycle') || '1');
            
            let newOrder = 'asc';
            let newCycle = 2;
            
            if (currentSort === column) {
                if (currentOrder === 'asc') {
                    newOrder = 'desc';
                    newCycle = 3;
                } else if (currentOrder === 'desc') {
                    // Tretí klik - vrátiť do pôvodného (podľa ID)
                    window.location.href = window.location.pathname + '?sort=id&order=asc';
                    return;
                }
            }
            
            url.searchParams.set('sort', column);
            url.searchParams.set('order', newOrder);
            url.searchParams.set('cycle', newCycle);
            
            window.location.href = url.toString();
        }
    </script>
</body>
</html>