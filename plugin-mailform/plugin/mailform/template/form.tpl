  <form class="plugin-mailform" action="" method="post">
    <p class="mailform-message">{$msg}</p>
    <input type="hidden" name="token" value="{$token}">
    {foreach}
    {if "hname"}
    <input type="hidden" name="{$hname}" value="{$hvalue}">
    {/if}
    {/foreach}
    <div class="mailform-fields">
    {foreach}
    {if "label"}
    <fieldset class="mailform-item" data-need="{$need}">
        <legend class="mailform-title">{$label}</legend>
        <div class="mailform-post">{$input}</div>
    </fieldset>
    {/if}
    {/foreach}
    </div>
    <div class="mailform-button">
        <button type="submit" class="mailform-confirm" name="action" value="confirm">{$btn_confirm}</button>
    </div>
</form>