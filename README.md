# Meeting AI System

A full-stack Laravel 11 + PostgreSQL + Redis queue + Groq AI + Vue 3 application for recording/uploading bilingual Arabic/English meetings, transcribing them, generating minutes/tasks, requiring approval, and emailing each assignee only their approved task.

## Architecture

```text
Vue 3 SPA -> Laravel API -> PostgreSQL
                     |-> Queue worker (Redis/database)
                     |-> Groq Whisper transcription
                     |-> Groq chat completion for minutes/tasks JSON
                     |-> Mail SMTP/SendGrid/Mailpit
```

## Main workflow

1. User records audio in the browser or uploads audio/video.
2. Laravel stores the file and creates a `meetings` record with `status=uploaded`.
3. `TranscribeMeetingJob` calls Groq speech-to-text, saves the transcript, and dispatches `ProcessMeetingAiJob`.
4. `ProcessMeetingAiJob` asks Groq to return structured JSON containing bilingual minutes, decisions, risks, and per-person tasks.
5. Tasks are created with `status=pending_approval`.
6. A manager/admin approves or rejects each task.
7. Approved tasks can be emailed to each assignee via `SendTaskEmailJob`.

## Requirements

- PHP 8.2+
- Composer
- Node.js 20+
- PostgreSQL 15+
- Redis 7+ recommended for queues
- Groq API key

## Quick start

### 1. Start local services

```bash
docker compose up -d postgres redis mailpit
```

### 2. Backend setup

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8000
```

Set your real Groq key in `backend/.env`:

```env
GROQ_API_KEY=gsk_...
```

Run the queue worker in a second terminal:

```bash
cd backend
php artisan queue:work redis --queue=meetings,emails,default --tries=3 --timeout=900
```

For local email preview, open Mailpit at <http://localhost:8025>.

Default seeded user:

```text
Email: admin@example.com
Password: password
Role: admin
```

### 3. Frontend setup

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

Open <http://localhost:5173>.

## Production notes

- Use Redis queue workers under Supervisor/systemd.
- Store files on S3-compatible storage for production.
- Set `SANCTUM_STATEFUL_DOMAINS` and CORS values carefully if using cookie-based SPA auth. This project uses Sanctum bearer tokens for a simpler API-first setup.
- For long meeting recordings, compress/chunk audio before transcription to stay inside Groq endpoint limits.
- Never commit `.env` or API keys.

## Included modules

- API token authentication with Laravel Sanctum
- Meeting upload/record API
- Queued transcription and AI processing
- PostgreSQL migrations and Eloquent models
- Approval workflow
- Queued task email notifications
- Vue 3 SPA with modern responsive UI, recording, upload, dashboard, meeting details, and approval actions
