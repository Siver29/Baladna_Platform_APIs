# 🎓 Baladna API — Student Setup & Installation Guide

This guide explains **everything you need to install on your computer** and **how to run the Baladna Laravel API** step by step.

---

## 1. What is this project?

Baladna is a **civic issue reporting REST API** built with **Laravel 12**. Citizens submit reports (damaged roads, water leaks, broken streetlights…), and employees update their status. It is an educational MVP to practice building and consuming REST APIs.

---

## 2. What you need to install first

Before you can run the project, install these tools on your device. **Install them in this order.**

| # | Tool | What it is | Why you need it | Download / Install |
|---|------|-----------|-----------------|--------------------|
| 1 | **PHP 8.2+** | The language the backend is written in | Laravel requires PHP | https://www.php.net/downloads |
| 2 | **Composer** | PHP package/dependency manager | Installs Laravel packages (`vendor/`) | https://getcomposer.org/download/ |
| 3 | **MySQL** | Relational database | Stores all data (users, reports, areas…) | https://dev.mysql.com/downloads/installer/ |
| 4 | **Git** | Version control | Clone the repo & push changes | https://git-scm.com/downloads |
| 5 | **Node.js + npm** *(optional)* | JavaScript runtime | Only needed if you build the React frontend | https://nodejs.org/ |

> **Tip for Windows users (XAMPP):** Instead of installing MySQL separately, you can install [XAMPP](https://www.apachefriends.org/) which bundles **Apache + MySQL + PHP** together. Very easy for beginners.

---

## 3. Verify your installation

Open a terminal (Command Prompt / PowerShell / Terminal) and run each command. You should see a version number for each:

```bash
php -v
composer -V
git --version
mysql --version
```

If any command is "not recognized", that tool is not installed or not added to your system PATH.

---

## 4. Get the project code

Clone the repository (or download it as a ZIP):

```bash
git clone <your-repository-url>
cd <project-folder>
```

---

## 5. Install the PHP dependencies

Install all the packages Laravel needs (creates the `vendor/` folder):

```bash
composer install
```

---

## 6. Create your environment file

Copy the example environment file to `.env`:

```bash
cp .env.example .env
```

> On **Windows (Command Prompt)** use: `copy .env.example .env`
>
> On **macOS / Linux** use: `cp .env.example .env`

Then generate the application encryption key:

```bash
php artisan key:generate
```

---

## 7. Configure the database (MySQL)

Open the `.env` file in a text editor and set your MySQL connection:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=baladna
DB_USERNAME=root
DB_PASSWORD=your_mysql_password
```

**Important:**
- Create a database called `baladna` in MySQL first (e.g. via phpMyAdmin or the MySQL command line: `CREATE DATABASE baladna;`).
- Set `DB_USERNAME` and `DB_PASSWORD` to match **your** MySQL credentials.

> **Quick local option (no MySQL needed):** The project can also run on SQLite. Set these in `.env`:
>
> ```env
> DB_CONNECTION=sqlite
> ```
>
> And create an empty file: `database/database.sqlite`

---

## 8. Run the migrations and seeders

Migrations create the database tables. Seeders fill them with demo data (areas, agencies, categories, users, reports, posts).

```bash
php artisan migrate --seed
```

---

## 9. Link the storage folder (for uploaded images)

This creates a public link so uploaded report images are accessible via the browser:

```bash
php artisan storage:link
```

---

## 10. Start the development server

```bash
php artisan serve
```

You should see something like:

```
INFO  Server running on [http://127.0.0.1:8000]
```

The API is now available at:

```
http://127.0.0.1:8000/api/v1
```

---

## 11. Test user accounts

After seeding, you can log in with these accounts (password is `password` for all):

| Role | Email | Password |
|------|-------|----------|
| 👑 Admin | `admin@baladna.test` | `password` |
| 🛠️ Employee | `employee@baladna.test` | `password` |
| 👤 Citizen | `citizen@baladna.test` | `password` |

---

## 12. Test the API (quick check)

Open your browser and visit:

```
http://127.0.0.1:8000/api/v1/areas
```

You should see a JSON list of areas. This confirms the API is running.

To test authenticated endpoints, use the **Postman collection** included in the project (`Baladna.postman_collection.json`) or a tool like **Postman** / **Bruno** / **Insomnia**.

---

## 13. Run the automated tests

To make sure everything works:

```bash
php artisan test
```

You should see **49 passed** tests (1 skipped if the GD image extension is not installed).

---

## 14. Common problems & fixes

| Problem | Likely cause | Solution |
|---------|-------------|----------|
| `composer: command not found` | Composer not installed/PATH | Reinstall & restart terminal |
| `php: command not found` | PHP not in PATH | Add PHP to PATH or use XAMPP |
| `SQLSTATE[HY000] [1045] Access denied` | Wrong MySQL credentials | Fix `DB_USERNAME` / `DB_PASSWORD` in `.env` |
| `Unknown database 'baladna'` | Database not created | Create the `baladna` database in MySQL |
| `storage:link` already exists | Already linked | Ignore it, or delete the link first |
| `GD extension is not installed` (test skip) | PHP GD missing | Optional — enable GD extension in `php.ini` |
| Port 8000 already in use | Another server running | Use `php artisan serve --port=8080` |

---

## 15. Quick command cheat-sheet

```bash
composer install                 # Install dependencies
php artisan key:generate         # Generate app key
php artisan migrate --seed       # Create tables + demo data
php artisan storage:link         # Public link for images
php artisan serve                # Start the server
php artisan test                 # Run automated tests
php artisan migrate:fresh --seed # Reset DB + reseed
```

---

## 16. Next steps (React frontend)

The API is designed to be consumed from a separate **React** app. The README contains an Axios integration guide with examples for login, fetching reports, uploading images with `FormData`, and handling validation errors.

Base URL for the React app: `http://127.0.0.1:8000/api/v1`

CORS is already configured to allow `http://localhost:3000` and `http://localhost:5173`.

---

If you get stuck, re-read the relevant section above and check the **README.md** for full API documentation.
