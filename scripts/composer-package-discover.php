<?php

declare(strict_types=1);

/**
 * post-autoload-dump: artisan package:discover bəzən vendor natamam olanda Symfony ConsoleEvents tapılmır.
 * Bu skript sinif mövcud deyilsə composer-i uğursuz bitirmir (0 exit); sonra tam quraşdırma üçün yenidən composer install.
 */
$root = dirname(__DIR__);
$autoload = $root.'/vendor/autoload.php';
if (! is_file($autoload)) {
    fwrite(STDERR, "[composer] vendor/autoload.php yoxdur — package:discover atlanır.\n");

    exit(0);
}

require $autoload;

if (! class_exists(\Symfony\Component\Console\ConsoleEvents::class)) {
    fwrite(STDERR, "[composer] symfony/console natamamdır — package:discover atlanır. İşlədin: composer install\n");

    exit(0);
}

$artisan = $root.'/artisan';
if (! is_file($artisan)) {
    exit(0);
}

passthru(escapeshellcmd(\PHP_BINARY).' '.escapeshellarg($artisan).' package:discover --ansi', $code);

exit((int) $code);
