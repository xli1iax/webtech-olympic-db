<?php
require_once '../navigation.php';
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?> – detail</title>
    <!-- Font Awesome pre ikony (volitelne, ale pekne) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/athlete.css">
</head>
<body>
    <div class="container">
    <a href="index.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Späť na zoznam olympionikov
    </a>

    <h1 id="athlete-name">Načítavam...</h1>

    <div id="personal-info" class="info-box">
        <h3>Osobné údaje</h3>
        <p>Načítavam...</p>
    </div>

    <h2>Medaily a umiestnenia</h2>
    <div id="medals-container">
        <p>Načítavam...</p>
    </div>
</div>

    <script>
        // Получаем id из адресной строки (например, detail.php?id=123)
        const urlParams = new URLSearchParams(window.location.search);
        const athleteId = urlParams.get('id');

        // Если id нет или он не число, показываем ошибку
        if (!athleteId) {
            document.getElementById('athlete-name').innerText = 'Neplatné ID';
            document.getElementById('personal-info').innerHTML = '<p>Chýba identifikátor športovca.</p>';
            document.getElementById('medals-container').innerHTML = '';
        } else {
            // Выполняем запрос к API
            fetch(`/api/olympians/${athleteId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP chyba: ${response.status}`);
                    }
                    return response.json();  // превращаем ответ в объект
                })
                .then(data => {
                    // data содержит всю информацию о спортсмене и его медалях
                    renderAthlete(data);
                })
                .catch(error => {
                    console.error('Chyba pri načítaní:', error);
                    document.getElementById('athlete-name').innerText = 'Chyba';
                    document.getElementById('personal-info').innerHTML = '<p>Nepodarilo sa načítať údaje.</p>';
                });
        }

        function renderAthlete(athlete) {
            // 1. Выводим имя в заголовок
            document.getElementById('athlete-name').innerText = athlete.first_name + ' ' + athlete.last_name;

            // 2. Личные данные
            let personalHtml = `
                <h3>Osobné údaje</h3>
                <p><strong>Meno:</strong> ${escapeHtml(athlete.first_name)} ${escapeHtml(athlete.last_name)}</p>
                <p><strong>Dátum narodenia:</strong> ${escapeHtml(athlete.birth_date || '—')}</p>
                <p><strong>Miesto narodenia:</strong> ${escapeHtml(athlete.birth_place || '—')}, ${escapeHtml(athlete.birth_country || '—')}</p>
            `;
            if (athlete.death_date) {
                personalHtml += `
                    <p><strong>Dátum úmrtia:</strong> ${escapeHtml(athlete.death_date)}</p>
                    <p><strong>Miesto úmrtia:</strong> ${escapeHtml(athlete.death_place || '—')}, ${escapeHtml(athlete.death_country || '—')}</p>
                `;
            }
            document.getElementById('personal-info').innerHTML = personalHtml;

            // 3. Медали
            const medals = athlete.medals || [];
            if (medals.length === 0) {
                document.getElementById('medals-container').innerHTML = '<p>Tento olympionik nemá žiadne zaznamenané medaily.</p>';
                return;
            }

            let tableHtml = `
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Rok</th>
                                <th>Miesto</th>
                                <th>Typ OH</th>
                                <th>Disciplína</th>
                                <th>Umiestnenie</th>
                                <th>Medaila</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            medals.forEach(medal => {
                let medalClass = '';
                if (medal.placing == 1) medalClass = 'medal-gold';
                else if (medal.placing == 2) medalClass = 'medal-silver';
                else if (medal.placing == 3) medalClass = 'medal-bronze';

                tableHtml += `
                    <tr>
                        <td>${escapeHtml(medal.year)}</td>
                        <td>${escapeHtml(medal.city || '—')}</td>
                        <td>${escapeHtml(medal.olympic_type || '—')}</td>
                        <td>${escapeHtml(medal.discipline || '—')}</td>
                        <td>${medal.placing}. miesto</td>
                        <td class="${medalClass}">${escapeHtml(medal.medal || '—')}</td>
                    </tr>
                `;
            });
            tableHtml += '</tbody></table></div>';
            document.getElementById('medals-container').innerHTML = tableHtml;
        }

        // Функция для безопасного вывода текста (чтобы не сломать HTML)
        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
    </script>
</body>
</html>