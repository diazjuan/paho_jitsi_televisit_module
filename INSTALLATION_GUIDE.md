# PAHO Jitsi Televisit Module - Installation Guide
## OpenEMR 7.0.3 Patch 4

This guide provides step-by-step instructions for installing the PAHO Jitsi Televisit module on both Docker and bare metal installations of OpenEMR 7.0.3 Patch 4.

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Installation Methods](#installation-methods)
   - [Docker Installation](#docker-installation)
   - [Bare Metal Installation](#bare-metal-installation)
3. [SQL Scripts Execution](#sql-scripts-execution)
4. [Module Configuration](#module-configuration)
5. [Jitsi Server Configuration](#jitsi-server-configuration)
6. [Verification](#verification)
7. [Troubleshooting](#troubleshooting)

---

## Prerequisites

Before installing the module, ensure you have:

- **OpenEMR 7.0.3 Patch 4** installed and running
- **MySQL/MariaDB** database access (root or database admin credentials)
- **PHP 7.4+** with required extensions (already included in OpenEMR)
- **Jitsi Meet server** installed and accessible (see [Jitsi Configuration Guide](#jitsi-server-configuration))
- **File system write permissions** for the OpenEMR installation directory
- **Backup** of your OpenEMR database and files (recommended)

---

## Installation Methods

### Docker Installation

#### Step 1: Access the Container

```bash
# Find your OpenEMR container name
docker ps | grep openemr

# Access the container (replace 'openemr-container' with your container name)
docker exec -it openemr-container bash
```

#### Step 2: Navigate to OpenEMR Directory

```bash
# Navigate to OpenEMR root (usually /var/www/localhost/htdocs/openemr or similar)
cd /var/www/localhost/htdocs/openemr

# Or if using a different path, find it:
find / -name "globals.php" -type f 2>/dev/null | grep openemr | head -1
```

#### Step 3: Extract Module Files

```bash
# Create the custom modules directory if it doesn't exist
mkdir -p interface/modules/custom_modules

# Extract the module ZIP file
# If you have the ZIP file in the container:
unzip paho_jitsi_televisit_module.zip -d interface/modules/custom_modules/

# Or copy files manually if mounted:
# Files should be placed in: interface/modules/custom_modules/paho_jitsi_televisit/
```

#### Step 4: Set Permissions

```bash
# Set proper ownership (usually www-data or apache)
chown -R www-data:www-data interface/modules/custom_modules/paho_jitsi_televisit

# Set directory permissions
find interface/modules/custom_modules/paho_jitsi_televisit -type d -exec chmod 755 {} \;

# Set file permissions
find interface/modules/custom_modules/paho_jitsi_televisit -type f -exec chmod 644 {} \;

# Make executable files executable
chmod +x interface/modules/custom_modules/paho_jitsi_televisit/*.php 2>/dev/null || true
```

#### Step 5: Exit Container and Access Database

```bash
# Exit the container
exit

# Access MySQL in the database container (or use host MySQL)
docker exec -it mysql-container mysql -u root -p openemr

# Or if using docker-compose with external MySQL:
mysql -h <mysql-host> -u root -p openemr
```

#### Step 6: Execute SQL Scripts

See [SQL Scripts Execution](#sql-scripts-execution) section below.

#### Step 7: Restart Services

```bash
# Restart Apache/PHP-FPM in the container
docker exec openemr-container service apache2 restart
# Or
docker exec openemr-container service php-fpm restart

# Or restart the entire container
docker restart openemr-container
```

---

### Bare Metal Installation

#### Step 1: Navigate to OpenEMR Directory

```bash
# Navigate to your OpenEMR installation directory
# Common locations:
# - /var/www/html/openemr
# - /usr/share/openemr
# - /opt/openemr

cd /var/www/html/openemr  # Adjust path as needed
```

#### Step 2: Extract Module Files

```bash
# Create the custom modules directory if it doesn't exist
sudo mkdir -p interface/modules/custom_modules

# Extract the module ZIP file
sudo unzip paho_jitsi_televisit_module.zip -d interface/modules/custom_modules/

# Verify files were extracted
ls -la interface/modules/custom_modules/paho_jitsi_televisit/
```

#### Step 3: Set Permissions

```bash
# Set proper ownership (usually www-data, apache, or httpd)
# Find your web server user:
ps aux | grep -E 'apache|httpd|nginx' | grep -v grep | head -1

# Set ownership (replace 'www-data' with your web server user)
sudo chown -R www-data:www-data interface/modules/custom_modules/paho_jitsi_televisit

# Or if using apache user:
sudo chown -R apache:apache interface/modules/custom_modules/paho_jitsi_televisit

# Set directory permissions
sudo find interface/modules/custom_modules/paho_jitsi_televisit -type d -exec chmod 755 {} \;

# Set file permissions
sudo find interface/modules/custom_modules/paho_jitsi_televisit -type f -exec chmod 644 {} \;

# Make executable files executable
sudo chmod +x interface/modules/custom_modules/paho_jitsi_televisit/*.php 2>/dev/null || true
```

#### Step 4: Execute SQL Scripts

See [SQL Scripts Execution](#sql-scripts-execution) section below.

#### Step 5: Restart Web Server

```bash
# For Apache:
sudo systemctl restart apache2
# Or
sudo systemctl restart httpd

# For Nginx + PHP-FPM:
sudo systemctl restart php-fpm
sudo systemctl restart nginx
```

---

## SQL Scripts Execution

**IMPORTANT:** Execute SQL scripts in the exact order specified below. Each script builds upon the previous one.

### Prerequisites

1. **Backup your database** before running any SQL scripts:
   ```bash
   mysqldump -u root -p openemr > openemr_backup_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **Ensure Spanish (Latin American) language is installed** in OpenEMR:
   - Log into OpenEMR as administrator
   - Go to: **Administration → Language → Add Language**
   - Add "Spanish (Latin American)" if not already present

### SQL Script Execution Order

#### SQL Script 1: Module Database Schema
**File:** `sql/01_module_schema.sql`

**Purpose:** Creates the database tables and initial configuration for the module.

**Execution:**
```bash
# Via MySQL command line:
mysql -u root -p openemr < sql/01_module_schema.sql

# Via phpMyAdmin:
# 1. Select your OpenEMR database
# 2. Click "Import" tab
# 3. Choose file: sql/01_module_schema.sql
# 4. Click "Go"
```

**Expected Output:** No errors. Tables `paho_jitsi_televisit_rooms` should be created.

**Verification:**
```sql
SHOW TABLES LIKE 'paho_jitsi%';
SELECT * FROM globals WHERE gl_name LIKE 'paho_jitsi%';
```

---

#### SQL Script 2: PAHO Theme Configuration (Optional)
**File:** `sql/02_paho_theme.sql`

**Purpose:** Configures the PAHO telehealth login theme and sets Spanish as default language.

**Execution:**
```bash
mysql -u root -p openemr < sql/02_paho_theme.sql
```

**Note:** This script requires the login theme template file to be installed (see [Login Screen Installation](#login-screen-installation)).

**Verification:**
```sql
SELECT gl_name, gl_value FROM globals 
WHERE gl_name IN ('login_page_layout', 'language_default');
```

---

#### SQL Script 3: Spanish Translations
**File:** `sql/03_spanish_translations.sql`

**Purpose:** Adds/updates all Spanish translations for the module and common UI elements.

**Execution:**
```bash
mysql -u root -p openemr < sql/03_spanish_translations.sql
```

**Note:** This script is large (4000+ lines). If you encounter timeout errors:

**Option A: Increase MySQL timeout:**
```sql
SET SESSION max_execution_time = 0;
SET SESSION interactive_timeout = 0;
SOURCE sql/03_spanish_translations.sql;
```

**Option B: Split execution (if provided):**
```bash
# If the file is split into parts:
mysql -u root -p openemr < sql/03_spanish_translations_part1.sql
mysql -u root -p openemr < sql/03_spanish_translations_part2.sql
mysql -u root -p openemr < sql/03_spanish_translations_part3.sql
```

**Verification:**
```sql
SELECT COUNT(*) as translation_count 
FROM lang_definitions ld
JOIN lang_languages ll ON ld.lang_id = ll.lang_id
WHERE ll.lang_description = 'Spanish (Latin American)';
```

---

#### SQL Script 4: Security Hardening (Optional)
**File:** `sql/04_security_hardening.sql`

**Purpose:** Applies security hardening configurations (hides SQL queries, sets password policies, etc.).

**Execution:**
```bash
mysql -u root -p openemr < sql/04_security_hardening.sql
```

**Note:** Review the script before running. It updates security settings that may affect your installation.

**Verification:**
```sql
SELECT gl_name, gl_value FROM globals 
WHERE gl_name IN ('sql_string_no_show_screen', 'password_max_failed_logins');
```

---

## Module Configuration

### Step 1: Enable the Module

1. Log into OpenEMR as administrator
2. Navigate to: **Administration → Modules → Manage Modules**
3. Find "PAHO Jitsi Televisit" in the list
4. Click **"Install"** or **"Enable"** button
5. Wait for the module to be installed (may take 30-60 seconds)

### Step 2: Configure Module Settings

1. Navigate to: **Administration → Modules → PAHO Jitsi Televisit → Configuration**
2. Configure the following settings:

   - **Jitsi Base URL:** 
     - Format: `https://your-jitsi-server:8440`
     - Example: `https://169.61.173.4:8440`
     - **IMPORTANT:** Must be HTTPS if OpenEMR is on HTTPS

   - **Televisit Category ID:** 
     - Usually auto-populated after SQL Script 1
     - If empty, check: `SELECT gl_value FROM globals WHERE gl_name = 'paho_jitsi_televisit_category_id';`

   - **Room Naming:** 
     - Options: `deterministic` (recommended) or `random`
     - `deterministic`: Room name based on appointment details
     - `random`: Random room name for each appointment

   - **JWT Secret** (Optional):
     - Only needed if Jitsi is configured with JWT authentication
     - Leave empty if not using JWT

3. Click **"Save"**

### Step 3: Set Module Permissions

1. Navigate to: **Administration → Access Control → ACL**
2. Find "PAHO Jitsi Televisit" in the module list
3. Assign appropriate permissions to user groups:
   - **Providers/Staff:** Full access
   - **Patients:** Portal access only (if using patient portal)

---

## Jitsi Server Configuration

See the separate **JITSI_CONFIGURATION_GUIDE.md** file for detailed Jitsi server setup instructions.

**Quick Checklist:**
- [ ] Jitsi Meet server installed and running
- [ ] HTTPS enabled on Jitsi server
- [ ] Port 8440 (or your chosen port) accessible
- [ ] Firewall rules configured
- [ ] SSL certificate installed
- [ ] Jitsi base URL configured in OpenEMR module settings

---

## Verification

### 1. Module Installation Check

```sql
-- Check if module tables exist
SHOW TABLES LIKE 'paho_jitsi%';

-- Check module configuration
SELECT * FROM globals WHERE gl_name LIKE 'paho_jitsi%';

-- Check if Televisit category exists
SELECT * FROM openemr_postcalendar_categories WHERE pc_constant_id = 'televisit';
```

### 2. UI Verification

1. Log into OpenEMR
2. Navigate to: **Calendar → Add New Appointment**
3. Verify "Televisit" appears in the appointment category dropdown
4. Create a test televisit appointment
5. Open the appointment and verify "Launch Televisit" button appears

### 3. Module Functionality Test

1. Create a televisit appointment with a test patient
2. Click "Launch Televisit" from the appointment
3. Verify Jitsi room opens (may open in separate window if on HTTPS)
4. Test video/audio functionality

---

## Troubleshooting

### Module Not Appearing in Module List

**Symptoms:** Module doesn't show in Administration → Modules

**Solutions:**
1. Verify files are in correct location: `interface/modules/custom_modules/paho_jitsi_televisit/`
2. Check file permissions (should be readable by web server)
3. Verify `manifest.json` exists and is valid JSON
4. Clear OpenEMR cache: Delete `sites/default/documents/cache/` contents
5. Check Apache/PHP error logs

### SQL Script Errors

**Symptoms:** SQL script fails with errors

**Solutions:**
1. Verify you're using the correct database name
2. Check MySQL user has CREATE, INSERT, UPDATE permissions
3. Ensure OpenEMR 7.0.3 Patch 4 is installed (check version)
4. Review error message and check if tables already exist
5. Try running scripts individually to identify which one fails

### Jitsi Room Not Opening

**Symptoms:** "Launch Televisit" button doesn't work or shows errors

**Solutions:**
1. Verify Jitsi base URL is correct and accessible
2. Check browser console for errors (F12)
3. Verify HTTPS if OpenEMR is on HTTPS (mixed content issues)
4. Check Jitsi server logs
5. Verify appointment has a valid patient and provider assigned

### Spanish Translations Not Showing

**Symptoms:** UI still shows English text

**Solutions:**
1. Verify Spanish (Latin American) language is installed
2. Check user's language preference in profile
3. Verify SQL Script 3 executed successfully
4. Check translations exist:
   ```sql
   SELECT * FROM lang_definitions ld
   JOIN lang_constants lc ON ld.cons_id = lc.cons_id
   JOIN lang_languages ll ON ld.lang_id = ll.lang_id
   WHERE ll.lang_description = 'Spanish (Latin American)'
   AND lc.constant_name = 'Televisit';
   ```

### Permission Denied Errors

**Symptoms:** "Permission denied" when accessing module files

**Solutions:**
1. Check file ownership: `ls -la interface/modules/custom_modules/paho_jitsi_televisit/`
2. Set correct ownership: `chown -R www-data:www-data interface/modules/custom_modules/paho_jitsi_televisit`
3. Set correct permissions: `find interface/modules/custom_modules/paho_jitsi_televisit -type d -exec chmod 755 {} \;`

---

## Support

For additional support:
- Review module documentation: `INSTALLATION_MANUAL.md`
- Check troubleshooting guide: `HTTPS_TROUBLESHOOTING.md`
- Review OpenEMR logs: `sites/default/logs/`
- Check Apache/PHP error logs

---

## Appendix: File Structure

After installation, your module should have this structure:

```
interface/modules/custom_modules/paho_jitsi_televisit/
├── assets/
│   ├── css/
│   │   └── televisit.css
│   └── js/
│       └── televisit.js
├── public/
│   ├── join_room.php
│   ├── portal_join.php
│   ├── test_portal.php
│   └── unified_televisit.php
├── sql/
│   ├── install.sql
│   ├── table.sql
│   └── fix_https_urls.sql
├── src/
│   ├── Bootstrap.php
│   └── Services/
│       ├── TelevisitConfigService.php
│       └── TelevisitRoomService.php
├── templates/
│   └── portal/
│       └── appointment-item.html.twig
├── manifest.json
├── moduleConfig.php
├── openemr.bootstrap.php
├── version.php
└── README.md
```

---

**Installation Complete!** 🎉

Your PAHO Jitsi Televisit module should now be installed and ready to use.

