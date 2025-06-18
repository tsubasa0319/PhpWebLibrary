            </section>
            <footer class="{$general.messageClass}">
                <div>
                    <span>{$general.messageId}</span>
                </div>
                <div>
                    <span>{$general.message}</span>
                </div>
            </footer>
            <input type="hidden" name="UNIT_SESSION_ID" value="{$general.unitSessionId}">
            <input type="hidden" id="focusName" value="{$general.focusName}" disabled>
            <input type="hidden" id="errorNames" value="{$general.errorNames}" disabled>
            <input type="hidden" id="status" value="{$general.status}" disabled>
        </form>
    </body>
</html>