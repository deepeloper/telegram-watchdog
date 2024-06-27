# Telegram Watchdog Chat Bot

[![Donation](https://img.shields.io/badge/Donation-Visa,%20MasterCard,%20Maestro,%20UnionPay,%20YooMoney,%20МИР-red)](https://yoomoney.ru/to/41001351141494)

Allows common chat users to tag admins by simple command `!report` as reply to spam messages.

Chat administrators can use following commands as reply to messages:
* `!ban+[ period]` - ban replied user and revoke messages;
* `!ban [ period]` - ban replied user and don't revoke messages;
* `!mute[ period]` - mute replied user;
* `!woof` - allow to chat replied user;

Also chat administrators can send `!ping` check if the bot is alive.

The bot is based on [Tunneled Webhooks](https://github.com/deepeloper/tunneled-webhooks), so can be used at local PC or server.  

## Compatibility
[![PHP 8.0](https://img.shields.io/badge/PHP->=8.0-%237A86B8)]()

## Installation
`composer require deepeloper/telegram-watchdog`

Copy &laquo;config.skeleton.php&raquo; to &laquo;config.php&raquo; and modify it:
* &laquo;service/command&raquo; value to use at local PC;
* &laquo;webhook/Telegram/Watchdog/token&raquo; value ([register Telegram bot](https://core.telegram.org/bots) to receive bot auth token).

Invite your bot to chats and set admin permissions.
