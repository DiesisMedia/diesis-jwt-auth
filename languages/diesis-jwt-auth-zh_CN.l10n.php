<?php

return array(
    'messages' => array(
        'Access denied.' => '拒绝访问。',
        'Allowed emails' => '允许的电子邮件',
        'Application audience' => 'Application audience',
        'Cloudflare Access authentication required' => '需要 Cloudflare Access 身份验证',
        'DIESIS JWT Auth' => 'DIESIS JWT Auth',
        'DIESIS JWT Auth for Cloudflare Access' => 'DIESIS JWT Auth for Cloudflare Access',
        'DIESIS JWT Auth for Cloudflare Access is incomplete. Reinstall the release ZIP containing its dependencies.' => 'DIESIS JWT Auth for Cloudflare Access 不完整。请您重新安装包含其依赖项的发行版 ZIP。',
        'Enforcement' => 'Enforcement',
        'Excluded paths' => '排除的路径',
        'Issuer' => 'Issuer',
        'Issuer and audience are required before enforcement can be enabled.' => '启用 Enforcement 之前必须填写 Issuer 和 audience。',
        'One path per line. A trailing * matches a prefix. Query strings are ignored.' => '每行一个路径。末尾的 * 匹配前缀。查询字符串会被忽略。',
        'Only exclude a path if the matching Cloudflare Access destination also leaves it public.' => '请您仅在对应的 Cloudflare Access 目标也将其保持公开时，才排除该路径。',
        'Optional defense in depth. Enter one address per line. Leave empty to trust the Access policy.' => '可选的额外防护。请您每行输入一个地址。留空即表示您信任 Access policy。',
        'Protected paths' => '受保护的路径',
        'Require a valid Access JWT on the configured paths' => '在已配置的路径上要求有效的 Access JWT',
        'Settings' => '设置',
        'The issuer must be an HTTPS cloudflareaccess.com URL.' => 'Issuer 必须是 cloudflareaccess.com 的 HTTPS URL。',
        'Validates Cloudflare Access at the WordPress origin. This plugin does not replace the WordPress login.' => '在 WordPress origin 验证 Cloudflare Access。本插件不替代 WordPress 登录。',
    ),
);
