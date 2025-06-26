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