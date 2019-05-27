<?php
namespace Queue\Queue;

use Cake\Core\Configure;

class Config
{

    /**
     *
     * @return int
     */
    public static function defaultWorkerTimeout()
    {
        return Configure::read('Queue.defaultWorkerTimeout', 600); // 10min
    }

    /**
     *
     * @return int
     */
    public static function workerMaxRuntime()
    {
        return Configure::read('Queue.workerMaxRuntime', 120);
    }

    /**
     *
     * @return int
     */
    public static function cleanupTimeout()
    {
        return Configure::read('Queue.cleanupTimeout', 2592000); // 30 days
    }

    /**
     *
     * @return int
     */
    public static function sleepTime()
    {
        return Configure::read('Queue.sleepTime', 10);
    }

    /**
     *
     * @return int
     */
    public static function gcprob()
    {
        return Configure::read('Queue.gcprob', 10);
    }

    /**
     *
     * @return int
     */
    public static function defaultWorkerRetries()
    {
        return Configure::read('Queue.defaultWorkerRetries', 1);
    }
}
