<?php

namespace ZillEAli\MikrotikLaravel\Exceptions;

use RuntimeException;

/**
 * ConnectionException
 *
 * Thrown when a TCP connection to the MikroTik router
 * cannot be established, or the connection is lost
 * during communication.
 *
 * @package ZillEAli\MikrotikLaravel\Exceptions
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class ConnectionException extends RuntimeException
{
}
