{* -------------------------------------------------------------------------------------------------
   チェックボックス

   History:
   0.19.00 2024/04/16 作成。
------------------------------------------------------------------------------------------------- *}
<label><input type="checkbox" {attributes items=[
    'name'     => $name|default:false,
    'class'    => $class|default:false,
    'checked'  => $checked|default:false,
    'disabled' => $disabled|default:false,
    'tabindex' => $tabindex|default:false
]}>{$text|default:''}</label>