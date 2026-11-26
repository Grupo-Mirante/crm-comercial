<?php

declare(strict_types=1);

/*
 * Ponto de entrada único do CRM. A pasta "public" é a ÚNICA que o servidor web deve expor.
 */

use App\Core\App;
use App\Core\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

App::boot();
App::handle(Request::capture())->send();
