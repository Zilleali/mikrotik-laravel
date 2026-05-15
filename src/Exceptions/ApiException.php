<?php

namespace ZillEAli\MikrotikLaravel\Exceptions;

use RuntimeException;

/**
 * ApiException
 *
 * Thrown when the MikroTik RouterOS API returns
 * a !trap or !fatal response — meaning the router
 * understood the request but rejected it.
 *
 * Common causes:
 *  - Invalid command
 *  - Permission denied
 *  - Resource not found (.id wrong)
 *
 * @package ZillEAli\MikrotikLaravel\Exceptions
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class ApiException extends RuntimeException {}