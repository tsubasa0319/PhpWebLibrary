{* -------------------------------------------------------------------------------------------------
   フッタ

   History:
   0.01.00 2024/02/05 作成。
   0.03.00 2024/02/07 画面単位セッションへ対応。
   0.04.00 2024/02/10 フォーカス移動/エラー処理/確認画面へ切り替えを実装。
   0.05.00 2024/02/20 メッセージ領域へ、スタイルシートのクラスを設定。
   0.07.00 2024/02/22 デバッグ用に、実行時間を追加。
   0.20.00 2024/04/23 サブ画面領域を追加。
   0.26.01 2024/06/22 サブプログラム呼び出しを実装。
   0.33.00 2024/08/27 JavaScriptのフレーム処理が、libs.frameに実装されていることを前提とした仕様へ変更。
------------------------------------------------------------------------------------------------- *}
<!-- Main section end -->
            </section>
            <footer>
                <div>
                    <span id="messageId" class="{$general.messageClass}">{$general.messageId}</span>
                </div>
                <div>
                    <span id="messageContent" class="{$general.messageClass}">{$general.message}</span>
                </div>
                <div class="right">
                    <span id="executeTime"></span>
                </div>
            </footer>
            <input type="hidden" name="UNIT_SESSION_ID" value="{$general.unitSessionId}">
            <input type="hidden" id="focusName" value="{$general.focusName}" disabled>
            <input type="hidden" id="errorNames" value="{$general.errorNames}" disabled>
            <input type="hidden" id="status" value="{$general.status}" disabled>
            <input type="hidden" id="callSubProgramId" value="{$general.callSubProgramId}" disabled>
            <input type="hidden" id="callType" value="{$general.callType}" disabled>
            <input type="hidden" name="startTime" value="{$general.startTime}">
        </form>
        <div id="subScreen" class="subScreen" onclick="libs.frame.closeSubScreen(event);">
            <iframe></iframe>
        </div>
    </body>
</html>