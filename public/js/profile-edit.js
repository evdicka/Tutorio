const form = document.getElementById('profile-form');
const descriptionField = document.getElementById('description');
const availabilityField = document.getElementById('availability');
const priceModelField = document.getElementById('price-model');
const checkboxContainer = document.getElementById('subject-checkboxes');
const message = document.getElementById('form-message');

// Zuerst ALLE Fächer laden (für die Checkboxen), danach das eigene
// Profil (um Werte + bereits gewählte Fächer vorauszufüllen).
async function init() {
  const [subjects, ownProfile] = await Promise.all([
    fetch('/api/subjects/list.php').then((r) => r.json()),
    fetch('/api/profile/me.php').then((r) => {
      if (r.status === 401) {
        window.location.href = '/login.html';
        throw new Error('Nicht angemeldet');
      }
      return r.json();
    }),
  ]);

  const ownSubjectIds = new Set(ownProfile.subjects.map((s) => s.id));

  checkboxContainer.innerHTML = subjects.map((s) => `
    <label>
      <input type="checkbox" name="subject" value="${s.id}" ${ownSubjectIds.has(s.id) ? 'checked' : ''}>
      ${s.name}
    </label><br>
  `).join('');

  descriptionField.value = ownProfile.description ?? '';
  availabilityField.value = ownProfile.availability ?? '';
  priceModelField.value = ownProfile.price_model ?? '';
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  message.textContent = '';

  const selectedSubjectIds = Array.from(
    document.querySelectorAll('input[name="subject"]:checked')
  ).map((checkbox) => parseInt(checkbox.value, 10));

  const response = await fetch('/api/profile/update.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      description: descriptionField.value,
      availability: availabilityField.value,
      price_model: priceModelField.value,
      subject_ids: selectedSubjectIds,
    }),
  });

  const data = await response.json();

  if (!response.ok) {
    message.className = 'form-message error';
    message.textContent = data.error || 'Speichern fehlgeschlagen.';
    return;
  }

  message.className = 'form-message success';
  message.textContent = 'Profil gespeichert.';
});

init();
