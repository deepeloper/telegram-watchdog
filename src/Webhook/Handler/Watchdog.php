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
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update as UpdateObject;
use Throwable;

class Watchdog extends HandlerAbstract
{
    public const VERSION = "1.1.0";

    protected const PROCESSING_TYPES = ["message", "my_chat_member"];

    protected array $config;

    protected ?Logger $logger = null;

    protected array $cache = [];

    protected array $loadedCache;

    protected Api $api;

    protected UpdateObject $update;

    protected int $chatId;

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
            $this->loadCache();
            $this->update = $this->api->getWebhookUpdate();
            $type = $this->update->objectType();

//            $this->log(\sprintf(
//                "update (%s):\n%s\n",
//                $type,
//                var_export($this->update, true),
//            ));
//            $this->log(\sprintf(
//                "getMe():\n%s\n",
//                var_export($this->api->getMe(), true),
//            ));

            if (!\in_array($type, self::PROCESSING_TYPES)) {
                return;
            }

            switch ($type) {
                case "message":
                    $this->processMessage();
                    break;

                case "my_chat_member":
                    $this->processChatManipulation();
                    break;
            }
        } catch (Throwable $exception) {
            $this->log(\sprintf(
                "%s, %d\n%s\n%s\n",
                $exception->getFile(),
                $exception->getLine(),
                $exception->getMessage(),
                $exception->getTraceAsString(),
            ));
        }

        $this->saveCache();
    }

    protected function loadCache(): void
    {
        if (\file_exists($this->config['cachePath'])) {
            $this->cache = require_once $this->config['cachePath'];
        } else {
            $this->cache['botId'] = $this->api->getMe()->id;
            $this->cache['chats'] = [];
        }
        $this->loadedCache = $this->cache;
    }

    protected function saveCache(): void
    {
        if ($this->loadedCache !== $this->cache) {
            \file_put_contents(
                $this->config['cachePath'],
                "<?php return " . \var_export($this->cache, true) . ";"
            );
            $this->loadedCache = $this->cache;
        }
    }

    protected function processMessage(): void
    {
        // Skip updates having messages from bots.
        if ($this->update->message->from->is_bot) {
            return;
        }

        $this->chatId = $this->update->message->chat->id;
        $chat = $this->api->getChat(['chat_id' => $this->chatId]);
        $this->fillPermissions($chat->permissions, ["can_send_messages"]);

//        $this->log(\sprintf(
//            "getChat():\n%s\n",
//            var_export($chat, true),
//        ));
//        $this->log(\sprintf(
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

    protected function processChatManipulation(): void
    {
        if (isset($this->update->my_chat_member->new_chat_member)) {
            $this->chatId = $this->update->my_chat_member->chat->id;
            $this->updateChatInfo();
        }
    }

    protected function updateChatInfo(): void
    {
        $this->cache['chats'][$this->chatId]['admins'] = [];
        $admins = $this->api->getChatAdministrators(['chat_id' => $this->chatId]);

//        $this->log(\sprintf(
//            "\$admins:\n%s\n",
//            var_export($admins, true),
//        ));

        foreach ($admins as $admin) {
//                $this->log(\sprintf(
//                    "\$admin:\n%s\n",
//                    var_export($admin, true),
//                ));

            if ($admin->user->id !== $this->cache['botId']) {
                if ("creator" === $admin->status) {
                    $this->cache['chats'][$this->chatId]['creator'] = $admin->user->id;
                }
                $this->cache['chats'][$this->chatId]['admins'][$admin->user->id] =
                    "@" . ($admin->user->username ?? $admin->user->first_name);
            } else {
//                $this->log(\sprintf(
//                    "bot:\n%s\n",
//                    var_export($admin, true),
//                ));

                // Check bot permissions.       // can_manage_chat >> report spam message
                $this->fillPermissions($admin, ["can_delete_messages", "can_restrict_members"]);
            }
        }
    }

    protected function processSupergroup(): void
    {

//        $this->log(\sprintf(
//            "\$permissions:\n%s\n",
//            var_export($this->cache['permissions'], true),
//        ));

        if (!isset($this->cache['chats'][$this->chatId]['admins'])) {
            $this->updateChatInfo();
        }

        if (isset($this->cache['chats'][$this->chatId]['admins'][$this->update->message->from->id])) {
            $this->processMessageFromChatAdmin();
        } else {
            $this->processMessageFromCommonChatUser();
        }
    }

//    protected function processPrivateChat(): void
//    {
//        if (!in_array($this->update->message->from->id, $this->config['owners'])) {
//            return;
//        }
//    }

    protected function processMessageFromChatAdmin(): void
    {
        $prefix = $this->config['commandPrefix'];
        switch ($this->update->message->text) {
            case $prefix . "ping":
                $this->sendPong();
                return;

            case $prefix . "admin":
                $this->updateChatInfo();
                $this->sendMessage("Chat admin list updated.");
                break;
        }

        if (
            empty($this->cache['chats'][$this->chatId]['permissions']['can_restrict_members']) ||
            !isset($this->update->message->reply_to_message)
        ) {
            return;
        }

        $userId = $this->update->message->reply_to_message->from->id;
        if ($userId === $this->cache['botId'] || isset(['chats'][$this->chatId]['admins'][$userId])) {
            $this->sendMessage("Cannot affect admins or bot.");
            return;
        }

        $period = null;
        $commands = ["ban+", "ban", "mute", "woof"];
        foreach ($commands as $command) {
            if (\str_starts_with($this->update->message->text, "$prefix$command")) {
                $period = \substr($this->update->message->text, strlen($command) + strlen($prefix));
                break;
            }
        }
        if (null === $period) {
            // Command not found.
            return;
        }

//        $this->log("\$command: '$command', \$period: $period");

        if (\str_contains($period, "*")) {
            $multipliers = explode("*", $period);
            $period = 1;
            \array_walk($multipliers, function ($multiplier) use (&$period) {
                $period = $period * (int)$multiplier;
            });
        } else {
            $period = (int)$period;
        }

        $untilDate = \time() + $period * 60;

//        $this->log(\sprintf(
//            "\$calculatedPeriod: %d, %s",
//            $period,
//            \date("Y-m-d H:i:s", $untilDate),
//        ));

        $message = "";
        $args = [$this->update->message->reply_to_message->from->username];

        if (!$this->checkPermission("can_restrict_members")) {
            return;
        }

        switch ($command) {
            case "ban+":
            case "ban":
                if (!$this->checkPermission("can_restrict_members")) {
                    return;
                }

                $this->affectToBan($untilDate, ['revoke_messages' => "ban" === $command]);
                if ($period > 0) {
                    $message = "@%s banned (%d minutes).";
                    $args[] = $period;
                } else {
                    $message = "@%s banned.";
                }
                break;

            case "mute":
                if (!$this->checkPermission("can_restrict_members")) {
                    return;
                }

                $this->affectToSendingMessage($untilDate, false);
                if ($period > 0) {
                    $message = "@%s muted (%d minutes).";
                    $args[] = $period;
                } else {
                    $message = "@%s muted.";
                }
                break;

            case "woof":
                $this->affectToSendingMessage(\time() + $this->config['woofDelay'], true);
                $message = "@%s allowed to write messages in %d seconds.";
                $args[] = $this->config['woofDelay'];
        }

        $this->sendMessage(\vsprintf($message, $args));
    }

    protected function sendPong(): void
    {
        $scope = json_decode(file_get_contents(__DIR__ . "/../../../composer.json"), true);
        $this->sendMessage(
            \str_replace(".", "\\.", \sprintf(
                "[%s v%s](%s/tree/%s)",
                $scope['description'],
                self::VERSION,
                $scope['homepage'],
                self::VERSION,
            )),
            [
                'parse_mode' => "MarkdownV2",
                'disable_web_page_preview' => true,
            ],
        );
    }

    protected function processMessageFromCommonChatUser(): void
    {
        if (
            !isset($this->update->message->reply_to_message) ||
            $this->config['commandPrefix'] . "report" !== $this->update->message->text
        ) {
            return;
        }
        $admins = $this->cache['chats'][$this->chatId]['admins'] + [$this->cache['botId'] => true];
        if (isset($admins[$this->update->message->reply_to_message->from->id])) {
            $this->sendMessage("Cannot report about admins or bot.");
            return;
        }

        // Tag admins.
        $admins = $this->cache['chats'][$this->chatId]['admins'];
        if (!$this->config['tagChatCreator'] && null !== $this->cache['chats'][$this->chatId]['creator']) {
            unset($admins[$this->cache['chats'][$this->chatId]['creator']]);
        }
        if (\sizeof($admins) > 0) {
            $this->sendMessage(
                \implode(", ", \array_values($admins)),
                [
                    'disable_notification' => false,
                    'reply_to_message_id' => $this->update->message->reply_to_message->message_id,
                ]
            );
        } else {
            $this->sendMessage("There are no admins.");
        }
    }

    protected function fillPermissions(mixed $source, array $permissions): void
    {
//        $this->log(\sprintf(
//            "fillPermissions():\n%s\n%s\n\n",
//            var_export($permissions, true),
//            var_export($source, true),
//        ));
        foreach ($permissions as $permission) {
            $this->cache['chats'][$this->chatId]['permissions'][$permission] = $source->$permission;
        }
    }

    protected function checkPermission(string $permission): bool
    {
        if (empty($this->cache['chats'][$this->chatId]['permissions'][$permission])) {
            $this->sendMessage("Not enough permissions.");
            return false;
        }

        return true;
    }

    protected function sendMessage(?string $message = "", ?array $params = []): void
    {
        if (!empty($this->cache['chats'][$this->chatId]['permissions']['can_send_messages'])) {
            $params += [
                'allow_sending_without_reply' => true,
                'chat_id' => $this->update->message->chat->id,
                'disable_notification' => true,
                'protect_content' => true,
                'reply_to_message_id' => $this->update->message->message_id,
            ];
            if ("" !== $message) {
                $params += ['text' => $message];
            }
            $this->api->sendMessage($params);
        }
    }

    protected function affectToBan(int $untilDate, ?array $params = []): void
    {
        if ($this->checkPermission("can_restrict_members")) {
            $this->api->banChatMember([
                    'chat_id' => $this->update->message->chat->id,
                    'user_id' => $this->update->message->reply_to_message->from->id,
                    'until_date' => $untilDate,
                ] + $params);
        }
    }

    protected function affectToSendingMessage(int $untilDate, bool $allow): void
    {
        if ($this->checkPermission("can_restrict_members")) {
//            $this->log(sprintf(
//                "affectToSendingMessage('%s', %s)",
//                date("Y-m-d H:i:s", $untilDate),
//                \var_export($allow, true),
//            ));

            $this->api->restrictChatMember([
                'chat_id' => $this->update->message->chat->id,
                'user_id' => $this->update->message->reply_to_message->from->id,
                'permissions' => ['can_send_messages' => $allow],
                'until_date' => $untilDate,
                'use_independent_chat_permissions' => true,
            ]);
        }
    }

    protected function log(string $message): void
    {
        $this->logger?->log(\sprintf(
            "[ %s ] %s\n",
            \date("Y-m-d H:i:s"),
            $message,
        ));
    }
}
