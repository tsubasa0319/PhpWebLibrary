<button {attributes items=[
    'type' => 'button',
    'name' => $name|default:'lstNextPage',
    'class' => $class|default:false,
    'onclick' => 'libs.web.send(event)',
    'disabled' => !$table.infos.isNext|default:false
]}>{$text|default:'▶'}</button>
<input type="hidden" name="nextPageButtonName" value="{$name|default:'lstNextPage'}">