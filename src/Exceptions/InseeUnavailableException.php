<?php

namespace OiLab\OiLaravelInsee\Exceptions;

/**
 * The API answered 5xx, is under maintenance or could not be reached, even
 * after the retries.
 */
class InseeUnavailableException extends InseeException {}
