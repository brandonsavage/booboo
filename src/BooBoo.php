<?php

namespace League\BooBoo;

use ErrorException;
use League\BooBoo\Exception\NoFormattersRegisteredException;
use League\BooBoo\Formatter\FormatterInterface;
use League\BooBoo\Handler\HandlerInterface;

class BooBoo
{
    /**
     * A constant for the error handling function.
     */
    const ERROR_HANDLER = 'errorHandler';

    /**
     * A constant for the exception handler.
     */
    const EXCEPTION_HANDLER = 'exceptionHandler';

    /**
     * A constant for the shutdown handler.
     */
    const SHUTDOWN_HANDLER = 'shutdownHandler';

    /**
     * @var array Handler stack array
     */
    protected $handlerStack = [];

    /**
     * @var bool Whether or not we should silence all errors.
     */
    protected $silenceErrors = false;

    /**
     * @var bool If set to true, will throw all errors as exceptions (making them blocking)
     */
    protected $throwErrorsAsExceptions = false;

    /**
     * This isn't set as a default, because we can't. Set in the constructor.
     *
     * @var int
     */
    protected $fatalErrors;

    /**
     * @param array $handlers
     */
    public function __construct(array $handlers = [])
    {
        // Let's honor the INI settings.
        if (ini_get('display_errors') == false) {
            $this->silenceAllErrors(true);
        }

        foreach ($handlers as $handler) {
            $this->pushHandler($handler);
        }

        $this->fatalErrors = E_ERROR | E_USER_ERROR | E_COMPILE_ERROR | E_CORE_ERROR | E_PARSE;
    }

    /**
     * An error handling function for PHP. Follows the protocols laid out
     * in the documentation for defining an error handler. Variable names
     * are straight from the PHP documentation.
     *
     * @param $errno
     * @param $errstr
     * @param $errfile
     * @param $errline
     *
     * @return bool
     * @throws \ErrorException
     */
    public function errorHandler($errno, $errstr, $errfile, $errline)
    {
        // Only handle errors that match the error reporting level.
        if (!($errno & error_reporting())) { // bitwise operation
            if ($errno & $this->fatalErrors) {
                $this->terminate();
            }
            return true;
        }

        $e = new ErrorException($errstr, 0, $errno, $errfile, $errline);

        if ($this->throwErrorsAsExceptions) {
            throw $e;
        } else {
            $this->exceptionHandler($e);
        }

        // Fatal errors should be fatal
        if ($errno & $this->fatalErrors) {
            $this->terminate();
        }
    }

    protected function terminate()
    {
        exit(1);
    }

    /**
     * An exception handler, per the documentation in PHP. This function is
     * also used for the handling of errors, even when they are non-blocking.
     *
     * @param \Exception $e
     */
    public function exceptionHandler($e)
    {
        http_response_code(500);

        $e = $this->runHandlers($e);

        if ($this->silenceErrors &&
            isset($this->errorPage) &&
            !($e instanceof ErrorException)
        ) {
            ob_start();
            $response = $this->errorPage->format($e);
            ob_end_clean();
            print $response;
            return;
        }
    }

    /**
     * A function for running the error handler on a fatal error.
     */
    public function shutdownHandler()
    {
        // We can't throw exceptions in the shutdown handler.
        $this->treatErrorsAsExceptions(false);

        $error = error_get_last();
        if ($error && $error['type'] & $this->fatalErrors) {
            $this->errorHandler(
                $error['type'],
                $error['message'],
                $error['file'],
                $error['line']
            );
        }
    }

    /**
     * Registers the error handlers, and is required to be called before the
     * error handling code is effective.
     *
     */
    public function register()
    {
        set_error_handler([$this, self::ERROR_HANDLER]);
        set_exception_handler([$this, self::EXCEPTION_HANDLER]);
        register_shutdown_function([$this, self::SHUTDOWN_HANDLER]);
    }

    /**
     * Add a new handler to the stack.
     *
     * @param \League\BooBoo\Handler\HandlerInterface $handler
     * @return $this
     */
    public function pushHandler(HandlerInterface $handler)
    {
        $this->handlerStack[] = $handler;
        return $this;
    }

    /**
     * Remove an error handler from the bottom of the stack.
     *
     * @return \League\BooBoo\Handler\HandlerInterface|null
     */
    public function popHandler()
    {
        return array_pop($this->handlerStack);
    }

    /**
     * Get a list of available handlers.
     *
     * @return array
     */
    public function getHandlers()
    {
        return $this->handlerStack;
    }

    /**
     * CLear all the handlers.
     *
     * @return $this
     */
    public function clearHandlers()
    {
        $this->handlerStack = [];
        return $this;
    }

    /**
     * Runs all the handlers registered, and returns the exception provided.
     *
     * @param \Exception $e
     * @return \Exception
     */
    protected function runHandlers($e)
    {
        /** @var \League\BooBoo\Handler\HandlerInterface $handler */
        foreach (array_reverse($this->handlerStack) as $handler) {
            $handledException = $handler->handle($e);
            if ($handledException instanceof \Exception) {
                $e = $handledException;
            }
        }

        return $e;
    }

    /**
     * Silences all errors.
     *
     * @param bool $bool
     */
    public function silenceAllErrors($bool)
    {
        $this->silenceErrors = (bool)$bool;
    }

    /**
     * Deregisters the error handling functions, returning them to their previous state.
     *
     * @return $this
     */
    public function deregister()
    {
        restore_error_handler();
        restore_exception_handler();
        return $this;
    }

    /**
     * Allows the user to explicitly require errors to be thrown as exceptions. This
     * makes all errors blocking, even if they are minor (e.g. E_NOTICE, E_WARNING).
     *
     * @param bool $bool
     */
    public function treatErrorsAsExceptions($bool)
    {
        $this->throwErrorsAsExceptions = (bool)$bool;
    }
}
