{* -------------------------------------------------------------------------------------------------
   ラジオボタンリスト

   History:
   0.08.00 2024/02/27 作成。
   0.19.00 2024/04/16 属性の既定値をnullからfalseへ変更。
------------------------------------------------------------------------------------------------- *}
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