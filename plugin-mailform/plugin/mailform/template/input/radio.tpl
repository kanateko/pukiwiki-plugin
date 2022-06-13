
{foreach}
<input type="radio" class="mailform-radio" name="{$name}" id="{$id}" value="{$op}"{if "checked"} {$checked}{/if}{if "required"} {$required}{/if}>
<label for="{$id}">{$op}</label>
{/foreach}
