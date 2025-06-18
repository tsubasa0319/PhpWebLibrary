{if $type|default:null === null}
<span {attributes items=[
    'id'    => $id|default:null,
    'class' => [
        'radioList',
        $class|default:null
    ],
    'style' => [
        'width' => $width|default:null
    ]
]}>
{foreach from=$list key=$key item=$item}
    <label><input {attributes items=[
        'type'     => 'radio',
        'name'     => $name|default:null,
        'value'    => $item.value|default:null,
        'checked'  => $item.value|default:null === $value|default:null,
        'disabled' => $disabled|default:null,
        'tabindex' => $tabindex|default:null
    ]}>{$item.text|default:''}</label>
{/foreach}
</span>
{/if}