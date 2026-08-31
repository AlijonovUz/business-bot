<?php

namespace App\Handlers;

use App\Services\KeyboardBuilder;

class CalculatorHandler extends BaseHandler
{

    public function handleSettingsCallback(string $data): void
    {
        $this->handleToggle(
            'calculator',
            $data,
            'kalkulyator',
            fn(string $newValue) => $this->renderSettings($newValue)
        );
    }

    private function renderSettings(string $value): void
    {
        $res = $value === 'on' ? 'Yoqilgan!' : 'O\'chirilgan!';

        $keyboard = KeyboardBuilder::make()
            ->toggleButtons($value, 'kalkulyator')
            ->backButton('settings')
            ->toJson();

        $this->editCallback(
            "<tg-emoji emoji-id='5341715473882955310'>⚙️</tg-emoji> <b>Kalkulyator sozlamalaridasiz!</b>\n\n" .
            "<i>— Hozirgi holat: $res</i>\n\n" .
            "<tg-emoji emoji-id='5406745015365943482'>⬇️</tg-emoji> <b>Chatlarda foydalanish:</b>\n\n" .
            "<code>.math</code> – <i>Ifodani yozing (masalan: <b>.math 25*4+10</b>) va bot natijani hisoblab beradi.</i>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard]
        );
    }

    public function handleMathCommand(?string $customText = null): void
    {
        $text = $customText ?? $this->update->getText() ?? $this->update->getEditedText() ?? '';

        if (!$this->storage->isEnabled('calculator')) {
            $this->editBusiness(
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Kalkulyator faolsizlantirilgan!</b>",
                ['parse_mode' => 'html']
            );
            return;
        }

        $expression = trim(substr($text, mb_stripos($text, '.math ') + 6));
        $expression = str_replace(' ', '', $expression);

        try {
            if ($expression === '' || !preg_match('#^[0-9+\-*/().\s]+$#', $expression)) {
                throw new \Exception("Noto'g'ri matematik ifoda kiritildi!");
            }

            $result = @eval('return ' . $expression . ';');

            if ($result === false || is_infinite($result) || is_nan($result)) {
                throw new \Exception("Qabul qilinmadi!");
            }

            $this->editBusiness(
                "<tg-emoji emoji-id='5422439311196834318'>💡</tg-emoji> <b>Natija:</b>\n\n" .
                "<code>{$expression} = {$result}</code>",
                ['parse_mode' => 'html']
            );

        } catch (\DivisionByZeroError) {
            $this->editBusiness(
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>Nolga bo'lish mumkin emas!</b>",
                ['parse_mode' => 'html']
            );
        } catch (\Throwable $e) {
            $this->editBusiness(
                "<tg-emoji emoji-id='5447644880824181073'>⚠️</tg-emoji> <b>{$e->getMessage()}</b>",
                ['parse_mode' => 'html']
            );
        }
    }
}