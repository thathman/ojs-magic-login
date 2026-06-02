<?php

/**
 * @file MagicLoginSettingsForm.php
 */

namespace APP\plugins\generic\magicLogin;

use APP\core\Application;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;

class MagicLoginSettingsForm extends Form
{
    /** Hard caps so an admin cannot accidentally configure insecure values. */
    private const TTL_MIN      = 1;
    private const TTL_MAX      = 120;  // minutes — 2 hours absolute ceiling
    private const TTL_DEFAULT  = 15;

    private const INTERVAL_MIN     = 30;    // seconds — prevents flooding
    private const INTERVAL_MAX     = 3600;  // seconds — 1 hour absolute ceiling
    private const INTERVAL_DEFAULT = 60;

    private MagicLoginPlugin $plugin;

    public function __construct(MagicLoginPlugin $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct($plugin->getTemplateResource('settings.tpl'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    public function initData(): void
    {
        $contextId = Application::get()->getRequest()->getContext()->getId();
        $this->setData('enabled',            (bool) $this->plugin->getSetting($contextId, 'enabled'));
        $this->setData('ttlMinutes',         $this->plugin->getSetting($contextId, 'ttlMinutes') ?: self::TTL_DEFAULT);
        $this->setData('minIntervalSeconds', $this->plugin->getSetting($contextId, 'minIntervalSeconds') ?: self::INTERVAL_DEFAULT);
        parent::initData();
    }

    public function readInputData(): void
    {
        $this->readUserVars(['enabled', 'ttlMinutes', 'minIntervalSeconds']);
    }

    public function execute(...$functionArgs)
    {
        $contextId = Application::get()->getRequest()->getContext()->getId();

        $this->plugin->updateSetting($contextId, 'enabled', (bool) $this->getData('enabled'), 'bool');

        $ttl = (int) $this->getData('ttlMinutes');
        $ttl = max(self::TTL_MIN, min(self::TTL_MAX, $ttl ?: self::TTL_DEFAULT));
        $this->plugin->updateSetting($contextId, 'ttlMinutes', $ttl, 'int');

        $interval = (int) $this->getData('minIntervalSeconds');
        $interval = max(self::INTERVAL_MIN, min(self::INTERVAL_MAX, $interval ?: self::INTERVAL_DEFAULT));
        $this->plugin->updateSetting($contextId, 'minIntervalSeconds', $interval, 'int');

        return parent::execute(...$functionArgs);
    }
}
