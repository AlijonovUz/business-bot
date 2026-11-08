<?php

namespace App\Handlers;

use App\Api\TelegramApi;
use App\Api\Update;
use App\Services\StorageService;
use App\Services\KeyboardBuilder;

abstract class BaseHandler
{
    protected TelegramApi $api;
    protected Update $update;
    protected StorageService $storage;
    protected int $adminId;
    protected string $botName;

    public function __construct(
        TelegramApi    $api,
        Update         $update,
        StorageService $storage,
        int            $adminId,
        string         $botName = ''
    )
    {
        $this->api = $api;
        $this->update = $update;
        $this->storage = $storage;
        $this->adminId = $adminId;
        $this->botName = $botName;
    }

    protected function isAdminCallback(): bool
    {
        return $this->update->getCallbackFromId() === $this->adminId;
    }

    protected function isAdminBusiness(): bool
    {
        return $this->update->getBusinessFromId() === $this->adminId;
    }

    protected function denyCallback(): void
    {
        $this->api->answerCallbackQuery(
            $this->update->getCallbackQueryId(),
            "⚠️ Bu tugma siz uchun mo'ljallanmagan!",
            false
        );
    }

    protected function resolveToggleValue(string $data, string $currentValue): string
    {
        return str_contains($data, '=')
            ? explode('=', $data, 2)[1]
            : $currentValue;
    }

    protected function editBusiness(string $text, array $extra = []): void
    {
        $bid = $this->update->getBusinessConnectionId() ?? $this->update->getEditedBusinessId();
        
        if ($bid) {
            $cid = $this->update->getBusinessChatId() ?? $this->update->getEditedChatId();
            $mid = $this->update->getBusinessMessageId() ?? $this->update->getEditedMessageId();
            $this->api->sendBusinessChatAction($bid, $cid, 'typing');
            $this->api->editBusinessMessage($bid, $cid, $mid, $text, $extra);
        } else {
            $cid = $this->update->getChatId();
            $this->api->sendMessage($cid, $text, $extra);
        }
    }

    protected function editCallback(string $text, array $extra = []): void
    {
        $bid = $this->update->getCallbackBusinessId();
        $cid = $this->update->getCallbackMessageChatId();
        $mid = $this->update->getCallbackMessageId();

        if ($bid) {
            $this->api->editBusinessMessage($bid, $cid, $mid, $text, $extra);
        } else {
            $this->api->editMessageText($cid, $mid, $text, $extra);
        }
    }

    protected function handleToggle(
        string   $settingName,
        string   $data,
        string   $callbackName,
        callable $renderFn
    ): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $current = $this->storage->getSetting($settingName);
        $newValue = $this->resolveToggleValue($data, $current);
        $res = $newValue === 'on' ? 'Yoqilgan!' : 'O\'chirilgan!';

        if ($data === $callbackName || $current !== $newValue) {
            if ($current !== $newValue) {
                $this->storage->setSetting($settingName, $newValue);
            }
            $renderFn($newValue);
        } else {
            
            $this->api->answerCallbackQuery(
                $this->update->getCallbackQueryId(),
                "⚠️ $res",
                true
            );
        }
    }

    protected function paginate(string $data, int $totalItems, int $perPage): ?array
    {
        $totalPages = max(1, (int)ceil($totalItems / $perPage));

        $requestedPage = 1;
        if (str_contains($data, '&page=')) {
            $requestedPage = (int)explode('&page=', $data, 2)[1];
        }

        if ($requestedPage < 1) {
            $this->api->answerCallbackQuery($this->update->getCallbackQueryId(), "Bosh sahifadasiz!", true);
            return null;
        }

        if ($requestedPage > $totalPages) {
            $this->api->answerCallbackQuery($this->update->getCallbackQueryId(), "Oxirgi sahifadasiz!", true);
            return null;
        }

        $page = max(1, min($totalPages, $requestedPage));
        $offset = ($page - 1) * $perPage;

        return [
            'page' => $page,
            'totalPages' => $totalPages,
            'offset' => $offset,
            'perPage' => $perPage,
        ];
    }

    protected function isValidHtml(string $text): bool
    {
        if (substr_count($text, '<tg-emoji') !== substr_count($text, '</tg-emoji>')) {
            return false;
        }

        preg_match_all('/<tg-emoji([^>]*)>/', $text, $matches);
        foreach ($matches[1] as $attrString) {
            if (!preg_match('/emoji-id\s*=\s*[\'"][0-9]+[\'"]/', $attrString)) {
                return false;
            }
        }

        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $converted = preg_replace('/<tg-emoji([^>]*)>/', '<span tg-emoji$1>', $text);
        $converted = preg_replace('/<\/tg-emoji>/', '</span>', $converted);

        @$doc->loadHTML('<?xml encoding="utf-8" ?><!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $converted . '</body></html>');
        $errors = libxml_get_errors();
        libxml_clear_errors();

        foreach ($errors as $error) {
            $msg = strtolower($error->message);
            if (str_contains($msg, 'tag tg-emoji')) continue;
            if (str_contains($msg, 'attribute') && str_contains($msg, 'tg-emoji')) continue;
            return false;
        }

        return true;
    }
}