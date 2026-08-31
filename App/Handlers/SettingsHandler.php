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

        $this->editCallback(
            "<tg-emoji emoji-id='5260293700088511294'>⛔️</tg-emoji> <b>Bo'lim yopildi!</b>",
            ['parse_mode' => 'html']
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
                KeyboardBuilder::btn("Kalkulyator", "kalkulyator", "5422439311196834318"),
                KeyboardBuilder::btn("Avto javob", "avto", "5443038326535759644")
            )
            ->row(
                KeyboardBuilder::btn("Valyuta", "valyuta", "5409048419211682843"),
                KeyboardBuilder::btn("Statistika", "statistika", "5231200819986047254")
            )
            ->rowSingle("Yopish", "yopish", "5260293700088511294")
            ->toJson();
    }
}