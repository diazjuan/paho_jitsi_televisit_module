<?php

/**
 * Televisit Token Service
 * Mints (and verifies) short-lived, room-scoped Jitsi JWTs (HS256) using the
 * bundled firebase/php-jwt library. The signing secret and app id are resolved
 * by TelevisitConfigService (environment-first, DB-globals fallback).
 *
 * Nothing here enforces authentication on its own — it only produces a token
 * the Jitsi server will trust once token auth is switched on (Phase B).
 *
 * @package OpenEMR\Modules\PahoJitsiTelevisit
 * @license GNU General Public License 3
 */

namespace OpenEMR\Modules\PahoJitsiTelevisit\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class TelevisitTokenService
{
    /** Signing algorithm expected by the Jitsi token verifier. */
    const ALGORITHM = 'HS256';

    /** Jitsi requires sub = the XMPP domain. */
    const SUBJECT = 'meet.jitsi';

    /** Token lifetime: ~6 hours. */
    const TTL_SECONDS = 21600;

    /** Backdate nbf by this many seconds to absorb clock skew between hosts. */
    const NBF_GRACE_SECONDS = 300;

    /**
     * Mint a signed, room-scoped token for a single participant.
     *
     * @param string $roomName    Exact Jitsi room name the token is valid for.
     * @param string $displayName Non-identifying display label shown in the call.
     * @param bool   $isModerator Whether the participant joins with moderator rights.
     * @return string Signed compact JWT.
     * @throws \RuntimeException When no signing secret is configured.
     */
    public static function mintToken($roomName, $displayName, $isModerator)
    {
        $secret = TelevisitConfigService::getJwtSecret();
        if (!is_string($secret) || $secret === '') {
            throw new \RuntimeException('Cannot mint token: JWT secret is not configured.');
        }
        $appId = TelevisitConfigService::getJwtAppId();

        $now = time();
        $payload = [
            'iss'  => $appId,
            'aud'  => $appId,
            'sub'  => self::SUBJECT,
            'room' => (string)$roomName,
            'iat'  => $now,
            'nbf'  => $now - self::NBF_GRACE_SECONDS,
            'exp'  => $now + self::TTL_SECONDS,
            'context' => [
                'user' => [
                    'name'      => (string)$displayName,
                    'moderator' => (bool)$isModerator,
                ],
            ],
        ];

        return JWT::encode($payload, $secret, self::ALGORITHM);
    }

    /**
     * Build a full room URL carrying a freshly minted token, e.g.
     * `https://host/room-name?jwt=<token>`. This is the single way provider and
     * patient entry paths should produce a Jitsi URL — no raw `baseUrl/room`.
     *
     * @param string $roomName    Exact Jitsi room name.
     * @param string $displayName Non-identifying display label.
     * @param bool   $isModerator Whether the token grants moderator rights.
     * @return string
     * @throws \RuntimeException When no signing secret is configured.
     */
    public static function buildAuthenticatedRoomUrl($roomName, $displayName, $isModerator)
    {
        $token = self::mintToken($roomName, $displayName, $isModerator);
        $base = TelevisitConfigService::getNormalizedBaseUrl();

        return $base . '/' . rawurlencode((string)$roomName) . '?jwt=' . rawurlencode($token);
    }

    /**
     * Verify a token against the configured secret and (optionally) a room.
     * Used by tests and the config-screen "mint test token" diagnostic; the
     * real enforcement is done by the Jitsi/prosody verifier.
     *
     * @param string      $token        Compact JWT to verify.
     * @param string|null $expectedRoom When given, the `room` claim must match.
     * @return object Decoded payload (stdClass).
     * @throws \RuntimeException When no signing secret is configured.
     * @throws \UnexpectedValueException When the room claim does not match.
     * @throws \Firebase\JWT\SignatureInvalidException When the signature is invalid.
     * @throws \Firebase\JWT\ExpiredException When the token has expired.
     * @throws \Firebase\JWT\BeforeValidException When nbf is still in the future.
     */
    public static function verifyToken($token, $expectedRoom = null)
    {
        $secret = TelevisitConfigService::getJwtSecret();
        if (!is_string($secret) || $secret === '') {
            throw new \RuntimeException('Cannot verify token: JWT secret is not configured.');
        }

        $decoded = JWT::decode($token, new Key($secret, self::ALGORITHM));

        if ($expectedRoom !== null) {
            $room = isset($decoded->room) ? (string)$decoded->room : '';
            if ($room !== (string)$expectedRoom) {
                throw new \UnexpectedValueException('Token room claim does not match the requested room.');
            }
        }

        return $decoded;
    }
}
