<?php

/**
 * @file MagicLoginPlugin.php
 *
 * Distributed under the GNU GPL v3.
 *
 * @class MagicLoginPlugin
 * @brief Passwordless sign-in for OJS 3.5 — Phase 1: email magic links.
 */

namespace APP\plugins\generic\magicLogin;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\magicLogin\mailables\MagicLoginLink;
use APP\plugins\generic\magicLogin\pages\MagicLoginHandler;
use Illuminate\Support\Facades\DB;
use PKP\core\JSONMessage;
use PKP\core\Registry;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class MagicLoginPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if (!$success) {
            return false;
        }

        // Rename any legacy versions row recorded under the wrong product key
        // ('ojs2') so that OJS version-tracking stays consistent after the
        // 1.0.0 → 1.1.0 fix.  Idempotent: no-op once the row is correct.
        $this->migrateVersionRecord();

        if (!$this->getEnabled($mainContextId)) {
            return true;
        }

        Hook::add('LoadHandler',           [$this, 'setupHandler']);
        Hook::add('TemplateManager::display', [$this, 'addLoginLink']);
        Hook::add('Mailer::Mailables',     [$this, 'addMailable']);

        // Ensure the email template is installed for the current context.
        // installEmailTemplates() with skipExisting=true is idempotent.
        $this->ensureEmailTemplates();

        return true;
    }

    // ── Routing ─────────────────────────────────────────────────────────────

    public function setupHandler(string $hookName, array $args): bool
    {
        if ($args[0] === 'magicLogin') {
            Registry::set('plugin', $this);
            $handler = &$args[3];
            $handler = new MagicLoginHandler();
            return Hook::ABORT;
        }
        return Hook::CONTINUE;
    }

    // ── Mailable registration ────────────────────────────────────────────────

    /**
     * Push MagicLoginLink into OJS's mailable collection so it appears
     * in Settings › Emails and can be customised by journal managers.
     * Must return void (matching the OJS 3.5 hook signature).
     */
    public function addMailable(string $hookName, array $args): void
    {
        $args[0]->push(MagicLoginLink::class);
    }

    /**
     * @copydoc Plugin::getInstallEmailTemplatesFile()
     */
    public function getInstallEmailTemplatesFile()
    {
        return $this->getPluginPath() . '/emailTemplates.xml';
    }

    /**
     * Install the MAGIC_LOGIN_LINK email template the first time this plugin
     * is activated on a context that does not yet have it.
     */
    private function ensureEmailTemplates(): void
    {
        try {
            $request = Application::get()->getRequest();
            $context = $request->getContext();
            if (!$context) {
                return;
            }
            $existing = Repo::emailTemplate()->getByKey($context->getId(), MagicLoginLink::EMAIL_KEY);
            if ($existing) {
                return;
            }
            $this->addLocaleData();
            Repo::emailTemplate()->dao->installEmailTemplates(
                $this->getInstallEmailTemplatesFile(),
                [],
                null,
                true
            );
        } catch (\Throwable $e) {
            error_log('[magicLogin] email template install failed: ' . $e->getMessage());
        }
    }

    // ── DB migration ─────────────────────────────────────────────────────────

    /**
     * v1.0.0 shipped with <application>ojs2</application> in version.xml,
     * causing OJS to record product='ojs2' in the versions table instead of
     * the correct 'magicLogin'.  Rename the row so version tracking and the
     * plugin list work correctly on existing installations.
     */
    private function migrateVersionRecord(): void
    {
        try {
            $alreadyCorrect = DB::table('versions')
                ->where('product_type', 'plugins.generic')
                ->where('product', 'magicLogin')
                ->exists();

            if (!$alreadyCorrect) {
                DB::table('versions')
                    ->where('product_type', 'plugins.generic')
                    ->where('product', 'ojs2')
                    ->update(['product' => 'magicLogin']);
            }
        } catch (\Throwable $e) {
            error_log('[magicLogin] migrateVersionRecord failed: ' . $e->getMessage());
        }
    }

    // ── Login page link ──────────────────────────────────────────────────────

    public function addLoginLink(string $hookName, array $args): bool
    {
        $template = $args[1];
        if (!str_contains($template, 'userLogin.tpl') && !str_contains($template, 'openidLogin.tpl')) {
            return Hook::CONTINUE;
        }
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) {
            return Hook::CONTINUE;
        }
        $args[0]->assign('magicLoginRequestUrl',
            $request->getDispatcher()->url($request, Application::ROUTE_PAGE, null, 'magicLogin', 'request')
        );
        return Hook::CONTINUE;
    }

    // ── Feature guards + config ──────────────────────────────────────────────

    public function ensureEnabled($request): void
    {
        $context = $request->getContext();
        if (!$context || !$this->getSetting($context->getId(), 'enabled')) {
            $request->redirect(null, 'login', 'signIn');
        }
    }

    public function ttlSeconds(int $contextId): int
    {
        $minutes = (int) $this->getSetting($contextId, 'ttlMinutes');
        return ($minutes >= 1 ? $minutes : 15) * 60;
    }

    public function minIntervalSeconds(int $contextId): int
    {
        $seconds = (int) $this->getSetting($contextId, 'minIntervalSeconds');
        return $seconds >= 30 ? $seconds : 60;
    }

    // ── Settings UI ──────────────────────────────────────────────────────────

    public function getActions($request, $actionArgs)
    {
        $actions = parent::getActions($request, $actionArgs);
        if (!$this->getEnabled()) {
            return $actions;
        }
        $router = $request->getRouter();
        array_unshift($actions, new LinkAction(
            'settings',
            new AjaxModal(
                $router->url($request, null, null, 'manage', null, array_merge($actionArgs, ['verb' => 'settings'])),
                $this->getDisplayName()
            ),
            __('manager.plugins.settings')
        ));
        return $actions;
    }

    public function manage($args, $request)
    {
        if ($request->getUserVar('verb') === 'settings') {
            $templateMgr = \APP\template\TemplateManager::getManager($request);
            $templateMgr->assign('pluginName', $this->getName());
            $form = new MagicLoginSettingsForm($this);
            if ($request->getUserVar('save')) {
                $form->readInputData();
                if ($form->validate()) {
                    $form->execute();
                    return new JSONMessage(true);
                }
            } else {
                $form->initData();
            }
            return new JSONMessage(true, $form->fetch($request));
        }
        return parent::manage($args, $request);
    }

    public function getDisplayName()
    {
        return __('plugins.generic.magicLogin.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.magicLogin.description');
    }
}
