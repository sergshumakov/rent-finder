<?php

namespace App\Console\Commands;

use App\Models\Flat;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SendToTelegramCommand extends Command
{
    protected $signature = 'send:telegram';
    protected $description = 'Send new flats to telegram channel';

    /**
     * @throws Exception
     */
    public function handle()
    {
        $flat = Flat::whereNull('published_at')->first();
        if(!$flat) {
            $this->info('Квартир для публикации не найдено');
            return;
        }

        $text = "*[{$this->escapeChars($flat->title)}](https://ss.ge$flat->link)*\n\n";
        if ($flat->description) {
            $text .= $this->escapeChars($flat->description) . "\n\n";
        }
        $text .= 'Адрес: ' . $this->escapeChars($flat->address) . "\n";
        $text .= 'Площадь: ' . $flat->flat_area . "\n";
        $text .= 'Дом: ' . $flat->flat_type . "\n";
        $text .= 'Этаж: ' . $flat->flat_floor . "\n\n";
        $text .= 'Цена: ' . $flat->price . "\n\n";
        $text .= "[Подробности и контакты](https://ss.ge$flat->link)";

        $rawPhotos = json_decode($flat->photos);
        if(count($rawPhotos)) {
            // album with photos
            $photos = [];
            foreach ($rawPhotos as $photo) {
                $photos[] = [
                    'type' => 'photo',
                    'media' => $photo,
                ];
            }
            $photos[0]['parse_mode'] = 'MarkdownV2';
            $photos[0]['caption'] = $text;

            $result = Http::asJson()
                ->post('https://api.telegram.org/bot5657009028:AAFsr2wbTnJ9V369DQdByUc8VcoIOAubWSg/sendMediaGroup', [
                    'chat_id' => -1001800255662,
                    'media' => $photos,
                ])
                ->json();
        } else {
            // text
            $result = Http::asJson()
                ->post('https://api.telegram.org/bot5657009028:AAFsr2wbTnJ9V369DQdByUc8VcoIOAubWSg/sendMessage', [
                    'chat_id' => -1001800255662,
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
            throw new Exception($result['description']);
        }
    }

    private function escapeChars($text): string
    {
        $chars = [
            '-', '.', '!', '(', ')', '{', '}',
        ];

        $replace = [];
        foreach($chars as $char) {
            $replace[] = '\\'.$char;
        }

        return Str::replace($chars, $replace, $text);
    }
}
