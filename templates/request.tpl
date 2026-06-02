{include file="frontend/components/header.tpl" pageTitle="plugins.generic.magicLogin.request.title"}
<div class="magic-login-page">

  {* ── Left panel ─────────────────────────────────────────── *}
  <aside class="magic-login-side">
    <div class="magic-login-status">
      <span class="magic-login-dot"></span>
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
      <div class="magic-login-cover-title">{translate key="plugins.generic.magicLogin.request.hero"}</div>
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

      {if $neutralMessage}

        <div class="magic-login-eyebrow magic-login-eyebrow-ok">{translate key="plugins.generic.magicLogin.request.section"}</div>
        <h1 class="magic-login-title">{translate key="plugins.generic.magicLogin.request.title"}</h1>
        <div class="magic-login-alert magic-login-alert-ok"><div>{$neutralMessage|escape}</div></div>
        <p class="magic-login-sub">{translate key="plugins.generic.magicLogin.request.sentNotice"}</p>
        <div class="magic-login-switch">
          <a href="{url page='login'}">← {translate key="plugins.generic.magicLogin.request.back"}</a>
        </div>

      {else}

        <div class="magic-login-eyebrow">{translate key="plugins.generic.magicLogin.request.section"}</div>
        <h1 class="magic-login-title">{translate key="plugins.generic.magicLogin.request.heading"}</h1>
        <p class="magic-login-sub">{translate key="plugins.generic.magicLogin.request.help"}</p>

        <form class="magic-login-form" id="magicRequestForm" method="post" action="{$sendUrl|escape}" novalidate>
          {csrf}
          <div class="magic-login-field">
            <label class="magic-login-label" for="email">{translate key="user.email"}</label>
            <input class="magic-login-input" type="email" name="email" id="email" required
              autocomplete="email"
              placeholder="{translate key="plugins.generic.magicLogin.request.emailPlaceholder"}">
          </div>
          <button class="magic-login-button" type="submit">
            <span>{translate key="plugins.generic.magicLogin.request.button"}</span>
            <span>→</span>
          </button>
        </form>

        <div class="magic-login-switch">
          <a href="{url page='login'}">← {translate key="plugins.generic.magicLogin.request.back"}</a>
        </div>

      {/if}
    </div>
  </main>

</div>
{include file="frontend/components/footer.tpl"}
