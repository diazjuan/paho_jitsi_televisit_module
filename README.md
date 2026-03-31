# PAHO Jitsi Televisit Module

Teleconsultation module for OpenEMR using Jitsi Meet with embedded video views for providers and patients.

## Features

- Automatic Jitsi room generation for Televisit appointments
- Embedded video views in provider interface
- Patient portal integration for joining televisits
- Deterministic room naming (same appointment = same room)
- No core file modifications (fully update-safe)

## Requirements

- OpenEMR 7.0.3 Patch 4 or higher
- PHP 8.3+
- Jitsi Meet instance (configured in module settings)
- MySQL/MariaDB

## Installation

1. **Place Module Files**
   - Ensure all module files are in `/var/www/html/openemr/interface/modules/custom_modules/paho_jitsi_televisit/`

2. **Register Module**
   - Log in as administrator
   - Go to **Admin → Modules**
   - Find "PAHO Jitsi Televisit" in the custom modules list
   - Click **Register**
   - Click **Enable**
   - The SQL migration will run automatically, creating the required table and Televisit category

3. **Configure Module**
   - Go to **Admin → Modules**
   - Find "PAHO Jitsi Televisit" and click **Configure**
   - Set **Jitsi Base URL**: `https://169.61.173.4:8440` (or your Jitsi instance URL)
   - Verify **Televisit Category** is selected (should auto-detect)
   - Click **Save Configuration**

4. **Verify Televisit Category**
   - Go to **Admin → Clinic → Calendar → Categories**
   - Verify "Televisit" category exists with `pc_constant_id = 'televisit'`
   - If missing, the module should have created it automatically

## Usage

### Provider Workflow

1. **Create Televisit Appointment**
   - Go to **Calendar**
   - Create new appointment
   - Select **Category**: "Televisit"
   - Select patient, provider, date, and time
   - Save appointment
   - A Jitsi room is automatically created

2. **Join Televisit**
   - Open the appointment details
   - Click on the **Televisit** panel header to expand
   - Click **Show Embedded View** to display Jitsi in OpenEMR
   - Or click **Open** to open in a new tab
   - Copy the room URL to share with patient if needed

### Patient Portal Workflow

1. **View Appointment**
   - Patient logs into patient portal
   - Navigate to **Appointments**
   - Televisit appointments show a "Televisit" badge
   - Click **Join Televisit** button
   - Embedded Jitsi view opens

## Database Schema

The module creates one table:

- `paho_jitsi_televisit_rooms` - Stores room mappings for appointments

## Configuration

Module settings are stored in the `globals` table:

- `paho_jitsi_base_url` - Base URL of Jitsi instance
- `paho_jitsi_televisit_category_id` - Calendar category ID for Televisit
- `paho_jitsi_room_naming` - Room naming strategy (currently: deterministic)
- `paho_jitsi_jwt_secret` - Future: JWT secret for authentication
- `paho_jitsi_jwt_app_id` - Future: JWT app ID

## Room Naming

Rooms are named deterministically using the format:
```
televisit-{patient_id}-{provider_id}-{date}-{time}
```

Example: `televisit-123-5-20241215-1430`

This ensures the same appointment always gets the same room, even if the appointment is edited.

## Troubleshooting

### Room Not Created
- Verify appointment category is "Televisit"
- Check OpenEMR error logs: `/var/www/html/openemr/logs/`
- Verify database table exists: `SELECT * FROM paho_jitsi_televisit_rooms LIMIT 1;`

### Jitsi Not Loading
- Verify Jitsi Base URL is correct in module configuration
- Check browser console for errors
- Verify Jitsi instance is accessible from the server
- Check Apache/nginx logs for connection errors

### Patient Portal Not Showing Join Button
- Verify appointment is in the future or today
- Check that appointment category is Televisit
- Verify patient is logged into portal
- Check that room exists in database for the appointment

## Testing Checklist

- [ ] Module registers and enables successfully
- [ ] SQL migration runs without errors
- [ ] Televisit category is created/identified
- [ ] Configuration page saves settings
- [ ] Provider can create Televisit appointment
- [ ] Room is created in database
- [ ] Provider can see Join Televisit panel
- [ ] Embedded view loads correctly
- [ ] Patient portal shows Televisit badge
- [ ] Patient can join from portal
- [ ] Provider and patient can see each other in Jitsi

## Security Notes

- Current version uses public Jitsi rooms with deterministic names
- Room names are not easily guessable (include patient/provider IDs)
- Access is restricted to authenticated OpenEMR/portal users
- Future versions will support JWT authentication

## Support

For issues or questions, check:
- OpenEMR error logs: `/var/www/html/openemr/logs/`
- Apache error logs: `/var/log/apache2/error.log`
- Jitsi logs: Check your Jitsi Docker container logs

## License

GPL-3.0

