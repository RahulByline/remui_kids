<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace theme_remui_kids\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper for generating and validating short lived tokens used by the secure
 * file proxy that powers rich previews (PPT, DOCX, XLSX, etc).
 *
 * All tokens are HMAC signed using a theme specific secret to avoid exposing
 * normal pluginfile.php URLs (which require a Moodle session and therefore do
 * not work inside Microsoft Office's online viewers).
 *
 * @package   theme_remui_kids
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class secure_file_token {
    /** @var int Default token lifetime in seconds (15 minutes). */
    public const DEFAULT_LIFETIME = 900;

    /** @var int Minimum allowed token lifetime in seconds (1 second, for testing). */
    public const MIN_LIFETIME = 1;

    /** @var int Maximum allowed token lifetime in seconds (24 hours). */
    public const MAX_LIFETIME = 86400;

    /**
     * Generate a signed token for the specified file and user.
     *
     * @param int $fileid The stored_file id.
     * @param int $userid The user requesting the preview.
     * @param int|null $lifetime Custom lifetime in seconds.
     * @return array{token:string,expires:int} Token payload.
     */
    public static function generate(int $fileid, int $userid, ?int $lifetime = null): array {
        if ($lifetime === null) {
            $lifetime = self::get_configured_lifetime();
        }
        $lifetime = (int) max(self::MIN_LIFETIME, min(self::MAX_LIFETIME, $lifetime));
        $expires = time() + $lifetime;
        $payload = self::build_payload($fileid, $userid, $expires);
        $token = hash_hmac('sha256', $payload, self::get_secret());

        return [
            'token' => $token,
            'expires' => $expires,
        ];
    }

    /**
     * Validate a provided token.
     *
     * @param int $fileid The stored_file id.
     * @param int $userid The user for whom the token was issued.
     * @param int $expires Expiration timestamp.
     * @param string $token Token to validate.
     * @return bool
     */
    public static function validate(int $fileid, int $userid, int $expires, string $token): bool {
        if (empty($token) || $expires < time()) {
            return false;
        }

        $payload = self::build_payload($fileid, $userid, $expires);
        $expected = hash_hmac('sha256', $payload, self::get_secret());

        return hash_equals($expected, $token);
    }

    /**
     * Compose the payload string used for signing.
     *
     * @param int $fileid
     * @param int $userid
     * @param int $expires
     * @return string
     */
    protected static function build_payload(int $fileid, int $userid, int $expires): string {
        return implode(':', [$fileid, $userid, $expires]);
    }

    /**
     * Resolve the shared secret used for HMAC signatures.
     *
     * @return string
     */
    protected static function get_secret(): string {
        global $CFG;

        $secret = get_config('theme_remui_kids', 'file_proxy_secret');
        if (!empty($secret)) {
            return $secret;
        }

        $fallback = $CFG->passwordsaltmain ?? $CFG->wwwroot;
        return hash('sha256', $fallback);
    }

    /**
     * Get token lifetime in seconds from theme config (Site admin setting).
     *
     * @return int Lifetime in seconds.
     */
    protected static function get_configured_lifetime(): int {
        $configured = get_config('theme_remui_kids', 'file_proxy_token_lifetime');
        if ($configured !== false && $configured !== null && $configured !== '') {
            $seconds = (int) $configured;
            if ($seconds >= self::MIN_LIFETIME && $seconds <= self::MAX_LIFETIME) {
                return $seconds;
            }
        }
        return self::DEFAULT_LIFETIME;
    }
}
