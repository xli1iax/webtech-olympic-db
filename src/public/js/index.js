
    // Cookies banner - kontrola ci uz bolo potvrdene
    document.addEventListener('DOMContentLoaded', function() {
        if (!localStorage.getItem('cookiesAccepted')) {
            document.getElementById('cookies-banner').style.display = 'block';
        }

        document.getElementById('cookies-accept').addEventListener('click', function() {
            localStorage.setItem('cookiesAccepted', 'true');
            document.getElementById('cookies-banner').style.display = 'none';
        });
    });



    // Inicializacia DataTables a vlastne triedenie
    document.addEventListener('DOMContentLoaded', function() {
        const table = document.getElementById('olympiansTable');
        if (!table) return;

        const headers = table.querySelectorAll('th.sortable');

        // Inicializacia DataTables s nastaveniami
        const dataTable = $('#olympiansTable').DataTable({
            paging: true,
            pageLength: 10,
            lengthMenu: [[10, 20, 50, -1], [10, 20, 50, 'Všetky']],
            ordering: false, // Vypneme zabudovane triedenie, budeme triedit vlastne
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

        // Ulozenie povodneho poradia (z databazy)
        const originalRows = dataTable.rows().nodes().toArray().slice();
        console.log('DataTables rows count:', dataTable.rows().count());

        let sortState = {
            column: null,
            direction: null
        };

        // Pridanie event listenerov na hlavicky pre vlastne triedenie
        headers.forEach(header => {
            header.addEventListener('click', function() {
                const column = this.dataset.sort;
                let direction;

                if (sortState.column !== column) {
                    direction = (column === 'rok') ? 'desc' : 'asc';
                } else {
                    if (column === 'rok') {
                        // rok: desc -> asc -> reset
                        if (sortState.direction === 'desc') direction = 'asc';
                        else if (sortState.direction === 'asc') direction = 'reset';
                        else direction = 'desc';
                    } else {
                        // text: asc -> desc -> reset
                        if (sortState.direction === 'asc') direction = 'desc';
                        else if (sortState.direction === 'desc') direction = 'reset';
                        else direction = 'asc';
                    }
                }

                sortTable(column, direction);
            });
        });

        // Funkcia na triedenie tabulky
        function sortTable(column, direction) {
            headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));

            if (direction === 'reset') {
                // Vratime povodne poradie
                dataTable.clear();
                dataTable.rows.add(originalRows);
                dataTable.draw(false);

                sortState = { column: null, direction: null };
                return;
            }

            // Ziskame vsetky riadky z DataTables (nie len viditelne)
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
                    aVal = '';
                    bVal = '';
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
    });

