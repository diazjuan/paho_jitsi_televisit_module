# PAHO Jitsi Televisit Module - Installation Manual

## Version: 1.0.0

## Overview

The PAHO Jitsi Televisit module enables healthcare providers to conduct teleconsultations using Jitsi Meet video conferencing directly within OpenEMR. The module automatically creates Jitsi meeting rooms for appointments with the "Televisit" category and provides a unified interface for providers to conduct video consultations while documenting encounters simultaneously.

## Features

- **Automatic Room Creation**: Automatically generates unique Jitsi meeting rooms for Televisit appointments
- **Unified Provider View**: Combines video conference and encounter documentation in a single window
- **Patient Portal Integration**: Patients can join televisits directly from the patient portal
- **Deterministic Room Naming**: Room names are generated based on patient ID, provider ID, and appointment date/time
- **Configurable**: Easy configuration through module settings or manual SQL

## Requirements

- OpenEMR version 7.0.0 or higher
- PHP 7.4 or higher
- MySQL/MariaDB database
- Access to a Jitsi Meet server (self-hosted or Jitsi Meet cloud)
- Browser with camera/microphone support for video conferencing

## Installation Steps

### Step 1: Extract the Module

1. Extract the `paho_jitsi_televisit.zip` file
2. Copy the `paho_jitsi_televisit` folder to your OpenEMR installation:
   ```
   /path/to/openemr/interface/modules/custom_modules/paho_jitsi_televisit/
   ```

### Step 2: Set Permissions

Ensure the module directory has proper permissions:

```bash
chown -R www-data:www-data /path/to/openemr/interface/modules/custom_modules/paho_jitsi_televisit
chmod -R 755 /path/to/openemr/interface/modules/custom_modules/paho_jitsi_televisit
```

### Step 3: Install via Module Manager

1. Log in to OpenEMR as an administrator
2. Navigate to **Administration → Modules → Modules Manager**
3. Find **"PAHO Jitsi Televisit"** in the list
4. Click the **"Install SQL"** button (gray button) first
5. Then click the **"Install"** button (blue button)
6. Click **"Enable"** to activate the module

### Step 4: Configure the Module

#### Option A: Via Module Configuration (Recommended)

1. Navigate to **Administration → Modules → Modules Manager**
2. Find **"PAHO Jitsi Televisit"** and click **"Configure"**
3. Enter the following settings:
   - **Jitsi Base URL**: Your Jitsi Meet server URL (e.g., `https://meet.jit.si` or `https://your-jitsi-server.com`)
   - **Televisit Category ID**: Leave empty initially (will be auto-detected)
   - **Room Naming Strategy**: `deterministic` (default)
   - **JWT Secret**: Leave empty unless using JWT authentication
   - **JWT App ID**: `paho-jitsi-televisit` (default)

4. Click **"Save"**

#### Option B: Manual SQL Configuration

If the configuration page doesn't work, you can configure manually:

```sql
-- Set Jitsi Base URL
UPDATE globals SET gl_value = 'https://your-jitsi-server.com' WHERE gl_name = 'paho_jitsi_base_url';

-- Set Room Naming Strategy
UPDATE globals SET gl_value = 'deterministic' WHERE gl_name = 'paho_jitsi_room_naming';

-- Get Televisit Category ID (after installation)
SELECT pc_catid FROM openemr_postcalendar_categories WHERE pc_constant_id = 'televisit';

-- Set Televisit Category ID
UPDATE globals SET gl_value = '[CATEGORY_ID_FROM_ABOVE]' WHERE gl_name = 'paho_jitsi_televisit_category_id';
```

### Step 5: Verify Installation

1. Check that the "Televisit" category exists:
   - Navigate to **Administration → Calendar → Categories**
   - Look for "Televisit" category

2. Check database tables:
   ```sql
   SHOW TABLES LIKE 'paho_jitsi_televisit%';
   ```
   Should show: `paho_jitsi_televisit_rooms`

3. Check configuration:
   ```sql
   SELECT gl_name, gl_value FROM globals WHERE gl_name LIKE 'paho_jitsi%';
   ```

## Usage

### For Providers

1. **Create a Televisit Appointment**:
   - Navigate to **Calendar**
   - Create a new appointment
   - Select **"Televisit"** as the category
   - Fill in patient, provider, date, and time
   - Save the appointment

2. **Start a Televisit**:
   - Open the appointment from the calendar
   - Expand the **"Televisit"** panel
   - Click **"Open Unified Televisit View"**
   - The unified window will open with:
     - Left side: Jitsi video conference (auto-loads)
     - Right side: Encounter documentation form

3. **Document During Consultation**:
   - The encounter form loads automatically
   - If no encounter exists, a new encounter form will be created
   - Document the visit while conducting the video consultation

### For Patients

1. **Join from Patient Portal**:
   - Log in to the patient portal
   - Navigate to **Appointments**
   - Find the scheduled Televisit appointment
   - Click **"Join Televisit"** button
   - The Jitsi meeting will open in a new window

## Configuration Details

### Jitsi Base URL

- **Self-hosted Jitsi**: `https://your-domain.com` or `https://your-ip:8443`
- **Jitsi Meet Cloud**: `https://meet.jit.si`
- **Custom Jitsi Instance**: Your custom Jitsi server URL

### Room Naming Strategy

- **deterministic**: Creates room names based on patient ID, provider ID, and appointment datetime
  - Format: `televisit-[patient_id]-[provider_id]-[YYYYMMDD]-[HHMM]`
  - Example: `televisit-1-5-20251212-0830`

### Televisit Category

The module automatically creates a "Televisit" category in OpenEMR's calendar system. This category:
- Has a blue color (#0066cc)
- Default duration: 30 minutes
- Category ID is stored in `globals` table

## Troubleshooting

### Module Not Appearing in Module Manager

- Check file permissions
- Verify the module directory is in `interface/modules/custom_modules/`
- Check OpenEMR error logs: `/var/log/openemr/openemr.log`

### SQL Installation Fails

- Check MySQL error logs
- Verify database user has CREATE TABLE permissions
- Manually run `sql/table.sql` if needed

### Configuration Page Blank

- Check PHP error logs
- Verify `src/Services/TelevisitConfigService.php` exists
- Try manual SQL configuration (Option B above)

### Video Not Embedding (Mixed Content)

- **Issue**: Browser blocks HTTPS Jitsi in HTTP OpenEMR
- **Solution**: 
  - Serve OpenEMR over HTTPS, OR
  - Use the popup fallback (automatically provided)
  - The module will show a button to open Jitsi in a new window

### Patient Portal "Join Televisit" Button Not Appearing

- Verify the appointment has "Televisit" category
- Check that the appointment is in the future
- Verify patient portal is enabled
- Check browser console for JavaScript errors

### Room Not Created for Appointment

- Verify appointment has "Televisit" category
- Check `paho_jitsi_televisit_rooms` table for entries
- Check OpenEMR error logs for module errors
- Verify `Bootstrap.php` event listeners are registered

## File Structure

```
paho_jitsi_televisit/
├── manifest.json              # Module metadata
├── version.php                # Version information
├── openemr.bootstrap.php      # Module bootstrap file
├── ModuleManagerListener.php  # Module manager hooks
├── moduleConfig.php           # Configuration UI
├── README.md                  # Module documentation
├── INSTALLATION_MANUAL.md     # This file
├── sql/
│   └── table.sql             # Database schema
├── src/
│   ├── Bootstrap.php         # Event listeners
│   └── Services/
│       ├── TelevisitConfigService.php
│       └── TelevisitRoomService.php
├── public/
│   ├── unified_televisit.php # Unified provider view
│   ├── join_room.php         # Provider join page
│   └── portal_join.php       # Patient portal join page
├── templates/
│   └── portal/
│       └── appointment-item.html.twig
└── assets/
    ├── css/
    └── js/
```

## Database Schema

The module creates one table:

### `paho_jitsi_televisit_rooms`

Stores the mapping between appointments and Jitsi rooms.

**Columns**:
- `id`: Primary key
- `pc_eid`: Appointment ID (foreign key)
- `encounter_id`: Encounter ID (if encounter created)
- `pid`: Patient ID
- `provider_id`: Provider ID
- `room_name`: Jitsi room identifier
- `room_url`: Full Jitsi room URL
- `status`: Room status (scheduled/active/completed/cancelled)
- `created_at`: Creation timestamp
- `started_at`: Start timestamp
- `ended_at`: End timestamp
- `jwt_secret`: Future JWT authentication support

## Security Considerations

1. **Room Names**: Currently uses deterministic naming. For enhanced security, consider:
   - Using random room names
   - Implementing JWT authentication
   - Adding room password protection

2. **Access Control**: 
   - Module respects OpenEMR ACLs
   - Requires "patients/med" permission
   - Patient portal access requires patient authentication

3. **HTTPS**: 
   - Recommended to serve OpenEMR over HTTPS
   - Enables proper video embedding
   - Protects patient data in transit

## Uninstallation

1. Navigate to **Administration → Modules → Modules Manager**
2. Find **"PAHO Jitsi Televisit"**
3. Click **"Disable"**
4. Click **"Uninstall"**
5. Optionally, remove the module directory:
   ```bash
   rm -rf /path/to/openemr/interface/modules/custom_modules/paho_jitsi_televisit
   ```

**Note**: Uninstallation does NOT remove database tables or configuration. To fully remove:

```sql
-- Remove configuration
DELETE FROM globals WHERE gl_name LIKE 'paho_jitsi%';

-- Remove category (optional)
DELETE FROM openemr_postcalendar_categories WHERE pc_constant_id = 'televisit';

-- Remove table (optional - removes all room data)
DROP TABLE IF EXISTS paho_jitsi_televisit_rooms;
```

## Support

For issues, questions, or contributions:

- **Author**: PAIO Medical Systems
- **License**: GPL-3.0
- **Version**: 1.0.0

## Changelog

### Version 1.0.0 (Initial Release)
- Automatic Jitsi room creation for Televisit appointments
- Unified provider view with embedded video and encounter forms
- Patient portal integration
- Configurable Jitsi server URL
- Deterministic room naming
- Database schema and migrations

## License

This module is licensed under the GNU General Public License version 3 (GPL-3.0), compatible with OpenEMR's license.

