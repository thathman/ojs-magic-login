<?php

/**
 * @file classes/TokenService.php
 *
 * Issue / verify / consume magic-login tokens.
 *
 * Security model
 * ──────────────
 *  • Selector-verifier scheme: the URL carries a random selector (lookup key)
 *    and a random verifier (secret). Only sha256(verifier) is stored, so a
 *    database read never reveals a usable token. Lookup is by selector;
 *    comparison is constant-time (hash_equals).
 *  • Selectors are 16 random bytes (128-bit) encoded as 32 lowercase hex chars.
 *  • Verifiers are 32 random bytes (256-bit) encoded as url-safe base64.
 *  • Single-use: consume() wipes all token fields the moment a login succeeds.
 *  • Short expiry (configurable, default 15 min).
 *  • Per-account rate limit: minimum interval between sends (TokenService).
 *  • No user enumeration: the send endpoint always shows the same neutral page.
 *  • Disabled accounts are rejected at every stage (issue, verify, session).
 *
 * Storage: OJS user_settings (one active token per user; issuing a new token
 * silently supersedes the previous one). For high-volume installations a
 * dedicated indexed table would be preferable — see README.
 */

namespace APP\plugins\generic\magicLogin\classes;

use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use PKP\user\User;

class TokenService
{
    private const SETTING_SELECTOR  = 'magicLoginSelector';
    private const SETTING_HASH      = 'magicLoginVerifierHash';
    private const SETTING_EXPIRY    = 'magicLoginExpiry';
    private const SETTING_LAST_SENT = 'magicLoginLastSent';

    /** Maximum selector length accepted in DB lookups (actual value is 32). */
    private const SELECTOR_MAX_LEN = 64;

    /**
     * Issue a token for $user.
     *
     * Returns the raw "selector.verifier" string to embed in the email link,
     * or null if the per-account minimum interval has not elapsed yet.
     * The caller must show a neutral message regardless of the return value.
     */
    public function issue(User $user, int $ttlSeconds, int $minIntervalSeconds): ?string
    {
        if ($user->getDisabled()) {
            return null;
        }

        $now      = time();
        $lastSent = (int) $this->getSetting($user->getId(), self::SETTING_LAST_SENT);
        if ($lastSent && ($now - $lastSent) < $minIntervalSeconds) {
            return null; // too soon — caller still shows the neutral message
        }

        // selector: random 16-byte hex (128-bit, used only for DB lookup)
        $selector = bin2hex(random_bytes(16));
        // verifier: random 32-byte url-safe base64 (256-bit secret, never stored)
        $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        // Write directly to user_settings — Repo::user()->edit() only persists
        // schema-defined properties and silently drops arbitrary custom keys.
        $this->saveSetting($user->getId(), self::SETTING_SELECTOR,  $selector);
        $this->saveSetting($user->getId(), self::SETTING_HASH,      hash('sha256', $verifier));
        $this->saveSetting($user->getId(), self::SETTING_EXPIRY,    (string)($now + $ttlSeconds));
        $this->saveSetting($user->getId(), self::SETTING_LAST_SENT, (string)$now);

        return $selector . '.' . $verifier;
    }

    /**
     * Verify a "selector.verifier" token.
     *
     * Returns the matching, active, non-disabled User on success, or null on
     * any failure (unknown selector, wrong verifier, expired, disabled account).
     *
     * Does NOT consume the token — call consume() only after the session has
     * been established successfully.
     */
    public function verify(string $token): ?User
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$selector, $verifier] = $parts;

        // Reject empty or suspiciously long selectors before touching the DB.
        if ($selector === '' || $verifier === '' || strlen($selector) > self::SELECTOR_MAX_LEN) {
            return null;
        }

        // Look up by selector (DB stores only the selector, never the verifier).
        $userId = DB::table('user_settings')
            ->where('setting_name', self::SETTING_SELECTOR)
            ->where('setting_value', $selector)
            ->value('user_id');

        if (!$userId) {
            return null;
        }

        $user = Repo::user()->get((int) $userId);
        if (!$user || $user->getDisabled()) {
            return null;
        }

        $storedHash = (string) $this->getSetting((int) $userId, self::SETTING_HASH);
        $expiry     = (int)    $this->getSetting((int) $userId, self::SETTING_EXPIRY);

        if (!$storedHash || time() > $expiry) {
            return null; // expired or already consumed
        }

        // Constant-time comparison prevents timing-based verifier enumeration.
        if (!hash_equals($storedHash, hash('sha256', $verifier))) {
            return null;
        }

        return $user;
    }

    /**
     * Invalidate the user's current token (single-use enforcement).
     * Must be called immediately before or during session creation.
     */
    public function consume(User $user): void
    {
        DB::table('user_settings')
            ->where('user_id', $user->getId())
            ->whereIn('setting_name', [
                self::SETTING_SELECTOR,
                self::SETTING_HASH,
                self::SETTING_EXPIRY,
            ])
            ->delete();
    }

    // ── Private DB helpers ────────────────────────────────────────────────────

    /** Read a single token setting directly from user_settings. */
    private function getSetting(int $userId, string $name): ?string
    {
        return DB::table('user_settings')
            ->where('user_id', $userId)
            ->where('setting_name', $name)
            ->value('setting_value');
    }

    /** Upsert a single token setting directly into user_settings. */
    private function saveSetting(int $userId, string $name, string $value): void
    {
        DB::table('user_settings')->upsert(
            [['user_id' => $userId, 'locale' => '', 'setting_name' => $name, 'setting_value' => $value]],
            ['user_id', 'locale', 'setting_name'],
            ['setting_value']
        );
    }
}
