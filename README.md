# Loom (WebProject)

Loom is a community-style web app built with **PHP + MySQL** where users can create posts, comment, vote, earn karma/badges, and receive notifications. It also includes an **in-browser AI content analyzer** (TensorFlow.js) that can give quality/toxicity feedback and adjust karma on post creation.

## Features
- **Authentication**: sign up, log in, log out (password hashing + sessions)
- **Posts**: create/read/update/delete, categories, tags, pagination
- **Comments**: threaded replies + AJAX add/edit/delete
- **Voting + karma**: upvote/downvote posts/comments, karma tracking, badge levels, leaderboard
- **Notifications**: unread badge, dropdown preview, mark-as-read, notification preferences
- **Profiles & settings**: profile pages, edit profile, profile picture upload, privacy options
- **Moderation**: report posts/comments + admin dashboard actions (resolve reports, ban/unban users, delete content)
- **AI content analyzer**: browser-side toxicity + quality scoring (TensorFlow.js + models)

## Tech Stack
- **Backend**: PHP (procedural PHP + MySQLi prepared statements)
- **Database**: MySQL / MariaDB
- **Frontend**: HTML/CSS + vanilla JavaScript (AJAX/fetch)
- **UI/Icons**: Font Awesome
- **AI (client-side)**: TensorFlow.js + Toxicity model + Universal Sentence Encoder

## Getting Started (Local)
### Prerequisites
- PHP 8.x (7.4+ should work for most parts)
- MySQL/MariaDB
- Apache (XAMPP/WAMP/Laragon all work)

### 1) Put the project in your web root
Example (XAMPP on Windows): copy the project folder into `htdocs/` so you can open:

- `http://localhost/loom/index.php` (if your folder name is `loom`)

### 2) Create the database
Create a database named `loom`, then import the schema:

- `database/schema.sql`

If you want demo categories/tags, import:

- `database/seed.sql`

### 3) Configure database credentials
Create a local config file (ignored by git), then update it with your DB credentials:

- Copy `includes/db_config.sample.php` → `includes/db_config.local.php`

Alternatively, set environment variables:

- `LOOM_DB_HOST`, `LOOM_DB_USER`, `LOOM_DB_PASS`, `LOOM_DB_NAME`

### 4) Ensure upload folders are writable
If profile picture uploads are enabled, ensure the uploads folder is writable by your web server:

- `uploads/profile_pictures/`

## Folder Structure
- `ajax/` — lightweight JSON endpoints (comments, votes, notification actions)
- `includes/` — shared PHP code (DB connect, auth, helpers, templates)
- `css/` — styling
- `js/` — client-side logic (comments, voting, notifications, AI analysis)
- `assets/` — versioned static assets (e.g., default avatar)
- `uploads/` — user uploads (profile pictures, etc.)
- `database/` — schema and optional seed data

## Admin Setup
To access the admin dashboard, your user must have admin privileges in the database:

- Set `users.is_admin = 1` for your account.

Then open:
- `admin.php`

## AI Content Analyzer (How It Works)
Loom loads TensorFlow.js models in the browser to:
- Detect toxic content (toxicity classifier)
- Estimate content “quality” via embedding similarity (Universal Sentence Encoder)
- Produce a karma adjustment applied when a post is created

Note: model downloads require an internet connection unless you self-host the JS/model assets.

## Project Notes
- Weekly progress-style update comments are available in `WEEKLY_LOG.md`.
- This project is built for learning/demo purposes; review security + deployment practices before using in production.

## Troubleshooting
- **DB connection errors**: verify DB name, credentials, and that MySQL is running.
- **Blank categories dropdown**: ensure you imported `database/seed.sql` (or created categories yourself).
- **Uploads not working**: make sure `uploads/profile_pictures/` is writable by the web server.
- **AI analysis not loading**: check browser console; models are loaded from CDNs unless you self-host them.

## License
See `LICENSE`.

## Acknowledgements
- Font Awesome
- TensorFlow.js, Toxicity model, Universal Sentence Encoder
