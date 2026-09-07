<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Confirmation;

/** Live server-side confirmation record states. Consumed/expired records carry no authority. */
enum ConfirmationRecordState: string
{
    case Pending = 'pending';
    case Approved = 'approved';
}
