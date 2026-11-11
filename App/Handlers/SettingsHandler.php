<?php

namespace App\Handlers;

use App\Services\KeyboardBuilder;

class SettingsHandler extends BaseHandler
{

    public function handleSettingsCommand(): void
    {
        $bid = $this->update->getBusinessConnectionId();
        $cid = $bid ? $this->update->getBusinessChatId() : $this->update->getChatId();
        $mid = $bid ? $this->update->getBusinessMessageId() : $this->update->getMessageId();

        $text = $this->settingsText();
        $extra = [
            'parse_mode' => 'html',
            'reply_markup' => $this->settingsKeyboard(),
        ];

        if ($bid) {
            $this->api->sendBusinessChatAction($bid, $cid, 'typing');
            $this->api->editBusinessMessage($bid, $cid, $mid, $text, $extra);
        } else {
            $this->api->sendMessage($cid, $text, $extra);
        }
    }

    public function handleSettingsCallback(): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $this->editCallback($this->settingsText(), [
            'parse_mode' => 'html',
            'reply_markup' => $this->settingsKeyboard(),
        ]);

        $this->storage->clearStep($this->update->getCallbackMessageChatId());
    }

    public function handleCloseCallback(): void
    {
        if (!$this->isAdminCallback()) {
            $this->denyCallback();
            return;
        }

        $this->storage->clearStep($this->update->getCallbackMessageChatId());

        $this->editCallback(
            "<tg-emoji emoji-id='5260293700088511294'>⛔️</tg-emoji> <b>Bo'lim yopildi!</b>",
            ['parse_mode' => 'html']
        );
    }

    public function handleUpdateLogCallback(string $data): void
    {
        $this->handleToggle(
            'update-log',
            $data,
            'update_log',
            fn(string $newValue) => $this->renderUpdateLogSettings($newValue)
        );
    }

    private function renderUpdateLogSettings(string $value): void
    {
        $res = $value === 'on' ? 'Yoqilgan!' : 'O\'chirilgan!';

        $keyboard = KeyboardBuilder::make()
            ->toggleButtons($value, 'update_log')
            ->backButton('settings')
            ->toJson();

        $this->editCallback(
            "<tg-emoji emoji-id='5231012545799666522'>🔍</tg-emoji> <b>Yangilanishlar logi sozlamalaridasiz!</b>\n\n" .
            "<i>— Hozirgi holat: $res</i>\n\n" .
            "<i>Ushbu rejim yoqilganda botga kelgan har bir yangilanish (update) xom JSON ko'rinishida adminga yuboriladi.</i>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard]
        );
    }

    private function settingsText(): string
    {
        return "<tg-emoji emoji-id='5341715473882955310'>⚙️</tg-emoji> <b>Sozlamalar bo'limidasiz!</b>\n\n<i>Quyidagi bo'limlardan birini tanlang:</i>";
    }

    public function settingsKeyboard(): string
    {
        return KeyboardBuilder::make()
            ->row(
                KeyboardBuilder::btn("Tahrirlangan xabar", "tahrirlangan", "5395444784611480792"),
                KeyboardBuilder::btn("O'chirilgan xabar", "ochirilgan", "5445267414562389170")
            )
            ->rowSingle("Vaqtli media xabarlar", "vaqtli-media", "5386367538735104399")
            ->row(
                KeyboardBuilder::btn("Avto javob", "avto", "5443038326535759644"),
                KeyboardBuilder::btn("Hikoyalar", "hikoya", "5348125953090403204")
            )
            ->row(
                KeyboardBuilder::btn("Kalkulyator", "kalkulyator", "5422439311196834318"),
                KeyboardBuilder::btn("Valyuta", "valyuta", "5409048419211682843")
            )
            ->row(
                KeyboardBuilder::btn("Statistika", "statistika", "5231200819986047254"),
                KeyboardBuilder::btn("Yangilanishlar logi", "update_log", "5231012545799666522")
            )
            ->rowSingle("Yopish", "yopish", "5260293700088511294")
            ->toJson();
    }
}