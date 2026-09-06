<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Binding;

enum BindingLifecycle: string
{
    case Page = 'page';
    case Component = 'component';
    case Session = 'session';
    case Persistent = 'persistent';
}
