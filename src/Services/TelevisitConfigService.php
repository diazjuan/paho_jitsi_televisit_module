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
        return self::getConfig(self::CONFIG_BASE_URL, 'https://169.61.173.4:8440');
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
        if ($televisitCatid && $televisitCatid == $catid) {
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

