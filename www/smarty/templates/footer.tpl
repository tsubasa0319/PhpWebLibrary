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
            <input type="hidden" name="startTime" value="{$general.startTime}">
        </form>
    </body>
</html>