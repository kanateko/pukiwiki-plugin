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
    {if "value"}
    <fieldset class="mailform-item" data-need="{$need}">
        <legend class="mailform-title">{$label}</legend>
        <span class="mailform-posted">{$value}</span>
        <input type="hidden" name="{$name}" value="{$value}">
    </fieldset>
    {/if}
    {/foreach}
    </div>
    <div class="mailform-autoreply">
        <input type="checkbox" id="chk_autoreply" class="mailform-checkbox" name="autoreply" value="check">
        <label for="chk_autoreply">{$autoreply}</label>
    </div>
    <div class="mailform-button">
        <button type="button" class="mailform-back" name="action" value="back" onClick="history.back()">{$btn_back}</button>
        <button type="submit" class="mailform-submit" name="action" onClick="lockScreen()" value="send">{$btn_submit}</button>
    </div>
</form>
<div class="mailform-progress"><span>{$progress}</span></div>