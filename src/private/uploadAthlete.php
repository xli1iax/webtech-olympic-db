<?php
session_start();
require_once '../navigation.php';

$isLoggedIn = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Pridať jedného olympionika</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/uploadAthlete.css">
</head>
<body>

<?php if (!$isLoggedIn): ?>
    <div class="auth-required">
        <i class="fas fa-lock"></i>
        <p>Pre pokračovanie sa prosím <a href="login.php">prihláste</a> alebo sa <a href="register.php">zaregistrujte</a>.</p>
    </div>
    <?php exit; ?>
<?php endif; ?>

<h1>Pridanie olympionika</h1>

<div class="welcome">
    <h3>Vitaj <?php echo htmlspecialchars($_SESSION['full_name']); ?></h3>
    <p><a href="restricted.php">Zabezpečená stránka</a></p>
</div>
<div id="success-message" class="success-message-global" style="display: none;">
    <i class="fas fa-check-circle"></i>
    <p>Pridanie olympionika prebehlo úspešne</p>
    <a href="privateZone.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Späť do privátnej zóny
            </a>
</div>
<div class="form-container">
    <form method="POST" action="/api/olympians" id = "uploadForm"> <!-- action podľa vášho API -->
        <div class="form-row">
            <div class="form-field">
                <label for="first_name">Meno *</label>
                <input type="text" id="first_name" name="first_name" required>
                    <div class="error-message-field" id="error-totp"></div>

            </div>
            <div class="form-field">
                <label for="last_name">Priezvisko *</label>
                <input type="text" id="last_name" name="last_name" required>
                    <div class="error-message-field" id="error-totp"></div>

            </div>
        </div>

        <div class="form-row">
            <div class="form-field">
                <label for="birth_date">Dátum narodenia *</label>
                <input type="date" id="birth_date" name="birth_date" required>
                
            </div>
            <div class="form-field">
                <label for="birth_place">Miesto narodenia *</label>
                <input type="text" id="birth_place" name="birth_place" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-field">
                <label for="birth_country">Krajina narodenia *</label>
                <input type="text" id="birth_country" name="birth_country" required>
            </div>
            <div class="form-field">
                <label for="death_date">Dátum úmrtia</label>
                <input type="date" id="death_date" name="death_date">
            </div>
        </div>

        <div class="form-row">
            <div class="form-field">
                <label for="death_place">Miesto úmrtia</label>
                <input type="text" id="death_place" name="death_place">
            </div>
            <div class="form-field">
                <label for="death_country">Krajina úmrtia</label>
                <input type="text" id="death_country" name="death_country">
            </div>
        </div>

        <div class="form-row">
            <div class="form-field">
                <label for="discipline">Disciplína *</label>
                <input type="text" id="discipline" name="discipline" required>
            </div>
            <div class="form-field">
                <label for="category">Kategória</label>
                <input type="text" id="category" name="category">
            </div>
        </div>

        <div class="form-row">
            <div class="form-field">
                <label for="placing">Umiestnenie (1,2,3,...) *</label>
                <input type="number" id="placing" name="placing" min="1" required>
            </div>
            <div class="form-field">
                <label for="oh_year">Rok OH *</label>
                <input type="number" id="oh_year" name="oh_year" min="1896" max="2026" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-field">
                <label for="oh_type">Typ OH *</label>
                <select id="oh_type" name="oh_type" required>
                    <option value="LOH">Letné olympijské hry (LOH)</option>
                    <option value="ZOH">Zimné olympijské hry (ZOH)</option>
                </select>
            </div>
            <div class="form-field">
                <label for="oh_city">Mesto konania OH *</label>
                <input type="text" id="oh_city" name="oh_city" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-field">
                <label for="oh_country">Krajina konania OH *</label>
                <input type="text" id="oh_country" name="oh_country" required>
            </div>
            <div class="form-field"></div> <!-- prázdny pre zarovnanie -->
        </div>

        <div class="form-actions">
            <button type="submit" id="submitBtn">Pridať olympionika</button>
            <a href="privateZone.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Späť do privátnej zóny
            </a>
        </div>
    </form>

    
</div>

<script src="js/uploadAthlete.js"></script>
</body>
</html>