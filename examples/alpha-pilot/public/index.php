<?php

require dirname(__DIR__) . '/vendor/autoload.php';
(require dirname(__DIR__) . '/bootstrap/app.php')->handleRequest(Illuminate\Http\Request::capture());
