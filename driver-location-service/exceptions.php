<?php declare(strict_types=1);

class HttpNotFoundException extends \Exception
{
	public function getStatusCode()
	{
		return React\Http\Message\Response::STATUS_NOT_FOUND;
	}
}

