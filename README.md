# Tutorio — Backend (Auth- + Profil-/Such-Modul)

## Struktur

```
config/
  env.php        — .env-Loader (ohne composer, siehe ADR-003)
  database.php   — PDO-Verbindung zu MySQL
src/
  Jwt.php               — JWT encode/verifyAndDecode (HS256, ohne Bibliothek)
  AuthService.php        — Login-Flow: Digikabu -> Re-Signing
  TutorRepository.php    — Suche, öffentliches Profil, eigenes Profil, Speichern
  SubjectRepository.php  — Liste aller Fächer
  Middleware/RequireAuth.php — Zugriffsschutz für Endpunkte
api/
  auth/login.php     — POST, öffentlich: Login, setzt httpOnly-Cookie
  me.php              — GET, geschützt: Beispiel für RequireAuth
  tutors/list.php     — GET, öffentlich: Suche/Filter (?q=, ?subject_id=)
  tutors/show.php     — GET, öffentlich: ein Tutor (?id=)
  subjects/list.php   — GET, öffentlich: alle Fächer
  profile/me.php       — GET, geschützt: eigenes Profil für's Bearbeiten
  profile/update.php   — POST, geschützt: eigenes Profil speichern
public/
  login.html + js/auth.js                 — Login-Formular
  index.html + js/tutors.js               — Startseite: Liste + Suche
  tutor.html + js/tutor-profile.js        — ein Tutorprofil ansehen
  profile-edit.html + js/profile-edit.js  — eigenes Profil bearbeiten
schema.sql — komplette Datenbank (ohne messages — nicht im Scope)
```

## Setup

1. `.env.example` nach `.env` kopieren, ausfüllen (DB-Zugang, `TUTORIO_JWT_SECRET`
   per `php -r "echo bin2hex(random_bytes(32));"`, `DIGIKABU_BASE_URL`,
   `DIGIKABU_SHARED_SECRET`).
2. `schema.sql` in die MySQL-Datenbank einspielen.
3. `public/` als Web-Root, `api/`, `src/`, `config/` daneben — Pfade in den
   `require_once`-Zeilen passen zu genau dieser Struktur.

## Offener Punkt — DIGIKABU_SHARED_SECRET

Für die Signaturprüfung des Digikabu-Tokens (HS256) wird ein gemeinsames
Secret angenommen. **Muss mit der Schul-IT/Digikabu-Betreiber abgeklärt
werden.** Falls Digikabu stattdessen RS256 mit Public Key nutzt, muss
`Jwt::verifyAndDecode` entsprechend erweitert werden (`openssl_verify`
statt `hash_hmac`). Falls gar kein Secret herausgegeben wird, muss der
Prüfschritt entfallen und als Risiko in Kapitel 10 dokumentiert werden.

## Profil-/Such-Modul — wichtig

Ein Tutor erscheint in der öffentlichen Suche (`tutors/list.php`) erst,
**nachdem** er einmal über `profile-edit.html` gespeichert hat — vorher
gibt es keine Beschreibung/Fächer zum Anzeigen. Das ist Absicht, kein Bug.

"Session buchen" aus dem Mockup ist noch nicht umgesetzt — dafür gibt es
noch kein Buchungsmodul in der Architektur.

## Lokal testen ohne echtes Digikabu

`requestDigikabuToken()` in `AuthService.php` kann temporär durch eine
Stub-Funktion ersetzt werden, die ein Test-JWT zurückgibt (signiert mit
demselben `DIGIKABU_SHARED_SECRET` aus der `.env`), um den Rest des Flows
zu testen. Vor echter Integration wieder entfernen.

## Profil- und Such-Modul

Добавлено:

```
src/
  TutorRepository.php   — поиск/фильтр, публичный профиль, свой профиль, сохранение
  SubjectRepository.php — список всех предметов
api/
  tutors/list.php   — GET, публичный: список + поиск (?q=) + фильтр (?subject_id=)
  tutors/show.php   — GET, публичный: один тьютор по ?id=
  subjects/list.php — GET, публичный: все предметы (для фильтра/чекбоксов)
  profile/me.php    — GET, защищённый: своё профиль для формы редактирования
  profile/update.php— POST, защищённый: сохранить своё профиль + предметы
public/
  index.html + js/tutors.js         — главная страница со списком/поиском
  tutor.html + js/tutor-profile.js  — страница одного тьютора
  profile-edit.html + js/profile-edit.js — редактирование своего профиля (только тьютор)
```

Важно: тьютор появляется в публичном поиске **только после того**, как
хоть раз сохранил профиль через `profile-edit.html` (иначе не с чем его
показывать — ни описания, ни предметов). Это осознанное решение, не баг.

"Session buchen" с мокапа пока не реализован — модуля бронирования сессий
в архитектуре ещё нет, это отдельная задача на будущее.

## Hilfsgesuche-Modul

```
src/HelpRequestRepository.php — Liste, Detail+Antworten, Erstellen, Antworten
api/requests/list.php    — GET, öffentlich: Board (?subject_id= filtert)
api/requests/show.php    — GET, öffentlich: ein Gesuch + Antworten (?id=)
api/requests/create.php  — POST, geschützt: neues Gesuch erstellen
api/requests/reply.php   — POST, geschützt: auf ein Gesuch antworten
public/requests.html + js/requests.js       — Board + Filter + "Gesuch erstellen"-Modal
public/request.html + js/request-detail.js  — ein Gesuch + Antworten + Antwortformular
```

Board selbst ist öffentlich lesbar (wie im Mockup), Erstellen/Antworten
braucht Login — wer nicht eingeloggt ist, wird beim Versuch auf
`login.html` weitergeleitet (HTTP 401 vom Backend).

## Bewertungs- und Report-Modul

```
src/RatingRepository.php — Bewertung erstellen, prüft: existiert Tutor?, schon bewertet?
src/ReportRepository.php — Meldung erstellen, prüft: existiert gemeldeter Nutzer?
api/ratings/create.php — POST, geschützt: Bewertung (1–5 Sterne + optionaler Kommentar)
api/reports/create.php — POST, geschützt: Nutzer melden (Grund als Freitext)
```

Regeln (serverseitig geprüft, nicht nur im Frontend):
- Sterne müssen 1–5 sein.
- Ein Nutzer kann einen Tutor nur **einmal** bewerten (zweiter Versuch -> 409).
- Niemand kann sich selbst bewerten oder melden.

Formular für Bewertung + "Diesen Tutor melden" sind direkt in
`tutor.html`/`tutor-profile.js` integriert (unter dem Profil).

**Noch offen:** Es gibt aktuell keine Moderator-Rolle im Schema
(`role` kennt nur `tutor`/`suchend`) und daher keinen Endpunkt, um
Reports zu sichten/zu bearbeiten. Das müsste noch besprochen werden —
z. B. eigene `role = 'moderator'` plus `api/reports/list.php` nur für sie.

## Frontend (Styling nach Mockup)

```
public/css/styles.css — gemeinsames Stylesheet für alle Seiten
public/js/nav.js       — zeigt "Anmelden" oder Name+"Abmelden" in der Navbar
api/auth/logout.php    — löscht das Session-Cookie
```

Reines CSS, kein Framework (ADR-003). Angelehnt an die Figma-Screenshots:
heller Hintergrund, schwarze Buttons (`.btn-primary`), blauer Akzent
(`.btn-accent`, CTA-Banner), abgerundete Karten (`.tutor-card`,
`.request-card`), graue Pillen für Fächer (`.tag`).

Jede Seite bindet `/css/styles.css` und `/js/nav.js` ein — letzteres prüft
den Login-Status über `/api/me.php` und passt die Navbar automatisch an.
