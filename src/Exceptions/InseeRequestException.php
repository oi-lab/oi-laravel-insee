<?php

namespace OiLab\OiLaravelInsee\Exceptions;

/**
 * The API rejected the request (4xx other than 429 and the 404 "no result"):
 * the message is the one given by the INSEE. Never retried.
 */
class InseeRequestException extends InseeException {}
