<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Enums;

enum ActionScope: string
{
    case Portable = 'portable';
    case PageScoped = 'page_scoped';
    case BrowserLocal = 'browser_local';
    case Headless = 'headless';
}
