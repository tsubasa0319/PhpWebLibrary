<button {attributes items=[
    'type' => 'button',
    'name' => $name|default:null,
    'class' => $class|default:null,
    'onclick' => 'general.web.send(event)',
    'disabled' => !$table.infos.isNext|default:null
]}>{$text|default:'次頁'}</button>