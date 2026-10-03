<?php

namespace Slimpay\School\Exceptions;

/** The request was not signed by Slimpay (or the timestamp is stale). Respond 401. */
class InvalidSignatureException extends SlimpayException
{
}
