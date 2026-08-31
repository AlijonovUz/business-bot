<?php

namespace App\Handlers;

use App\Services\KeyboardBuilder;

class CurrencyHandler extends BaseHandler
{
    public function handleSettingsCallback(string $data): void
    {
        $this->handleToggle('currency', $data, 'valyuta', fn($v) => $this->renderSettings($v));
    }

    private function renderSettings(string $value): void
    {
        $res = $value === 'on' ? 'Yoqilgan!' : 'O\'chirilgan!';
        $keyboard = KeyboardBuilder::make()->toggleButtons($value, 'valyuta')->backButton('settings')->toJson();

        $this->editCallback(
            "<tg-emoji emoji-id='5341715473882955310'>⚙️</tg-emoji> <b>Valyuta sozlamalaridasiz!</b>\n\n" .
            "<i>— Hozirgi holat: $res</i>\n\n" .
            "<tg-emoji emoji-id='5406745015365943482'>⬇️</tg-emoji> <b>Chatlarda foydalanish:</b>\n\n" .
            "<code>.currency</code> – <i>Hozirgi valyuta kurslarini ko'rsatadi.</i>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard]
        );
    }

    public function handleCurrencyCommand(): void
    {
        if (!$this->storage->isEnabled('currency')) {
            $this->editBusiness("<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Valyuta faolsizlantirilgan!</b>", ['parse_mode' => 'html']);
            return;
        }

        $ch = curl_init('https://cbu.uz/uz/arkhiv-kursov-valyut/json/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = $response ? json_decode($response, true) : null;

        if (!$data || !is_array($data)) {
            $this->editBusiness(
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Valyuta kurslarini yuklab bo'lmadi!</b>\n\n<i>Markaziy bank serveri bilan bog'lanishda xatolik yuz berdi.</i>",
                ['parse_mode' => 'html']
            );
            return;
        }

        $rates = [];
        foreach ($data as $item) {
            if (isset($item['Ccy']) && in_array($item['Ccy'], ['USD', 'EUR', 'RUB'])) {
                $rates[$item['Ccy']] = $item['Rate'] ?? '0';
            }
        }

        $usd = $rates['USD'] ?? 'Noma\'lum';
        $eur = $rates['EUR'] ?? 'Noma\'lum';
        $rub = $rates['RUB'] ?? 'Noma\'lum';

        $text = "<tg-emoji emoji-id='5231200819986047254'>📊</tg-emoji> <b>Valyuta kurslari!</b>\n\n" .
            "<tg-emoji emoji-id='5409048419211682843'>💵</tg-emoji> <i>1 ( USD ) - {$usd} UZS</i>\n" .
            "<tg-emoji emoji-id='5233326571099534068'>💸</tg-emoji> <i>1 ( EUR ) - {$eur} UZS</i>\n" .
            "<tg-emoji emoji-id='5231449120635370684'>💸</tg-emoji> <i>1 ( RUB ) - {$rub} UZS</i>";

        $this->editBusiness($text, ['parse_mode' => 'html']);
    }
}