<?php

namespace App\Console\Commands;

use App\Models\Flat;
use devoleg\FastImageCompare\FastImageCompare;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class FindDuplicatesCommand extends Command
{
    protected $signature = 'find:duplicates';
    protected $description = 'Find flat duplicates';

    private FastImageCompare $comparer;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();

        $this->comparer = new FastImageCompare();
        $this->comparer->setTemporaryDirectory(Storage::path('temp'));
    }

    public function handle()
    {
        while($flat = Flat::whereNull('compared_at')->latest()->lockForUpdate()->first()) {
            $this->info($flat->id);
            $isUnique = true;

            try {
                foreach($flat->photos as $key => $photo) {
                    $this->info($photo);
                    $flatPhoto = 'photos/' . $flat->id . '_' . $key .'.jpg';
                    $photoBin = Http::get($photo);
                    if ($photoBin->status() == 404) {
                        throw new Exception('Photo not found', 404);
                    }
                    Storage::put($flatPhoto, $photoBin->body());

                    $photoIsUnique = $this->isUniquePhoto($flatPhoto);
                    if (!$photoIsUnique) {
                        Storage::delete($flatPhoto);
                        $isUnique = false;
                        break;
                    }
                }
            } catch (Exception) {
                $this->error('Photo is not found');
                $flat->compared_at = now();
                $flat->save();
                continue;
            }

            if (!$isUnique) {
                $flat->duplicate_at = now();
                $this->warn('Find duplicate! ID: ' . $flat->id);
            } else {
                $this->info($flat->id . ' – is unique!');
            }

            $flat->compared_at = now();
            $flat->save();
        }
    }

    /**
     * @throws Exception
     */
    private function isUniquePhoto($input): bool
    {
        $tStart = microtime(true);
        $input = Storage::path($input);

        $bankPhotos = Storage::files('photos');
        $this->info('Count photos in bank: ' . count($bankPhotos));

        $items = array_map(
            fn ($file) => Storage::path($file),
            $bankPhotos
        );

        foreach ($items as $item) {
            $duplicates = $this->comparer->findDuplicates([$input, $item], 0.04);
            if (count($duplicates)) {
                print_r($duplicates);
                return false;
            }
        }

        $tEnd = microtime(true);
        $this->info('Speed: ' . ($tEnd - $tStart));

        return true;
    }
}
