<?php
require '../vendor/autoload.php';
use Kint\Kint;
use Carbon\Carbon;
echo '<h1>Deploy me!</h1>';
printf("Now: %s", Carbon::now()->isoFormat('D/MM/Y'));

Kint::dump($_SERVER);
