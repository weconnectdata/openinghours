<?php

namespace App\Services;

use App\Jobs\DeleteLodChannel;
use App\Jobs\UpdateVestaOpeninghours;
use App\Models\Channel;
use App\Models\Service;

/**
 * Internal Business logic Service for Service
 */
class ServiceService
{
    /**
     * Singleton class instance.
     *
     * @var ServiceService
     */
    private static $instance;

    /**
     * @var QueueService
     */
    private $queueService;

    /**
     * Private contructor for Singleton pattern
     */
    private function __construct()
    {
        $this->queueService = app('QueueService');
    }

    /**
     * GetInstance for Singleton pattern
     *
     * @return ServiceService
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        self::$instance->serviceModel = null;

        return self::$instance;
    }

    /**
     * Creat Jobs to sync data to external services
     *
     * Make job for VESTA update when service has a vesta source.
     * Make job delete LOD
     *
     * @param  Service $service
     * @return void
     */
    public function makeSyncJobsForExternalServices(Service $service)
    {
        error_log('serviceService');
        error_log($service);

//        // Update VESTA if the service is linked to a VESTA UID
//        if (!empty($service) && $service->source == 'vesta') {
//            $job = new UpdateVestaOpeninghours($service->identifier, $service->id);
//            $this->queueService->addJobToQueue($job, get_class($service), $service->id);
//        }
//
//        // Remove the LOD repository
//        $job = new DeleteLodChannel($service->id, $channel->id);
//        $this->queueService->addJobToQueue($job, get_class($channel), $channel->id);
    }
}
