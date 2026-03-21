// Pockame na nacitanie DOM stromu
document.addEventListener('DOMContentLoaded', function() {
    const nameForm = document.getElementById('updateNameForm');
    if (nameForm) {
        // Referencie na polia a pocitadla
        const firstName = document.getElementById('first_name');
        const lastName = document.getElementById('last_name');
        const firstnameCounter = document.getElementById('firstname-counter');
        const lastnameCounter = document.getElementById('lastname-counter');
        const maxLength = 64;

        // Funkcia na aktualizaciu pocitadla a odstranenie vizualnych stavov
        function updateCounter(field, counter) {
            const len = field.value.length;
            counter.textContent = len + '/' + maxLength;
            if (len > maxLength) {
                counter.classList.add('limit');
            } else {
                counter.classList.remove('limit');
            }
            field.classList.remove('error', 'success');
        }

        // Pridanie event listenerov na pocitanie pri písaní
        firstName.addEventListener('input', function() {
            updateCounter(firstName, firstnameCounter);
        });
        lastName.addEventListener('input', function() {
            updateCounter(lastName, lastnameCounter);
        });

        // Inicializacia pocitadiel pri nacitani stranky
        updateCounter(firstName, firstnameCounter);
        updateCounter(lastName, lastnameCounter);

        // Funkcia na zobrazenie chyby pre konkretne pole
        function showError(fieldId, message) {
            const errorDiv = document.getElementById('error-' + fieldId);
            const field = document.getElementById(fieldId === 'firstname' ? 'first_name' : 'last_name');
            if (field) field.classList.add('error');
            if (errorDiv) {
                errorDiv.textContent = message;
                errorDiv.classList.add('visible');
            }
        }

        // Obsluha odoslania formulara (klient-side validacia)
        nameForm.addEventListener('submit', function(e) {
            // Vymazeme predchadzajuce chyby
            document.querySelectorAll('.error-message').forEach(el => {
                el.textContent = '';
                el.classList.remove('visible');
            });
            [firstName, lastName].forEach(f => f.classList.remove('error', 'success'));

            let isValid = true;
            const firstNameVal = firstName.value.trim();
            const lastNameVal = lastName.value.trim();

            // Validacia mena
            if (firstNameVal === '') {
                showError('firstname', 'Meno je povinné.');
                isValid = false;
            } else if (firstNameVal.length > maxLength) {
                showError('firstname', 'Meno môže mať maximálne ' + maxLength + ' znakov.');
                isValid = false;
            } else {
                // Povolene znaky: pismena (aj s diakritikou), medzery, spojovnik, apostrof
                const nameRegex = /^[a-zA-Zá-žÁ-Ž\s\-']+$/;
                if (!nameRegex.test(firstNameVal)) {
                    showError('firstname', 'Meno obsahuje nepovolené znaky.');
                    isValid = false;
                } else {
                    firstName.classList.add('success');
                }
            }

            // Validacia priezviska
            if (lastNameVal === '') {
                showError('lastname', 'Priezvisko je povinné.');
                isValid = false;
            } else if (lastNameVal.length > maxLength) {
                showError('lastname', 'Priezvisko môže mať maximálne ' + maxLength + ' znakov.');
                isValid = false;
            } else {
                const nameRegex = /^[a-zA-Zá-žÁ-Ž\s\-']+$/;
                if (!nameRegex.test(lastNameVal)) {
                    showError('lastname', 'Priezvisko obsahuje nepovolené znaky.');
                    isValid = false;
                } else {
                    lastName.classList.add('success');
                }
            }

            // Ak niektora validacia zlyhala, zablokujeme odoslanie
            if (!isValid) {
                e.preventDefault();
            }
        });
    }
});