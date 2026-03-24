function uploadFile() {
    const input = document.querySelector('input[name="csv_file"]');
    const file = input.files[0];

    if (!file) {
        console.error('Vyberte súbor.');
        return;
    }

    const reader = new FileReader();

    reader.onload = function (e) {
        let rawData;

        try {
            rawData = JSON.parse(e.target.result);
        } catch (err) {
            console.error('Súbor nie je platný JSON.');
            return;
        }

        if (!Array.isArray(rawData)) {
            console.error('JSON musí obsahovať pole olympionikov.');
            return;
        }

        const transformed = rawData.map(item => ({
            name: item.name,
            surname: item.surname,
            birth_day: item.birth_day,
            birth_place: item.birth_place,
            birth_country: item.birth_country,
            death_day: item.death_day,
            death_place: item.death_place || null,
            death_country: item.death_country || null,
            oh_year: parseInt(item.oh_year, 10),
            oh_type: item.oh_type,
            oh_city: item.oh_city,
            oh_country: item.oh_country,
            discipline: item.discipline,
            placing: parseInt(item.placing, 10)
        }));

        console.log('Odosielam JSON:', transformed);

        fetch('/api/olympians/bulk', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(transformed)
        })
        .then(async response => {
            const text = await response.text();
            console.log('STATUS:', response.status);
            console.log('RAW RESPONSE:', text);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${text}`);
            }

            return JSON.parse(text);
        })
        .then(data => {
            console.log(`Nahrané: ${data.success} úspešne, ${data.failed} chýb.`);
            if (data.errors && data.errors.length) {
                console.error('Chyby:', data.errors);
            }
            loadAthletes();
        })
        .catch(err => {
            console.error('Chyba pri nahrávaní súboru:', err);
            console.error('Nepodarilo sa nahrať súbor: ' + err.message);
        });
    };

    reader.readAsText(file);
}

// Удаление всех данных
function deleteAllData() {
    fetch('/api/olympians/bulk', {
    method: 'DELETE',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin'
})
.then(async response => {
    const text = await response.text();

    console.log('STATUS:', response.status);
    console.log('RESPONSE:', text);

    if (!response.ok) {
        throw new Error(text || 'Chyba pri mazaní');
    }

    console.error('Všetky údaje boli vymazané.');
    loadAthletes();
})
.catch(error => {
    console.error('Chyba pri mazaní:', error);
});
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

let dataTable;



// ---------- Загрузка данных из API и инициализация DataTables ----------
function loadAthletes() {
    
    let url = '/api/olympians/bulk';
    

    fetch(url)
        .then(response => {
            if (!response.ok) throw new Error('Network error');
            return response.json();
        })
        .then(data => {
            // Преобразуем данные в формат для DataTables (массив массивов)
            const tableData = data.map(athlete => [
                `<a href="athlete.php?id=${athlete.id}" class="athlete-link">${escapeHtml(athlete.first_name)} ${escapeHtml(athlete.last_name)}</a>`,
                athlete.placing || '',
                athlete.discipline || '',
                athlete.category || '',
                athlete.birth_date || '',
                athlete.birth_place || '',
                athlete.birth_country || '',
                athlete.death_date || '',
                athlete.death_place || '',
                athlete.death_country || '',
                athlete.oh_type || '',
                athlete.oh_year || '',
                athlete.oh_city || '',
                athlete.oh_country || ''
                ]);

            if (dataTable) {
                // Обновляем данные в существующей таблице
                dataTable.clear();
                dataTable.rows.add(tableData);
                dataTable.draw(false);
                // Обновляем originalRows
                originalRows = dataTable.rows().nodes().toArray().slice();
            } else {
                // Создаём новую DataTables
                dataTable = $('#olympiansTable').DataTable({
                    data: tableData,
                    columns: [
                        { title: "Meno a priezvisko" },
                        { title: "Umiestnenie" },
                        { title: "Šport" },
                        { title: "Kategoria" },
                        { title: "Den narodenia" },
                        { title: "Miesto narodenia" },
                        { title: "Krajina narodenia" },
                        { title: "death_day" },
                        { title: "death_place" },
                        { title: "death_country" },
                        { title: "oh_type" },
                        { title: "oh_year" },
                        { title: "oh_city" },
                        { title: "oh_country" }
                    ],
                    paging: true,
                    pageLength: 10,
                    lengthMenu: [[10, 20, 50, -1], [10, 20, 50, 'Všetky']],
                    ordering: false,
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
                originalRows = dataTable.rows().nodes().toArray().slice();
            }

            // Сбрасываем сортировку
            sortState = { column: null, direction: null };
            const headers = document.querySelectorAll('#olympiansTable th.sortable');
            headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));


        })
        .catch(error => {
            console.error('Chyba pri načítaní dát:', error);
            // Показываем сообщение об ошибке в таблице
            if (dataTable) {
                dataTable.clear().draw();
            }
            const tbody = document.getElementById('table-body');
            if (tbody) tbody.innerHTML = '<td colspan="9">Žiadne dáta k dispozícii</tr>';
        });
}

document.addEventListener('DOMContentLoaded', function() {

    loadAthletes();
    // Кнопка "Nahrať a spracovať"
const uploadBtn = document.getElementById('downloadAthletes');
if (uploadBtn) {
    uploadBtn.addEventListener('click', function(e) {
        e.preventDefault();
        uploadFile();
    });
}

// Кнопка "Vymazať všetky údaje"
const deleteBtn = document.getElementById('deleteAthletes');
if (deleteBtn) {
    deleteBtn.addEventListener('click', function(e) {
        e.preventDefault();
        // Проверяем, отмечен ли чекбокс подтверждения
        const confirmCheckbox = document.querySelector('input[name="confirm_delete"]');
        if (!confirmCheckbox || !confirmCheckbox.checked) {
            alert('Pre vymazanie musíte potvrdiť zaškrtnutím políčka.');
            return;
        }
        deleteAllData();
    });
}
});