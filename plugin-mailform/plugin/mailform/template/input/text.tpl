<input type="text" class="mailform-input" name="{$name}" onInput="lengthObserver(this)"{if "holder"} placeholder="{$holder}"{/if}{if "value"} value="{$value}"{/if} data-max="{$max}"{if "required"} {$required}{/if}>
<i class="mailform-limit">{$max}</i>