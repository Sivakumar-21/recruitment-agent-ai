# Recruitment Agent AI

An AI-powered recruitment management system built with **Laravel 11**, **Livewire**, and **OpenAI/Grok LLM**. This application automates resume parsing, job criteria matching, semantic candidate searches, candidate communications (rejection, screening, self-scheduling, and offer letters), automatic interview notes grading, and compensation recommendations.

---

## Features

- **Resume Parsing**: Automatically extracts names, contact info, skills, education, and previous work experience from PDF and DOCX uploads.
- **AI Matching & Score Grading**: Scores applicants against custom job criteria (skills, education, and experience matching).
- **Recruiter Assistant Drawer**: A unified side-drawer displaying detailed evaluation summaries, strengths, concerns, and tailored interview questions.
- **RAG Semantic Search**: Query your applicant pool with natural language (e.g., *"Laravel developer with AWS experience"*).
- **Candidate Self-Screening Portal**: Interfacing chatbot for capturing expected salary, notice period, work status, and booking calendars.
- **Offer Advisor Agent**: Suggests salary ranges, benefits packages, and logs automated offer letters.

---

## Prerequisites

- **PHP**: $\ge$ 8.2
- **Composer**
- **Node.js & npm**
- **MySQL** / **MariaDB**
- **OpenAI API Key** (for live parsing & matching)

---

## Project Setup

Follow these steps to set up the project locally:

### 1. Clone & Install Dependencies
```bash
# Clone the repository
git clone https://github.com/Sivakumar-21/recruitment-agent-ai.git
cd recruitment-agent-ai

# Install PHP dependencies
composer install

# Install Frontend assets
npm install
```

### 2. Environment Configuration
Copy the `.env.example` file to `.env`:
```bash
cp .env.example .env
```

Open `.env` and set up the following parameters:

```env
# Database Credentials
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=recruitment_agent
DB_USERNAME=your_mysql_username
DB_PASSWORD=your_mysql_password

# LLM Provider Configuration (openai or grok)
LLM_PROVIDER=openai

# OpenAI API Credentials
OPENAI_API_KEY=your_openai_api_key_here
```

### 3. Application Key & Migrations
Initialize the encryption key and set up database tables using the consolidated migration file:
```bash
# Generate app key
php artisan key:generate

# Run migrations (runs the unified schema including all custom tables)
php artisan migrate:fresh
```

---

## Running the Application

To start the local development servers, run the following commands in separate terminals:

### 1. Start PHP Serve
```bash
php artisan serve
```
By default, the backend will run at [http://127.0.0.1:8000](http://127.0.0.1:8000).

### 2. Start Vite Asset Compiler
```bash
npm run dev
```

### 3. Run Queue Workers
Since resume processing is dispatched to background workers, start a queue worker:
```bash
php artisan queue:work
```

---

## Folder Structure Highlights

- **`database/migrations/2026_06_05_000000_create_recruitment_agent_system_tables.php`**: Consolidates all recruitment database schemas.
- **`app/Services/OpenAIService.php`**: Handles all API communications with OpenAI for parsing, matching, grading, and compensation recommendations.
- **`app/Services/DocumentParserService.php`**: Extracts raw text from PDF and DOCX files.
- **`app/Jobs/ProcessResumeJob.php`**: Handles background execution of resume uploads.
- **`app/Livewire/`**: Contains core Livewire controllers (`JobList`, `JobDetails`, `CandidatePortal`).
- **`resources/views/livewire/`**: Blade templates.
