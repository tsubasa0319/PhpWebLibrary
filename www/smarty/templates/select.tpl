{* -------------------------------------------------------------------------------------------------
   セレクトリスト

   History:
   0.08.00 2024/02/27 作成。
   0.19.00 2024/04/16 属性の既定値をnullからfalseへ変更。
------------------------------------------------------------------------------------------------- *}
<select {attributes items=[
    'id'       => $id|default:false,
    'name'     => $name|default:false,
    'class'    => $class|default:false,
    'style'    => [
        'width' => $width|default:false
    ],
    'disabled' => $disabled|default:false,
    'tabindex' => $tabindex|default:false
]}>
{if isset($unselValue)}
    <option {attributes items=[
        'value'    => $unselValue|default:'',
        'selected' => $unselValue|default:'' === $value|default:''
    ]}>{$unselLabel|default:'----------'}</option>
{/if}
{foreach from=$list item=item}
    <option {attributes items=[
        'value'    => $item.value|default:'',
        'selected' => $item.value|default:'' === $value|default:''
    ]}>{$item.label|default:''}</option>
{/foreach}
</select>