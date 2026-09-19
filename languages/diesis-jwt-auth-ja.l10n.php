<?php

return array(
    'messages' => array(
        'Access denied.' => 'アクセスが拒否されました。',
        'Allowed emails' => '許可するメールアドレス',
        'Application audience' => 'Application audience',
        'Cloudflare Access authentication required' => 'Cloudflare Access 認証が必要です',
        'DIESIS JWT Auth' => 'DIESIS JWT Auth',
        'DIESIS JWT Auth for Cloudflare Access' => 'DIESIS JWT Auth for Cloudflare Access',
        'DIESIS JWT Auth for Cloudflare Access is incomplete. Reinstall the release ZIP containing its dependencies.' => 'DIESIS JWT Auth for Cloudflare Access は不完全です。依存関係を含むリリース ZIP を再インストールしてください。',
        'Enforcement' => 'Enforcement',
        'Excluded paths' => '除外するパス',
        'Issuer' => 'Issuer',
        'Issuer and audience are required before enforcement can be enabled.' => 'Enforcement を有効にする前に Issuer と Application audience が必要です。',
        'One path per line. A trailing * matches a prefix. Query strings are ignored.' => '1行に1つのパス。末尾の * はプレフィックスに一致します。クエリストリングは無視されます。',
        'Only exclude a path if the matching Cloudflare Access destination also leaves it public.' => '対応する Cloudflare Access の宛先も公開のままである場合にのみ、パスを除外してください。',
        'Optional defense in depth. Enter one address per line. Leave empty to trust the Access policy.' => '任意の追加防御。1行に1つのアドレスを入力してください。空欄のままにすると Access policy を信頼します。',
        'Protected paths' => '保護するパス',
        'Require a valid Access JWT on the configured paths' => '設定したパスで有効な Access JWT を要求する',
        'Settings' => '設定',
        'The issuer must be an HTTPS cloudflareaccess.com URL.' => 'Issuer は HTTPS の cloudflareaccess.com URL である必要があります。',
        'Validates Cloudflare Access at the WordPress origin. This plugin does not replace the WordPress login.' => 'WordPress origin で Cloudflare Access を検証します。このプラグインは WordPress のログインを置き換えません。',
    ),
);
