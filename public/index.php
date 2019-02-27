<?php

require '../vendor/autoload.php';
use Kint\Kint;
use Carbon\Carbon;

echo '<h1>Deploy me!</h1>';
printf('Now: %s', Carbon::now()->isoFormat('D/MM/Y'));
echo '<p>Change 15:03</p>';

Kint::dump($_SERVER);

$whoops = new Whoops\Run();
$whoops->pushHandler(new Whoops\Handler\PrettyPageHandler());
$whoops->register();

echo 2 / 0;
