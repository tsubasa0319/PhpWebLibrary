<button {attributes items=[
    'type' => 'button',
    'name' => $name|default:null,
    'class' => $class|default:null,
    'onclick' => 'general.web.send(event)',
    'disabled' => !$table.infos.isPrev|default:null
]}>{$text|default:'前頁'}</button>