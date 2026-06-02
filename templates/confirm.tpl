{include file="frontend/components/header.tpl" pageTitle="plugins.generic.magicLogin.confirm.title"}
<div class="magic-login-page">

  {* ── Left panel ─────────────────────────────────────────── *}
  <aside class="magic-login-side">
    <div class="magic-login-status">
      {if $error}
        <span class="magic-login-dot magic-login-dot-err"></span>
      {else}
        <span class="magic-login-dot magic-login-dot-ok"></span>
      {/if}
      <span>{translate key="plugins.generic.magicLogin.shared.secureConnection"}</span>
    </div>

    <a class="magic-login-logo" href="{url page='index'}">
      {if $currentContext && $currentContext->getLocalizedData('coverImage')}
        <img src="{$currentContext->getLocalizedData('coverImage')|escape}" alt="{$currentContext->getLocalizedName()|strip_tags|escape}" style="width:40px;height:40px;object-fit:contain;border-radius:2px;flex-shrink:0;">
      {else}
        <div class="magic-login-mark">{$currentContext->getLocalizedAcronym()|strip_tags|truncate:3:""|default:"ML"|escape}</div>
      {/if}
      <div class="magic-login-logo-text">
        <div class="magic-login-logo-title">{$currentContext->getLocalizedName()|strip_tags|truncate:36:""|escape}</div>
        <div class="magic-login-logo-subtitle">{translate key="plugins.generic.magicLogin.shared.memberAccess"}</div>
      </div>
    </a>

    <div class="magic-login-cover">
      <div class="magic-login-cover-title">
        {if $error}{translate key="plugins.generic.magicLogin.confirm.invalidTitle"}{else}{translate key="plugins.generic.magicLogin.confirm.hero"}{/if}
      </div>
      <div class="magic-login-cover-caption">
        <span>{$currentContext->getLocalizedName()|strip_tags|escape}</span>
        <span>{translate key="plugins.generic.magicLogin.shared.memberAccess"}</span>
      </div>
    </div>

    {if $currentContext->getLocalizedData('description')}
      <p class="magic-login-desc">{$currentContext->getLocalizedData('description')|strip_tags|truncate:160:"…"}</p>
    {/if}

    <div class="magic-login-meta">
      {if $currentContext->getData('printIssn')}<div>ISSN {$currentContext->getData('printIssn')|escape}{if $currentContext->getData('onlineIssn')} &middot; eISSN {$currentContext->getData('onlineIssn')|escape}{/if}</div>{/if}
      <div>{translate key="plugins.generic.magicLogin.shared.builtOn"}</div>
    </div>
  </aside>

  {* ── Right panel ─────────────────────────────────────────── *}
  <main class="magic-login-main">
    <div class="magic-login-card">

      {if $error}

        <div class="magic-login-eyebrow magic-login-eyebrow-err">{translate key="plugins.generic.magicLogin.confirm.invalidTitle"}</div>
        <h1 class="magic-login-title">{translate key="plugins.generic.magicLogin.confirm.invalidHeading"}</h1>
        <div class="magic-login-alert magic-login-alert-err"><div>{$error|escape}</div></div>
        <div class="magic-login-switch">
          <a href="{url page='magicLogin' op='request'}">{translate key="plugins.generic.magicLogin.confirm.retry"}</a>
          &nbsp;&middot;&nbsp;
          <a href="{url page='login'}">← {translate key="plugins.generic.magicLogin.confirm.back"}</a>
        </div>

      {else}

        <div class="magic-login-eyebrow magic-login-eyebrow-ok">{translate key="plugins.generic.magicLogin.confirm.section"}</div>
        <h1 class="magic-login-title">{translate key="plugins.generic.magicLogin.confirm.heading"}</h1>
        <p class="magic-login-sub">{translate key="plugins.generic.magicLogin.confirm.help"}</p>

        <form class="magic-login-form" method="post" action="{$loginUrl|escape}">
          {csrf}
          <input type="hidden" name="token" value="{$token|escape}">
          <button class="magic-login-button" type="submit">
            <span>{translate key="plugins.generic.magicLogin.confirm.button"}</span>
            <span>→</span>
          </button>
        </form>

        <div class="magic-login-switch">
          <a href="{url page='login'}">← {translate key="plugins.generic.magicLogin.confirm.back"}</a>
          &nbsp;&middot;&nbsp;
          <a href="{url page='magicLogin' op='request'}">{translate key="plugins.generic.magicLogin.confirm.retry"}</a>
        </div>

      {/if}
    </div>
  </main>

</div>
{include file="frontend/components/footer.tpl"}
