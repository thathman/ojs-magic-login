<?php

/**
 * @file mailables/MagicLoginLink.php
 * @brief One-time magic sign-in link email.
 */

namespace APP\plugins\generic\magicLogin\mailables;

use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\security\Role;

class MagicLoginLink extends Mailable
{
    use Configurable;
    use Recipient;

    public const EMAIL_KEY = 'MAGIC_LOGIN_LINK';

    protected static ?string $name = 'plugins.generic.magicLogin.mailable.link.name';
    protected static ?string $description = 'plugins.generic.magicLogin.mailable.link.description';
    protected static ?string $emailTemplateKey = self::EMAIL_KEY;
    protected static bool $supportsTemplates = true;
    protected static array $groupIds = [self::GROUP_OTHER];
    protected static array $fromRoleIds = [self::FROM_SYSTEM];
    protected static array $toRoleIds = [Role::ROLE_ID_AUTHOR, Role::ROLE_ID_READER, Role::ROLE_ID_REVIEWER];

    public function __construct(Context $context)
    {
        parent::__construct(func_get_args());
    }

    public static function getName(): string
    {
        $v = __(static::$name);
        if (!$v || $v === static::$name || preg_match('/^##.+##$/', $v)) {
            return 'Magic Link Sign-in';
        }
        return $v;
    }

    public static function getDescription(): string
    {
        $v = __(static::$description);
        if (!$v || $v === static::$description || preg_match('/^##.+##$/', $v)) {
            return 'Sent when a user requests a one-time passwordless sign-in link.';
        }
        return $v;
    }

    public static function getDataDescriptions(): array
    {
        return array_merge(parent::getDataDescriptions(), [
            'recipientName'  => __('plugins.generic.magicLogin.mailable.var.recipientName'),
            'contextName'    => __('emailTemplate.variable.context.name'),
            'magicUrl'       => __('plugins.generic.magicLogin.mailable.var.magicUrl'),
            'expiryMinutes'  => __('plugins.generic.magicLogin.mailable.var.expiryMinutes'),
        ]);
    }
}
