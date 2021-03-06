<?php


namespace Core\Exception;


use Throwable;

/**
 * Class NotFoundException
 * @package Core\Exception
 */
class NotFoundException extends \Exception
{
    /**
     * NotFoundException constructor.
     * @param string $message
     * @param Throwable|null $previous
     */
    public function __construct(string $message = "", Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}