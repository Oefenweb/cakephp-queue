<?php
namespace Queue\Queue;

use Cake\Core\Configure;

class Config
{

    /**
     *
     * @return int
     */
    public static function defaultWorkerTimeout(): int
    {
        return Configure::read('Queue.defaultWorkerTimeout', 2 * MINUTE); // 2min
    }

    /**
     *
     * @return int
     */
    public static function workerMaxRuntime(): int
    {
        return Configure::read('Queue.workerMaxRuntime', 0);
    }

    /**
     *
     * @return int
     */
    public static function cleanupTimeout(): int
    {
        return Configure::read('Queue.cleanupTimeout', DAY); // 1 day
    }

    /**
     *
     * @return int
     */
    public static function sleepTime(): int
    {
        return Configure::read('Queue.sleepTime', 10);
    }

    /**
     *
     * @return int
     */
    public static function gcprob(): int
    {
        return Configure::read('Queue.gcprob', 10);
    }

    /**
     *
     * @return int
     */
    public static function defaultWorkerRetries(): int
    {
        return Configure::read('Queue.defaultWorkerRetries', 4);
    }
}
