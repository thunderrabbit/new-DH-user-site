# DreamHost Site Template (MVP Framework)

**Always reference these instructions first and fallback to search or bash commands only when you encounter unexpected information that does not match the info here.**

The DreamHost Site Template is a minimalist PHP web application framework originally developed for DreamHost hosting environments. It features a custom templating engine, database migration system, user authentication, and admin dashboard. The application runs on PHP 8.3+ with MySQL and uses a custom autoloader system.

## Working Effectively

### Bootstrap and Setup
Execute these commands in exact order to set up a working development environment:

1. **Start MySQL service:**
   ```bash
   sudo systemctl start mysql || sudo service mysql start
   ```

2. **Create database and user (for testing):**
   ```bash
   sudo mysql --defaults-file=/etc/mysql/debian.cnf -e "CREATE DATABASE IF NOT EXISTS test_dh_site; CREATE USER IF NOT EXISTS 'testuser'@'localhost' IDENTIFIED BY 'testpass123'; GRANT ALL PRIVILEGES ON test_dh_site.* TO 'testuser'@'localhost'; FLUSH PRIVILEGES;"
   ```

3. **Create Config.php from sample:**
   ```bash
   cp classes/ConfigSample.php classes/Config.php
   ```

4. **Edit Config.php with your database credentials:**
   - Update `$dbHost`, `$dbUser`, `$dbPass`, `$dbName` with your database settings
   - Update `$app_path` to point to your repository root (absolute path)
   - Update `$domain_name` for your environment (localhost for development)

4b. **Fix hardcoded paths for local development:**
   ```bash
   find wwwroot -name "*.php" -exec sed -i 's|/home/dh_fbrdk3/db.marbletrack3.com|'$(pwd)'|g' {} \;
   ```

5. **Start PHP development server:**
   ```bash
   php -S localhost:8080 -t wwwroot
   ```
   **TIMING**: Server starts immediately (< 1 second). Set timeout to 10+ seconds.

### Run and Test the Application
- **Access the application:** Visit `http://localhost:8080`
- **First visit behavior:** Application automatically creates database tables and shows admin user registration form
- **Create admin user:** Fill out the registration form with username/password 
- **Application response time:** < 50ms for all page loads
- **Database migration timing:** < 50ms (runs automatically on first access)

### Key Development Workflows

#### Database Migrations
- **Location:** `db_schemas/` directory with numbered subdirectories (`00_bedrock/`, `01_gumdrop_cloud/`, etc.)
- **Auto-execution:** Migrations run automatically on first application access
- **Manual migration:** Access `/admin/migrate_tables.php` (requires authentication)
- **Timing:** All migrations complete in < 100ms total

#### Template System
- **Templates location:** `templates/` directory
- **Layout templates:** `templates/layout/admin_base.tpl.php`
- **Content templates:** `templates/admin/`, `templates/login/`, etc.
- **Usage:** Templates use PHP with `<?php ?>` tags and variable extraction

#### Authentication System  
- **Cookie-based authentication** stored in database `cookies` table
- **User roles:** admin, user (defined in `users` table)
- **Login protection:** Pages automatically redirect to login if not authenticated

### Validation Scenarios
Always validate changes with these complete end-to-end scenarios:

#### Scenario 1: Fresh Installation
1. Start with empty database
2. Access root URL (`/`)
3. Verify database migration messages appear
4. Verify registration form displays
5. Complete admin user registration
6. Verify "Admin Created" success message
7. Access admin area (`/admin/`) 
8. Verify authentication redirect works

#### Scenario 2: Authenticated User Flow
1. Access login page (`/login/`)
2. Verify login form displays
3. Login with admin credentials
4. Access admin dashboard (`/admin/`)
5. Verify dashboard loads with navigation
6. Test logout functionality (`/logout/`)

#### Scenario 3: Database Operations
1. Check database tables exist: `users`, `cookies`, `applied_DB_versions`
2. Verify migration tracking in `applied_DB_versions` table
3. Test user creation and authentication
4. Verify foreign key constraints work

## Validation and Quality Assurance

### Syntax Checking
**ALWAYS run PHP syntax checks before committing:**
```bash
php -l prepend.php
find classes -name "*.php" -exec php -l {} \;
find wwwroot -name "*.php" -exec php -l {} \;
```
**Timing:** Syntax checks complete in < 5 seconds total

### Manual Testing Requirements
**CRITICAL**: After any code changes, you MUST:
1. Start fresh PHP server
2. Reset database to clean state  
3. Run through complete user registration flow
4. Test admin access and authentication
5. Verify all page loads work without errors
6. Check error logs for warnings/notices

### Configuration Issues
- **Hardcoded paths:** Many files contain hardcoded DreamHost paths (`/home/dh_fbrdk3/db.marbletrack3.com/`). Update these for your environment.
- **Required changes for local development:**
  - Update all `include_once` paths in `wwwroot/` files to use correct absolute paths
  - Configure database credentials in `classes/Config.php`
  - Set proper `$app_path` in Config.php

### Build Process
**NO BUILD PROCESS REQUIRED** - This is a pure PHP application with no compilation or bundling steps.

### Dependencies and Requirements
- **PHP 8.3+** (tested and validated)
- **MySQL 5.7+** or **MariaDB 10.3+**
- **Web server** (Apache, Nginx, or PHP built-in server for development)
- **Extensions:** mysqli, session, password_hash functions

## Common Tasks and File Locations

### Repository Structure
```
.
├── README.md                    # Project documentation
├── prepend.php                  # Bootstrap/initialization file  
├── classes/                     # Core PHP classes
│   ├── Template.php            # Custom templating engine
│   ├── Config.php              # Database and app configuration
│   ├── Database/               # Database layer classes
│   ├── Auth/                   # Authentication classes
│   └── Mlaphp/                 # Autoloader and request handling
├── wwwroot/                    # Public web files (document root)
│   ├── index.php              # Main entry point
│   ├── admin/                 # Admin dashboard pages
│   ├── login/                 # Authentication pages
│   └── css/                   # Stylesheets
├── templates/                  # Template files for views
│   ├── layout/                # Base layout templates
│   ├── admin/                 # Admin interface templates
│   └── login/                 # Login/registration templates
└── db_schemas/                # Database migration files
    ├── 00_bedrock/            # Core database schema
    ├── 01_gumdrop_cloud/      # Authentication tables
    └── 02_workers/            # Example application tables
```

### Frequently Modified Files
- **Core logic:** `prepend.php` (application bootstrap)
- **Configuration:** `classes/Config.php` (database settings, paths)
- **Templates:** `templates/layout/admin_base.tpl.php` (main layout)
- **Admin pages:** `wwwroot/admin/index.php`, templates in `templates/admin/`
- **Authentication:** `classes/Auth/IsLoggedIn.php`

### Key Classes and Their Purpose
- **Template:** Custom templating engine with layout support
- **Database\Database:** MySQL database abstraction layer
- **Auth\IsLoggedIn:** Session and cookie-based authentication
- **Database\DBExistaroo:** Database existence and migration checker
- **Mlaphp\Autoloader:** PSR-0 style class autoloading

### Common Issues and Solutions
- **Database connection errors:** Check Config.php credentials and MySQL service status
- **Hardcoded paths:** Update all `/home/dh_fbrdk3/` references to your actual path
- **Permission errors:** Ensure web server can read all files and write to session directory
- **Migration failures:** Check database user permissions and schema file syntax

### Development Best Practices
- **Always test with fresh database** when developing migration features
- **Use PHP built-in server** for development (`php -S localhost:8080 -t wwwroot`)
- **Check error logs** regularly during development
- **Validate all forms** and user input handling
- **Test authentication flows** after any auth-related changes

## Timing Expectations and Warnings

### Operation Timings (Validated)
- **Application startup:** < 50ms
- **Database connection:** < 10ms  
- **Page rendering:** < 50ms
- **Database migrations:** < 100ms (all schemas)
- **PHP syntax checking:** < 5 seconds (entire codebase)
- **User registration:** < 100ms
- **Authentication check:** < 20ms

### NEVER CANCEL Operations
**NO LONG-RUNNING OPERATIONS** - This application has no build process, compilation, or time-consuming operations. All operations complete in milliseconds to seconds.

**Timeout Recommendations:**
- PHP syntax checks: 60 seconds
- Database operations: 30 seconds  
- Web server startup: 10 seconds
- Application testing: 30 seconds per scenario

All operations in this codebase are designed to be fast and lightweight, typical of a simple PHP web application.