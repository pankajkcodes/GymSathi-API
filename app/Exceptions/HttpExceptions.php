<?php
// Services throw these; bootstrap turns them into JSON error responses.
// Services never write responses themselves.

class HttpException extends RuntimeException
{
    /** @var int */
    public $status;
    /** @var mixed */
    public $data;

    public function __construct($message, $status = 400, $data = null)
    {
        parent::__construct($message);
        $this->status = $status;
        $this->data = $data;
    }
}

class ValidationException extends HttpException
{
    public function __construct($message, $data = null)
    {
        parent::__construct($message, 400, $data);
    }
}

class UnauthorizedException extends HttpException
{
    public function __construct($message = "Session expired. Please log in again.")
    {
        parent::__construct($message, 401);
    }
}

class ForbiddenException extends HttpException
{
    public function __construct($message = "You don't have permission to do this")
    {
        parent::__construct($message, 403);
    }
}

class NotFoundException extends HttpException
{
    public function __construct($message = "Not found")
    {
        parent::__construct($message, 404);
    }
}

class ConflictException extends HttpException
{
    public function __construct($message)
    {
        parent::__construct($message, 409);
    }
}
