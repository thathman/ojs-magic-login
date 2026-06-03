{include file="frontend/components/header.tpl" pageTitle="plugins.generic.magicLogin.confirm.title"}
<div class="magic-login-page">

  {* ── Form column (single-column layout) ──────────────────── *}
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
