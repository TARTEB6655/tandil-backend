# Production Setup Guide

## Database Configuration

On production servers (like Cloudways), you **MUST** use MySQL, not SQLite.

### Update `.env` file on production:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

### Steps to Fix the Current Error:

1. **SSH into your Cloudways server**
2. **Navigate to your project directory:**
   ```bash
   cd /home/1180784.cloudwaysapps.com/ecmbnbvxsm/public_html
   ```

3. **Edit the `.env` file:**
   ```bash
   nano .env
   ```

4. **Update database configuration:**
   - Change `DB_CONNECTION=sqlite` to `DB_CONNECTION=mysql`
   - Add your MySQL credentials:
     ```env
     DB_CONNECTION=mysql
     DB_HOST=127.0.0.1
     DB_PORT=3306
     DB_DATABASE=your_database_name
     DB_USERNAME=your_database_user
     DB_PASSWORD=your_database_password
     ```

5. **Get your database credentials from Cloudways:**
   - Log into Cloudways Platform
   - Go to your application
   - Click on "Access Details" or "Database" tab
   - Copy the database credentials

6. **Clear cache:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

7. **Run migrations:**
   ```bash
   php artisan migrate --force
   ```

8. **Seed the database (if needed):**
   ```bash
   php artisan db:seed --force
   ```

## Important Notes:

- **Never use SQLite in production** - it's only for local development
- Always use MySQL/MariaDB on production servers
- Make sure your database exists before running migrations
- Keep your `.env` file secure and never commit it to git

