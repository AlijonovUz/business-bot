<?php

namespace App\Handlers;

use App\Services\KeyboardBuilder;

class StatisticsHandler extends BaseHandler
{
    public function handleStatisticsCallback(string $data): void
    {
        if ($data === 'statistika') {
            $this->renderMenu();
        } elseif ($data === 'statistika=bugun') {
            $this->renderPeriodStats('today');
        } elseif ($data === 'statistika=hafta') {
            $this->renderPeriodStats('week');
        } elseif ($data === 'statistika=oy') {
            $this->renderPeriodStats('month');
        } elseif ($data === 'statistika=reyting') {
            $this->renderTopStats();
        }
    }

    private function renderMenu(): void
    {
        $keyboard = KeyboardBuilder::make()
            ->row(
                KeyboardBuilder::btn("Bugun", "statistika=bugun", "5413879192267805083"),
                KeyboardBuilder::btn("Shu hafta", "statistika=hafta", "5231200819986047254"),
                KeyboardBuilder::btn("Shu oy", "statistika=oy", "5244837092042750681")
            )
            ->rowSingle("Eng faol foydalanuvchilar", "statistika=reyting", "5217822164362739968")
            ->backButton('settings')
            ->toJson();

        $this->editCallback(
            "<tg-emoji emoji-id='5231200819986047254'>📊</tg-emoji> <b>Statistika bo'limi</b>\n\n" .
            "<i>Suhbatlar arxividan foydalanib o'zingizning biznes faolligingizni chuqur tahlil qiling. Quyidagi hisobot turlaridan birini tanlang:</i>",
            ['parse_mode' => 'html', 'reply_markup' => $keyboard]
        );
    }

    private function getAllHistory(): array
    {
        return $this->storage->getAllMessages();
    }

    private function renderPeriodStats(string $period): void
    {
        $messages = $this->getAllHistory();
        $now = time();
        
        if ($period === 'today') {
            $startDate = date('Y-m-d 00:00:00', $now);
            $title = "Bugungi hisobot (" . date('d.m.Y') . ")";
            $emoji = "5413879192267805083";
            $fallback = "🗓";
        } elseif ($period === 'week') {
            $startDate = date('Y-m-d 00:00:00', strtotime('monday this week'));
            $title = "Haftalik hisobot";
            $emoji = "5411306352138006323";
            $fallback = "📊";
        } else {
            $startDate = date('Y-m-01 00:00:00');
            $months = ['January'=>'Yanvar','February'=>'Fevral','March'=>'Mart','April'=>'Aprel','May'=>'May','June'=>'Iyun','July'=>'Iyul','August'=>'Avgust','September'=>'Sentabr','October'=>'Oktabr','November'=>'Noyabr','December'=>'Dekabr'];
            $title = "Oylik hisobot (" . $months[date('F')] . ", " . date('Y') . ")";
            $emoji = "5415843477147630737";
            $fallback = "📈";
        }

        $periodMessages = array_filter($messages, function($msg) use ($startDate) {
            return ($msg['date'] ?? '') >= $startDate;
        });

        $totalCount = count($periodMessages);
        $userCounts = [];
        $userNames = [];

        $hours = [];
        $mediaCounts = ['photo' => 0, 'video' => 0, 'voice' => 0];
        $adminMessagesCount = 0;
        $customerMessagesCount = 0;

        foreach ($periodMessages as $msg) {
            $uid = $msg['user_id'] ?? 'noma\'lum';
            
            if ($uid === $this->adminId) {
                $adminMessagesCount++;
            } else {
                $customerMessagesCount++;
            }

            $name = trim(($msg['first_name'] ?? '') . ' ' . ($msg['last_name'] ?? '')) ?: 'Foydalanuvchi';
            if ($msg['username'] ?? null) $name .= " (@{$msg['username']})";

            if (!isset($userCounts[$uid])) {
                $userCounts[$uid] = 0;
                $userNames[$uid] = $name;
            }
            $userCounts[$uid]++;

            if (!empty($msg['media'])) {
                foreach ($msg['media'] as $m) {
                    $type = $m['type'] ?? '';
                    if (isset($mediaCounts[$type])) $mediaCounts[$type]++;
                }
            }

            $h = substr($msg['date'], 11, 2);
            if (is_numeric($h)) {
                $hours[(int)$h] = ($hours[(int)$h] ?? 0) + 1;
            }
        }

        $peakHourStr = "Noma'lum";
        if (!empty($hours)) {
            arsort($hours);
            $peakH = array_key_first($hours);
            $peakHourStr = sprintf("%02d:00 - %02d:00", $peakH, $peakH + 1);
        }

        $pastMessages = array_filter($messages, fn($msg) => ($msg['date'] ?? '') < $startDate);
        $pastUserIds = array_unique(array_column($pastMessages, 'user_id'));
        $periodUserIds = array_keys($userCounts);
        
        $newUsers = 0;
        $returningUsers = 0;
        foreach ($periodUserIds as $uid) {
            if ($uid === $this->adminId) continue;
            if (in_array($uid, $pastUserIds)) $returningUsers++;
            else $newUsers++;
        }

        unset($userCounts[$this->adminId]);
        arsort($userCounts);
        $topUsers = array_slice($userCounts, 0, 5, true);
        $uniqueUsers = count($userCounts);

        if ($totalCount === 0) {
            $html = "<b><tg-emoji emoji-id='{$emoji}'>{$fallback}</tg-emoji> {$title}</b>\n\n<i>Ushbu davr uchun hech qanday xabar almashinuvi bo'lmagan.</i>";
            $keyboard = KeyboardBuilder::make()->backButton('statistika')->toJson();
            $this->editCallback($html, ['parse_mode' => 'html', 'reply_markup' => $keyboard]);
            return;
        }

        $html = "<b><tg-emoji emoji-id='{$emoji}'>{$fallback}</tg-emoji> {$title}</b><br><br>";
        $html .= "<b>Jami xabarlar soni:</b> $totalCount ta<br>";
        $html .= "<b>Siz yozgan xabarlar:</b> $adminMessagesCount ta<br>";
        $html .= "<b>Foydalanuvchilar xabarlari:</b> $customerMessagesCount ta<br>";
        $html .= "<b>Aloqaga chiqqanlar:</b> $uniqueUsers kishi <i>(Yangi: $newUsers, Eski: $returningUsers)</i><br>";
        $html .= "<b>Eng tig'iz soat:</b> $peakHourStr<br><br>";
        
        $mediaTotal = array_sum($mediaCounts);
        if ($mediaTotal > 0) {
            $html .= "<b>Qabul qilingan media:</b> {$mediaCounts['photo']} rasm, {$mediaCounts['video']} video, {$mediaCounts['voice']} ovozli xabar.<br><br>";
        }
        
        if (!empty($topUsers)) {
            $html .= "<b>Faol 5 ta mijoz:</b><br>";
            $html .= "<table bordered striped>";
            $html .= "<tr><th>№</th><th>Foydalanuvchi</th><th>Xabarlar</th></tr>";
            $i = 1;
            foreach ($topUsers as $uid => $count) {
                $name = htmlspecialchars($userNames[$uid]);
                $html .= "<tr><td align='center'>$i</td><td>$name</td><td align='center'>$count</td></tr>";
                $i++;
            }
            $html .= "</table>";
        }

        $keyboard = KeyboardBuilder::make()->backButton('statistika')->toJson();
        
        $cid = $this->update->getCallbackMessageChatId();
        $mid = $this->update->getCallbackMessageId();
        
        $this->api->deleteMessage($cid, $mid);
        $this->api->sendRichMessage($cid, ['html' => $html], ['reply_markup' => $keyboard]);
    }



    private function renderTopStats(): void
    {
        $messages = $this->getAllHistory();

        $totalCount = count($messages);
        $userCounts = [];
        $userNames = [];

        $mediaCounts = ['photo' => 0, 'video' => 0, 'voice' => 0];
        $adminMessagesCount = 0;
        $customerMessagesCount = 0;

        foreach ($messages as $msg) {
            $uid = $msg['user_id'] ?? 'noma\'lum';
            
            if ($uid === $this->adminId) {
                $adminMessagesCount++;
            } else {
                $customerMessagesCount++;
            }

            $name = trim(($msg['first_name'] ?? '') . ' ' . ($msg['last_name'] ?? '')) ?: 'Foydalanuvchi';
            if ($msg['username'] ?? null) $name .= " (@{$msg['username']})";

            if (!isset($userCounts[$uid])) {
                $userCounts[$uid] = 0;
                $userNames[$uid] = $name;
            }
            $userCounts[$uid]++;

            if (!empty($msg['media'])) {
                foreach ($msg['media'] as $m) {
                    $type = $m['type'] ?? '';
                    if (isset($mediaCounts[$type])) $mediaCounts[$type]++;
                }
            }
        }

        unset($userCounts[$this->adminId]);
        arsort($userCounts);
        $topUsers = array_slice($userCounts, 0, 10, true);
        $uniqueUsers = count($userCounts);

        if ($totalCount === 0) {
            $html = "<b><tg-emoji emoji-id='5217822164362739968'>👑</tg-emoji> Umumiy Reyting (Top 10)</b>\n\n<i>Tarix bo'sh. Hech qanday ma'lumot topilmadi.</i>";
            $keyboard = KeyboardBuilder::make()->backButton('statistika')->toJson();
            $this->editCallback($html, ['parse_mode' => 'html', 'reply_markup' => $keyboard]);
            return;
        }

        $html = "<b><tg-emoji emoji-id='5217822164362739968'>👑</tg-emoji> Umumiy Reyting (Top 10)</b><br><br>";
        $html .= "<b>Jami tarixiy xabarlar:</b> $totalCount ta<br>";
        $html .= "<b>Siz yozgan xabarlar:</b> $adminMessagesCount ta<br>";
        $html .= "<b>Foydalanuvchilar xabarlari:</b> $customerMessagesCount ta<br>";
        $html .= "<b>Jami noyob foydalanuvchilar:</b> $uniqueUsers kishi<br><br>";
        
        $mediaTotal = array_sum($mediaCounts);
        if ($mediaTotal > 0) {
            $html .= "<b>Jami qabul qilingan media:</b> {$mediaCounts['photo']} rasm, {$mediaCounts['video']} video, {$mediaCounts['voice']} ovozli xabar.<br><br>";
        }
        
        $html .= "<table bordered striped>";
        $html .= "<tr><th>O'rin</th><th>Foydalanuvchi</th><th>Xabarlar</th></tr>";

        $i = 1;
        foreach ($topUsers as $uid => $count) {
            $name = htmlspecialchars($userNames[$uid]);
            $html .= "<tr><td align='center'>$i</td><td>$name</td><td align='center'><b>$count</b></td></tr>";
            $i++;
        }
        $html .= "</table>";

        $keyboard = KeyboardBuilder::make()->backButton('statistika')->toJson();
        
        $cid = $this->update->getCallbackMessageChatId();
        $mid = $this->update->getCallbackMessageId();
        
        $this->api->deleteMessage($cid, $mid);
        $this->api->sendRichMessage($cid, ['html' => $html], ['reply_markup' => $keyboard]);
    }
}