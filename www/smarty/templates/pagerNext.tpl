{* -------------------------------------------------------------------------------------------------
   次頁ボタン

   History:
   0.18.00 2024/03/30 作成。
   0.19.00 2024/04/16 属性の既定値をnullからfalseへ変更。
   0.20.00 2024/04/23 name属性の既定値をlstNextPage、テキストの初期値を▶へ変更。
   0.22.01 2024/05/17 次頁キーに対応。
   0.33.00 2024/08/27 ライブラリの入った変数名を変更。
------------------------------------------------------------------------------------------------- *}
<button {attributes items=[
    'type' => 'button',
    'name' => $name|default:'lstNextPage',
    'class' => $class|default:false,
    'onclick' => 'libs.web.send(event)',
    'disabled' => !$table.infos.isNext|default:false
]}>{$text|default:'▶'}</button>
<input type="hidden" name="nextPageButtonName" value="{$name|default:'lstNextPage'}">