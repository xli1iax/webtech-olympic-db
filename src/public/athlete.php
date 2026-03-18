<?php
// Nacitame konfiguracne udaje a navigacne menu
require_once __DIR__ . '/../config.php';
require_once '../navigation.php';

// Ziskame ID sportovca z URL parametra 'id'
$athleteId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// Ak je ID neplatne (nula alebo zaporne), presmerujeme na zoznam
if ($athleteId <= 0) {
    header('Location: index.php');
    exit;
}

/**
 * Funkcia ziska detailne informacie o sportovcovi z databazy podla ID.
 * @param PDO $pdo Objekt PDO pre pripojenie.
 * @param int $id ID sportovca.
 * @return array|null Asociativne pole s udajmi alebo null, ak neexistuje.
 */
function getAthleteDetails(PDO $pdo, int $id): ?array {
    $sql = "
        SELECT 
            a.id,
            a.first_name,
            a.last_name,
            a.birth_date,
            a.birth_place,
            bc.name as birth_country,
            a.death_date,
            a.death_place,
            dc.name as death_country
        FROM athletes a
        LEFT JOIN countries bc ON a.birth_country_id = bc.id
        LEFT JOIN countries dc ON a.death_country_id = dc.id
        WHERE a.id = :id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

/**
 * Funkcia ziska vsetky medaily a umiestnenia sportovca z databazy.
 * @param PDO $pdo Objekt PDO.
 * @param int $id ID sportovca.
 * @return array Pole zaznamov o medailach (prazdne pole, ak nema ziadne).
 */
function getAthleteMedals(PDO $pdo, int $id): array {
    $sql = "
        SELECT 
            g.year,
            g.city,
            g.type as games_type,
            c.name as games_country,
            d.name as discipline,
            d.category,
            mt.placing,
            mt.name as medal
        FROM athlete_medals am
        JOIN olympic_games g ON am.olympic_games_id = g.id
        JOIN countries c ON g.country_id = c.id
        JOIN disciplines d ON am.discipline_id = d.id
        JOIN medal_types mt ON am.medal_type_id = mt.id
        WHERE am.athlete_id = :id
        ORDER BY g.year DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Hlavny blok skriptu - pripojenie k databaze a ziskanie dat
try {
    $conn = connectDatabase($hostname, $database, $username, $password);
    $athlete = getAthleteDetails($conn, $athleteId);
    // Ak sportovec neexistuje, presmerujeme
    if (!$athlete) {
        header('Location: index.php');
        exit;
    }
    $medals = getAthleteMedals($conn, $athleteId);
} catch (Throwable $e) {
    die('Chyba pripojenia k databáze: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?> – detail</title>
    <!-- Font Awesome pre ikony (volitelne, ale pekne) -->
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
            background: #f3e8ff;  /* svetlá levanduľová */
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            padding: 0 20px;
            margin: 20px auto;
        }

        .main-navigation {
            width: 100%;
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
        }
        /* Tlačidlo späť */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 20px 0;
            background: #8b5cf6;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 500;
            transition: background 0.2s, transform 0.2s;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .back-link:hover {
            background: #7c3aed;
            transform: scale(1.02);
        }
        .back-link i {
            font-size: 1rem;
        }

        /* Hlavný nadpis */
        h1 {
            color: #2d1b4e;
            font-size: 2.5rem;
            margin-bottom: 20px;
            font-weight: 600;
            border-bottom: 3px solid #8b5cf6;
            padding-bottom: 10px;
        }

        /* Informačný box */
        .info-box {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.15);
            padding: 25px;
            margin: 25px 0;
            border: 1px solid rgba(255,255,255,0.6);
        }
        .info-box h3 {
            color: #2d1b4e;
            margin-bottom: 15px;
            font-size: 1.5rem;
            font-weight: 600;
        }
        .info-box p {
            margin: 8px 0;
            color: #2d1b4e;
            font-size: 1.1rem;
        }
        .info-box strong {
            color: #8b5cf6;
            font-weight: 600;
            min-width: 140px;
            display: inline-block;
        }

        /* Tabuľka medailí */
        h2 {
            color: #2d1b4e;
            margin: 30px 0 20px;
            font-size: 2rem;
            font-weight: 600;
        }

        .table-wrapper {
            overflow-x: auto;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.15);
            margin: 20px 0;
            background: white;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            min-width: 800px;  /* zaručí scroll na malých obrazovkach */
        }

        th {
            background: #8b5cf6;
            color: white;
            padding: 14px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 1rem;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e0d6f0;
            color: #2d1b4e;
        }

        tr:hover {
            background: #f3e8ff;
        }

        .medal-gold {
            background-color: #ffd700;
            font-weight: bold;
            text-align: center;
            border-radius: 20px;
        }
        .medal-silver {
            background-color: #c0c0c0;
            font-weight: bold;
            text-align: center;
            border-radius: 20px;
        }
        .medal-bronze {
            background-color: #cd7f32;
            color: white;
            font-weight: bold;
            text-align: center;
            border-radius: 20px;
        }

        /* Responzívne úpravy */
        @media (max-width: 768px) {
            h1 {
                font-size: 2rem;
            }
            h2 {
                font-size: 1.6rem;
            }
            .info-box p {
                font-size: 1rem;
            }
            .info-box strong {
                min-width: 120px;
            }
            .back-link {
                padding: 8px 16px;
                font-size: 0.95rem;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 15px;
            }
            h1 {
                font-size: 1.7rem;
            }
            .info-box {
                padding: 20px;
            }
            .info-box strong {
                display: block;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Odkaz na navrat na hlavny zoznam -->
        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Späť na zoznam olympionikov
        </a>

        <!-- Meno sportovca ako nadpis -->
        <h1><?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?></h1>

        <!-- Box s osobnymi udajmi -->
        <div class="info-box">
            <h3>Osobné údaje</h3>
            <p><strong>Meno:</strong> <?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?></p>
            <p><strong>Dátum narodenia:</strong> <?php echo htmlspecialchars($athlete['birth_date'] ?? '—'); ?></p>
            <p><strong>Miesto narodenia:</strong> <?php echo htmlspecialchars($athlete['birth_place'] ?? '—'); ?>, <?php echo htmlspecialchars($athlete['birth_country'] ?? '—'); ?></p>
            <?php if (!empty($athlete['death_date'])): ?>
            <p><strong>Dátum úmrtia:</strong> <?php echo htmlspecialchars($athlete['death_date']); ?></p>
            <p><strong>Miesto úmrtia:</strong> <?php echo htmlspecialchars($athlete['death_place'] ?? '—'); ?>, <?php echo htmlspecialchars($athlete['death_country'] ?? '—'); ?></p>
            <?php endif; ?>
        </div>

        <!-- Nadpis pre medailovu tabulku -->
        <h2>Medaily a umiestnenia</h2>
        <?php if (empty($medals)): ?>
            <p style="color: #2d1b4e;">Tento olympionik nemá žiadne zaznamenané medaily.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Rok</th>
                            <th>Miesto</th>
                            <th>Typ OH</th>
                            <th>Krajina</th>
                            <th>Disciplína</th>
                            <th>Kategória</th>
                            <th>Umiestnenie</th>
                            <th>Medaila</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($medals as $m): 
                            // Nastavenie triedy pre medailu podla umiestnenia
                            $medalClass = '';
                            if ($m['placing'] == 1) $medalClass = 'medal-gold';
                            elseif ($m['placing'] == 2) $medalClass = 'medal-silver';
                            elseif ($m['placing'] == 3) $medalClass = 'medal-bronze';
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($m['year']); ?></td>
                            <td><?php echo htmlspecialchars($m['city']); ?></td>
                            <td><?php echo htmlspecialchars($m['games_type']); ?></td>
                            <td><?php echo htmlspecialchars($m['games_country']); ?></td>
                            <td><?php echo htmlspecialchars($m['discipline']); ?></td>
                            <td><?php echo htmlspecialchars($m['category'] ?? '—'); ?></td>
                            <td><?php echo $m['placing']; ?>. miesto</td>
                            <td class="<?php echo $medalClass; ?>"><?php echo htmlspecialchars($m['medal']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>