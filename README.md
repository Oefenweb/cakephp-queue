# Queue plugin for CakePHP

[![Build Status](https://travis-ci.org/Oefenweb/cakephp-queue.png?branch=master)](https://travis-ci.org/Oefenweb/cakephp-queue)
[![PHP 7 ready](http://php7ready.timesplinter.ch/Oefenweb/cakephp-queue/badge.svg)](https://travis-ci.org/Oefenweb/cakephp-queue)
[![Coverage Status](https://codecov.io/gh/Oefenweb/cakephp-queue/branch/master/graph/badge.svg)](https://codecov.io/gh/Oefenweb/cakephp-queue)
[![Packagist downloads](http://img.shields.io/packagist/dt/Oefenweb/cakephp-queue.svg)](https://packagist.org/packages/oefenweb/cakephp-queue)
[![Code Climate](https://codeclimate.com/github/Oefenweb/cakephp-queue/badges/gpa.svg)](https://codeclimate.com/github/Oefenweb/cakephp-queue)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Oefenweb/cakephp-queue/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Oefenweb/cakephp-queue/?branch=master)

## Requirements

* CakePHP 3.7.0 or greater.
* PHP 7.1.0 or greater.

## Installation
```
composer require oefenweb/cakephp-queue
```

Enable the plugin within your config/bootstrap.php (unless you use loadAll):
```php
Plugin::load('Queue');
```
Enable the plugin within your src/Application.php bootstrap function:
```php
$this->addPlugin('Queue');
```

It is also advised to have the `posix` PHP extension enabled.


## Configuration

### Global configuration
The plugin allows some simple runtime configuration.
You may create a file called `app_queue.php` inside your `config` folder (NOT the plugins config folder) to set the following values:

- Seconds to sleep() when no executable job is found:

    ```php
    $config['Queue']['sleepTime'] = 10;
    ```

- Probability in percent of an old job cleanup happening:

    ```php
    $config['Queue']['gcprob'] = 10;
    ```

- Default timeout after which a job is requeued if the worker doesn't report back:

    ```php
    $config['Queue']['defaultWorkerTimeout'] = 1800;
    ```

- Default number of retries if a job fails or times out:

    ```php
    $config['Queue']['defaultWorkerRetries'] = 3;
    ```

- Seconds of running time after which the worker will terminate (0 = unlimited):

    ```php
    $config['Queue']['workerMaxRuntime'] = 120;
    ```

    *Warning:* Do not use 0 if you are using a cronjob to permanantly start a new worker once in a while and if you do not exit on idle.

- Should a Workerprocess quit when there are no more tasks for it to execute (true = exit, false = keep running):

    ```php
    $config['Queue']['exitWhenNothingToDo'] = false;
    ```

- Minimum number of seconds before a cleanup run will remove a completed task (set to 0 to disable):

    ```php
    $config['Queue']['cleanupTimeout'] = 2592000; // 30 days
    ```

- Whether or not to cleanup on exit:

    ```php
    $config['Queue']['gcOnExit'] = true;
    ```

Don't forget to load that config file with `Configure::load('app_queue');` in your bootstrap.
You can also use `Plugin::load('Queue', ['bootstrap' => true]);` which will load your `app_queue.php` config file automatically.

Example `app_queue.php`:

```php
return [
    'Queue' => [
        'workerMaxRuntime' => 60,
        'sleepTime' => 15,
    ],
];
```

You can also drop the configuration into an existing config file (recommended) that is already been loaded.
The values above are the default settings which apply, when no configuration is found.

### Task configuration

You can set two main things on each task as property: timeout and retries.
```php
    /**
     * Timeout for this task in seconds, after which the task is reassigned to a new worker.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Number of times a failed instance of this task should be restarted before giving up.
     *
     * @var int
     */
    public $retries = 1;
```
Make sure you set the timeout high enough so that it could never run longer than this, otherwise you risk it being re-run while still being run.
It is recommended setting it to at least 2x the maximum possible execution length. See "Concurrent workers" below.

Set the retries to at least 1, otherwise it will never execute again after failure in the first run.

## Writing your own task

In most cases you wouldn't want to use the existing task, but just quickly build your own.
Put it into `/src/Shell/Task/` as `Queue{YourNameForIt}Task.php`.

You need to at least implement the run method:
```php
namespace App\Shell\Task;

...

class QueueYourNameForItTask extends QueueTask implements QueueTaskInterface {

    /**
     * @var int
     */
    public $timeout = 20;

    /**
     * @var int
     */
    public $retries = 1;

    /**
     * @param array $data The array passed to QueuedTasksTable::createJob()
     * @param int $jobId The id of the QueuedJob entity
     * @return void
     */
    public function run(array $data, $jobId) {
        $this->loadModel('FooBars');
        if (!$this->FooBars->doSth()) {
            throw new RuntimeException('Couldnt do sth.');
        }
    }

}
```
Make sure it throws an exception with a clear error message in case of failure.

Note: You can use the provided `Queue\Model\QueueException` if you do not need to include a strack trace.
This is usually the default inside custom tasks.

## Usage

Run the following using the CakePHP shell:

* Display Help message:

        bin/cake queue

* Try to call the cli add() function on a task:

        bin/cake queue add <TaskName>

    Tasks may or may not provide this functionality.

* Run a queue worker, which will look for a pending task it can execute:

        bin/cake queue runworker

    The worker will always try to find jobs matching its installed Tasks.


Most tasks will not be triggered from the console, but from the APP code.
You will need to use the model access for QueuedTasks and the createJob() function to do this.

The `createJob()` function takes three arguments.
- The first argument is the name of the type of job that you are creating.
- The second argument is optional, but if set must be an array of data and will be passed as a parameter to the `run()` function of the worker.
- The third argument is notBefore datetime.

For sending emails, for example:

```php
// In your controller
$this->loadModel('Queue.QueuedTasks');
$this->QueuedTasks->createJob('Email', ['to' => 'user@example.org', ...]);

// Somewhere in the model or lib
TableRegistry::get('Queue.QueuedTasks')->createJob('Email',
    ['to' => 'user@example.org', ...]);
```

It will use your custom APP `QueueEmailTask` to send out emails via CLI.

Important: Do not forget to set your [domain](https://book.cakephp.org/3.0/en/core-libraries/email.html#sending-emails-from-cli) when sending from CLI.


### Running only specific tasks per worker
You can filter "running" by type:
```
bin/cake queue runworker -t MyType,AnotherType,-ThisOneToo
bin/cake queue runworker -t "-ThisOneNot"
```
Use `-` prefix to exclude. Note that you might need to use `""` around the value then to avoid it being seen as option key.

That can be helpful when migrating servers and you only want to execute certain ones on the new system or want to test specific servers.

### Logging

By default errors are always logged, and with log enabled also the execution of a job.
Make sure you add this to your config:
```php
'Log' => [
    ...
    'queue' => [
        'className' => ...,
        'type' => 'queue',
        'levels' => ['info'],
        'scopes' => ['queue'],
    ],
],
```

When debugging (using -v) on the runworker, it will also log the worker run and end.

You can disable info logging by setting `Queue.log` to `false` in your config.


### Notes

`<TaskName>` may either be the complete classname (eg. QueueExample) or the shorthand without the leading "Queue" (e.g. Example).

Also note that you dont need to add the type ("Task"): `bin/cake queue add SpecialExample` for QueueSpecialExampleTask.

Custom tasks should be placed in src/Shell/Task.
Tasks should be named `QueueSomethingTask.php` and implement a "QueueSomethingTask", keeping CakePHP naming conventions intact. Custom tasks should extend the `QueueTask` class (you will need to include this at the top of your custom task file: `use Queue\Shell\Task\QueueTask;`).

Plugin tasks go in plugins/PluginName/src/Shell/Task.

A detailed Example task can be found in src/Shell/Task/QueueExampleTask.php inside this folder.

If you copy an example, do not forget to adapt the namespace!


## Setting up the trigger cronjob

As outlined in the [book](http://book.cakephp.org/3.0/en/console-and-shells/cron-jobs.html) you can easily set up a cronjob
to start a new worker.

The following example uses "crontab":

    */10  *  *  *  *  cd /full/path/to/app && bin/cake queue runworker -q

Make sure you use `crontab -e -u www-data` to set it up as `www-data` user, and not as root etc.

This would start a new worker every 10 minutes. If you configure your max life time of a worker to 15 minutes, you
got a small overlap where two workers would run simultaneously. If you lower the 10 minutes and raise the lifetime, you
get quite a few overlapping workers and thus more "parallel" processing power.
Play around with it, but just don't shoot over the top.


## Tips for Development


### Only pass identification data if possible

If you have larger data sets, or maybe even objects/entities, do not pass those.
They would not survive the json_encode/decode part and will maybe even exceed the text field in the database.

Instead, pass only the ID of the entity, and get your data in the Task itself.
If you have other larger chunks of data, store them somewhere and pass the path to this file.


### Using built in Execute task
The built in task directly runs on the same path as your app, so you can use relative paths or absolute ones:
```php
$data = [
    'command' => 'bin/cake importer run',
    'content' => $content,
];
$queuedTasksTable = TableRegistry::get('Queue.QueuedTasks');
$queuedTasksTable->createJob('Execute', $data);
```

The task automatically captures stderr output into stdout. If you don't want this, set "redirect" to false.
It also escapes by default using "escape" true. Only disable this if you trust the source.

By default it only allows return code `0` (success) to pass. If you need different accepted return codes, pass them as "accepted" array.
If you want to disable this check and allow any return code to be successful, pass `[]` (empty array).

*Warning*: This can essentially execute anything on CLI. Make sure you never expose this directly as free-text input to anyone.
Use only predefined and safe code-snippets here!
