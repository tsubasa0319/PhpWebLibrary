<button {attributes items=[
    'type' => 'button',
    'name' => $name|default:false,
    'class' => $class|default:false,
    'onclick' => 'general.web.send(event)',
    'disabled' => !$table.infos.isNext|default:false
]}>{$text|default:'次頁'}</button>