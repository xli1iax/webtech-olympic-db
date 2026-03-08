<?php
session_start(); 

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}

?>

<!doctype html>
<html lang="sk">

    <!-- Zvysok HTML template -->

    <main>
        <h3>Vitaj <?php echo $_SESSION['full_name'] ?></h3>
        <p><strong>e-mail:</strong> <?php echo $_SESSION['email']; ?></p>
        
        <p><strong>Si prihlásený cez lokálne údaje.</strong></p>
        <p><strong>Dátum vytvonia konta:</strong> <?php echo $_SESSION['created_at'] ?></p>

        <p><a href="logout.php">Odhlásenie</a> alebo <a href="index.php">Úvodná stránka</a></p>

    </main>
</body>

</html>