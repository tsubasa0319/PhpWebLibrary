<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="utf-8">
        <title>{$general.title}</title>
        <link rel="stylesheet" href="{$general.css}">
        <script type="module" src="/js/imports/tsubasaLibs/webLoader.js"></script>
        <script type="module" src="/js/subScreen/webLoader.js"></script>
        <script defer src="/js/frame.js"></script>
        <script defer src="/js/general.js"></script>
        <script defer src="{$general.js}"></script>
    </head>
    <body
        onload="frame.body_load(event);"
        onpagehide="frame.body_pagehide(event);"
        onkeydown="return frame.body_keydown(event);"
    >
        <form method="post" onsubmit="frame.form_submit(event);">
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
                            <button id="btnLogout" type="button" onclick="frame.logout(event);"{$general.logoutDisabled}>ログアウト</button>
                        </div>
                    </div>
                </section>
                <section id="bodyHeaderMenu">
                    <div>{foreach $general.menuItems as $item}
                        <div class="valign-middle"><a href="{$item.url}">{$item.name}</a></div>
                        <div class="valign-middle">|</div>
                    {/foreach}</div>
                    <div class="valign-middle">
                        <button id="btnPasswordChange" type="button" onclick="frame.move('passwordChange');">パスワード変更</button>
                    </div>
                </section>
            </header>
            <section id="main" class="hidden{foreach from=$classes|default:[] item=$item} {$item}{/foreach}">