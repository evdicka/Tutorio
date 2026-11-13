const form = document.getElementById('login-form');
const errorBox = document.getElementById('login-error');

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  errorBox.textContent = '';

  const username = document.getElementById('username').value;
  const password = document.getElementById('password').value;

  const response = await fetch('/api/auth/login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username, password }),
  });

  const data = await response.json();

  if (!response.ok) {
    errorBox.textContent = data.error || 'Anmeldung fehlgeschlagen.';
    return;
  }

  window.location.href = '/index.html';
});
