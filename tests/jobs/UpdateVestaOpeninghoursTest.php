<?php

namespace Tests\Jobs;

use App\Jobs\UpdateVestaOpeninghours;
use App\Models\Service;
use App\Services\RecurringOHService;
use App\Services\VestaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class UpdateVestaOpeninghoursTest extends \TestCase
{
    use DatabaseTransactions;

    /**
     * @var string
     */
    private $initQDriver;

    /**
     * setup for each test
     */
    public function setUp()
    {
        parent::setUp();
        $this->initQDriver = env('QUEUE_DRIVER');
        config(['queue.default' => 'sync']);

        $this->app->singleton(RecurringOHService::class, function () {
            $mock = $this->createMock(\App\Services\RecurringOHService::class, ['getRecurringOHForService']);
            $mock->expects($this->once())
                ->method('getRecurringOHForService')
                ->willReturn(date('ymdhis'));

            return $mock;
        });
    }

    public function tearDown()
    {
        config(['queue.default' => $this->initQDriver]);
        parent::tearDown();
    }

    /**
     * @test
     * @group validation
     */
    public function testFailOnWrongService()
    {
        $service = factory(Service::class)->create(['identifier' => 'JyeehBaby', 'source' => 'recreatex']);
        $this->setExpectedException(
            \Exception::class,
            'The App\Jobs\UpdateVestaOpeninghours job did not find a VESTA service (' . $service->id .
            ') with uid JyeehBaby.'
        );
        $job = new UpdateVestaOpeninghours($service->identifier, $service->id, true);

        $queue = dispatch($job);
    }

    /**
     * @test
     * @group validation
     */
    public function testFailOnEmptyService()
    {
        $this->app->singleton(RecurringOHService::class, function () {
            $mock = $this->createMock(\App\Services\RecurringOHService::class, ['getRecurringOHForService']);
            $mock->expects($this->once())
                ->method('getRecurringOHForService')
                ->willReturn('');

            return $mock;
        });
        $service = factory(Service::class)->create(['identifier' => 'JyeehBaby', 'source' => 'vesta']);
        $this->setExpectedException(
            \Exception::class,
            'The App\Jobs\UpdateVestaOpeninghours job tried to sync empty data for service (' . $service->id .
            ') with uid JyeehBaby to VESTA.'
        );
        $job = new UpdateVestaOpeninghours($service->identifier, $service->id, true);

        $queue = dispatch($job);
    }

    /**
     * @test
     * @group validation
     */
    public function testFailForDraft()
    {
        $service = factory(Service::class)->create(['identifier' => 'JyeehBaby', 'source' => 'vesta', 'draft' => 1]);
        $this->setExpectedException(
            \Exception::class,
            'The App\Jobs\UpdateVestaOpeninghours job tried to sync an inactive service (' . $service->id .
            ') with uid JyeehBaby to VESTA.'
        );
        $job = new UpdateVestaOpeninghours($service->identifier, $service->id, true);

        $queue = dispatch($job);
    }

    /**
     * no error = good
     * @test
     * @group validation
     */
    public function testHappyPath()
    {
        $this->app->singleton(VestaService::class, function () {
            $mock = $this->createMock(\App\Services\VestaService::class, ['getOpeningshoursByGuid']);
            $mock->expects($this->once())
                ->method('getOpeningshoursByGuid')
                ->willReturn('thisWouldBeOutdatedData');

            return $mock;
        });

        $service = Service::find(1);
        $service->source = 'vesta';
        $service->identifier = 'JyeehBaby';
        $service->save();

        $job = new UpdateVestaOpeninghours($service->identifier, $service->id);
        $job->handle();
        $this->assertTrue(true);
    }
}
