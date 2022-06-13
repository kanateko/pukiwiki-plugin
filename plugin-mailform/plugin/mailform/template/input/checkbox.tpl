{foreach}
<input type="checkbox" class="mailform-checkbox" name="{$name}[]" id="{$id}" value="{$op}"{if "checked"} {$checked}{/if}{if "required"} onChange="groupValidation(this)" {$required}{/if}>
<label for="{$id}">{$op}</label>
{/foreach}