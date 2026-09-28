const urlParams = new URLSearchParams(window.location.search);
const requestId = urlParams.get('id');

const detailBox = document.getElementById('request-detail');
const repliesBox = document.getElementById('replies');
const replyForm = document.getElementById('reply-form');
const replyError = document.getElementById('reply-error');

async function loadRequest() {
  const response = await fetch(`/api/requests/show.php?id=${requestId}`);

  if (!response.ok) {
    detailBox.textContent = 'Gesuch nicht gefunden.';
    return;
  }

  const r = await response.json();
  const time = new Date(r.created_at).toLocaleString('de-DE');

  detailBox.innerHTML = `
    <a href="/requests.html">← Zurück zu den Gesuchen</a>
    <h2>${r.title}</h2>
    <p>${r.author_name} · <span class="tag">${r.subject_name}</span> · ${time}</p>
    <p>${r.description}</p>
  `;

  repliesBox.innerHTML = r.replies.length > 0
    ? r.replies.map(renderReply).join('')
    : '<p class="empty-state">Noch keine Antworten.</p>';
}

function renderReply(reply) {
  const time = new Date(reply.created_at).toLocaleString('de-DE');
  return `
    <div class="reply">
      <p>${reply.content}</p>
      <small>${reply.author_name} · ${time}</small>
    </div>
  `;
}

replyForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  replyError.textContent = '';

  const response = await fetch('/api/requests/reply.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      request_id: parseInt(requestId, 10),
      content: document.getElementById('reply-content').value,
    }),
  });

  if (response.status === 401) {
    window.location.href = '/login.html';
    return;
  }

  const data = await response.json();

  if (!response.ok) {
    replyError.textContent = data.error || 'Antworten fehlgeschlagen.';
    return;
  }

  replyForm.reset();
  loadRequest();
});

loadRequest();
