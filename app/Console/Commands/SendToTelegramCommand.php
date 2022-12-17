<?php

namespace App\Console\Commands;

use App\Models\District;
use App\Models\Flat;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LanguageDetection\Language;

class SendToTelegramCommand extends Command
{
    protected $signature = 'send:telegram';
    protected $description = 'Отправляет в телеграм каналы новые квартиры';

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        foreach (District::all() as $district) {
            $this->sendFlatFromDistrict($district);
        }
    }

    /**
     * @throws Exception
     */
    private function sendFlatFromDistrict(District $district): void
    {
        $flat = Flat::whereDistrictId($district->id)
            ->whereNull('published_at')
            ->whereNull('error_at')
            ->whereNull('duplicate_at')
            ->whereNotNull('compared_at')
            ->first();

        if(!$flat) {
            $this->info('Квартир для публикации не найдено');
            return;
        }

        $id = explode('-', $flat->uuid)[1];
        $text = "*[{$this->escapeChars($flat->title)}]($flat->link) \| ID: $id*\n\n";

        if ($flat->description) {
            $descriptionWithLimit = Str::limit($flat->description, 512);
            $description = $this->getTranslateDescription($descriptionWithLimit);
            $text .= $this->escapeChars($description) . "\n\n";
        }

        if ($flat->lat && $flat->lng) {
            $address = "[{$this->escapeChars($flat->address)}](https://yandex.com.ge/maps/10277/tbilisi/?ll=$flat->lng%2C$flat->lat&mode=whatshere&whatshere%5Bpoint%5D=$flat->lng%2C$flat->lat&whatshere%5Bzoom%5D=15&z=15)";
        } else {
            $address = $this->escapeChars($flat->address);
        }

        $text .= 'Адрес: ' . $address . "\n";
        $text .= 'Площадь: ' . $this->escapeChars($flat->flat_area) . "\n";

        if ($flat->flat_type) {
            $text .= 'Дом: ' . $flat->flat_type . "\n";
        }

        $text .= 'Этаж: ' . $flat->flat_floor . "\n\n";
        $text .= 'Цена: ' . $this->escapeChars($flat->price) . "\n\n";
        $text .= "[Подробности и контакты]($flat->link)";

        $tags = $this->getTags($flat);
        if ($tags->count()) {
            $text .= "\n\n" . $this->escapeChars($tags->join(' '));
        }

        if($flat->photos) {
            // album with photos
            $photos = [];
            foreach ($flat->photos as $photo) {
                $photos[] = [
                    'type' => 'photo',
                    'media' => $photo,
                ];
            }
            $photos[0]['parse_mode'] = 'MarkdownV2';
            $photos[0]['caption'] = $text;

            $result = Http::asJson()
                ->post('https://api.telegram.org/bot' . config('services.telegram.token') . '/sendMediaGroup', [
                    'chat_id' => $district->channel_id,
                    'media' => $photos,
                ])
                ->json();
        } else {
            // text
            $result = Http::asJson()
                ->post('https://api.telegram.org/bot' . config('services.telegram.token') . '/sendMessage', [
                    'chat_id' => $district->channel_id,
                    'text' => $text,
                    'parse_mode' => 'MarkdownV2'
                ])
                ->json();
        }

        if (
            array_key_exists('ok', $result) &&
            $result['ok'] === true
        ) {
            $flat->published_at = now();
            $flat->save();
            $this->info('Квартира: ' . $flat->title . ' – успешно опубликована');
        } else {
            if ($result['error_code'] == 400) {
                $flat->error_at = now();
                $flat->save();
            }
            Log::error('For Text: ' . $text);
            throw new Exception($result['description']);
        }
    }

    /**
     * @throws Exception
     */
    private function getTranslateDescription(string $text): string
    {
        $ld = new Language();
        $languages = $ld->detect($text)->close();

        if (!count($languages)) {
            return $text;
        }

        if ($languages['ru'] > 0.1) {
            return $text;
        }

        // translate
        $translate = Http::asJson()
            ->withToken(config('services.yandexTranslate.token'), 'Api-Key')
            ->post('https://translate.api.cloud.yandex.net/translate/v2/translate', [
                'targetLanguageCode' => 'ru',
                'texts' => [$text],
                'folderId' => config('services.yandexTranslate.folderId'),
            ])
            ->json();

        if (array_key_exists('translations', $translate)) {
            return $translate['translations'][0]['text'];
        }

        throw new Exception($translate['message']);
    }

    private function escapeChars(string $text): string
    {
        $chars = [
            '_', '*', '[', ']', '(', ')', '~', '`',
            '>', '#', '+', '-', '=', '|', '{', '}',
            '.', '!',
        ];

        $replace = [];
        foreach($chars as $char) {
            $replace[] = '\\'.$char;
        }

        return Str::replace($chars, $replace, $text);
    }

    private function getTags(Flat $flat): Collection
    {
        $tags = new Collection();

        $numbersInPrice = preg_replace('/[^0-9]/', '', $flat->price);

        if($numbersInPrice > 0) {
            $from = (int) floor($numbersInPrice / 500) * 500;
            $to = $from + 500;

            if ($from === 0) {
                $tags->add('#до' . $to);
            } else {
                $tags->add('#от' . $from . 'до' . $to);
            }
        }

        return $tags;
    }
}
