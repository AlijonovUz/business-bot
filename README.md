# business-bot

Telegram Business hisoblari uchun moslashtirilgan yordamchi bot. Ushbu qo'llanmada loyihani o'rnatish, sozlash va ishga tushirish bo'yicha to'liq ko'rsatmalar keltirilgan.

## Tizim talablari

- PHP 8.1 yoki undan yuqori versiya
- PHP cURL va JSON kengaytmalari
- SSL sertifikatiga ega veb-server (HTTPS)

## O'rnatish

1. Repozitoriyani serverga yuklab oling:
```bash
git clone https://github.com/AlijonovUz/business-bot.git
cd business-bot
```

2. Konfiguratsiya faylini yarating:
```bash
cp .env.example .env
```

3. `.env` faylini oching va kerakli parametrlarni kiriting:
```env
BOT_TOKEN=7115455473:AA...
ADMIN_ID=6150504681
ADMIN_USER=AlijonovUz
BOT_NAME=byBusinessBot
TIMEZONE=Asia/Tashkent
```

### Parametrlar tavsifi:
- `BOT_TOKEN`: @BotFather orqali olingan bot tokeni.
- `ADMIN_ID`: Adminning Telegram ID raqami.
- `ADMIN_USER`: Adminning Telegram username'i (@ belgisisiz).
- `BOT_NAME`: Bot nomi.
- `TIMEZONE`: Vaqt mintaqasi (standart: `Asia/Tashkent`).

## Webhook o'rnatish

Telegram Business hodisalarini qabul qilish uchun webhook manzilini `allowed_updates` parametrlari bilan birga sozlash zarur.

Brauzer yoki terminal orqali quyidagi so'rovni yuboring:

```text
https://api.telegram.org/bot<BOT_TOKEN>/setWebhook?url=https://sizning-domen.uz/index.php&allowed_updates=["message","edited_message","callback_query","business_connection","business_message","edited_business_message","deleted_business_messages"]
```

Qaytgan javobda `"ok": true` bo'lsa, webhook muvaffaqiyatli o'rnatilgan hisoblanadi.

## Telegram Business hisobiga ulash

1. Telegram ilovasida **Sozlamalar (Settings)** bo'limiga o'ting.
2. **Telegram Business** -> **Chatbots (Chat-botlar)** bo'limini tanlang.
3. Botingiz username'ini qidiring va hisobingizga ulang.
4. Barcha zarur ruxsatlarni (xabarlarga javob berish, xabarlarni o'qish) yoqing.

## Foydalanish va buyruqlar

### Admin boshqaruv paneli:
- `/start` - Botni ishga tushirish va ruxsatlarni tekshirish.
- `/settings` - Botning barcha funksiyalarini (avto-javob, kalkulyator, valyuta, xabarlar tarixi, vaqtli media) yoqish/o'chirish va sozlash paneli.

### Chatlarda ishlatiladigan tezkor buyruqlar:
- `.math <ifoda>` - Matematik ifodalarni hisoblash (masalan: `.math 50*2+15`).
- `.currency` - O'zbekiston Markaziy Banki rasmiy valyuta kurslarini ko'rish.
- `.ping` - Botning yuklanish tezligini (ms) tekshirish.
- `.memory` - Bot sarflayotgan operativ xotira (RAM) hajmini ko'rish.

## Litsenziya

Ushbu loyiha MIT litsenziyasi ostida tarqatiladi.
