<?php

namespace App\Http\Controllers\Api\V2;

/**
 * V2 status endpoints currently share the same implementation as V1.
 * Extending V1 keeps behavior aligned (including text/caption fallback in store()).
 */
class StatusController extends \App\Http\Controllers\Api\V1\StatusController
{
}

