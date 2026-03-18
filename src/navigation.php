<?php
// navigation.php – лежит в папке src/
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<nav class="main-navigation">
    <div class="nav-container">
        <div class="nav-logo">
            <a href="/public/index.php">
                <i class="fas fa-medal"></i> Olympijská databáza
            </a>
        </div>
        <!-- Кнопка-бургер для мобільних -->
        <button class="nav-toggle" aria-label="Toggle menu">
            <i class="fas fa-bars"></i>
        </button>
        <ul class="nav-menu">
            <li><a href="/private/privateZone.php"><i class="fas fa-upload"></i> Pridať olympionika</a></li>

            <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
                <li><a href="/private/restricted.php"><i class="fas fa-user-circle"></i> Môj účet</a></li>
                <li><a href="/private/logout.php"><i class="fas fa-sign-out-alt"></i> Odhlásiť sa</a></li>
            <?php else: ?>
                <li><a href="/private/login.php" class="login-btn"><i class="fas fa-sign-in-alt"></i> Prihlásiť sa</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<style>
/* ===== АДАПТИВНА НАВІГАЦІЯ З БУРГЕР-МЕНЮ ===== */
.main-navigation {
    width: 100vw;
    position: relative;
    left: calc(-50vw + 50%);
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    margin-bottom: 30px;
    border-radius: 0 0 10px 10px;
}

.nav-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    min-height: 70px;
    position: relative;
}

.nav-logo a {
    color: white;
    font-size: 1.7rem;
    font-weight: 700;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: opacity 0.2s;
}

.nav-logo a:hover {
    opacity: 0.9;
}

/* Кнопка-бургер – прихована на десктопі */
.nav-toggle {
    display: none;
    background: none;
    border: none;
    color: white;
    font-size: 2rem;
    cursor: pointer;
    padding: 0 10px;
    line-height: 1;
}

.nav-toggle:focus {
    outline: 2px solid rgba(255,255,255,0.5);
}

/* Меню */
.nav-menu {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    gap: 25px;
    align-items: center;
    transition: all 0.3s ease;
}

.nav-menu li a {
    color: white;
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 30px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
    transition: background 0.3s, transform 0.2s;
}

.nav-menu li a:hover {
    background: rgba(255,255,255,0.2);
    transform: scale(1.05);
}

.nav-menu li a.login-btn {
    background: white;
    color: #8b5cf6;
    padding: 10px 20px;
    font-weight: 600;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.nav-menu li a.login-btn:hover {
    background: #f3e8ff;
    transform: scale(1.05);
}

/* ===== МОБІЛЬНА ВЕРСІЯ ===== */
@media (max-width: 700px) {
    .nav-container {
        padding: 10px 20px;
    }

    .nav-toggle {
        display: block; /* показуємо бургер */
    }

    .nav-menu {
        display: none; /* приховуємо меню за замовчуванням */
        width: 100%;
        flex-direction: column;
        gap: 10px;
        padding: 20px 0 10px;
        border-top: 1px solid rgba(255,255,255,0.2);
    }

    .nav-menu.active {
        display: flex; /* показуємо при активному класі */
    }

    .nav-menu li {
        width: 100%;
        text-align: center;
    }

    .nav-menu li a {
        justify-content: center;
        padding: 12px;
        width: 100%;
    }
}
</style>

<script>
// Простий JavaScript для перемикання меню на мобільних
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.querySelector('.nav-toggle');
    const menu = document.querySelector('.nav-menu');
    
    if (toggle && menu) {
        toggle.addEventListener('click', function() {
            menu.classList.toggle('active');
            // Зміна іконки (необов'язково)
            const icon = toggle.querySelector('i');
            if (icon) {
                if (menu.classList.contains('active')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }
        });

        // Закрити меню при кліку на посилання (для зручності)
        menu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                menu.classList.remove('active');
                const icon = toggle.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            });
        });
    }
});
</script>