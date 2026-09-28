const list = document.getElementById('request-list');
const subjectFilter = document.getElementById('subject-filter');
const modal = document.getElementById('create-modal');
const createForm = document.getElementById('create-form');
const createError = document.getElementById('create-error');
const newSubjectSelect = document.getElementById('new-subject');

async function loadSubjectsIntoSelects() {
  const response = await fetch('/api/subjects/list.php');
  const subjects = await response.json();
  const options = subjects.map((s) => `<option value="${s.id}">${s.name}</option>`).join('');

  subjectFilter.insertAdjacentHTML('beforeend', options);
  newSubjectSelect.insertAdjacentHTML('beforeend', options);
}

async function loadRequests() {
  const params = new URLSearchParams();
  if (subjectFilter.value !== '') {
    params.set('subject_id', subjectFilter.value);
  }

  const response = await fetch('/api/requests/list.php?' + params.toString());
  const requests = await response.json();

  if (requests.length === 0) {
    list.innerHTML = '<p class="empty-state">Keine Gesuche gefunden.</p>';
    return;
  }

  list.innerHTML = requests.map(renderRequestCard).join('');
}

function renderRequestCard(r) {
  const time = new Date(r.created_at).toLocaleString('de-DE');
  return `
    <div class="request-card">
      <h3><a href="/request.html?id=${r.id}">${r.title}</a></h3>
      <p>${r.author_name} · <span class="tag">${r.subject_name}</span> · ${time}</p>
      <p>${r.description}</p>
      <p>${r.reply_count} Antworten</p>
    </div>
  `;
}

subjectFilter.addEventListener('change', loadRequests);

// Modal öffnen/schließen — das <dialog>-Element bringt showModal()/close() schon mit.
document.getElementById('open-create-btn').addEventListener('click', () => {
  createError.textContent = '';
  modal.showModal();
});
document.getElementById('close-modal-btn').addEventListener('click', () => modal.close());

createForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  createError.textContent = '';

  const response = await fetch('/api/requests/create.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      subject_id: parseInt(newSubjectSelect.value, 10),
      title: document.getElementById('new-title').value,
      description: document.getElementById('new-description').value,
    }),
  });

  if (response.status === 401) {
    window.location.href = '/login.html';
    return;
  }

  const data = await response.json();

  if (!response.ok) {
    createError.textContent = data.error || 'Erstellen fehlgeschlagen.';
    return;
  }

  modal.close();
  createForm.reset();
  loadRequests();
});

loadSubjectsIntoSelects().then(loadRequests);
