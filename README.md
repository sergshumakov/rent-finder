# Rent Finder
🇬🇪🇬🇪🇬🇪 Поиск квартир для долгосрочной аренды в Грузии. Бесплатно. Без агентов и комиссий.

### Как это работает?
Новые объявления об аренде недвижимости автоматически собираются с грузинских сайтов ss.ge и myhome.ge, переводятся на русский язык и публикуются в телеграм-каналах. Каждый канал посвящен отдельному городу или району (для Тбилиси). 
Подписывайтесь на нужные вам локации и получайте самые свежие варианты квартир сразу после публикации!

Вот тут навигация по городам и районам Грузии: 
https://t.me/ShumakovLife/13

### Инструкция для разработчика
Если у вас нет PHP 8.1 и MySQL на машине – проще всего сделать все через Docker:

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v $(pwd):/var/www/html \
    -w /var/www/html \
    laravelsail/php81-composer:latest \
    composer install --ignore-platform-reqs
    
cp .env.example .env
    
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

### Конфигурация
В корне лежит [.env](./.env.example) в самом конце есть 2 важных ключа

- TELEGRAM_BOT_TOKEN – получаете в https://t.me/BotFather при создании бота
- YANDEX_TRANSLATE_TOKEN и YANDEX_TRANSLATE_FOLDER_ID – получаете в https://console.cloud.yandex.ru – читайте мануалы Яндекса, все по аналогии с сервисами Amazon.

### Принцип работы
- Вся бизнес логика лежит в папке [app/Console/Commands](app/Console/Commands).
- [ParseMyHomeCommand](app/Console/Commands/ParseMyHomeCommand.php) и [ParseSSCommand](app/Console/Commands/ParseSSCommand.php) занимаются парсингом объявлений
- [SendToTelegramCommand](app/Console/Commands/SendToTelegramCommand.php) занимается отправкой новых вариантов в телеграм

После запуска миграций, у вас в БД будет таблица ```districts``` –
это главная таблица для конфигурации, в ней я храню районы (или города), урлы для парсинга и ID канала в telegram, куда будут публиковаться объявления.

Кстати, чтобы узнать ID канала в телеге проще всего переслать любое сообщение из канала боту – https://t.me/username_to_id_bot

Заполнять таблицу с районами вам предстоит вручную, веб-интерфейса я для этого не сделал.

После этого запускайте команды парсеров:
```bash
./vendor/bin/sail artisan parse:ss
./vendor/bin/sail artisan parse:myhome
```

Для отправки в телеграм запускайте команду:
```bash
./vendor/bin/sail artisan send:telegram
```

Для production у меня сделан запуск по расписанию, смотри: [app/console/Kernel.php](app/Console/Kernel.php)

Просто добавь на сервере в Cron задачу:
```shell
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Документация по планировщику тут: https://laravel.com/docs/9.x/scheduling#introduction

### Для ускорения работы
Чтобы кэш от сравнения изображений работал быстро, лучше вынести его в оперативную память:
```bash
sudo mount -t tmpfs -o size=1G tmpfs /path-to-your-project/storage/app/temp

# Добавить в /etc/fstabs:
tmpfs /path-to-your-project/storage/app/temp tmpfs defaults,size=1G 0 0
```

### Есть желание развивать проект?
Буду рад вашим пулл-реквестам и баг-репортам и любому фидбэку по проекту.

### Об авторе
Меня зовут Сергей Шумаков. Вот тут: https://t.me/ShumakovLife я пишу про свою жизнь в Грузии и продукты, которые разрабатываю :)

Связаться со мной можно в Telegram: https://t.me/sergshumakov

