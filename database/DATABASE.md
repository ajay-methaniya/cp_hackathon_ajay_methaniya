# MySQL setup (required before login / register)

The app expects a database named **`cp_promptx`** with the tables from **`schema.sql`**. Until this exists (and `.env` matches your MySQL user), registration will fail.

## Option A — phpMyAdmin

1. Open **phpMyAdmin** → **Databases** → **Create database**
   - Name: **`cp_promptx`**
   - Collation: **`utf8mb4_unicode_ci`**
2. Select **`cp_promptx`** in the left sidebar.
3. Open the **Import** tab → **Choose file** → select **`database/schema.sql`** from this project → **Go**.

   Or use the **SQL** tab, paste the full contents of **`database/schema.sql`**, and run it.

## Option B — command line

```bash
mysql -u root -p < database/schema.sql
```

(Use your MySQL root password when prompted.)

## Match `.env` to your server

Edit **`.env`** so PHP can connect:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cp_promptx
DB_USERNAME=root
DB_PASSWORD=your_mysql_password_here
```

If phpMyAdmin logs in as `root` with a password, that same password must appear in **`DB_PASSWORD`**.

After importing, try **Register** again with a **valid email** (e.g. `you@example.com`) and a password **at least 8 characters**.

### Updates after pulling newer code

If you see errors about a missing column, run migrations in order, e.g.:

```bash
mysql -u root -p cp_promptx < database/migrations/006_add_last_error_to_calls.sql
mysql -u root -p cp_promptx < database/migrations/007_add_sales_question_coverage.sql
mysql -u root -p cp_promptx < database/migrations/008_add_whisper_language_hint.sql
```
