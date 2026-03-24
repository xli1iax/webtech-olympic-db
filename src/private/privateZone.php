<?php
// Zacneme session pre pracu s prihlasenym pouzivatelom
session_start();

// Nacitame navigacne menu
require_once '../navigation.php';

// Zisti, ci je pouzivatel prihlaseny
$isLoggedIn = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;

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
    <?php endif; ?>
    
    <h1>JSON Upload + Databáza</h1>

    <!-- Blok s privitanim prihlaseneho pouzivatela -->
    <div class="welcome">
        <h3>Vitaj <?php echo htmlspecialchars($_SESSION['full_name']); ?></h3>
        <p><a href="restricted.php">Zabezpečená stránka</a></p>
    </div>


    <!-- Formular pre nahratie CSV suboru -->
   <div class="form-group">
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="csv_file" accept=".json" required>
        <button id="downloadAthletes" type="submit">Nahrať a spracovať</button>
    </form>
    <div class="action-links">
        <a href="uploadAthlete.php">
            <i class="fas fa-plus"></i> Pridať jedného olympionika
        </a>
        <a href="editAthlete.php">
            <i class="fas fa-edit"></i> Upraviť olympionika
        </a>
    </div>
</div>
    

    <!-- Formular pre vymazanie vsetkych dat (vyžaduje potvrdenie checkboxom) -->
    <form method="post">
        <label>
            <input type="checkbox" name="confirm_delete" value="yes" required>
            Potvrdzujem vymazanie všetkých údajov (táto akcia je nenávratná).
        </label>
        <button type="submit" name="delete_all" class="btn-danger" id = "deleteAthletes">Vymazať všetky údaje</button>
    </form>
    
        <div class="olympians-table-container">
    <h3>Olympionici a ich ocenenia</h3>
    <!-- Ovládacie prvky pre pagináciu a vyhľadávanie -->

    <table id="olympiansTable">
        <thead>
            <tr>
                <th >Meno a priezvisko</th>
                <th  >Umiestnenie</th>
                <th>Šport</th>
                <th>Kategoria</th>
                <th>Den narodenia</th>
                <th>Miesto narodenia</th>
            
                <th>Krajina narodenia</th>
                <th>death_day</th>
                <th>death_place</th>
                <th>death_country</th>
                <th>oh_type</th>
                <th>oh_year</th>
                <th>oh_city</th>
                <th>oh_country</th>
                
            </tr>
        </thead>
        <tbody id="table-body">
            <!-- sem sa budú dynamicky vkladať riadky -->
        </tbody>
    </table>
    <!-- tu neskôr môžeš pridať vlastnú pagináciu, ak chceš -->
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
   <script src = 'js/privateZone.js'></script>
</body>
</html>