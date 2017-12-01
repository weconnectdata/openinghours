<?php

namespace Tests\Console;

use App\Jobs\FetchServices;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class FetchServicesTest extends \TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function commandFiresJobs()
    {
        $this->expectsJobs(FetchServices::class);
        \Artisan::call('openinghours:fetch-services');
    }
}
