<button {attributes items=[
    'type' => 'button',
    'name' => $name|default:false,
    'class' => $class|default:false,
    'onclick' => 'general.web.send(event)',
    'disabled' => !$table.infos.isPrev|default:false
]}>{$text|default:'前頁'}</button>