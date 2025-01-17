{if !isset($type)}
<span {attributes items=[
    'id'    => $id|default:false,
    'class' => [
        'radioList',
        $class|default:false
    ],
    'style' => [
        'width' => $width|default:false
    ]
]}>
{foreach from=$list key=$key item=$item}
    <label><input {attributes items=[
        'type'     => 'radio',
        'name'     => $name|default:false,
        'value'    => $item.value|default:'',
        'checked'  => $item.value|default:'' === $value|default:'',
        'disabled' => $disabled|default:false,
        'tabindex' => $tabindex|default:false
    ]}>{$item.label|default:''}</label>
{/foreach}
</span>
{/if}