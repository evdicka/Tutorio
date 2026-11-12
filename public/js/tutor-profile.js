const profileBox = document.getElementById('profile');

// Tutor-ID kommt aus der URL, z. B. /tutor.html?id=7
const urlParams = new URLSearchParams(window.location.search);
const tutorId = urlParams.get('id');

async function loadProfile() {
  const response = await fetch(`/api/tutors/show.php?id=${tutorId}`);

  if (!response.ok) {
    profileBox.textContent = 'Tutor nicht gefunden.';
    return;
  }

  const tutor = await response.json();
  profileBox.innerHTML = renderProfile(tutor);
}

function renderProfile(tutor) {
  const subjectsHtml = tutor.subjects.map((s) => `<span class="tag">${escapeHtml(s.name)}</span>`).join('');
  const avgText = tutor.rating.avg !== null
    ? `⭐ ${tutor.rating.avg} (${tutor.rating.count} Bewertungen)`
    : 'Noch keine Bewertungen';

  const ratingsHtml = tutor.ratings.length > 0
    ? tutor.ratings.map((r) => `
        <div class="rating">
          ${'⭐'.repeat(r.stars)} — ${escapeHtml(r.comment)}
          <br><small>${escapeHtml(r.rater_name)}</small>
        </div>
      `).join('')
    : '<p class="empty-state">Noch keine Bewertungen.</p>';

  return `
    <a href="/index.html">← Zurück zu den Tutoren</a>
    <h2>${escapeHtml(tutor.display_name)}</h2>
    <p>${avgText}</p>
    <p>${escapeHtml(tutor.description)}</p>
    <h3>Fächer</h3>
    <div class="subjects">${subjectsHtml}</div>
    <p><strong>Verfügbarkeit:</strong> ${escapeHtml(tutor.availability ?? '–')}</p>
    <p><strong>Preismodell:</strong> ${escapeHtml(tutor.price_model ?? '–')}</p>
    <p><strong>Preismodell:</strong> ${escapeHtml(tutor.price_model ?? '–')}</p>
    <h3>Bewertungen</h3>
    ${ratingsHtml}
  `;
}

document.getElementById('rating-form').addEventListener('submit', async (event) => {
  event.preventDefault();
  const messageBox = document.getElementById('rating-message');
  messageBox.textContent = '';

  const response = await fetch('/api/ratings/create.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      tutor_id: parseInt(tutorId, 10),
      stars: parseInt(document.getElementById('stars').value, 10),
      comment: document.getElementById('rating-comment').value,
    }),
  });

  if (response.status === 401) {
    window.location.href = '/login.html';
    return;
  }

  const data = await response.json();

  if (!response.ok) {
    messageBox.className = 'form-message error';
    messageBox.textContent = data.error || 'Bewertung fehlgeschlagen.';
    return;
  }

  messageBox.className = 'form-message success';
  messageBox.textContent = 'Danke für deine Bewertung!';
  loadProfile(); // Bewertungsliste + Schnitt neu laden
});

const reportModal = document.getElementById('report-modal');
document.getElementById('report-btn').addEventListener('click', () => reportModal.showModal());
document.getElementById('close-report-btn').addEventListener('click', () => reportModal.close());

document.getElementById('report-form').addEventListener('submit', async (event) => {
  event.preventDefault();
  const messageBox = document.getElementById('report-message');
  messageBox.textContent = '';

  const response = await fetch('/api/reports/create.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      reported_user_id: parseInt(tutorId, 10),
      reason: document.getElementById('report-reason').value,
    }),
  });

  if (response.status === 401) {
    window.location.href = '/login.html';
    return;
  }

  const data = await response.json();

  if (!response.ok) {
    messageBox.textContent = data.error || 'Meldung fehlgeschlagen.';
    return;
  }

  messageBox.textContent = 'Meldung wurde übermittelt.';
  setTimeout(() => reportModal.close(), 1000);
});

loadProfile();
