<?php

require '../vendor/autoload.php';
// use Kint\Kint;
use Carbon\Carbon;

echo '<h1>Deploy me!</h1>';
printf('Now: %s', Carbon::now()->isoFormat('D/MM/Y'));
echo '<p>Change 16:34</p>';

$whoops = new Whoops\Run();
$whoops->pushHandler(new Whoops\Handler\PrettyPageHandler());
$whoops->register();

// Kint::dump($_SERVER);

echo 2 / 0;
