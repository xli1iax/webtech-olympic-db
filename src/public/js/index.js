
// ---------- Помощник для безопасного вывода ----------
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// ---------- Глобальные переменные ----------
let dataTable;
let originalRows = [];
let sortState = { column: null, direction: null };

// ---------- Заполнение фильтров (селектов) уникальными данными ----------
function populateFilters() {
    fetch('/api/olympians')
        .then(response => response.json())
        .then(data => {
            const yearsSet = new Set();
            const categoriesSet = new Set();

            data.forEach(item => {
                if (item.year) yearsSet.add(item.year);
                if (item.category) categoriesSet.add(item.category);
            });

            const yearSelect = document.getElementById('rok');
            const categorySelect = document.getElementById('kategoria');
            if (yearSelect) {
                const sortedYears = Array.from(yearsSet).sort((a,b) => b - a);
                sortedYears.forEach(year => {
                    const option = document.createElement('option');
                    option.value = year;
                    option.textContent = year;
                    yearSelect.appendChild(option);
                });
            }
            if (categorySelect) {
                const sortedCategories = Array.from(categoriesSet).sort();
                sortedCategories.forEach(cat => {
                    const option = document.createElement('option');
                    option.value = cat;
                    option.textContent = cat;
                    categorySelect.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Chyba pri načítaní filtrov:', error));
}

// ---------- Отрисовка строк таблицы ----------
function renderRows(data) {
    const tbody = document.getElementById('table-body');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (!data || data.length === 0) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 9;
        cell.textContent = 'Žiadne záznamy.';
        row.appendChild(cell);
        tbody.appendChild(row);
        return;
    }

    data.forEach(athlete => {
        const row = document.createElement('tr');

        // Имя с ссылкой
        const nameCell = document.createElement('td');
        nameCell.className = 'mena-cell';
        nameCell.innerHTML = `<a href="athlete.php?id=${athlete.id}" class="athlete-link">${escapeHtml(athlete.first_name)} ${escapeHtml(athlete.last_name)}</a>`;
        row.appendChild(nameCell);

        // Год
        const yearCell = document.createElement('td');
        yearCell.className = 'rok-cell';
        yearCell.textContent = athlete.year || '';
        row.appendChild(yearCell);

        // Город
        const cityCell = document.createElement('td');
        cityCell.textContent = athlete.city || '';
        row.appendChild(cityCell);

        // Тип ОИ
        const typeCell = document.createElement('td');
        typeCell.textContent = athlete.olympic_type || '';
        row.appendChild(typeCell);

        // Страна
        const countryCell = document.createElement('td');
        countryCell.textContent = athlete.country_name || '';
        row.appendChild(countryCell);

        // Спорт
        const disciplineCell = document.createElement('td');
        disciplineCell.textContent = athlete.discipline || '';
        row.appendChild(disciplineCell);

        // Категория
        const categoryCell = document.createElement('td');
        categoryCell.className = 'kategoria-cell';
        categoryCell.textContent = athlete.category || '';
        row.appendChild(categoryCell);

        // Место
        const placingCell = document.createElement('td');
        placingCell.textContent = athlete.placing ? athlete.placing + '. miesto' : '';
        row.appendChild(placingCell);

        // Медаль с классом
        const medalCell = document.createElement('td');
        if (athlete.placing === 1) medalCell.className = 'medal-gold';
        else if (athlete.placing === 2) medalCell.className = 'medal-silver';
        else if (athlete.placing === 3) medalCell.className = 'medal-bronze';
        medalCell.textContent = athlete.medal || '';
        row.appendChild(medalCell);

        tbody.appendChild(row);
    });
}

// ---------- Загрузка данных из API и инициализация DataTables ----------
function loadAthletes() {
    const year = document.getElementById('rok')?.value || '';
    const category = document.getElementById('kategoria')?.value || '';

    let url = '/api/olympians';
    const params = new URLSearchParams();
    if (year) params.append('year', year);
    if (category) params.append('discipline', category);
    if (params.toString()) url += '?' + params.toString();

    fetch(url)
        .then(response => {
            if (!response.ok) throw new Error('Network error');
            return response.json();
        })
        .then(data => {
            // Преобразуем данные в формат для DataTables (массив массивов)
            const tableData = data.map(athlete => [
                `<a href="athlete.php?id=${athlete.id}" class="athlete-link">${escapeHtml(athlete.first_name)} ${escapeHtml(athlete.last_name)}</a>`,
                athlete.year || '',
                athlete.city || '',
                athlete.olympic_type || '',
                athlete.country_name || '',
                athlete.discipline || '',
                athlete.category || '',
                athlete.placing ? athlete.placing + '. miesto' : '',
                `<span class="${athlete.placing === 1 ? 'medal-gold' : (athlete.placing === 2 ? 'medal-silver' : (athlete.placing === 3 ? 'medal-bronze' : ''))}">${athlete.medal || ''}</span>`
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
                        { title: "Rok" },
                        { title: "Miesto konania" },
                        { title: "Typ OH" },
                        { title: "Reprezentovaná krajina" },
                        { title: "Šport" },
                        { title: "Kategória" },
                        { title: "Umiestnenie" },
                        { title: "Medaila" }
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
            let url = '/api/olympians';
const params = new URLSearchParams();
if (year) params.append('year', year);
if (category) params.append('discipline', category);  // важно: discipline
if (params.toString()) url += '?' + params.toString();
console.log('Final URL:', url);

        })
        .catch(error => {
            console.error('Chyba pri načítaní dát:', error);
            // Показываем сообщение об ошибке в таблице
            if (dataTable) {
                dataTable.clear().draw();
            }
            const tbody = document.getElementById('table-body');
            if (tbody) tbody.innerHTML = '骨<td colspan="9">Nepodarilo sa načítať dáta.骨</tr>';
        });
}

// ---------- Сортировка таблицы (с сохранением текущей страницы) ----------
function sortTable(column, direction) {
    const headers = document.querySelectorAll('#olympiansTable th.sortable');
    headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));

    if (direction === 'reset') {
        dataTable.clear();
        dataTable.rows.add(originalRows);
        dataTable.draw(false);
        sortState = { column: null, direction: null };
        return;
    }

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
            return 0;
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

// ---------- Инициализация после загрузки DOM ----------
document.addEventListener('DOMContentLoaded', function() {
    // Заполняем селекты уникальными значениями
    populateFilters();

    // Загружаем данные
    loadAthletes();

    // Вешаем обработчики на заголовки для сортировки
    const headers = document.querySelectorAll('#olympiansTable th.sortable');
    headers.forEach(header => {
        header.addEventListener('click', function() {
            const column = this.dataset.sort;
            if (!column) return;

            let direction;
            if (sortState.column !== column) {
                direction = (column === 'rok') ? 'desc' : 'asc';
            } else {
                if (column === 'rok') {
                    if (sortState.direction === 'desc') direction = 'asc';
                    else if (sortState.direction === 'asc') direction = 'reset';
                    else direction = 'desc';
                } else {
                    if (sortState.direction === 'asc') direction = 'desc';
                    else if (sortState.direction === 'desc') direction = 'reset';
                    else direction = 'asc';
                }
            }
            sortTable(column, direction);
        });
    });

    // Кнопки фильтров
    const filterBtn = document.getElementById('filterBtn');
    const resetBtn = document.getElementById('resetBtn');
    if (filterBtn) {
        console.log('Filter button clicked');
        filterBtn.addEventListener('click', loadAthletes);}
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            const yearSelect = document.getElementById('rok');
            const catSelect = document.getElementById('kategoria');
            if (yearSelect) yearSelect.value = '';
            if (catSelect) catSelect.value = '';
            loadAthletes();
        });
    }

    // Cookies
    if (document.getElementById('cookies-banner')) {
        if (!localStorage.getItem('cookiesAccepted')) {
            document.getElementById('cookies-banner').style.display = 'block';
        }
        const acceptBtn = document.getElementById('cookies-accept');
        if (acceptBtn) {
            acceptBtn.addEventListener('click', function() {
                localStorage.setItem('cookiesAccepted', 'true');
                document.getElementById('cookies-banner').style.display = 'none';
            });
        }
    }
});

