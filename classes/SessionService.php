<?php

/**
 * @file classes/SessionService.php
 *
 * Establish a logged-in OJS session after identity has been cryptographically
 * verified by the magic-link token flow.
 *
 * Security invariants:
 *  1. Disabled accounts are rejected before any session write.
 *  2. Uses OJS's own Validation::registerUserSession() so all PKP session
 *     accounting (last-login date, session guard state) stays consistent.
 *  3. Session ID is rotated via the session guard's regenerate() method if
 *     available (anti session-fixation), otherwise the call proceeds anyway
 *     since OJS's own login flow does not require a forced rotate here.
 */

namespace APP\plugins\generic\magicLogin\classes;

use APP\core\Application;
use PKP\security\Validation;
use PKP\user\User;

class SessionService
{
    /**
     * Establish a logged-in session for $user.
     *
     * Returns true on success, false if the account is disabled or OJS reports
     * the login failed.
     */
    public static function establishSession(User $user, ?string $reason = null): bool
    {
        if ($user->getDisabled()) {
            error_log('[magicLogin] establishSession rejected: account disabled user_id=' . $user->getId());
            return false;
        }

        // Best-effort session ID rotation (anti session-fixation).
        // Failures are logged but do NOT abort login — OJS's own login path
        // (PKPLoginHandler) also does not mandate a forced rotate.
        try {
            $guard = Application::get()->getRequest()->getSessionGuard();
            if (method_exists($guard, 'regenerate')) {
                $guard->regenerate(true);
            }
        } catch (\Throwable $e) {
            error_log('[magicLogin] session regenerate warning (non-fatal): ' . $e->getMessage());
        }

        $disabledReason = null;
        $result = Validation::registerUserSession($user, $disabledReason);

        if ($result === false) {
            error_log('[magicLogin] registerUserSession returned false for user_id=' . $user->getId()
                . ($disabledReason ? ' reason=' . $disabledReason : ''));
            return false;
        }

        return Validation::isLoggedIn();
    }
}
