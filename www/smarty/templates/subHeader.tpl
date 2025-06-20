<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="utf-8">
        <title>{$general.title}</title>
        <link rel="stylesheet" href="{$general.css}">
        <script type="module" src="/js/imports/tsubasaLib/webLoader.js"></script>
        <script type="module" src="/js/subScreen/webLoader.js"></script>
        <script defer src="/js/frame.js"></script>
        <script defer src="/js/general.js"></script>
        <script defer src="{$general.js}"></script>
    </head>
    <body onload="frame.body_load(event);" onkeydown="return frame.body_keydown(event);">
        <form method="post" onsubmit="frame.form_submit(event);">
            <header>
                <section id="bodyHeaderInfo" class="subScreen">
                    <div>
                        <div class="info pgmid">
                            <span>PGMID:</span>
                            <span>{$general.programId}</span>
                        </div>
                        <div class="info pgmid">
                            <span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                            <span>{$general.programId2}</span>
                        </div>
                    </div>
                    <div class="title">
                        <span>{$general.title}</span>
                    </div>
                    <div class="flex-column">
                        <div class="info date">
                            <span>DATE:</span>
                            <span>{$general.date}</span>
                        </div>
                        <div class="info time">
                            <span>TIME:</span>
                            <span>{$general.time}</span>
                        </div>
                    </div>
                </section>
            </header>
            <section id="main" class="hidden{foreach from=$classes|default:[] item=$item} {$item}{/foreach}">