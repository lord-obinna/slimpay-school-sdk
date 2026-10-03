<?php

namespace Slimpay\School\Exceptions;

/** The signed body wasn't the JSON shape Slimpay documents. Respond 400. */
class MalformedRequestException extends SlimpayException
{
}
