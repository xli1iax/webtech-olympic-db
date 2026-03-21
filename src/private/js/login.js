
    // Pockame na nacitanie DOM stromu
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('loginForm');
        if (!form) return;  // ak formular neexistuje (napr. po uspesnom prihlaseni), koncime

        // Referencie na vstupne polia
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        const totp = document.getElementById('totp');

        // Funkcia na vymazanie vsetkych chybovych stavov
        function clearErrors() {
            document.querySelectorAll('.error-message-field').forEach(el => {
                el.textContent = '';
                el.classList.remove('visible');
            });
            document.querySelectorAll('input').forEach(el => el.classList.remove('error'));
        }

        // Funkcia na zobrazenie chyby pri konkretnom poli
        function showError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const errorDiv = document.getElementById('error-' + fieldId);
            if (field) field.classList.add('error');
            if (errorDiv) {
                errorDiv.textContent = message;
                errorDiv.classList.add('visible');
            }
        }

        // Obsluha odoslania formulara - spusti sa pred odoslanim na server
        form.addEventListener('submit', function(e) {
            clearErrors();   // najprv odstranime stare chyby
            let isValid = true;

            // Kontrola emailu: prazdny a spravny format
            const emailValue = email.value.trim();
            if (emailValue === '') {
                showError('email', 'E-mail je povinný.');
                isValid = false;
            } else {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(emailValue)) {
                    showError('email', 'Neplatný formát e-mailu.');
                    isValid = false;
                }
            }

            // Kontrola hesla - nesmie byt prazdne
            if (password.value === '') {
                showError('password', 'Heslo je povinné.');
                isValid = false;
            }

            // Kontrola 2FA kodu - nesmie byt prazdny
            if (totp.value === '') {
                showError('totp', 'Kód pre 2FA je povinný.');
                isValid = false;
            }

            // Ak niektora validacia zlyhala, zablokujeme odoslanie formulara
            if (!isValid) {
                e.preventDefault();
            }
        });
    });
