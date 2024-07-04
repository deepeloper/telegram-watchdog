# Telegram Watchdog Chat Bot

[![PHP 8.0](https://img.shields.io/badge/PHP->=8.0-%237A86B8)]()
[![GitHub license](https://img.shields.io/github/license/deepeloper/telegram-watchdog.svg)](https://github.com/deepeloper/telegram-watchdog/blob/main/LICENSE)
[![GitHub issues](https://img.shields.io/github/issues-raw/deepeloper/telegram-watchdog.svg)](https://github.com/deepeloper/telegram-watchdog/issues)
[![Views](https://views.whatilearened.today/views/github/deepeloper/telegram-watchdog.svg)]()
[![Clones](https://img.shields.io/badge/dynamic/json?color=success&label=Clones&query=count&url=https://gist.githubusercontent.com/deepeloper/f08610a4386790ca473b7a48f5fcab32/raw/clone.json&logo=github)](https://github.com/deepeloper/telegram-watchdog)

[![Donation](https://img.shields.io/badge/Donation-Visa,%20MasterCard,%20Maestro,%20UnionPay,%20YooMoney,%20МИР-red)](https://yoomoney.ru/to/41001351141494)

Allows common chat users to tag admins by simple command `.report` as reply to spam messages.

Chat administrators can use following commands as reply to messages (if the bot has admin permissions):
* `.ban+[ period]` - ban replied user and revoke messages;
* `.ban [ period]` - ban replied user and don't revoke messages;
* `.mute[ period]` - mute replied user;
* `.woof` - allow to chat replied user;

Also chat administrators can send `!ping` check if the bot is alive.

The bot is based on [Tunneled Webhooks](https://github.com/deepeloper/tunneled-webhooks), so can be used at local PC or server.  

## Installation
Clone project from repository (`git clone https://github.com/deepeloper/telegram-watchdog`) and run `composer install`.

Copy &laquo;config.skeleton.php&raquo; to &laquo;config.php&raquo; and modify it:
* &laquo;service/command&raquo; value to use at local PC;
* &laquo;webhook/Telegram/Watchdog/token&raquo; value ([register Telegram bot](https://core.telegram.org/bots) to receive bot auth token).

Invite your bot to chats and optionally set admin permissions.
