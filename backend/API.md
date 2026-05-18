# API endpoints

Base URL: `/api`

## Auth

- `POST /auth/register`
- `POST /auth/login`
- `GET /me`
- `POST /auth/logout`

Use `Authorization: Bearer <token>` for protected routes.

## Meetings

- `GET /meetings`
- `POST /meetings/upload` multipart fields: `title`, `meeting_file`
- `GET /meetings/{meeting}`
- `POST /meetings/{meeting}/reprocess` admin/manager

## Tasks

- `GET /tasks?status=pending_approval`
- `GET /tasks/{task}`
- `POST /tasks/{task}/approve` admin/manager
- `POST /tasks/{task}/reject` admin/manager body: `{ "comment": "reason" }`
- `POST /tasks/{task}/send-email` admin/manager, only after approval
