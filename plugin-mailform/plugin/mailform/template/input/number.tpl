<input type="number" class="mailform-input" name="{$name}" onInput="lengthObserver(this)"{if "holder"} placeholder="{$holder}"{/if}{if "value"} value="{$value}"{/if}{if "start"} min="{$start}"{/if}{if "end"} max="{$end}"{/if} data-max="{$max}"{if "required"} {$required}{/if}>
<i class="mailform-limit">{$max}</i>