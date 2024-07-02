<?php

/**
 * Watchdog bot implementation.
 *
 * @author [deepeloper](https://github.com/deepeloper)
 * @license [MIT](https://opensource.org/licenses/mit-license.php)
 */

declare(strict_types=1);

namespace deepeloper\TelegramWatchdog\Webhook\Handler;

use deepeloper\Lib\FileSystem\Logger;
use deepeloper\TunneledWebhooks\Webhook\Handler\HandlerAbstract;
use Telegram\Bot\Objects\Update as UpdateObject;
use Telegram\Bot\Api;
use Throwable;

class Watchdog extends HandlerAbstract
{
    protected array $config;

    protected ?Logger $logger = null;

    protected Api $api;

    protected UpdateObject $update;

    protected int $botId;

    protected array $admins = [];

    public function __construct(array $config)
    {
        $this->config = $config;

        if (isset($config['logger'])) {
            $this->logger = new Logger($config['logger']);
        }
    }

    public function run(mixed $options = null): void
    {
        try {
            $this->api = $this->io->getApi();
            $this->update = $this->api->getWebhookUpdate();

//            $this->log(sprintf(
//                "update:\n%s\n",
//                var_export($this->update, true),
//            ));
//            $this->log(sprintf(
//                "getMe():\n%s\n",
//                var_export($this->api->getMe(), true),
//            ));

            switch ($this->update->objectType()) {
                case "message":
                    $this->processMessage();
                    break;

//            case "my_chat_member":
//                $this->processChatManipulation();
//                break;
            }
        } catch (Throwable $exception) {
            $this->log(sprintf(
                "%s, %d\n%s\n%s\n",
                $exception->getFile(),
                $exception->getLine(),
                $exception->getMessage(),
                $exception->getTraceAsString(),
            ));
            return;
        }
    }

    protected function processMessage(): void
    {
        // Skip updates having no message or messages from bots.
        if ($this->update->message->from->is_bot) {
            return;
        }

//        $this->log(sprintf(
//            "getChat():\n%s\n",
//            var_export($this->api->getChat(['chat_id' => $this->update->message->chat->id]), true),
//        ));
//        $this->log(sprintf(
//            "chatType:\n%s\n",
//            var_export($this->update->message->chat->type, true),
//        ));

        switch ($this->update->message->chat->type) {
            case "supergroup":
                $this->processSupergroup();
                break;

//            case "private":
//                $this->processPrivateChat();
//                break;
        }
    }

//    protected function processChatManipulation(): void
//    {
//    }

    protected function processSupergroup(): void
    {
        $this->botId = $this->api->getMe()->id;
        $admins = $this->api->getChatAdministrators(['chat_id' => $this->update->message->chat->id]);
        $botIsAdmin = false;
        foreach ($admins as $admin) {
            if ($admin->user->id !== $this->botId) {
                $this->admins[$admin->user->id] = "@" . $admin->user->username;
            } else {
//                $this->log(sprintf(
//                    "admin:\n%s\n",
//                    var_export($admin, true),
//                ));

                // Check bot permissions.
                $botIsAdmin =
                    $admin->can_manage_chat && $admin->can_delete_messages && $admin->can_restrict_members;
            }
        }
        if (isset($this->admins[$this->update->message->from->id])) {
            $this->processMessageFromChatAdmin($botIsAdmin);
        } else {
            $this->processMessageFromCommonChatUser($botIsAdmin);
        }
    }

//    protected function processPrivateChat(): void
//    {
//        if (!in_array($this->update->message->from->id, $this->config['owners'])) {
//            return;
//        }
//    }

    protected function processMessageFromChatAdmin(bool $botIsAdmin): void
    {
        if ("!ping" === $this->update->message->text) {
            $message = [
                'chat_id' => $this->update->message->chat->id,
                'reply_to_message_id' => $this->update->message->message_id,
                'text' => "!pong",
            ];
            if ($botIsAdmin) {
                $message += [
                    'disable_notification' => true,
                    'protect_content' => true,
                ];
            }
            $this->api->sendMessage($message);
            return;
        }

        if (!$botIsAdmin || !isset($this->update->message->reply_to_message)) {
            return;
        }

        $userId = $this->update->message->reply_to_message->from->id;
        if ($userId === $this->botId || isset($this->admins[$userId])) {
            // Cannot manipulate with bot and admins.
            $this->deleteLastMessage();
            return;
        }

        $period = null;
        $commands = ["ban+", "ban", "mute", "woof"];
        foreach ($commands as $command) {
            if (str_starts_with($this->update->message->text, "!$command")) {
                $period = substr($this->update->message->text, strlen($command) + 1);
                break;
            }
        }
        if (null === $period) {
            // Command not found.
            return;
        }

        if (str_contains($period, "*")) {
            $multipliers = explode("*", $period);
            $period = 1;
            array_walk($multipliers, function ($multiplier) use (&$period) {
                $period = $period * (int)$multiplier;
            });
        } else {
            $period = (int)$period;
        }

        $untilDate = time() + $period * 60;

        switch ($command) {
            case "ban+":
                $this->affectBan($untilDate, ['revoke_messages' => true]);
                break;
            case "ban":
                $this->affectBan($untilDate, ['revoke_messages' => false]);
                break;
            case "mute":
                $this->affectSendingMessage($untilDate, false);
                break;
            case "woof":
                $this->affectSendingMessage($untilDate, true);
                break;
        }

        $this->api->sendMessage([
            'chat_id' => $this->update->message->chat->id,
            'disable_notification' => true,
            'protect_content' => true,
            'reply_to_message_id' => $this->update->message->message_id,
            'text' => 0 !== $period
                ? sprintf(
                    "%s (%d minutes) for @%s",
                    $command,
                    $period,
                    $this->update->message->reply_to_message->from->username,
                )
                : sprintf(
                    "%s for @%s",
                    $command,
                    $this->update->message->reply_to_message->from->username,
                ),
        ]);
    }

    protected function processMessageFromCommonChatUser(bool $botIsAdmin): void
    {
        if (
            !isset($this->update->message->reply_to_message) ||
            "!report" !== $this->update->message->text
        ) {
            return;
        }
        $admins = $this->admins + [$this->botId => true];
        if (isset($admins[$this->update->message->reply_to_message->from->id])) {
            if ($botIsAdmin) {
                // Cannot manipulate with bot and admins.
                $this->deleteLastMessage();
            }
            return;
        }

        // Tag admins.
        $this->api->sendMessage([
            'chat_id' => $this->update->message->chat->id,
            'protect_content' => true,
            'reply_to_message_id' => $this->update->message->reply_to_message->message_id,
            'text' => implode(", ", array_values($this->admins)),
        ]);
    }

    protected function affectBan(int $untilDate, array $params = []): void
    {
        $this->api->banChatMember([
            'chat_id' => $this->update->message->chat->id,
            'user_id' => $this->update->message->reply_to_message->from->id,
            'until_date' => $untilDate,
        ] + $params);
    }

    protected function affectSendingMessage(int $untilDate, bool $allow): void
    {
        $this->api->restrictChatMember([
            'chat_id' => $this->update->message->chat->id,
            'user_id' => $this->update->message->reply_to_message->from->id,
            'permissions' => ['can_send_messages' => $allow],
            'until_date' => $untilDate,
        ]);
    }

    protected function deleteLastMessage(): void
    {
        $this->api->deleteMessage([
            'chat_id' => $this->update->message->chat->id,
            'message_id' => $this->update->message->message_id,
        ]);
    }

    protected function log(string $message): void
    {
        $this->logger?->log(sprintf(
            "[ %s ] %s\n",
            date("Y-m-d H:i:s"),
            $message,
        ));
    }
}
