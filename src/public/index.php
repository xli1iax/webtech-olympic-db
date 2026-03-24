<?php
require_once '../navigation.php';
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Olympijská databáza - Prehľad olympionikov</title>
    <!-- Jednoduché štýly (namiesto DataTables) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <h1>Olympijská databáza - Prehľad olympionikov</h1>
    
    <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
    <div class="welcome">
        <h3>Vitaj <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'používateľ'); ?></h3>
        <p><a href="/private/restricted.php">Zabezpečená stránka</a></p>
    </div>
    <?php endif; ?>
    
    <!-- Formulár pre filtre (bez odosielania formulára) -->
    <div class="filters-container">
        <div class="filters-form">
            <div class="filter-group">
                <label for="rok">Filter podľa roku:</label>
                <select name="rok" id="rok">
                    <option value="">Všetky roky</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="kategoria">Filter podľa kategórie (športu):</label>
                <select name="kategoria" id="kategoria">
                    <option value="">Všetky kategórie</option>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="button" id="filterBtn" class="btn btn-primary">Filtrovať</button>
                <button type="button" id="resetBtn" class="btn btn-secondary">Zrušiť filtre</button>
            </div>
        </div>
    </div>

    <div class="olympians-table-container">
    <h3>Olympionici a ich ocenenia</h3>
    <!-- Ovládacie prvky pre pagináciu a vyhľadávanie -->

    <table id="olympiansTable">
        <thead>
            <tr>
                <th data-sort="name" class="sortable">Meno a priezvisko</th>
                <th data-sort="rok" class="sortable">Rok</th>
                <th>Miesto konania</th>
                <th>Typ OH</th>
                <th>Reprezentovaná krajina</th>
                <th>Šport</th>
                <th data-sort="kategoria" class="sortable">Kategória</th>
                <th>Umiestnenie</th>
                <th>Medaila</th>
            </tr>
        </thead>
        <tbody id="table-body">
            <!-- sem sa budú dynamicky vkladať riadky -->
        </tbody>
    </table>
    <!-- tu neskôr môžeš pridať vlastnú pagináciu, ak chceš -->
</div>

    <!-- Cookies banner -->
    <div id="cookies-banner" style="display: none; position: fixed; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.9); color: #fff; padding: 15px; text-align: center; z-index: 9999;">
        <span style="margin-right: 20px;">Táto webová stránka používa cookies na ukladanie osobných informácií. Pokračovaním súhlasíte s ich používaním.</span>
        <button id="cookies-accept" style="background: #c7b4e7; color: white; border: none; padding: 8px 20px; border-radius: 4px; cursor: pointer;">Súhlasím</button>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="js/index.js"></script>
</body>
</html>
       