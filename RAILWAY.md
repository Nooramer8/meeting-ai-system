# Railway Deployment

Deploy this repository as a small monorepo on Railway.

## Services

Create these Railway services from the same GitHub repository:

- `backend`: root directory `backend`, Dockerfile deploy.
- `frontend`: root directory `frontend`, Dockerfile deploy.
- `queue`: root directory `backend`, Dockerfile deploy, start command:
  `php artisan queue:work --queue=meetings,emails,default --tries=1 --timeout=900`
- PostgreSQL plugin.
- Redis plugin.

## Backend variables

Set these on the `backend` and `queue` services:

```env
APP_NAME="Meeting AI System"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...
APP_URL=https://your-backend-service.up.railway.app
FRONTEND_URL=https://your-frontend-service.up.railway.app
DATABASE_URL=${{Postgres.DATABASE_URL}}
REDIS_URL=${{Redis.REDIS_URL}}
REDIS_CLIENT=predis
QUEUE_CONNECTION=redis
CACHE_STORE=database
SESSION_DRIVER=database
GROQ_API_KEY=your_groq_key
GROQ_BASE_URL=https://api.groq.com/openai/v1
GROQ_WHISPER_MODEL=whisper-large-v3
GROQ_CHAT_MODEL=llama-3.3-70b-versatile
GROQ_TIMEOUT=600
MAIL_MAILER=smtp
MAIL_HOST=your_smtp_host
MAIL_PORT=587
MAIL_USERNAME=your_smtp_username
MAIL_PASSWORD=your_smtp_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"
```

Generate `APP_KEY` locally with:

```bash
cd backend
php artisan key:generate --show
```

## Frontend variables

Set this on the `frontend` service before deploying:

```env
VITE_API_BASE_URL=https://your-backend-service.up.railway.app/api
```
