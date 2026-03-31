# Jitsi Meet Server Configuration Guide
## For PAHO Jitsi Televisit Module

This guide provides step-by-step instructions for configuring a Jitsi Meet server to work with the PAHO Jitsi Televisit module in OpenEMR.

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Installation Methods](#installation-methods)
   - [Docker Installation](#docker-installation)
   - [Bare Metal Installation](#bare-metal-installation)
3. [HTTPS Configuration](#https-configuration)
4. [Firewall Configuration](#firewall-configuration)
5. [OpenEMR Integration](#openemr-integration)
6. [Testing](#testing)
7. [Troubleshooting](#troubleshooting)

---

## Prerequisites

Before configuring Jitsi, ensure you have:

- **Server with Ubuntu 20.04+ or Debian 11+** (recommended)
- **Root or sudo access** to the server
- **Domain name or IP address** for the Jitsi server
- **SSL certificate** (Let's Encrypt recommended for HTTPS)
- **Minimum 2GB RAM** and 2 CPU cores
- **Ports 80, 443, 8440, 10000/udp** available

---

## Installation Methods

### Docker Installation

#### Step 1: Install Docker and Docker Compose

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Verify installation
docker --version
docker-compose --version
```

#### Step 2: Create Jitsi Configuration

```bash
# Create directory for Jitsi
mkdir -p ~/jitsi-docker
cd ~/jitsi-docker

# Create docker-compose.yml
cat > docker-compose.yml << 'EOF'
version: '3.8'

services:
    web:
        image: jitsi/web:latest
        restart: unless-stopped
        ports:
            - '${HTTP_PORT}:80'
            - '${HTTPS_PORT}:443'
            - '8440:8440'
        volumes:
            - ${CONFIG}/web:/config
            - ${CONFIG}/web/letsencrypt:/etc/letsencrypt
            - ${CONFIG}/web/logs:/var/log/nginx
        environment:
            - ENABLE_AUTH
            - ENABLE_GUESTS
            - ENABLE_LETSENCRYPT
            - ENABLE_HTTP_REDIRECT
            - ENABLE_TRANSCRIPTIONS
            - DISABLE_HTTPS
            - JICOFO_AUTH_USER
            - LETSENCRYPT_DOMAIN
            - LETSENCRYPT_EMAIL
            - PUBLIC_URL
            - XMPP_DOMAIN
            - XMPP_AUTH_DOMAIN
            - XMPP_GUEST_DOMAIN
            - XMPP_MUC_DOMAIN
            - XMPP_RECORDER_DOMAIN
            - XMPP_MODULES
            - XMPP_MUC_MODULES
            - XMPP_INTERNAL_MUC_DOMAIN
            - JVB_BREWERY_MUC
            - JVB_PORT
            - JVB_TCP_PORT
            - JVB_TCP_HARVESTER_DISABLED
            - JVB_TCP_MAPPED_PORT
            - JVB_STUN_SERVERS
            - JVB_ENABLE_APIS
            - ENABLE_COLIBRI_WEBSOCKET
            - TZ
            - JIBRI_BREWERY_MUC
            - JIBRI_PENDING_TIMEOUT
            - JIBRI_XMPP_USER
            - JIBRI_XMPP_PASSWORD
            - JIBRI_RECORDER_USER
            - JIBRI_RECORDER_PASSWORD
            - JIBRI_RECORDING_DIR
            - JIBRI_FINALIZE_RECORDING_SCRIPT_PATH
            - JIBRI_XMPP_DOMAIN
            - JIBRI_BREWERY_MUC
            - JIBRI_PENDING_TIMEOUT
            - ENABLE_RECORDING
        networks:
            meet.jitsi:
                aliases:
                    - ${XMPP_DOMAIN}

    prosody:
        image: jitsi/prosody:latest
        restart: unless-stopped
        volumes:
            - ${CONFIG}/prosody:/config
        environment:
            - AUTH_TYPE
            - ENABLE_AUTH
            - ENABLE_GUESTS
            - GLOBAL_MODULES
            - GLOBAL_CONFIG
            - LDAP_URL
            - LDAP_BASE
            - LDAP_BINDDN
            - LDAP_BINDPW
            - LDAP_FILTER
            - LDAP_AUTH_METHOD
            - LDAP_VERSION
            - LDAP_USE_TLS
            - LDAP_TLS_CIPHERS
            - LDAP_TLS_CHECK_PEER
            - LDAP_TLS_CACERT_FILE
            - LDAP_TLS_CACERT_DIR
            - LDAP_START_TLS
            - XMPP_DOMAIN
            - XMPP_AUTH_DOMAIN
            - XMPP_GUEST_DOMAIN
            - XMPP_MUC_DOMAIN
            - XMPP_INTERNAL_MUC_DOMAIN
            - XMPP_MODULES
            - XMPP_MUC_MODULES
            - XMPP_RECORDER_DOMAIN
            - JICOFO_COMPONENT_SECRET
            - JICOFO_AUTH_USER
            - JICOFO_AUTH_PASSWORD
            - JVB_AUTH_USER
            - JVB_AUTH_PASSWORD
            - JIGASI_XMPP_USER
            - JIGASI_XMPP_PASSWORD
            - JIBRI_XMPP_USER
            - JIBRI_XMPP_PASSWORD
            - JIBRI_RECORDER_USER
            - JIBRI_RECORDER_PASSWORD
            - JWT_APP_ID
            - JWT_APP_SECRET
            - JWT_ACCEPTED_ISSUERS
            - JWT_ACCEPTED_AUDIENCES
            - JWT_ASAP_KEYSERVER
            - JWT_ALLOW_EMPTY
            - JWT_AUTH_TYPE
            - JWT_TOKEN_AUTH_MODULE
            - LOG_LEVEL
        networks:
            meet.jitsi:
                aliases:
                    - ${XMPP_DOMAIN}
                    - ${XMPP_AUTH_DOMAIN}
                    - ${XMPP_MUC_DOMAIN}
                    - ${XMPP_INTERNAL_MUC_DOMAIN}

    jicofo:
        image: jitsi/jicofo:latest
        restart: unless-stopped
        volumes:
            - ${CONFIG}/jicofo:/config
        environment:
            - ENABLE_AUTH
            - XMPP_DOMAIN
            - XMPP_AUTH_DOMAIN
            - XMPP_INTERNAL_MUC_DOMAIN
            - JICOFO_AUTH_USER
            - JICOFO_AUTH_PASSWORD
            - JICOFO_ENABLE_BRIDGE_CHANNEL
            - JICOFO_BRIDGE_CHANNEL_LAST_N
            - JICOFO_BRIDGE_MUC
            - JICOFO_ENABLE_HEALTH_CHECKS
            - JVB_BREWERY_MUC
            - JIGASI_BREWERY_MUC
            - JIGASI_ENABLE_SIP_HEALTH_CHECK
            - TZ
        depends_on:
            - prosody
        networks:
            meet.jitsi:

    jvb:
        image: jitsi/jvb:latest
        restart: unless-stopped
        ports:
            - '${JVB_PORT}/udp:10000/udp'
            - '${JVB_TCP_PORT}:4443/tcp'
        volumes:
            - ${CONFIG}/jvb:/config
        environment:
            - DOCKER_HOST_ADDRESS
            - XMPP_AUTH_DOMAIN
            - XMPP_INTERNAL_MUC_DOMAIN
            - JVB_AUTH_USER
            - JVB_AUTH_PASSWORD
            - JVB_BREWERY_MUC
            - JVB_PORT
            - JVB_TCP_PORT
            - JVB_TCP_HARVESTER_DISABLED
            - JVB_TCP_MAPPED_PORT
            - JVB_STUN_SERVERS
            - JVB_ENABLE_APIS
            - ENABLE_COLIBRI_WEBSOCKET
            - TZ
        depends_on:
            - prosody
        networks:
            meet.jitsi:

networks:
    meet.jitsi:
EOF

# Create .env file
cat > .env << 'EOF'
# Jitsi Configuration
CONFIG=~/.jitsi-meet-cfg
HTTP_PORT=80
HTTPS_PORT=443
JVB_PORT=10000
JVB_TCP_PORT=4443

# Domain Configuration
# Replace with your domain or IP
PUBLIC_URL=https://169.61.173.4:8440
XMPP_DOMAIN=meet.jitsi
XMPP_AUTH_DOMAIN=auth.meet.jitsi
XMPP_GUEST_DOMAIN=guest.meet.jitsi
XMPP_MUC_DOMAIN=muc.meet.jitsi
XMPP_INTERNAL_MUC_DOMAIN=internal-muc.meet.jitsi
XMPP_RECORDER_DOMAIN=recorder.meet.jitsi

# Authentication (set to 1 to enable)
ENABLE_AUTH=0
ENABLE_GUESTS=1

# Let's Encrypt (set to 1 to enable)
ENABLE_LETSENCRYPT=0
LETSENCRYPT_DOMAIN=
LETSENCRYPT_EMAIL=

# Other settings
ENABLE_HTTP_REDIRECT=1
ENABLE_TRANSCRIPTIONS=0
DISABLE_HTTPS=0
TZ=UTC
EOF

# Edit .env file with your settings
nano .env
```

#### Step 3: Start Jitsi

```bash
# Create config directory
mkdir -p ~/.jitsi-meet-cfg/{web,prosody,jicofo,jvb}

# Start Jitsi
docker-compose up -d

# Check logs
docker-compose logs -f
```

---

### Bare Metal Installation

#### Step 1: Add Jitsi Repository

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Add Jitsi repository
echo 'deb https://download.jitsi.org stable/' | sudo tee /etc/apt/sources.list.d/jitsi-stable.list
wget -qO - https://download.jitsi.org/jitsi-key.gpg.key | sudo apt-key add -

# Update package list
sudo apt update
```

#### Step 2: Install Jitsi Meet

```bash
# Install Jitsi Meet (full installation)
sudo apt install -y jitsi-meet

# During installation, you'll be prompted for:
# - Domain name: Enter your domain or IP (e.g., 169.61.173.4)
# - SSL certificate: Choose "Generate a new self-signed certificate" or use Let's Encrypt
```

#### Step 3: Configure HTTPS (if using Let's Encrypt)

```bash
# If you chose Let's Encrypt during installation, it's already configured
# If not, run:
sudo /usr/share/jitsi-meet/scripts/install-letsencrypt-cert.sh
```

#### Step 4: Configure Custom Port (8440)

Edit Nginx configuration:

```bash
sudo nano /etc/nginx/sites-available/meet.jitsi
```

Add or modify:

```nginx
server {
    listen 8440 ssl http2;
    server_name 169.61.173.4;  # Replace with your IP/domain

    ssl_certificate /etc/letsencrypt/live/your-domain/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain/privkey.pem;

    # Or for self-signed:
    # ssl_certificate /etc/jitsi/meet/your-domain.crt;
    # ssl_certificate_key /etc/jitsi/meet/your-domain.key;

    location / {
        proxy_pass http://localhost:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Reload Nginx:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

---

## HTTPS Configuration

### Using Let's Encrypt (Recommended)

```bash
# Install certbot
sudo apt install -y certbot

# Get certificate
sudo certbot certonly --standalone -d your-domain.com

# Or for IP address (requires DNS setup):
# You'll need to use a self-signed certificate or configure DNS
```

### Using Self-Signed Certificate

```bash
# Generate self-signed certificate
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/jitsi-selfsigned.key \
  -out /etc/ssl/certs/jitsi-selfsigned.crt

# Configure Nginx to use it (see Nginx configuration above)
```

**Note:** Self-signed certificates will show security warnings in browsers. For production, use Let's Encrypt or a commercial certificate.

---

## Firewall Configuration

```bash
# Allow HTTP
sudo ufw allow 80/tcp

# Allow HTTPS
sudo ufw allow 443/tcp

# Allow custom port (8440)
sudo ufw allow 8440/tcp

# Allow JVB UDP port (for video)
sudo ufw allow 10000/udp

# Enable firewall
sudo ufw enable

# Check status
sudo ufw status
```

---

## OpenEMR Integration

### Step 1: Configure Jitsi Base URL in OpenEMR

1. Log into OpenEMR as administrator
2. Navigate to: **Administration → Modules → PAHO Jitsi Televisit → Configuration**
3. Set **Jitsi Base URL** to: `https://your-jitsi-server:8440`
   - Example: `https://169.61.173.4:8440`
4. Click **"Save"**

### Step 2: Verify Connection

Test the connection from OpenEMR:

```bash
# From OpenEMR server, test connectivity:
curl -k https://169.61.173.4:8440

# Should return HTML (Jitsi Meet interface)
```

---

## Testing

### Test 1: Access Jitsi Web Interface

1. Open browser and navigate to: `https://your-jitsi-server:8440`
2. You should see the Jitsi Meet interface
3. Enter a room name and click "Go"
4. Allow camera/microphone permissions
5. Verify video/audio works

### Test 2: Test from OpenEMR

1. Create a televisit appointment in OpenEMR
2. Click "Launch Televisit" button
3. Verify Jitsi room opens (may open in separate window if on HTTPS)
4. Test video/audio functionality

### Test 3: Network Connectivity

```bash
# Test from OpenEMR server
ping your-jitsi-server

# Test HTTPS port
curl -k -v https://your-jitsi-server:8440

# Test UDP port (JVB)
nc -u -v your-jitsi-server 10000
```

---

## Troubleshooting

### Jitsi Not Accessible

**Symptoms:** Cannot access Jitsi web interface

**Solutions:**
1. Check if Jitsi is running:
   ```bash
   # Docker
   docker ps | grep jitsi
   
   # Bare metal
   sudo systemctl status jitsi-videobridge2
   sudo systemctl status prosody
   sudo systemctl status jicofo
   ```

2. Check firewall:
   ```bash
   sudo ufw status
   ```

3. Check Nginx/Apache:
   ```bash
   sudo systemctl status nginx
   sudo nginx -t
   ```

4. Check logs:
   ```bash
   # Docker
   docker-compose logs
   
   # Bare metal
   sudo journalctl -u jitsi-videobridge2 -f
   sudo tail -f /var/log/nginx/error.log
   ```

### SSL Certificate Errors

**Symptoms:** Browser shows SSL certificate warnings

**Solutions:**
1. For self-signed certificates: This is expected. Click "Advanced" → "Proceed"
2. For Let's Encrypt: Verify certificate is valid:
   ```bash
   sudo certbot certificates
   ```

3. Renew certificate if expired:
   ```bash
   sudo certbot renew
   ```

### Video/Audio Not Working

**Symptoms:** Video or audio doesn't work in Jitsi

**Solutions:**
1. Check browser permissions (camera/microphone)
2. Check firewall allows UDP port 10000
3. Check JVB is running:
   ```bash
   sudo systemctl status jitsi-videobridge2
   ```

4. Check STUN servers in configuration:
   ```bash
   sudo nano /etc/jitsi/videobridge/config
   # Look for: org.jitsi.videobridge.STUN_SERVERS
   ```

### Mixed Content Errors

**Symptoms:** "Mixed content" errors when OpenEMR is on HTTPS

**Solutions:**
1. Ensure Jitsi is also on HTTPS (not HTTP)
2. Verify Jitsi base URL in OpenEMR uses `https://`
3. Check browser console for specific errors
4. See `HTTPS_TROUBLESHOOTING.md` for detailed solutions

### Port Already in Use

**Symptoms:** Error "port already in use"

**Solutions:**
1. Find process using the port:
   ```bash
   sudo lsof -i :8440
   sudo netstat -tulpn | grep 8440
   ```

2. Stop conflicting service or change Jitsi port

---

## Security Recommendations

1. **Use HTTPS:** Always use HTTPS for Jitsi, especially if OpenEMR is on HTTPS
2. **Firewall:** Only open necessary ports (80, 443, 8440, 10000/udp)
3. **Authentication:** Consider enabling Jitsi authentication for production
4. **Updates:** Keep Jitsi updated:
   ```bash
   # Docker
   docker-compose pull && docker-compose up -d
   
   # Bare metal
   sudo apt update && sudo apt upgrade jitsi-meet
   ```

5. **Monitoring:** Set up monitoring for Jitsi services

---

## Additional Resources

- Jitsi Meet Documentation: https://jitsi.github.io/handbook/docs/
- Jitsi Community: https://community.jitsi.org/
- OpenEMR Module Documentation: See `INSTALLATION_GUIDE.md`

---

**Configuration Complete!** 🎉

Your Jitsi server should now be configured and ready to work with the PAHO Jitsi Televisit module.

