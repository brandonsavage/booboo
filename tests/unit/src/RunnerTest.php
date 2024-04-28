<?php

namespace League\BooBoo;

use League\BooBoo\Exception\NoFormattersRegisteredException;
use League\BooBoo\Formatter;
use League\BooBoo\Handler;
use Mockery;
use PHPUnit\Framework\TestCase;

function error_get_last()
{
    return [
        'type' => BooBooExt::$LAST_ERROR,
        'message' => 'error in file',
        'file' => 'test.php',
        'line' => 8,
    ];
}

class BooBooExt extends BooBoo {

    static public $LAST_ERROR = E_ERROR;

    public function getSilence() {
        return $this->silenceErrors;
    }

    public function register() {
        parent::register();
        $this->registered = true;
    }

    public function deregister() {
        parent::deregister();
        $this->registered = false;
    }

    protected function terminate()
    {
        return;
    }

}

class RunnerTest extends TestCase {

    /**
     * @var BooBoo
     */
    protected $runner;

    /**
     * @var Mockery\MockInterface
     */
    protected $formatter;
    protected $handler;

    protected function setUp() :void {
        ini_set('display_errors', true);
        $this->runner = new BooBoo([]);

        $this->formatter = Mockery::mock('League\BooBoo\Formatter\AbstractFormatter');
        $this->handler = Mockery::mock('League\BooBoo\Handler\HandlerInterface');
        $this->runner->pushFormatter($this->formatter);
    }

    public function testNoFormatterRaisesException() {
        $this->expectException(NoFormattersRegisteredException::class);
        $runner = new BooBoo([]);
        $runner->register();
    }

    public function testHandlerMethods() {
        $runner = new BooBoo([]);

        $this->assertEmpty($runner->getHandlers());

        $runner->pushHandler(Mockery::mock('League\BooBoo\Handler\HandlerInterface'));
        $runner->pushHandler(Mockery::mock('League\BooBoo\Handler\HandlerInterface'));

        $this->assertEquals(2, count($runner->getHandlers()));
        $this->assertInstanceOf('League\BooBoo\Handler\HandlerInterface', $runner->popHandler());
        $this->assertEquals(1, count($runner->getHandlers()));

        $runner->clearHandlers();
        $this->assertEmpty($runner->getHandlers());
    }

    public function testFormatterMethods() {
        $runner = new BooBoo([]);

        $this->assertEmpty($runner->getFormatters());

        $runner->pushFormatter(Mockery::mock('League\BooBoo\Formatter\FormatterInterface'));
        $runner->pushFormatter(Mockery::mock('League\BooBoo\Formatter\FormatterInterface'));

        $this->assertEquals(2, count($runner->getFormatters()));
        $this->assertInstanceOf('League\BooBoo\Formatter\FormatterInterface', $runner->popFormatter());
        $this->assertEquals(1, count($runner->getFormatters()));

        $runner->clearFormatters();
        $this->assertEmpty($runner->getFormatters());
    }

    public function testConstructorAssignsHandlersAndFormatters() {
        $runner = new BooBoo(
            [
                Mockery::mock('League\BooBoo\Formatter\FormatterInterface'),
                Mockery::mock('League\BooBoo\Formatter\FormatterInterface'),
            ],
            [
                Mockery::mock('League\BooBoo\Handler\HandlerInterface'),
            ]
        );

        $this->assertCount(2, $runner->getFormatters());
        $this->assertCount(1, $runner->getHandlers());
    }

    public function testErrorsSilencedWhenSilenceTrue() {
        $formatter = new class implements Formatter\FormatterInterface {
            public function format($e) { throw new \Exception; }
            public function setErrorLimit($limit){ throw new \Exception; }
            public function getErrorLimit() { throw new \Exception; }
        };

        $runner = new BooBoo([]);
        $runner->silenceAllErrors(true);
        $runner->pushFormatter($formatter);

        // Now we fake an error
        $result = $runner->errorHandler(E_WARNING, 'warning', 'index.php', 11);
        $this->assertTrue($result);

    }

    public function testThrowErrorsAsExceptions() {
        $this->expectException(\ErrorException::class);
        $this->runner->treatErrorsAsExceptions(true);
        $this->runner->errorHandler(E_WARNING, 'test', 'test.php', 11);
    }

    public function testFormattersFormatCode() {
        $formatter = new class implements Formatter\FormatterInterface {
            public static $formatCalled = 0;
            public function format($e) { self::$formatCalled++; return null; }
            public function setErrorLimit($limit){ throw new \Exception; }
            public function getErrorLimit() { return E_ALL; }
        };

        $this->runner->clearFormatters();
        $this->runner->pushFormatter($formatter);
        $this->runner->errorHandler(E_WARNING, 'warning', 'index.php', 11);
        $this->runner->exceptionHandler(new \Exception);
        $this->assertEquals(2, $formatter::$formatCalled);
    }

    public function testErrorReportingOffSilencesErrors() {
        error_reporting(0);
        $result = $this->runner->errorHandler(E_WARNING, 'error', 'index.php', 11);
        $this->assertTrue($result);
        error_reporting(E_ALL);
    }


    public function testErrorReportingOffStillKillsFatalErrors() {
        error_reporting(0);
        $runner = new BooBooExt([]);
        $result = $runner->errorHandler(E_ERROR, 'error', 'index.php', 11);
        $this->assertTrue($result);
        error_reporting(E_ALL);
    }

    public function testErrorsSilencedWhenErrorReportingOff() {
        $er = ini_get('display_errors');
        ini_set('display_errors', 0);

        $runner = new BooBooExt([]);
        ini_set('display_errors', $er);

        $this->assertTrue($runner->getSilence());
    }

    public function testRegisterAndDeregister() {
        $formatter = Mockery::mock('League\BooBoo\Formatter\FormatterInterface');
        $formatter->shouldIgnoreMissing();

        $runner = new BooBooExt([$formatter]);

        $runner->register();
        $this->assertTrue($runner->registered);

        $runner->deregister();
        $this->assertFalse($runner->registered);
    }

    public function testErrorPageHandler() {
        $formatter = new class implements Formatter\FormatterInterface {
            public static $formatCalled = 0;
            public function format($e) { self::$formatCalled++; return null; }
            public function setErrorLimit($limit){ throw new \Exception; }
            public function getErrorLimit() { return E_ALL; }
        };

        $this->runner->setErrorPageFormatter($formatter);
        $this->runner->silenceAllErrors(true);

        $this->runner->exceptionHandler(new \Exception);

        $this->assertEquals(1, $formatter::$formatCalled);
    }

    public function testHandlersAreRun()
    {
        $runner = new BooBoo([]);

        $this->assertEmpty($runner->getHandlers());

        $handler = Mockery::mock('League\BooBoo\Handler\HandlerInterface');
        $handler->shouldReceive('handle')->once()->with(Mockery::type('Exception'));

        $runner->pushHandler($handler);
        $runner->exceptionHandler(new \Exception);
    }

    public function testShutdownHandler()
    {
        $formatter = new class implements Formatter\FormatterInterface {
            public static $formatCalled = 0;
            public function format($e) { self::$formatCalled++; return null; }
            public function setErrorLimit($limit){ throw new \Exception; }
            public function getErrorLimit() { return E_ERROR; }
        };

        $runner = new BooBooExt([$formatter]);
        $runner->shutdownHandler();

        $this->assertEquals(1, $formatter::$formatCalled);
    }

    public function testShutdownHandlerIgnoresNonfatal()
    {
        BooBooExt::$LAST_ERROR = E_WARNING;
        $formatter = new class implements Formatter\FormatterInterface {
            public static $formatCalled = 0;
            public function format($e) { self::$formatCalled++; return null; }
            public function setErrorLimit($limit){ throw new \Exception; }
            public function getErrorLimit() { return E_ERROR; }
        };

        $runner = new BooBooExt([$formatter]);
        $runner->shutdownHandler();

        BooBooExt::$LAST_ERROR = E_ERROR;

        $this->assertEquals(0, $formatter::$formatCalled);
    }

    protected function tearDown() : void
    {
        Mockery::close();
    }
}
