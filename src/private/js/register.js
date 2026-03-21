
// Pockame na nacitanie DOM stromu
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registerForm');
    if (!form) return; // Ak formular neexistuje (po uspesnej registracii), koncime

    // Referencie na vstupne polia
    const firstName = document.getElementById('firstname');
    const lastName = document.getElementById('lastname');
    const email = document.getElementById('email');
    const password = document.getElementById('password');
    const passwordRepeat = document.getElementById('password_repeat');

    // Pocitadla znakov
    const firstnameCounter = document.getElementById('firstname-counter');
    const lastnameCounter = document.getElementById('lastname-counter');
    const maxLength = 64;

    // Regulárny výraz pre meno a priezvisko (povoľuje len latinku, medzery a spojovníky)
    const nameRegex = /^[A-Za-z\s\-]+$/;

    // Funkcia na aktualizaciu pocitadla a odstranenie vizualnych stavov
    function updateNameField(field, counter) {
        const len = field.value.length;
        counter.textContent = len + '/' + maxLength;
        if (len > maxLength) {
            counter.classList.add('limit');
        } else {
            counter.classList.remove('limit');
        }
        // Odstranime triedy error/success – budu nastavene az pri submit
        field.classList.remove('error', 'success');
    }

    // Pridanie event listenerov na pocitanie znakov pri písaní
    firstName.addEventListener('input', function() {
        updateNameField(firstName, firstnameCounter);
    });
    lastName.addEventListener('input', function() {
        updateNameField(lastName, lastnameCounter);
    });

    // Inicializacia pocitadiel pri nacitani stranky (ak je hodnota z POST)
    updateNameField(firstName, firstnameCounter);
    updateNameField(lastName, lastnameCounter);

    // Funkcia na vymazanie vsetkych chybovych stavov
    function clearErrors() {
        document.querySelectorAll('.error-message-field').forEach(el => {
            el.textContent = '';
            el.classList.remove('visible');
        });
        document.querySelectorAll('input').forEach(el => {
            el.classList.remove('error', 'success');
        });
    }

    // Funkcia na zobrazenie chyby pre konkretne pole
    function showError(fieldId, message) {
        const field = document.getElementById(fieldId);
        const errorDiv = document.getElementById('error-' + fieldId);
        if (field) field.classList.add('error');
        if (errorDiv) {
            errorDiv.textContent = message;
            errorDiv.classList.add('visible');
        }
    }

    // Obsluha odoslania formulara (klient-side validacia)
    form.addEventListener('submit', function(e) {
        clearErrors(); // Najprv odstranime stare chyby
        let isValid = true;

        // Validacia mena
        const firstNameVal = firstName.value.trim();
        if (firstNameVal === '') {
            showError('firstname', 'Meno je povinné.');
            isValid = false;
        } else if (firstNameVal.length > maxLength) {
            showError('firstname', 'Meno môže mať maximálne ' + maxLength + ' znakov.');
            isValid = false;
        } else if (!nameRegex.test(firstNameVal)) {
            showError('firstname', 'Meno môže obsahovať len latinské písmená, medzery a spojovníky.');
            isValid = false;
        } else {
            firstName.classList.add('success');
        }

        // Validacia priezviska
        const lastNameVal = lastName.value.trim();
        if (lastNameVal === '') {
            showError('lastname', 'Priezvisko je povinné.');
            isValid = false;
        } else if (lastNameVal.length > maxLength) {
            showError('lastname', 'Priezvisko môže mať maximálne ' + maxLength + ' znakov.');
            isValid = false;
        } else if (!nameRegex.test(lastNameVal)) {
            showError('lastname', 'Priezvisko môže obsahovať len latinské písmená, medzery a spojovníky.');
            isValid = false;
        } else {
            lastName.classList.add('success');
        }

        // Validacia emailu
        const emailVal = email.value.trim();
        if (emailVal === '') {
            showError('email', 'E-mail je povinný.');
            isValid = false;
        } else {
            const simpleEmailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!simpleEmailRegex.test(emailVal)) {
                showError('email', 'Neplatný formát e-mailu (napr. meno@domena.sk).');
                isValid = false;
            } else {
                email.classList.add('success');
            }
        }

        // Validacia hesla (nesmie byt prazdne)
        if (password.value === '') {
            showError('password', 'Heslo je povinné.');
            isValid = false;
        } else if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/.test(password.value)) {
            showError(
                'password',
                'Heslo musí mať aspoň 8 znakov, 1 malé písmeno, 1 veľké písmeno a 1 číslo.'
            );
            isValid = false;
        } else {
            password.classList.add('success');
        }

        // Validacia potvrdenia hesla
        if (passwordRepeat.value === '') {
            showError('password_repeat', 'Zopakovanie hesla je povinné.');
            isValid = false;
        } else if (password.value !== passwordRepeat.value) {
            showError('password_repeat', 'Heslá sa nezhodujú.');
            isValid = false;
        } else {
            passwordRepeat.classList.add('success');
        }

        // Ak niektora validacia zlyhala, zablokujeme odoslanie formulara
        if (!isValid) {
            e.preventDefault();
        }
    });

    document.querySelectorAll('input').forEach(input => {
input.addEventListener('input', () => {
    const error = document.getElementById('globalError');
    if (error) {
        error.remove();
    }
});
});
});
