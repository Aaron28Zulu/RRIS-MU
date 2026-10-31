# RRIS (Research Repository Interface System)

RRIS is a centralized Research Management and Project Database System for every school and department of Mulungushi University. The system tracks research projects, provides access-controlled catalogs, automates similarity and plagiarism detection, identifies institutional research gaps, and digitizes student–supervisor workflows.

---

## 1. System Architecture & Module Breakdown

RRIS is designed as a modular monolith using **Laravel 11**, **Filament v3**, and **Livewire v3**.

### Module 1: Access-Controlled Research Repository & Discovery
* **Purpose:** Serves as the central digital archive for completed and ongoing research projects while strictly protecting intellectual property and academic integrity.
* **Functional Scope:**
  * Public/Student view: Displays titles, categories, completion year, abstracts, recommendations, identified gaps, and ongoing research areas.
  * Access restrictions: Automatically blocks ordinary students from viewing or downloading full manuscripts, raw experimental data, detailed methodology chapters, and supervisor evaluations.
* **Implementation Mechanism:**
  * **Frontend:** A reactive search, filter, and faceted exploration catalog built with **Livewire v3** and styled using Tailwind CSS.
  * **Authorization:** Strict Laravel **Policies** (`ProjectPolicy`) enforcing granular attribute and file gating.
  * **Storage:** Files are held in a private, non-public storage disk (`Storage::disk('secure_research')`). Document downloads are served strictly via signed, time-limited, role-checked streaming endpoints.

---

### Module 2: Research Proposal & Supervision Workflow
* **Purpose:** Manages the research lifecycle from proposal submission to final defense between students and academic supervisors.
* **Functional Scope:**
  * Registered students submit initial proposals, upload milestone progress reports, track approval stages, and view supervisor feedback.
  * Supervisors inspect submitted drafts, leave inline comments/revisions, accept or reject submissions, and submit digital evaluation rubrics.
* **Implementation Mechanism:**
  * **Student Portal:** Multi-step Livewire submission wizards with client-side and server-side file validation.
  * **Supervisor & Faculty Panel:** **Filament v3 Resource Pages** configured with approval workflow actions, notification dispatchers, and grading rubric forms.
  * **State Management:** Handled via typed PHP Enums (`ProjectStatus`: `Draft`, `ProposalSubmitted`, `UnderSupervision`, `UnderReview`, `Completed`, `Rejected`).

---

### Module 3: AI-Driven Plagiarism & Similarity Detection
* **Purpose:** Detects content overlap, unoriginal submissions, conceptual duplication, and paraphrasing across past and current institutional projects.
* **Functional Scope:**
  * Analyzes uploaded documents against the internal database of completed research.
  * Generates an internal similarity score and itemized similarity report highlighting matching methodology, objectives, and text sections.
  * Restricts access to similarity reports strictly to supervisors, research directors, and administrators.
* **Implementation Mechanism:**
  * **Text Extraction:** Dispatches `ExtractDocumentTextJob` upon file upload using tools like `smalot/pdfparser` or command-line wrappers to extract normalized plain text.
  * **Similarity Engine:** Queued `RunSimilarityAnalysisJob` generates semantic embeddings and compares text chunks against indexed PostgreSQL vector tables or similarity algorithms.
  * **Reporting:** Persists structured results to the `similarity_reports` table, viewable exclusively within the supervisor Filament panel.

---

### Module 4: Research Gap & Trend Analytics
* **Purpose:** Analyzes institutional research history to spotlight over-saturated topics, emerging challenges in each discipline, and unaddressed research opportunities.
* **Functional Scope:**
  * Evaluates completed projects by theme (e.g., Soil Fertility, Maternal & Child Health, Digital Learning & Pedagogy, Governance & Public Policy).
  * Automatically flags saturated research areas and highlights suggested future study topics to guide prospective researchers.
* **Implementation Mechanism:**
  * **Analytics Pipeline:** Scheduled console jobs (`ProcessResearchGapsCommand`) aggregate categorized project abstracts and conclusion/recommendation sections.
  * **Admin/Dean Dashboard:** Visualized via **Filament Widgets** (Chart.js / ApexCharts) illustrating departmental output trends, topic distributions, and keyword heatmaps.

---

### Module 5: Administrative & Academic Governance
* **Purpose:** Provides oversight tools for the Dean, Director of Research, and System Administrators to track departmental performance and manage user roles.
* **Functional Scope:**
  * Global view of all research records, faculty workloads, evaluation archives, and academic performance metrics.
  * Granular Role-Based Access Control (RBAC) management and institutional audit logging.
* **Implementation Mechanism:**
  * **Panel Engine:** Dedicated Filament Admin Panel guarded by multi-auth and spatie/laravel-permission integration.
  * **Audit Trails:** Automatic event logging for document downloads, status transitions, and grade submissions.

#### Who can do what
Each account has one primary role (shown beside the name) and may hold extra duties, for example a senior lecturer who also holds the Repository Librarian role. Roles grant only what the job needs:

| Role | Can do |
| --- | --- |
| Student / Student Researcher | Student portal: their own proposal, milestones and downloads. Can edit a study only while it is still under way. |
| Academic Supervisor | Lecturer portal: reviews, grades and downloads only the students assigned to them; scores challenge entries. |
| Dean of School | Linked to one of the eight schools when the account is created. Sees, grades, downloads and removes only that school's projects, and sees only its research gaps and students. Does not run the Research Office programmes. |
| Director of Research | University-wide: every school's projects, gaps and students, a school-by-school comparison, and every manuscript. Leads the Research Office (challenge, events, research sites, thematic areas). Does not grade, edit or remove research. |
| Repository Librarian | Repository workspace: every uploaded manuscript with its summary, gaps and citation, and downloads for cataloguing or publication. Cannot grade or edit research. Can be given to senior staff as an extra duty. |
| System Administrator | Users (add, set roles, deactivate, remove, sign in as), plus project oversight and the office programmes. Uses the student portal only by signing in as a student. |

Thematic areas belong to the Research Office because they shape the research agenda; the administrator can also manage them. Accounts that authored, graded or recorded research cannot be removed, only deactivated, so the archive never loses records. Signing in as another user is logged, shows a banner on every page, and is not allowed for other administrators.

---

## 2. Technical Prerequisites

Make sure the following local development tools are installed on your macOS machine:

1. **Laravel Herd:** Provides PHP 8.3+, Composer, and Nginx. ([Download Herd](https://herd.laravel.com))
2. **DBngin:** Lightweight local database manager for running PostgreSQL. ([Download DBngin](https://dbngin.com))
3. **TablePlus:** Modern GUI client for database inspection and query execution. ([Download TablePlus](https://tableplus.com))
4. **Node.js & NPM:** For compiling frontend assets.

---

## 3. Installation & Setup Guide

### Step 1: Database Setup with DBngin & TablePlus
1. Open **DBngin** and click **+ New Service**.
2. Select **PostgreSQL**, choose version **16** (or latest), set the port to `5432`, and click **Create**.
3. Click **Start** next to the PostgreSQL service.
4. Open **TablePlus**, create a new connection to **PostgreSQL**:
   * **Host:** `127.0.0.1`
   * **Port:** `5432`
   * **User:** `postgres`
   * **Password:** *(blank or whatever configured in DBngin)*
5. Click **Connect**, then create a new database named `rris_db`.

---

### Step 2: Clone & Configure the Project
Clone the repository into your Herd-managed directory (typically `~/Herd`):

```bash
cd ~/Herd
git clone <repository-url> rris
cd rris
```

Copy the environment configuration file:

```bash
cp .env.example .env
```

Open `.env` in your code editor and update the database settings to connect to DBngin:

```env
APP_NAME=RRIS
APP_URL=http://rris.test

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=rris_db
DB_USERNAME=postgres
DB_PASSWORD=
```

---

### Step 3: Install Dependencies
Run Composer and NPM inside the project directory:

```bash
# Install PHP packages
composer install

# Install Frontend packages
npm install
```

Generate the Laravel application encryption key:

```bash
php artisan key:generate
```

---

### Step 4: Run Migrations & Seeders
Execute the database schema setup along with initial roles and demo users:

```bash
php artisan migrate --seed
```

---

### Step 5: Build Assets & Run Application
Compile frontend assets:

```bash
# For local hot-reload
npm run dev

# Or build production bundle
npm run build
```

Link the local storage disk:

```bash
php artisan storage:link
```

---

### Step 6: Start Queue Worker
Since heavy operations (PDF text parsing, similarity checks, gap processing) run in the background, start a local queue worker:

```bash
php artisan queue:work
```

---

### Step 7: Access the System
Open your browser and navigate to:
* **Student Catalog & Portal:** `http://rris.test`
* **Lecturer & Research Office Panel:** `http://rris.test/supervisor`

### Demo accounts
The seeders create these key accounts (plus more supervisors and researchers for the demo archive). Every seeded account uses the password `password`.

| Role | Name | Email | What to try |
| --- | --- | --- | --- |
| Admin | Admin User | `admin@rris.mu.ac.zm` | Administration → Users: add people, set roles, deactivate or remove accounts, and "Sign in as" anyone who is not an admin |
| Dean | Dr. M. Phiri | `dean@rris.mu.ac.zm` | School of Business Studies only: its projects, gaps and students |
| Dean | Prof. K. Lungu | `dean.agriculture@rris.mu.ac.zm` | School of Agriculture and Natural Resources only |
| Research Director | Prof. E. Banda | `director@rris.mu.ac.zm` | Schools overview across the university and the Research Office: challenge, events, research sites and thematic areas |
| Librarian | Mrs. G. Chileshe | `librarian@rris.mu.ac.zm` | Repository → Uploaded research: every uploaded file with its summary, ready to download |
| Lecturer (supervisor) | Dr. C. Mulenga | `cmulenga@rris.mu.ac.zm` | Reviews advisees, runs originality checks and records rubric decisions |
| Student | Kelvin Mwape | `kelvin@rris.mu.ac.zm` | Student workspace: proposal, milestone uploads and feedback |
| Researcher | Lukundo Bwalya | `lukundo@rris.mu.ac.zm` | Searches the archive and saves records to a folder |

In the `local` environment the sign-in page also has one-click buttons for most of these accounts.

---

## 4. Deploying to Railway (MySQL)

Railway builds the app with Railpack, which detects Laravel from `artisan`, installs Composer and npm dependencies, runs `npm run build`, serves `public/` and runs `php artisan migrate --force` on every start. PHP 8.4 is read from `composer.json`.

1. Create a project with a **MySQL** service and a service for this repository.
2. On the app service, add the variables below (Variables → Raw Editor). `${{MySQL.…}}` are Railway reference variables, so keep the MySQL service named `MySQL` or change the prefix.

   ```env
   APP_NAME=RRIS
   APP_ENV=production
   APP_KEY=base64:...            # output of: php artisan key:generate --show
   APP_DEBUG=false
   APP_URL=https://${{RAILWAY_PUBLIC_DOMAIN}}

   DB_CONNECTION=mysql
   DB_HOST=${{MySQL.MYSQLHOST}}
   DB_PORT=${{MySQL.MYSQLPORT}}
   DB_DATABASE=${{MySQL.MYSQLDATABASE}}
   DB_USERNAME=${{MySQL.MYSQLUSER}}
   DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}

   SESSION_DRIVER=database
   CACHE_STORE=database
   QUEUE_CONNECTION=database
   FILESYSTEM_DISK=local
   LOG_CHANNEL=stderr
   LOG_LEVEL=info

   RAILPACK_PHP_EXTENSIONS=intl,pdo_mysql,zip
   ```

3. Generate a public domain (Settings → Networking), then deploy.
4. Seed the demo archive once from the app service's shell (or with `railway ssh`): `php artisan db:seed --force`.
5. Add a volume mounted at `/app/storage/app` so uploaded manuscripts survive redeploys.
6. Optional: a second service from the same repo with the start command `php artisan queue:work --tries=3` runs similarity checks and gap analysis in the background, with the same variables.

Seeded accounts use the password `password`. Change them, or remove the ones you do not need, before sharing the site.
