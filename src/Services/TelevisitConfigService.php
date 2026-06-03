<?php

/**
 * Televisit Configuration Service
 * Manages module configuration stored in globals table
 *
 * @package OpenEMR\Modules\PahoJitsiTelevisit
 * @license GNU General Public License 3
 */

namespace OpenEMR\Modules\PahoJitsiTelevisit\Services;

class TelevisitConfigService
{
    const CONFIG_BASE_URL = 'paho_jitsi_base_url';
    const CONFIG_CATEGORY_ID = 'paho_jitsi_televisit_category_id';
    const CONFIG_ROOM_NAMING = 'paho_jitsi_room_naming';
    const CONFIG_JWT_SECRET = 'paho_jitsi_jwt_secret';
    const CONFIG_JWT_APP_ID = 'paho_jitsi_jwt_app_id';

    /** Environment variable names (canonical source of truth, set in .env). */
    const ENV_JWT_SECRET = 'JWT_APP_SECRET';
    const ENV_JWT_APP_ID = 'JWT_APP_ID';

    /** Default app id when neither env nor DB provides one. */
    const DEFAULT_JWT_APP_ID = 'paho-jitsi-televisit';

    /**
     * Read an environment variable, tolerating the various ways mod_php / CLI
     * expose them. Returns null when unset or empty so callers can fall back.
     *
     * @param string $name
     * @return string|null
     */
    public static function getEnvValue($name)
    {
        // $_SERVER / $_ENV are populated for mod_php (Apache SetEnv / PassEnv,
        // and docker-compose `environment:`); getenv() covers the CLI/SAPI case.
        if (isset($_SERVER[$name]) && $_SERVER[$name] !== '') {
            return (string)$_SERVER[$name];
        }
        if (isset($_ENV[$name]) && $_ENV[$name] !== '') {
            return (string)$_ENV[$name];
        }
        $value = getenv($name);
        if ($value !== false && $value !== '') {
            return (string)$value;
        }
        return null;
    }

    /**
     * Resolve the JWT signing secret, environment-first with DB-globals fallback.
     *
     * @return string Empty string when not configured anywhere.
     */
    public static function getJwtSecret()
    {
        $env = self::getEnvValue(self::ENV_JWT_SECRET);
        if ($env !== null) {
            return $env;
        }
        return self::getConfig(self::CONFIG_JWT_SECRET, '');
    }

    /**
     * Resolve the JWT app id (iss/aud), environment-first with DB-globals fallback.
     *
     * @return string
     */
    public static function getJwtAppId()
    {
        $env = self::getEnvValue(self::ENV_JWT_APP_ID);
        if ($env !== null) {
            return $env;
        }
        return self::getConfig(self::CONFIG_JWT_APP_ID, self::DEFAULT_JWT_APP_ID);
    }

    /**
     * Whether a usable JWT secret is configured (env or DB).
     *
     * @return bool
     */
    public static function isJwtConfigured()
    {
        $secret = self::getJwtSecret();
        return is_string($secret) && $secret !== '';
    }

    /**
     * Mirror the env-provided JWT secret/app-id into the globals table so code
     * paths that cannot read the process environment (some mod_php setups) still
     * resolve them via the DB fallback. No-op when env is absent or already in sync.
     *
     * @return void
     */
    public static function mirrorJwtEnvToDb()
    {
        $secretEnv = self::getEnvValue(self::ENV_JWT_SECRET);
        if ($secretEnv !== null && self::getConfig(self::CONFIG_JWT_SECRET, '') !== $secretEnv) {
            self::setConfig(self::CONFIG_JWT_SECRET, $secretEnv);
        }

        $appIdEnv = self::getEnvValue(self::ENV_JWT_APP_ID);
        if ($appIdEnv !== null && self::getConfig(self::CONFIG_JWT_APP_ID, '') !== $appIdEnv) {
            self::setConfig(self::CONFIG_JWT_APP_ID, $appIdEnv);
        }
    }

    /**
     * Get configuration value from globals table
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function getConfig($key, $default = '')
    {
        $result = sqlQuery("SELECT gl_value FROM globals WHERE gl_name = ?", [$key]);
        return $result ? $result['gl_value'] : $default;
    }

    /**
     * Set configuration value in globals table
     *
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @return bool
     */
    public static function setConfig($key, $value)
    {
        return sqlStatement(
            "INSERT INTO globals (gl_name, gl_index, gl_value) VALUES (?, 0, ?) ON DUPLICATE KEY UPDATE gl_value = ?",
            [$key, $value, $value]
        ) !== false;
    }

    /**
     * Get Jitsi base URL
     *
     * @return string
     */
    public static function getBaseUrl()
    {
        // Fallback used only before an admin configures the base URL; this is the
        // staging Jitsi host (mirrors the install default in sql/table.sql).
        return self::getConfig(self::CONFIG_BASE_URL, 'https://169.61.173.4:8440');
    }

    /**
     * Base URL forced to https and trailing-slash trimmed, suitable for building
     * room URLs. Mirrors the normalization in TelevisitRoomService::getOrCreateRoom.
     *
     * @return string
     */
    public static function getNormalizedBaseUrl()
    {
        $baseUrl = self::getBaseUrl();
        if (stripos($baseUrl, 'http://') === 0) {
            $baseUrl = 'https://' . substr($baseUrl, strlen('http://'));
        } elseif (stripos($baseUrl, 'https://') !== 0) {
            $baseUrl = 'https://' . ltrim($baseUrl, '/');
        }
        return rtrim($baseUrl, '/');
    }

    /**
     * Get Televisit category ID
     *
     * @return int|null
     */
    public static function getCategoryId()
    {
        $catid = self::getConfig(self::CONFIG_CATEGORY_ID);
        return !empty($catid) ? (int)$catid : null;
    }

    /**
     * Check if a category is the Televisit category
     *
     * @param int $catid Category ID to check
     * @return bool
     */
    public static function isTelevisitCategory($catid)
    {
        if (!$catid) {
            return false;
        }

        $televisitCatid = self::getCategoryId();
        if ($televisitCatid && (int)$televisitCatid === (int)$catid) {
            return true;
        }

        // Also check by constant_id as fallback
        $result = sqlQuery(
            "SELECT pc_catid FROM openemr_postcalendar_categories WHERE pc_catid = ? AND pc_constant_id = 'televisit'",
            [$catid]
        );
        return $result !== false;
    }

    /**
     * Get all configuration as array
     *
     * @return array
     */
    public static function getAllConfig()
    {
        return [
            'base_url' => self::getBaseUrl(),
            'category_id' => self::getCategoryId(),
            'room_naming' => self::getConfig(self::CONFIG_ROOM_NAMING, 'deterministic'),
            'jwt_secret' => self::getConfig(self::CONFIG_JWT_SECRET, ''),
            'jwt_app_id' => self::getConfig(self::CONFIG_JWT_APP_ID, 'paho-jitsi-televisit'),
        ];
    }

    /**
     * Save all configuration
     *
     * @param array $config Configuration array
     * @return bool
     */
    public static function saveAllConfig($config)
    {
        $success = true;
        $success = $success && self::setConfig(self::CONFIG_BASE_URL, $config['base_url'] ?? '');
        $success = $success && self::setConfig(self::CONFIG_CATEGORY_ID, $config['category_id'] ?? '');
        $success = $success && self::setConfig(self::CONFIG_ROOM_NAMING, $config['room_naming'] ?? 'deterministic');
        if (isset($config['jwt_secret'])) {
            $success = $success && self::setConfig(self::CONFIG_JWT_SECRET, $config['jwt_secret']);
        }
        if (isset($config['jwt_app_id'])) {
            $success = $success && self::setConfig(self::CONFIG_JWT_APP_ID, $config['jwt_app_id']);
        }
        return $success;
    }
}

