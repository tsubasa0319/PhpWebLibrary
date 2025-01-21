<button {attributes items=[
    'type' => 'button',
    'name' => $name|default:'lstPrevPage',
    'class' => $class|default:false,
    'onclick' => 'general.web.send(event)',
    'disabled' => !$table.infos.isPrev|default:false
]}>{$text|default:'◀'}</button>