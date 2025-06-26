{* -------------------------------------------------------------------------------------------------
   ヘッダ

   History:
   0.01.00 2024/02/05 作成。
   0.04.00 2024/02/10 body読込時イベント、form送信時イベントを実装。
   0.05.00 2024/02/20 個人ライブラリへ追加したJavaScriptのフレーム処理を実装。
   0.06.00 2024/02/22 form押下時イベントを実装。
   0.15.00 2024/03/15 メインセクションに、スタイルクラスを設定できるように対応。
   0.20.00 2024/04/23 サブ画面呼び出しを実装。body頁非表示時イベントを実装。
   0.21.00 2024/04/24 ユーザIDの後ろに、ユーザ名を追加。
   0.23.00 2024/05/18 formのdisabledを削除。
   0.26.01 2024/06/22 bodyのイベントで長くなったため、折り返して整理。
   0.33.00 2024/08/27 ライブラリ名を訂正。直接に個人ライブラリ名を使用しないように変更。
------------------------------------------------------------------------------------------------- *}
<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="utf-8">
        <title>{$general.title}</title>
        <link rel="stylesheet" href="{$general.css}">
        <script type="module" src="/js/libs/webLoader.js"></script>
        <script type="module" src="/js/subScreen/webLoader.js"></script>
        <script defer src="{$general.js}"></script>
    </head>
    <body
        onload="libs.frame.body_load(event);"
        onpagehide="libs.frame.body_pagehide(event);"
        onkeydown="return libs.frame.body_keydown(event);"
    >
        <form method="post" onsubmit="libs.frame.form_submit(event);">
            <header>
                <section id="bodyHeaderInfo">
                    <div>
                        <div class="info pgmid">
                            <span>PGMID&nbsp;:</span>
                            <span>{$general.programId}</span>
                        </div>
                        <div class="info userid">
                            <span>USERID:</span>
                            <span>{$general.userId}</span>
                            <span>{$general.userName}</span>
                        </div>
                    </div>
                    <div class="title">
                        <span>{$general.title}</span>
                    </div>
                    <div>
                        <div>
                            <div class="info date">
                                <span>DATE:</span>
                                <span>{$general.date}</span>
                            </div>
                            <div class="info time">
                                <span>TIME:</span>
                                <span>{$general.time}</span>
                            </div>
                        </div>
                        <div class="logout">
                            <button id="btnLogout" type="button" onclick="libs.frame.logout(event);"{$general.logoutDisabled}>ログアウト</button>
                        </div>
                    </div>
                </section>
                <section id="bodyHeaderMenu">
                    <div>{foreach $general.menuItems as $item}
                        <div class="valign-middle"><a href="{$item.url}">{$item.name}</a></div>
                        <div class="valign-middle">|</div>
                    {/foreach}</div>
                    <div class="valign-middle">
                        <button id="btnPasswordChange" type="button" onclick="libs.frame.move('passwordChange');">パスワード変更</button>
                    </div>
                </section>
            </header>
            <section id="main" class="hidden{foreach from=$classes|default:[] item=$item} {$item}{/foreach}">
<!-- Main section start -->
