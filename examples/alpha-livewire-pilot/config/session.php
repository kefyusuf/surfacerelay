<?php

return ['driver' => 'file', 'lifetime' => 120, 'expire_on_close' => false,
    'encrypt' => false, 'files' => storage_path('framework/sessions'),
    'cookie' => 'alpha_livewire_pilot_session', 'path' => '/', 'domain' => null,
    'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'lottery' => [0, 100]];
