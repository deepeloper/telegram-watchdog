<?php

/**
 * Config file.
 *
 * @author [deepeloper](https://github.com/deepeloper)
 * @license [MIT](https://opensource.org/licenses/mit-license.php)
 */

declare(strict_types=1);

return [
    'logging' => [
        'target' => "php://stdout",
        'sources' => ["*"],
        'level' => E_ALL,
    ],

    'service' => [
        'class' => "\\deepeloper\\TunneledWebhooks\\Service\Ngrok",
        // CLI command to run service.
        // Modify path here:
        'command' => "/path/to/ngrok http 80",
        // Delay after starting service.
        'delay' => 5, // in seconds
        // Default ngrok status page
        'status' => "http://localhost:4040/api/tunnels",
    ],

    'webhook' => [
        'Telegram' => [
            'Watchdog' => [
                'class' => "\\deepeloper\\TunneledWebhooks\\Webhook\\Connector\\Telegram",
                'url' => [
                    'register' => "https://api.telegram.org/bot%s/setWebhook?url=%s/bot.watchdog.php",
                    'release' => "https://api.telegram.org/bot%s/deleteWebhook",
                ],
                // Telegram bot token.
                // Modify token here:
                'token' => "TOKEN",

                'cachePath' => "/path/to/cache/file.php",

                'commandPrefix' => ".",
                'tagChatCreator' => true,
                'woofDelay' => 30, // In seconds, 30 minimum.

                // Optional section.
                // See https://github.com/deepeloper/lib-fs?tab=readme-ov-file#logging-functionality-supporting-files-rotation.
/*
                'logger' => [
                    'path'    => "/path/to/log/file",
                    'rotation' => 1,
                ],
*/
            ],
        ],
    ],
];
