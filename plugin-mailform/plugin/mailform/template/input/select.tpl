<select class="mailform-input" name="{$name}"{if "required"} {$required}{/if}>
    {foreach}
    <option value="{$op}"{if "selected"} {$selected}{/if}>{$op}</option>
    {/foreach}
</select>