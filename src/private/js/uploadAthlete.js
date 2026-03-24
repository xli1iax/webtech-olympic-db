function uploadAthlete() {
    const transformed = {
            name: document.getElementById('first_name').value,
            surname: document.getElementById('last_name').value,
            birth_day: document.getElementById('birth_date').value,
            birth_place: document.getElementById('birth_place').value,
            birth_country: document.getElementById('birth_country').value,
            death_day: document.getElementById('death_date').value,
            death_place: document.getElementById('death_place').value || null,
            death_country: document.getElementById('death_country').value || null,
            oh_year: parseInt(document.getElementById('oh_year').value, 10),
            oh_type: document.getElementById('oh_type').value,
            oh_city: document.getElementById('oh_city').value,
            oh_country: document.getElementById('oh_country').value,
            discipline: document.getElementById('discipline').value,
            placing: parseInt(document.getElementById('placing').value, 10)
        };
    
    fetch('/api/olympians', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(transformed)
        })
        .then(async response => {
            const text = await response.text();
            console.log('STATUS:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${text}`);
            }
                return text;
    })
    .then(data => {
        console.log('Server response:', data);
        document.getElementById('uploadForm').style.display = 'none';

    // показать success сообщение
    const successDiv = document.getElementById('success-message');
    successDiv.style.display = 'flex';

 
        })
        .catch(err => {
            console.error('Chyba pri nahrávaní súboru:', err);
            console.error('Nepodarilo sa nahrať súbor: ' + err.message);
        });
}


// Pockame na nacitanie DOM stromu
document.addEventListener('DOMContentLoaded', function() {
const form = document.getElementById('uploadForm');
if (!form) return;  // ak formular neexistuje (napr. po uspesnom prihlaseni), koncime

// Referencie na vstupne polia
const first_name = document.getElementById('first_name');
const last_name = document.getElementById('last_name');


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
    const nameRegex = /^[a-z ,.'-]+$/i;
    // Kontrola emailu: prazdny a spravny format
    const firstNameValue = first_name.value.trim();
    if (firstNameValue === '') {
        showError('first_name', 'first name je povinný.');
        isValid = false;
    } else {
        if (!nameRegex.test(firstNameValue)) {
            showError('first_name', 'Neplatný formát mena.');
            isValid = false;
        }
    }

    const lastNameValue = last_name.value.trim();
    if (lastNameValue === '') {
        showError('last_name', 'last name je povinný.');
        isValid = false;
    } else {
        if (!nameRegex.test(lastNameValue)) {
            showError('last_name', 'Neplatný formát priezviska.');
            isValid = false;
        }
    }

   

    // Ak niektora validacia zlyhala, zablokujeme odoslanie formulara
    if (!isValid) {
        e.preventDefault();
    } else {
        e.preventDefault();
        uploadAthlete();
    }


});
});
