<?php

namespace App\Console\Commands;

use App\Models\Flat;
use devoleg\FastImageCompare\FastImageCompare;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DropOldFlatsCommand extends Command
{
    protected $signature = 'drop:old-flats';
    protected $description = 'Удаляет объявления старше 7 дней и чистит кэш дубликатов';

    /**
     * @throws Exception
     */
    public function handle()
    {
        $flatsForDrop = Flat::where('created_at', '<=', now()->subDays(7))
            ->latest()
            ->get();

        foreach($flatsForDrop as $flat) {
            Storage::delete([
                'photos/' . $flat->id . '_0.jpg',
                'photos/' . $flat->id . '_1.jpg',
                'photos/' . $flat->id . '_2.jpg',
                'photos/' . $flat->id . '_3.jpg',
            ]);

            $flat->delete();

            $this->info($flat->id . ' удалена');
        }

        $comparer = new FastImageCompare();
        $comparer->setTemporaryDirectory(Storage::path('temp'));
        $comparer->clearCache();
    }
}
