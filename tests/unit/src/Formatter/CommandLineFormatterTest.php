<?php

use \League\BooBoo\Formatter\CommandLineFormatter;
use PHPUnit\Framework\TestCase;

class CommandLineFormatterTest extends TestCase
{
    public function testHandleErrorsReturnsValidErrorMessage()
    {
        $exception = new ErrorException('test message', 0, E_ERROR, 'test.php', 15);

        $formatter = new CommandLineFormatter();
        $format = $formatter->format($exception);

        $expected = "\n+-------------+\n| FATAL ERROR |\n+-------------+\ntest message in test.php on line 15\n";
        $this->assertEquals($expected, $format);
    }

    public function testHandleExceptionsReturnsValidErrorMessage()
    {
        $exception = new Exception('test message');

        $formatter = new CommandLineFormatter();
        $format = $formatter->format($exception);

        $this->assertTrue(strlen($format) > 0);
        $this->assertTrue((strpos($format, 'UNHANDLED EXCEPTION') !== false));
    }
}
