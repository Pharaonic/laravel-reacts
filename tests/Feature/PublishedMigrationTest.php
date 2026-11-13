<?php

use Symfony\Component\Process\Process;

/*
 * Publishing and running the migration happens in a process of its own
 * (tests/Processes/published-migration.php): a PHP process can declare the
 * migration class from one file only, and an application only ever loads
 * one file per migration name.
 */

test('the published migration replaces the package one instead of running twice', function () {
    $process = new Process(
        [PHP_BINARY, __DIR__.'/../Processes/published-migration.php'],
        null,
        $this->useSharedDatabase(),
        null,
        60
    );
    $process->run();

    $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());

    $this->assertSame([
        'publish' => 0,
        'copied' => true,
        'migrate' => 0,
        'recorded' => 1,
        'listed' => 1,
        'rollback' => 0,
        'dropped' => true,
        'migrate_again' => 0,
        'created' => true,
        'fresh' => 0,
        'recorded_again' => 1,
        'reacted' => true,
    ], json_decode($process->getOutput(), true));
});
