# Queue plugin for CakePHP

[![Build Status](https://travis-ci.org/Oefenweb/cakephp-queue.png?branch=master)](https://travis-ci.org/Oefenweb/cakephp-queue) [![PHP 7 ready](http://php7ready.timesplinter.ch/Oefenweb/cakephp-queue/badge.svg)](https://travis-ci.org/Oefenweb/cakephp-queue) [![Coverage Status](https://codecov.io/gh/Oefenweb/cakephp-queue/branch/master/graph/badge.svg)](https://codecov.io/gh/Oefenweb/cakephp-queue) [![Packagist downloads](http://img.shields.io/packagist/dt/Oefenweb/cakephp-queue.svg)](https://packagist.org/packages/oefenweb/cakephp-queue) [![Code Climate](https://codeclimate.com/github/Oefenweb/cakephp-queue/badges/gpa.svg)](https://codeclimate.com/github/Oefenweb/cakephp-queue)

## Requirements

* CakePHP 2.6.0 or greater.
* PHP 5.4.16 or greater.

## Installation

Clone/Copy the files in this directory into `app/Plugin/Queue`

## Configuration

Ensure the plugin is loaded in `app/Config/bootstrap.php` by calling:

```
CakePlugin::load('Queue');
```

Ensure to configure the following lines in `app/Config/bootstrap.php`:

```
Configure::write('Queue.workers', 3);
Configure::write('Queue.sleepTime', 10);
Configure::write('Queue.gcprop', 10);
Configure::write('Queue.defaultWorkerTimeout', 2 * MINUTE);
Configure::write('Queue.defaultWorkerRetries', 4);
Configure::write('Queue.workerMaxRuntime', 0);
Configure::write('Queue.cleanupTimeout', DAY);
Configure::write('Queue.exitWhenNothingToDo', false);
```

Load schema:

```
Console/cake schema create;
```

## Usage

### Console

Run from your APP folder:

```
# Tries to call the `add()` function on a task.
Console/cake Queue.queue add <taskname>;
```

```
# Run a queue worker.
Console/cake Queue.queue runworker;
```

```
# Display some general statistics.
Console/cake Queue.queue stats;
```

```
# Manually call cleanup function to delete task data of completed tasks.
Console/cake Queue.queue clean;
```

```
# Manually call cleanup_failed function to delete task data of failed tasks.
Console/cake Queue.queue clean_failed;
```
