<?php

return ['default' => 'file', 'prefix' => 'alpha_acceptance', 'stores' => ['file' => [
    'driver' => 'file', 'path' => storage_path('framework/cache/data'),
    'lock_path' => storage_path('framework/cache/locks'),
]]];
