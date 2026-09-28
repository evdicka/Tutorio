const list = document.getElementById('tutor-list');
const searchInput = document.getElementById('search');
const subjectFilter = document.getElementById('subject-filter');

// Fach-Filter beim Laden der Seite einmal mit allen Fächern befüllen.
async function loadSubjectsIntoFilter() {
  const response = await fetch('/api/subjects/list.php');
  const subjects = await response.json();

  const options = subjects.map((s) => `<option value="${s.id}">${s.name}</option>`);
  subjectFilter.insertAdjacentHTML('beforeend', options.join(''));
}

// Tutoren laden — abhängig vom aktuellen Such-/Filterwert.
async function loadTutors() {
  const params = new URLSearchParams();

  if (searchInput.value.trim() !== '') {
    params.set('q', searchInput.value.trim());
  }
  if (subjectFilter.value !== '') {
    params.set('subject_id', subjectFilter.value);
  }

  const response = await fetch('/api/tutors/list.php?' + params.toString());
  const tutors = await response.json();

  if (tutors.length === 0) {
    list.innerHTML = '<p class="empty-state">Keine Tutoren gefunden.</p>';
    return;
  }

  list.innerHTML = tutors.map(renderTutorCard).join('');
}

function renderTutorCard(tutor) {
  const ratingText = tutor.rating.avg !== null
    ? `⭐ ${tutor.rating.avg} (${tutor.rating.count} Bewertungen)`
    : 'Noch keine Bewertungen';

  const subjectTags = tutor.subjects.map((s) => `<span class="tag">${s.name}</span>`).join(' ');

  return `
    <div class="tutor-card">
      <h3>${tutor.display_name}</h3>
      <p>${ratingText}</p>
      <div class="subjects">${subjectTags}</div>
      <p>${tutor.description ?? ''}</p>
      <p><strong>Verfügbar:</strong> ${tutor.availability ?? '–'}</p>
      <a href="/tutor.html?id=${tutor.tutor_id}" class="btn btn-primary">Profil ansehen</a>
    </div>
  `;
}

// Bei jedem Tastenanschlag neu laden fühlt sich sofort an, ist aber
// unnötig viele Anfragen — deshalb kurz warten (debounce), bis die
// Nutzerin 300ms nichts mehr getippt hat.
let debounceTimer;
searchInput.addEventListener('input', () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(loadTutors, 300);
});

subjectFilter.addEventListener('change', loadTutors);

loadSubjectsIntoFilter().then(loadTutors);
