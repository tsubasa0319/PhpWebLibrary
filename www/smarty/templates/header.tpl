<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="utf-8">
        <title>{$general.title}</title>
        <link rel="stylesheet" href="{$general.css}">
        <script src="{$general.js}"></script>
    </head>
    <body onload="body_onload(event);">
        <form method="post" onsubmit="form_submit(event);">
            <header>
                <section id="bodyHeaderInfo">
                    <div>
                        <div class="info">
                            <span>PGMID&nbsp;:</span>
                            <span>{$general.programId}</span>
                        </div>
                        <div class="info">
                            <span>USERID:</span>
                            <span>{$general.userId}</span>
                        </div>
                    </div>
                    <div class="title">
                        <span>{$general.title}</span>
                    </div>
                    <div>
                        <div>
                            <div class="info">
                                <span>DATE:</span>
                                <span>{$general.date}</span>
                            </div>
                            <div class="info">
                                <span>TIME:</span>
                                <span>{$general.time}</span>
                            </div>
                        </div>
                        <div class="logout">
                            <button id="btnLogout" type="button" onclick="logout(event);"{$general.logoutDisabled}>ログアウト</button>
                        </div>
                    </div>
                </section>
                <section id="bodyHeaderMenu">
                    <div>{foreach $general.menuItems as $item}
                        <div class="valign-middle"><a href="{$item.url}">{$item.name}</a></div>
                        <div class="valign-middle">|</div>
                    {/foreach}</div>
                    <div class="valign-middle">
                        <button type="button" onclick="move('passwordChange');">パスワード変更</button>
                    </div>
                </section>
            </header>
            <section id="main" class="hidden">