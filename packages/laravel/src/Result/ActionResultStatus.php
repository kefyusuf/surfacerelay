<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Result;

/**
 * Public Action Result status vocabulary, exactly matching the frozen
 * spec/0.1 action-result.schema.json. Protocol-neutral; no HTTP-oriented
 * statuses. Runtime meanings (D-031):
 *
 * - succeeded: execution completed (data may legitimately be null).
 * - failed: invocation/runtime failed to complete (execution/system failure).
 * - rejected: invocation understood but deliberately prevented before
 *   successful execution (precondition/policy halt).
 * - confirmation_required: paused awaiting a real trusted confirmation
 *   challenge (never fabricated by the normalizer).
 */
enum ActionResultStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Rejected = 'rejected';
    case ConfirmationRequired = 'confirmation_required';
}
