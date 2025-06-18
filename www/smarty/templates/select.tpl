<select {attributes items=[
    'id'       => $id|default:null,
    'name'     => $name|default:null,
    'class'    => $class|default:null,
    'style'    => [
        'width' => $width|default:null
    ],
    'disabled' => $disabled|default:null,
    'tabindex' => $tabindex|default:null
]}>
{foreach from=$list item=item}
    <option {attributes items=[
        'value'    => $item.value|default:null,
        'selected' => $item.value|default:null === $value|default:null
    ]}>{$item.text}</option>
{/foreach}
</select>