<?php

/**
 * Watchdog bot implementation.
 *
 * @author [deepeloper](https://github.com/deepeloper)
 * @license [MIT](https://opensource.org/licenses/mit-license.php)
 */

declare(strict_types=1);

use deepeloper\TelegramWatchdog\Webhook\Handler\Watchdog;
use deepeloper\TunneledWebhooks\Webhook\Handler\IO\Telegram as IOTelegram;

error_reporting(E_ALL);

require_once "../vendor/autoload.php";

$config = (require_once "../config.php")['webhook']['Telegram']['Watchdog'];

$io = new IOTelegram();
$io->init($config);

$bot = new Watchdog($config);
$bot->init($io);

$bot->run();
