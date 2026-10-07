<?php

namespace OiLab\OiLaravelInsee\Support;

/**
 * Who is calling, which decides what the limiter does when the budget runs out.
 */
enum CallPriority
{
    /** findSiret(), findSiren() and the other historical methods: never throw, wait a few seconds at most. */
    case Legacy;

    /** Typed single lookups (establishmentOrFail()): may use the whole hourly quota. */
    case Unit;

    /** Searches and counts: stop at `rate_limits.background_ceiling` to keep a reserve for unit calls. */
    case Background;
}
