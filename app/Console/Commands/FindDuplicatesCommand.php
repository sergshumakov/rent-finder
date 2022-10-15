<?php

namespace App\Console\Commands;

use App\Jobs\FindDuplicateJob;
use App\Models\Flat;
use Illuminate\Console\Command;

class FindDuplicatesCommand extends Command
{
    protected $signature = 'find:duplicates';
    protected $description = 'Find flat duplicates';

    public function handle()
    {
        $flats = Flat::whereNull('compared_at')
            ->latest()
            ->get();

        foreach($flats as $flat) {
            FindDuplicateJob::dispatch($flat);
        }
    }


}
